# Arquitetura

Este documento descreve a unica parte do site que nao cabe numa pagina: o
**AI SQL Builder**, a tool que fala com o servidor (ADR-002). As outras oito
rodam 100% no navegador e nao tem backend: o servidor entrega a pagina, o SEO e
a conta.

## Visao em uma tela

```
navegador (Vue/Inertia)
  SchemaForm -> POST /api/ai/queries -> resposta 200 (cache) | 202 (fila)
       ^                                        |
       |                                        v
  polling GET /api/ai/queries/{ulid}     Laravel (grupo web, sessao + CSRF)
       ^                                        |
       |                                        v
  ResultPanel                           StartAiQuery (transacao)
       ^                                  |  dedupe
       |                                  |  cache hit -> done, sem ledger
       |                                  |  cache miss -> reserva + queued
       |                                  v
       |                            fila `database` (tabela jobs)
       |                                  |
       |                                  v
       |                            ProcessAiQuery (worker)
       |                                  |  cache de novo
       |                                  |  contrato + gate lexical
       |                                  v
       |                            DeepSeek (HTTP JSON)
       |                                  |
       |                                  v
       |                            SettleAiQuery / FailAiQuery
       |                                  |  usage real, ajuste/estorno
       |                                  v
       +---------------------------- MySQL (ai_requests, ai_cache, usage_ledger)
```

## Fronteiras

| Camada | Arquivo | Responsabilidade | Nao faz |
| ------ | ------- | ---------------- | ------- |
| Tela | `resources/js/tools/ai-sql-builder/` | Formulario, estados, polling, abas de resultado, copiar/baixar. | Nao decide autorizacao nem cota; desenha o que o servidor mandou. |
| Props | `app/Support/Ai/AiToolProps.php` | Estado inicial da pagina (provider ligado, motivo de bloqueio, cota, alvos, limites, endpoints). | Nao e autorizacao: o POST valida tudo de novo. |
| HTTP | `AiQueryController` + `StoreAiQueryRequest` + `AiQueryResource` | Validacao de forma, dono da solicitacao, status 200/202, envelope de resposta. | Nao fala com o provedor e nao grava ledger. |
| Inicio | `app/Actions/Ai/StartAiQuery.php` | Dedupe, cache, limites do plano, reserva, criacao da solicitacao e dispatch. | Nao chama o provedor. |
| Fila | `app/Jobs/ProcessAiQuery.php` | Reidrata o payload, consulta o cache de novo, chama o provedor, valida e delega a liquidacao. | Nao carrega schema/descricao no payload da fila. |
| Nucleo | `app/Support/Ai/` | Canonicalizacao, hash, prompts, estimativa, contrato, gate, cache e ledger. | Nao conhece HTTP nem Vue. |
| Provedor | `app/Services/Ai/DeepSeekProvider.php` | Um POST JSON compatível com OpenAI, timeout e mapeamento de erro. | Nao inclui o corpo do provedor em excecao/log. |

## Ciclo de uma solicitacao

### 1. POST

`StartAiQuery` roda numa transacao e **trava a conta** (`users` com
`lockForUpdate`). Duas abas da mesma conta nao avaliam cota e cache ao mesmo
tempo; a segunda espera e, se o hash for igual, recebe a solicitacao em voo.

A ordem e deliberada:

1. **Dedupe antes do cache**: uma solicitacao em voo ja vai produzir a resposta
   - nao faz sentido procurar no cache o que esta sendo gerado agora.
2. **Cache antes dos limites de plano**: um hit nao custa nada e nao precisa
   passar pelo teto de cota.
3. **Dialeto antes de tudo**: a resposta de um alvo que nao aceita aquele
   dialeto nao serve, e nao vale gastar uma chamada para descobrir.

O payload canonicalizado (fonte, dialeto, alvo, schema, descricao, modelo e
versao do prompt) vira um JSON com chaves ordenadas; o MD5 dele e o
`prompt_hash`. O hash e chave de busca, **nao prova de identidade**: antes de
servir um hit, o payload guardado e comparado com o que chegou (ADR-016).

### 2. Reserva

Nao existe coluna de saldo. A Cota e a soma do `usage_ledger` menos estornos e
concessoes (`App\Support\Quota`), e a reserva e um **debito** imediato:

- Free: 1 uso (unidade `uses`, limite vitalicio).
- Pago: `estimated_tokens` (unidade `tokens`, janela da assinatura).

`quota_unit`, `reserved_tokens` e `reserved_uses` ficam na solicitacao: a
liquidacao usa a unidade do momento do debito, e nao o plano de agora. Toda
linha do ledger daquela solicitacao usa o mesmo `created_at` da solicitacao
para a janela do plano nao desalinhar entre o inicio e o ajuste.

### 3. Fila

`ProcessAiQuery` recebe **somente o ULID**. O schema e a descricao viajam
comprimidos em `ai_requests.canonical_payload`, com prazo de vida: a
solicitacao limpa o payload ao terminar. Isso mantem dado pessoal fora do
payload serializado da fila e faz o retry ler sempre o estado atual.

O job consulta o cache **de novo**. Se duas contas pedirem a mesma coisa ao
mesmo tempo, as duas passam pelo POST, uma preenche o cache e a outra encontra
a resposta aqui: a reserva e estornada integralmente e a solicitacao termina
`done` com `cache_hit = true`.

### 4. Provedor e validacao

O `DeepSeekProvider` faz `POST {AI_BASE_URL}/chat/completions` com
`response_format: {type: "json_object"}`. A resposta passa por:

1. `ContractValidator`: os oito campos do contrato, o dialeto pedido, o
   `outputs[target]` e os limites de tamanho.
2. `SqlGuard` (so no alvo `sql`): gate lexical do SQL (ADR-041).

O SQL **nunca e executado**. O gate existe para nao devolver uma escrita como se
fosse leitura; os alvos de ORM nao geram SQL para executar e param no contrato.

### 5. Liquidacao

`SettleAiQuery` trava a conta e depois a solicitacao (sempre nessa ordem, como
no inicio), e e idempotente: um retry que encontra a solicitacao terminal nao
escreve nada.

- Pago: `tokens_in + tokens_out` reais. Se o real for maior que a reserva,
  debita a diferenca; se for menor, estorna; se for igual, nada.
- Free: nao ha ajuste (a unidade e o uso).
- A resposta e gravada em `response_json`, o payload e apagado e o cache e
  **gravado depois do commit**: falhar ao gravar cache nao desfaz uma resposta
  boa.

`FailAiQuery` estorna a reserva inteira e marca `failed` com um
`AiFailureCode`. Vale para falha do provedor, contrato invalido, SQL recusado,
timeout e `ai:reconcile`.

### 6. Polling

A tela consulta `GET /api/ai/queries/{ulid}`. O primeiro atraso vem do servidor
(1500 ms); depois o cliente dobra ate 5000 ms e desiste em 120 s. O `show`
responde 200 em qualquer estado e 404 quando o dono e outra conta.

## Cache

- Chave: `prompt_hash` (MD5 do payload canonico). O payload e a resposta ficam
  em `ai_cache`, o payload comprimido.
- TTL: 30 dias (`AI_CACHE_TTL_DAYS`). A leitura ignora expirado; o comando
  `ai:prune-cache` apaga fisicamente.
- Hit **nao debita** em nenhum caminho. No POST, a solicitacao nasce `done` sem
  ledger; no job, a reserva e estornada.
- `hits` e `last_hit_at` existem para operacao, nao para cobranca.
- Um hit so vale se o contrato continuar valido; linha corrompida e apagada e
  o pedido segue para o provedor.

## Fila e tempos

| Etapa | Tempo | Onde |
| ----- | ----- | ---- |
| Requisicao ao provedor | 60 s (connect 10 s) | `config/ai.php` (`AI_TIMEOUT`) |
| Job | 90 s, 2 tentativas, backoff 5 s/15 s | `ProcessAiQuery` |
| Worker | 120 s | Supervisor (`deploy/supervisor/devtools-worker.conf`) |
| `retry_after` da fila | 150 s | `DB_QUEUE_RETRY_AFTER` |

A ordem e obrigatoria: `retry_after` menor que o timeout do job faria o mesmo
job ser entregue a outro worker enquanto o primeiro ainda trabalha. Se o
`.env` de producao mantiver um valor antigo, um job de 90 s pode rodar duas
vezes - por isso o RUNBOOK cobra `DB_QUEUE_RETRY_AFTER=150` e `queue:restart`
no deploy.

`ai:reconcile` cobre o worker que morreu sem executar `failed()`: solicitacoes
presas em `queued|processing` alem de `timeout + 30 s` sao marcadas `timeout`
e estornadas. Sem ele, a reserva ficaria debitada para sempre e o dedupe
bloquearia pedidos identicos ate o TTL.

## O que nunca sai daqui

- **Schema e descricao nao vao para log.** Logs carregam id, status, tokens,
  `error_code` e classe da excecao. Nem o corpo do provedor, nem a mensagem de
  uma excecao que possa ecoar o prompt.
- **O corpo do provedor nao vira excecao, banco ou resposta.** So o contrato
  validado chega ao cliente.
- **O SQL nao e executado** em nenhuma circunstancia.
- **O cache guarda o schema comprimido por ate 30 dias**; a solicitacao o
  apaga ao terminar. A Politica de Privacidade diz isso nos cinco idiomas.
- **A API e de sessao**: sem token de API, com cookie + CSRF e dono sempre por
  `user_id`.
