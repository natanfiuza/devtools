# ADR-040: Ciclo da solicitacao de IA - reserva, liquidacao e cache sem debito

Status: Aceito
Data: 2026-10-20
Relacionados: ADR-008 (abuso e custo), ADR-012 (planos e cota), ADR-016 (AICache MD5), ADR-019 (filas)

## Contexto

A F5 cobra em duas unidades: o Free tem **5 usos vitalicios** e os planos pagos
tem uma franquia **mensal de tokens**. O projeto nao tem coluna de saldo: a Cota
e sempre calculada a partir do `usage_ledger` (ADR-012). Ao mesmo tempo, a
resposta do provedor chega depois, num job, e pode falhar, expirar ou ser
servida pelo cache sem custo.

Isso levanta quatro problemas que uma implementacao ingenua erra:

1. **Cobranca antecipada x atrasada**: debitar so no fim deixa a conta consumir
   varios pedidos sem cota; debitar no inicio e nunca devolver cobra por falha.
2. **Corrida entre abas**: dois POSTs da mesma conta podem passar pela mesma
   verificacao de cota e debitar duas vezes.
3. **Cache hit**: a resposta nao custou chamada; cobrar por ela seria cobrar
   duas vezes pelo mesmo trabalho.
4. **Processo morto**: se o worker morre sem `failed()`, a reserva ficaria
   presa e o pedido ficaria `queued` para sempre.

## Decisao

### 1. Reserva no inicio, dentro de uma transacao

`StartAiQuery` roda numa transacao e trava a linha da conta
(`users` com `lockForUpdate`). A ordem e fixa:

1. **dedupe**: mesma conta, mesmo `prompt_hash`, em `queued|processing` e dentro
   de `AI_INFLIGHT_TTL_SECONDS` (600 s) devolve a solicitacao existente com
   202, **sem** novo debito e **sem** novo job;
2. **cache**: hit cria a solicitacao ja `done`, `cache_hit = true`, **sem**
   nenhuma linha de ledger, e responde 200;
3. **limites do plano** (schema, descricao, dialeto aceito pelo alvo) e **cota**;
4. **cria** a solicitacao com `canonical_payload` comprimido;
5. **debita** a reserva: Free `uses = 1`; pago `tokens = estimativa`
   (`chars/4` de entrada + `AI_ESTIMATE_OUTPUT_TOKENS`);
6. **enfileira** o job com `afterCommit()`.

A solicitacao guarda a **fotografia** da reserva: `quota_unit`,
`reserved_tokens`, `reserved_uses`. A liquidacao usa essa unidade, e nao o
plano vigente na hora do fim - um plano que muda no meio do caminho nao
desalinha a cobranca.

### 2. Toda linha do ledger pertence a uma solicitacao

As linhas ligadas a uma solicitacao usam `usage_ledger.ai_request_ulid` e tem o
**mesmo `created_at` da solicitacao**, inclusive estornos e ajustes. A janela da
assinatura e calculada por data, e um estorno gravado no dia seguinte cairia
fora do periodo do debito. As linhas sao `debit`, `refund` e `grant`; nao ha
linha de saldo.

### 3. Liquidacao idempotente

`SettleAiQuery` trava a conta e depois a solicitacao (sempre nessa ordem, como
no inicio) e ignora uma solicitacao que ja esta terminal:

- **pago**: cobra `tokens_in + tokens_out` reais. Se o real for maior que a
  reserva, debita a diferenca; se for menor, estorna; igual nao gera linha;
- **Free**: nao ha ajuste - a unidade e o uso;
- grava `response_json`, apaga `canonical_payload` e **grava o cache depois do
  commit** (falhar no cache nao desfaz uma resposta boa);
- `needs_clarification` e uma resposta valida: termina `done` e **consome** a
  reserva, porque o trabalho foi feito.

`FailAiQuery` estorna a reserva inteira, marca `failed` com `AiFailureCode` e
apaga o payload. Vale para falha do provedor, contrato invalido, SQL recusado,
timeout e reconciliacao.

### 4. Cache hit nunca debita

- **No POST**: o hit nasce `done` sem ledger (200).
- **No job**: o job consulta o cache de novo (duas contas podem pedir a mesma
  coisa ao mesmo tempo); se encontrar, estorna a reserva integralmente e
  termina `done` com `cache_hit = true`. O resultado e o mesmo de ter
  respondido no POST.

### 5. Reconciliacao cobre o worker morto

`ai:reconcile` encontra solicitacoes `queued|processing` mais antigas que
`AI_JOB_TIMEOUT` + folga (120 s por padrao), marca `timeout` e estorna a
reserva. Sem ele, um deploy no meio de um job deixaria a cota debitada para
sempre e o dedupe bloquearia o mesmo pedido ate o TTL.

### Matriz do ledger

| Evento | Free | Pago |
| ------ | ---- | ---- |
| POST com cache hit | nada | nada |
| POST sem cache | debito `uses = 1` | debito `tokens = E` |
| Job encontra cache | estorno `uses = 1` | estorno `tokens = E` |
| Job conclui | nada | debita `A - E` se `A > E`; estorna `E - A` se `A < E` |
| Falha (qualquer causa) | estorno `uses = 1` | estorno `tokens = E` |
| Repeticao/terminal | nada | nada |

`E` e a reserva estimada; `A` e `tokens_in + tokens_out` informados pelo
provedor.

## Consequencias

### Positivas

- A conta nao consegue estourar a cota com pedidos concorrentes: a trava da
  conta serializa a decisao.
- Nenhuma chamada paga por falha: toda falha termina em estorno integral.
- Cache hit nao custa nada em nenhum caminho, coerente com a promessa da tela.
- Nao existe coluna de saldo para desatualizar: a Cota e sempre a soma do
  ledger, e o ledger e a unica fonte.
- Um plano pago pode ter **duas** linhas por solicitacao (reserva + ajuste);
  isso e esperado, nao duplicidade.

### Negativas / trade-offs

- A transacao do POST segura a linha da conta ate o fim; dois pedidos
  simultaneos da mesma conta esperam um pelo outro (por desenho).
- O Free paga 1 uso mesmo que a resposta seja `needs_clarification`; e uma
  resposta entregue, mas o usuario pode perceber como pouco valor.
- Se o job falhar **antes** de liquidar e o retry tambem falhar, so o
  `failed()`/`ai:reconcile` devolve a reserva; por isso os dois existem.
- O `ai_request_id` bigint legado do ledger fica obsoleto nesta fase (a coluna
  nova e a fonte). Nao dropamos agora: migration destrutiva nao entra em deploy
  sem downtime.

## Alternativas descartadas

- **Cobrar so no fim**: permitiria uma conta sem cota disparar varios pedidos
  antes do primeiro debito, e o custo do provedor seria nosso.
- **Coluna de saldo na conta**: mais uma fonte de verdade para sincronizar com
  o ledger; ADR-012 ja decidiu que a Cota e derivada.
- **Nao deduplicar**: duas abas gerariam duas chamadas e dois debitos para o
  mesmo schema.
- **Cobrar cache hit**: contradiz o objetivo do cache e a expectativa de que
  repetir a mesma pergunta seja gratis.
- **Processar o provedor de forma sincrona**: prenderia o request HTTP e
  duplicaria falhas de timeout (ADR-019).
