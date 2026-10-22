# Arquitetura

Este documento descreve as partes do site que nao cabem numa pagina: o
**AI SQL Builder**, a tool que fala com o servidor (ADR-002), e o **ciclo de
pagamento** - checkout, recurrencia, doacoes e webhooks (F6). As outras oito
tools rodam 100% no navegador e nao tem backend: o servidor entrega a pagina, o
SEO e a conta.

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
| API v1 | `app/Http/Controllers/Api/V1/` | Traduz o PAT do Sanctum para a mesma acao `StartAiQuery`, com lista escopada por token. | Nao tem regra propria de cota: usa `Quota` e o `usage_ledger`. |
| Seguranca | `app/Http/Middleware/SecurityHeaders.php` | Nonce por resposta, CSP, HSTS e cabecalhos de navegador. | Nao decide autorizacao nem conteudo. |

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
- O admin poda pela tela de Cache de IA, que so ve metadados (hash truncado,
  modelo, versao do prompt, tokens, acertos e datas). Prompt, schema,
  `canonical_payload` e resposta nunca saem do servidor.

## API publica v1

Duas portas para a mesma acao, com autenticacoes diferentes:

| Porta | Autenticacao | Quem consome | Escopo da leitura |
| ----- | ------------ | ------------ | ----------------- |
| `/api/ai/queries` | Sessao + CSRF (grupo `web`) | A tela do AI SQL Builder | Solicitacao da conta (`user_id`) |
| `/api/v1/sql-queries` | `Authorization: Bearer` (Sanctum PAT, ability `ai:query`) | Cliente externo servidor-a-servidor | Solicitacao do **token** (`api_token_id`) |

As duas passam por `StartAiQuery`, portanto tem a mesma validacao, a mesma
cota e o mesmo cache. A diferenca esta no dono da leitura: a API v1 grava
`api_token_id` ao criar e filtra `show`/`index` por ele, de modo que um token
nao enxerga consultas criadas por outro token da mesma conta (404). O toggle
de historico decide apenas a tela web; a lista da API e a do proprio token.

Rate limits ficam em `config/api.php` (env `API_RATE_*`), por token: POST 30/min
e 300/h, leitura 120/min. O 402 vem da cota do plano, nao do limitador, e o 429
traz `Retry-After`. O envelope de erro e o mesmo dos outros endpoints de
maquina (`{error:{code,message,details?}}`). Os detalhes de contrato estao em
`docs/API.md` e `docs/openapi.yaml`.

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

## Pagamentos e webhooks

O dinheiro entra por tres caminhos: venda manual (ADR-027), Checkout Pro avulso
e Preapproval (ADR-045). Todos terminam no mesmo lugar: uma linha fiscal em
`payments` e um periodo em `subscriptions` (ADR-046).

### Fluxo do pagamento

```text
navegador -> /subscribe
  Pagar agora ---------> POST /subscribe/checkout -> StartCheckout --+
  Renovacao automatica -> POST /subscribe/recurring -> StartPreapproval -+
                                                                        |
                                                                        v
                                                          Mercado Pago (Checkout Pro / Preapproval)
                                                                        |
  retorno /subscribe/checkout/{ulid}/return <---------------------------+
                                                                        |
  Mercado Pago -> POST /webhooks/mercadopago <--------------------------+
                       | assinatura HMAC + unique (gateway, event_id)
                       v
                 webhook_events  --fila database-->  ProcessWebhookEvent
                                                          |
                                                          | busca o recurso na API
                                                          v
                                                   HandleWebhookEvent
                                                          |
                                                          v
                          ApproveGatewayPayment -> OpenSubscriptionPeriod + RegisterPayment
                                                          |
                                                          v
                 checkout_sessions aprovada, payments (fiscal), subscriptions (periodo)
```

### Camadas do pagamento

| Camada | Arquivo | Responsabilidade | Nao faz |
| ------ | ------- | ---------------- | ------- |
| Tela | `resources/js/pages/Subscribe.vue`, `Donate.vue`, `dashboard/Billing.vue` | Escolha do caminho, valores, QR, avisos de renovacao e erro generico. | Nao confirma pagamento nem decide vigencia. |
| Contrato | `app/Contracts/Payments/PaymentGateway.php` | Interface unica do provedor (preferencia, preapproval, Pix, consulta, estorno, cancelamento). | Nao conhece Eloquent. |
| Provedor | `app/Services/Payments/MercadoPagoGateway.php` + `app/Support/Payments/` | HTTP fino, timeouts, `X-Idempotency-Key`, DTOs e excecao sem corpo. | Nao grava banco nem decide acesso. |
| Checkout | `app/Actions/Payments/StartCheckout.php`, `StartPreapproval.php`, `CancelRecurring.php` | Regras de inicio, criacao da sessao e chamada ao provedor. | Nao libera periodo. |
| Webhook | `Webhooks\MercadoPagoWebhookController` + `ProcessWebhookEvent` + `HandleWebhookEvent` | Assinar, guardar, enfileirar, buscar o recurso e rotear por topico. | Nao confia no corpo. |
| Aprovacao | `ApproveGatewayPayment` | Confere valor/moeda com a sessao, abre periodo e grava pagamento. | Nao chama o provedor de novo. |
| Periodo | `OpenSubscriptionPeriod` | Empilha ou inicia o periodo conforme a assinatura vigente. | Nao mexe em pagamento. |
| Doacao | `CreateDonation` + `ConfirmDonation` + `PixPayload` | Pix dinamico/estatico, confirmacao idempotente e recibo. | Nao entra em `payments` nem consome cota. |

### Um periodo por pagamento

`subscriptions` ja nasce com uma linha por concessao. A partir da F6, **cada
pagamento aprovado abre uma linha nova**, e nao estica a existente:

- Free nao cria linha;
- mesmo plano empilha no maior `current_period_end` futuro daquele plano, ou
  comeca agora se nao houver periodo futuro;
- plano diferente comeca agora.

A assinatura vigente e a que cobre o instante (`Subscription::scopeCurrent()`),
e `User::activeSubscription()` ordena por `current_period_start desc, id desc`.
`User::nextSubscription()` devolve a proxima linha futura, para a fatura
mostrar o que ja foi pago sem confundir com o vigente. A cota le sempre o
periodo da assinatura vigente: uma linha futura nao abre franquia nem consome a
atual.

Cancelar a renovacao grava `renewal_canceled_at` e nao muda `status`: o acesso
segue ate o fim do periodo pago. A falha de renovacao grava
`renewal_failed_at`, avisa uma vez e some na aprovacao seguinte.

### Webhooks

A rota fica fora do locale e do CSRF, e a unica autenticacao e a assinatura
`x-signature` sobre `id`/`request-id`/`ts` (HMAC-SHA256, comparacao em tempo
constante). O controller responde rapido: grava o payload cru em
`webhook_events` (unique `(gateway, event_id)`) e enfileira
`ProcessWebhookEvent`.

O job, com 3 tentativas e backoff 10/60/300 s, busca o recurso citado na API do
provedor (`payment`, `preapproval` ou `authorized_payment`) e age sobre o
resultado real. As acoes sao idempotentes por `gateway_ref`: o mesmo pagamento
chegando por `payment` e por `subscription_authorized_payment` nao duplica
linha, periodo nem recibo. Topico desconhecido fica `ignored`. O admin le os
eventos em `/admin/webhooks`, com payload e erro, sem botao de reprocessar.

### Doacoes

`CreateDonation` decide entre **Pix dinamico** (`POST /v1/payments`, QR do
provedor, referencia `don_<ULID>`, expiracao de 60 min) e **Pix estatico**
(`PixPayload` EMV + CRC16 montado localmente, QR em SVG, sem confirmacao
automatica). A doacao vive fora de `payments`: nao entra em fatura e nao
consome cota. `ConfirmDonation` confere valor e moeda com a linha gravada e so
transita `pending -> paid` uma vez, disparando o recibo quando ha e-mail.

### Tempos e rotinas

| Etapa | Tempo | Onde |
| ----- | ----- | ---- |
| Requisicao ao provedor | 20 s (connect 5 s) | `config/payments.php` |
| Job de webhook | 60 s, 3 tentativas, backoff 10/60/300 s | `ProcessWebhookEvent` |
| Checkout e QR de doacao | 60 min | `PAYMENTS_CHECKOUT_EXPIRES_MINUTES`, `DONATIONS_EXPIRES_MINUTES` |
| Varredura de vencidos | 5 min | `payments:expire` em `task_schedules` |
| Poda de eventos | diaria as 03:30, 30 dias | `webhooks:prune --days=30` |
| Poda do historico | diaria as 03:45, 90 dias | `history:prune` (`AI_HISTORY_RETENTION_DAYS`) |

A expiracao e resolvida tambem na leitura (`effectiveStatus()`): um pendente
vencido ja aparece vencido antes de a rotina passar, e o Pix estatico
(`gateway = manual`) nunca expira. Nenhuma rotina apaga `payments`,
`subscriptions` ou `donations`.

## Seguranca HTTP

`SecurityHeaders` roda em toda resposta HTML: gera um nonce novo, entrega ao
`@vite` e ao script inline do tema e monta a CSP. Script de terceiro so entra
se estiver na lista (GA e Turnstile); estilo aceita `'unsafe-inline'` por causa
do `<style>` do tema e dos `@fonts`, e `object-src`/`frame-ancestors` fecham o
que nao e usado. O modo vem de `CSP_MODE`: `enforce` em producao, `report-only`
para diagnosticar e `off` em local.

HSTS so e emitido em requisicao segura. Atras do proxy, `TRUSTED_PROXIES`
decide se `X-Forwarded-Proto` vale - sem ele o Laravel veria `http` e o cookie
`secure`/canonical sairiam errados. `URL::forceScheme('https')` em producao
garante que canonical, OG, sitemap e robots nunca anunciem `http`.

Turnstile fica no cadastro e valida no servidor (`siteverify`, timeout de 5 s).
Sem as duas chaves, o widget nao renderiza e a regra passa: desativado e
explicito, nunca um meio-termo. Com chaves, falha de rede fecha o cadastro.

## O que nunca sai daqui

- **Schema e descricao nao vao para log.** Logs carregam id, status, tokens,
  `error_code` e classe da excecao. Nem o corpo do provedor, nem a mensagem de
  uma excecao que possa ecoar o prompt.
- **O corpo do provedor nao vira excecao, banco ou resposta.** So o contrato
  validado chega ao cliente.
- **O SQL nao e executado** em nenhuma circunstancia.
- **O cache guarda o schema comprimido por ate 30 dias**; a solicitacao o
  apaga ao terminar. A Politica de Privacidade diz isso nos cinco idiomas.
- **A API da tela e de sessao** (cookie + CSRF, dono por `user_id`); a **API
  publica v1** usa Personal Access Token do Sanctum com a ability `ai:query` e
  escopa a leitura por `api_token_id`. O valor do token so aparece uma vez e o
  banco guarda apenas o hash.
- **Segredo do gateway nao sai do `.env`.** `PaymentGatewayException` nao
  carrega corpo de resposta nem token; a tela mostra mensagem generica
  traduzida, e o log recebe o report sem credencial. O webhook secret tambem
  nunca aparece em log, resposta ou evento.
- **O payload do webhook e dado pessoal.** Fica em `webhook_events` por 30 dias,
  visivel so no admin, e a poda apaga apenas essa tabela. Nome e e-mail de
  doador so aparecem para o admin; a pagina de status, publica por ULID, mostra
  valor e estado, nunca os dados de quem doou.
