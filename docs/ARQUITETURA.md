# Arquitetura

Este documento descreve o que não cabe em uma página: o **AI SQL Builder**, a
tool que fala com o servidor (ADR-002); o **Base64 Image**, a exceção de
processamento no servidor (ADR-055); e o **ciclo de pagamento** — checkout,
recorrência, doações, estornos e webhooks (F6). As oito tools restantes do
registro rodam 100% no navegador: o servidor entrega a página, o SEO e a conta.
O **Contato** é uma superfície pública fora do registro de tools.

Este documento guarda desenho, fronteiras, invariantes e o porquê. O contrato
HTTP está em [API.md](API.md) e [openapi.yaml](openapi.yaml); a operação, em
[RUNBOOK.md](RUNBOOK.md), [guides/cron.md](guides/cron.md) e
[guides/mercado-pago.md](guides/mercado-pago.md); o produto e as fases, em
[SPEC.md](SPEC.md); a interface, em [design/DESIGN.md](design/DESIGN.md); e o
vocabulário, em [glossario.md](glossario.md). As decisões ficam em `ADR-0xx`.

## Mapa das superfícies do servidor

| Superfície | Entrada | O que faz | Onde aprofundar |
| ---------- | ------- | --------- | --------------- |
| AI SQL Builder (web) | `POST /api/ai/queries`, `GET /api/ai/history`, `GET /api/ai/queries/{ulid}` | Sessão + CSRF; formulário, fila, polling e histórico. | `## Ciclo de uma solicitação`; ADR-040, ADR-042, ADR-051 |
| API pública v1 | `/api/v1/*` | Bearer PAT; a mesma ação `StartAiQuery`, com leitura escopada por token. | `## API pública v1`; ADR-050 |
| Pagamentos | `/subscribe`, `/subscribe/checkout`, `/dashboard/billing`, `/admin/payments`, `/admin/intents` | Venda manual, Checkout Pro, Preapproval e confirmação de intenção. | `## Pagamentos e webhooks`; ADR-045, ADR-046, ADR-049, ADR-054 |
| Webhooks | `POST /webhooks/mercadopago` | Assinatura HMAC, armazenamento cru, fila e busca do recurso. | `### Webhooks`; ADR-047 |
| Base64 Image | `/api/tools/base64-image/*`, `/tools/base64-image/{download,preview}` | Conversão no servidor, armazenamento, retenção e download. | `### Base64 Image`; ADR-055 |
| Contato | `GET/POST /{locale}/contact` | Formulário público, gravação e e-mail para a administração. | `### Contato` |
| Admin | `/admin/*` | Contas, assinaturas, planos, intenções, pagamentos, webhooks, alvos e cache. | ADR-025; [DESIGN.md](design/DESIGN.md) |
| i18n e prompts | `/{locale}`, `lang/`, `resources/prompts/` | Cinco idiomas; o locale entra no payload e no hash da IA. | ADR-010, ADR-026, ADR-056; [SPEC.md](SPEC.md) |
| SEO | `sitemap.xml`, `robots.txt`, JSON-LD e OG | Páginas públicas, hreflang, dados estruturados e imagem por tool. | ADR-009, ADR-030, ADR-033, ADR-053 |
| Deploy e operação | `deploy/`, Supervisor, cron | Deploy por cópia instalada, worker e rotinas de `task_schedules`. | [RUNBOOK.md](RUNBOOK.md), [guides/cron.md](guides/cron.md); ADR-014, ADR-035 |
| Contas, planos e cota | Auth, `/settings/*`, `plans`, `subscriptions` | Conta, plano, franquia acumulativa e tokens de API. | ADR-012, ADR-025, ADR-050, ADR-054 |
| Segurança HTTP | Middleware e configuração | CSP, HSTS, proxies confiáveis e Turnstile. | `## Segurança HTTP`; ADR-052 |

### Base64 Image (ADR-055)

É a exceção deliberada à ADR-002: o navegador envia o arquivo e a conversão
acontece no PHP, porque uma imagem grande em Data URI estoura a memória da
aba. O limite é 2 MB para Free e anônimo e 25 MB para plano pago; o plano pode
sobrepor com `max_image_upload_bytes`, e os padrões ficam em
[config/tools.php](../config/tools.php).

O servidor grava o original (`<ulid>.original.<ext>`) e o `.txt` com a Data URI
em `storage/app/data/base64_images/{user_id}` ou em `.../anonymous`. Só conta
autenticada cria linha em `base64_image_conversions`; o anônimo existe apenas
em disco. Quando o plano é pago e a Data URI passa de 2 MB, a resposta não
devolve o texto: marca `direct_download` e o cliente baixa o `.txt` pela rota
de download. Abaixo disso, a própria resposta traz `data_uri`. A prévia serve
o original com o MIME real.

A retenção é de 24 horas para Free e anônimo e de 30 dias para pago. O comando
`base64-images:prune` roda de hora em hora, apaga só o arquivo físico e grava
`file_deleted_at`; a linha de metadados permanece para o histórico. Arquivos
anônimos são podados pelo `mtime`, sem linha. O histórico autenticado exige
sessão, confere o dono e lista as últimas 50 conversões; apagar uma conversão
remove arquivos e linha (ou os arquivos anônimos). A ferramenta não consome
cota de IA e não passa pelo `usage_ledger`.

### Contato

`GET /{locale}/contact` é uma página pública e indexável. O `POST` grava
`contact_messages` com nome, e-mail, assunto, mensagem, IP, user agent e, se
houver sessão, `user_id`; depois enfileira `ContactMessageReceived` para
`CONTACT_EMAIL`. O `StoreContactMessageRequest` limita a 5 envios por minuto
por IP e o honeypot `hp_website` descarta o bot em silêncio, devolvendo a
mesma mensagem de sucesso. Não há tela de admin para ler as mensagens.

## Visão em uma tela

```text
navegador (Vue/Inertia)
  SchemaForm --> POST /api/ai/queries --> 200 (cache) | 202 (fila)
       ^                                            |
       |                                            v
  polling GET /api/ai/queries/{ulid}          Laravel (grupo web, sessão + CSRF)
       ^                                            |
       |                                            v
  ResultPanel / Histórico                     StartAiQuery
       ^                                      |  dialeto antes da transação
       |                                      |  locale -> prompt -> hash
       |                                      |  dedupe -> cache -> limites
       |                                      |  hit -> done, sem ledger
       |                                      |  miss -> reserva + queued
       |                                      v
       |                                fila `database` (tabela jobs)
       |                                      |
       |                                      v
       |                                ProcessAiQuery (worker)
       |                                      |  cache de novo
       |                                      |  contrato + Gate do SQL
       |                                      v
       |                                DeepSeek (HTTP JSON)
       |                                      |
       |                                      v
       |                                SettleAiQuery / FailAiQuery
       |                                      |  usage real, ajuste/estorno
       |                                      v
       +---------------------------------- MySQL (ai_requests, ai_cache, usage_ledger)
```

## Fronteiras da IA

| Camada | Arquivo | Responsabilidade | Não faz |
| ------ | ------- | ---------------- | ------- |
| Tela | `resources/js/tools/ai-sql-builder/` | Formulário, estados, polling, abas de resultado, copiar/baixar e link para o histórico. | Não decide autorização nem cota; desenha o que o servidor mandou. |
| Props | `app/Support/Ai/AiToolProps.php` | Estado inicial da página (provider ligado, motivo de bloqueio, cota, alvos, limites, endpoints). | Não é autorização: o POST valida tudo de novo. |
| HTTP web | `AiQueryController` + `StoreAiQueryRequest` + `AiQueryResource` | Validação de forma, dono da solicitação, status 200/202, envelope e `poll_after_ms`. | Não fala com o provedor e não grava ledger. |
| API v1 | `app/Http/Controllers/Api/V1/` | Traduz o PAT do Sanctum para a mesma ação `StartAiQuery`, com lista escopada por token. | Não tem regra própria de cota: usa `Quota` e o `usage_ledger`. |
| Início | `app/Actions/Ai/StartAiQuery.php` | Dialeto, locale, dedupe, cache, limites do plano, reserva, criação e dispatch. | Não chama o provedor. |
| Fila | `app/Jobs/ProcessAiQuery.php` | Reidrata o payload, consulta o cache de novo, chama o provedor, valida e delega a liquidação. | Não carrega schema/descrição no payload da fila. |
| Núcleo | `app/Support/Ai/` | Canonicalização, hash, prompts, estimativa, contrato, Gate do SQL, cache e ledger. | Não conhece HTTP nem Vue. |
| Provedor | `app/Services/Ai/DeepSeekProvider.php` | Um POST JSON compatível com OpenAI, timeout e mapeamento de erro. | Não inclui o corpo do provedor em exceção/log. |
| Segurança | `app/Http/Middleware/SecurityHeaders.php` | Nonce por resposta, CSP, HSTS e cabeçalhos de navegador. | Não decide autorização nem conteúdo. |

A API v1 não é uma segunda implementação: é outra porta para a mesma ação,
com autenticação e escopo de leitura diferentes. Tudo o que vale para a web
vale para ela — cota, cache, estados e falhas —, mas a lista de consultas é
escopada pelo token; o opt-in de histórico decide apenas a tela web.

## Ciclo de uma solicitação

### 1. POST

Antes de abrir a transação, `StartAiQuery` confere a configuração do provedor
e o modelo, valida se o alvo aceita o dialeto, resolve o locale, carrega o
prompt, canonicaliza o payload, monta as mensagens e estima os tokens. O
dialeto vem primeiro porque a resposta de um alvo que não aceita aquele
dialeto não serve, e não vale gastar uma chamada para descobrir.

A ordem dentro da transação também é deliberada:

1. **Trava a conta** (`users` com `lockForUpdate`). Duas abas da mesma conta não
   avaliam cota e cache ao mesmo tempo.
2. **Dedupe antes do cache**: uma solicitação em voo já vai produzir a resposta.
   O dedupe é por `user_id` + `api_token_id` + `prompt_hash`, apenas entre
   pendentes dentro de `AI_INFLIGHT_TTL_SECONDS` (600 s); ele não cruza canais
   nem tokens.
3. **Cache antes dos limites do plano**: um hit não custa nada e não precisa
   passar pelo teto de cota.
4. **Limites e reserva**: só a solicitação nova valida `max_schema_bytes`,
   `max_prompt_chars` e a cota, cria a linha `queued` e grava o débito.

O payload canonicalizado contém fonte do schema, dialeto, alvo, schema,
descrição, modelo, versão do prompt e **locale**. O JSON tem chaves
ordenadas; o MD5 dele é o `prompt_hash`. O hash é chave de busca, **não prova
de identidade**: antes de servir um hit, o payload guardado é comparado
inteiro com o que chegou (ADR-016, ADR-056). Os prompts de sistema vivem em
`resources/prompts/system/` nos cinco idiomas.

O dispatch de `ProcessAiQuery` acontece depois do commit e só para solicitação
nova. Cache hit nasce `done` sem ledger, e dedupe devolve uma solicitação que
já tem job.

### 2. Reserva e cota

Não existe coluna de saldo. A cota é calculada na leitura por
`App\Support\Quota`, e a reserva é um **débito imediato**:

- **Free**: 5 usos vitalícios na carga inicial (`lifetime_free_uses`); cada
  solicitação reserva 1 uso e a liquidação não ajusta, porque a unidade é o uso.
- **Pago**: reserva `estimated_tokens`; a liquidação compara com
  `tokens_in + tokens_out` e debita a diferença ou estorna o excedente.

`quota_unit`, `reserved_tokens` e `reserved_uses` ficam na solicitação: a
liquidação usa a unidade do momento do débito, não o plano de agora.

Desde a ADR-054, o plano pago não lê uma janela fechada. O limite é a soma
dos lotes mensais já iniciados de **todas** as assinaturas pagas não estornadas
(1 lote no mensal, 1 por mês de calendário até 12 no anual), mais as concessões
manuais de tokens. O consumo é todo o histórico de débitos de tokens menos os
estornos registrados. O que sobra de um mês passa para o seguinte; uma
assinatura com `ended_at` ainda credita o que já começou, e `refunded_at` tira
o crédito da linha inteira. `remaining` nunca é negativo: consumo acima do
direito aparece como `exhausted`, não como saldo devedor.

A assinatura vigente define plano e unidade. Uma linha futura já paga não abre
franquia e não consome a atual; se não há assinatura vigente, a conta opera no
Free e o saldo acumulado de tokens volta a valer quando um plano pago
reativar. `window` no JSON da cota é o período vigente apenas para exibição, e
`next_credit_at` aponta o próximo lote contratado.

### 3. Fila

`ProcessAiQuery` recebe **somente o ULID**. O schema e a descrição viajam
comprimidos em `ai_requests.canonical_payload`, com prazo de vida: a solicitação
limpa o payload ao terminar. Isso mantém dado pessoal fora do payload
serializado da fila e faz o retry ler sempre o estado atual.

O job consulta o cache **de novo**. Se duas contas pedirem a mesma coisa ao
mesmo tempo, as duas passam pelo POST, uma preenche o cache e a outra encontra
a resposta aqui: a reserva é estornada integralmente e a solicitação termina
`done` com `cache_hit = true`. Só depois desse segundo miss a solicitação vira
`processing` e o provedor é chamado.

### 4. Provedor e validação

O `DeepSeekProvider` faz `POST {AI_BASE_URL}/chat/completions` com
`response_format: {type: "json_object"}`. A resposta passa por:

1. `ContractValidator`: os oito campos obrigatórios do contrato (`sql`,
   `dialect`, `assumptions`, `warnings`, `tables_used`,
   `needs_clarification`, `clarification`, `outputs`), o dialeto pedido, o
   `outputs[target]` e os limites de tamanho. `title` e `explanation` são
   opcionais.
2. `SqlGuard` (só no alvo `sql` e quando não é pedido de esclarecimento):
   **Gate do SQL**, a verificação lexical da ADR-041.

O SQL **nunca é executado**. O Gate existe para não devolver uma escrita como
se fosse leitura; os alvos de ORM param no contrato e não geram SQL para
executar. Quando `needs_clarification` é verdadeiro, o contrato exige `sql`
vazio e `clarification` preenchida; a solicitação termina `done` e consome a
reserva, porque o trabalho foi feito.

### 5. Liquidação

`SettleAiQuery` trava a conta e depois a solicitação (sempre nessa ordem) e é
idempotente: um retry que encontra a solicitação terminal não escreve nada.

- **Pago**: `tokens_in + tokens_out` reais. Se o real for maior que a reserva,
  debita a diferença; se for menor, estorna; se for igual, nada.
- **Free**: não há ajuste (a unidade é o uso).
- A resposta é gravada em `response_json`, o `canonical_payload` é apagado e o
  cache é **gravado depois do commit**: falhar ao gravar cache não desfaz uma
  resposta boa.

`FailAiQuery` estorna a reserva inteira e marca `failed` com um
`AiFailureCode`. Vale para falha do provedor, contrato inválido, SQL recusado,
timeout e `ai:reconcile`. Um cache hit dentro do job também estorna a reserva
inteira, por `SettleAiQuery::fromCache`.

### 6. Polling

A tela consulta `GET /api/ai/queries/{ulid}`. O primeiro atraso vem do
servidor (`poll_after_ms`, 1500 ms); depois o cliente dobra até 5000 ms e
desiste em 120 s. O `show` responde 200 em qualquer estado e 404 quando o dono
é outra conta. O corpo inclui o estado atual da cota, calculado na leitura. Na
API v1, o equivalente é `poll_url`, que fica nulo em `done` e `failed` — o
sinal para o cliente parar.

### 7. Histórico e retenção

O histórico é opt-in e exclusivo dos planos pagos (ADR-051). Ele nasce
desligado em `users.history_enabled`; a tela do Free mostra o CTA de upgrade,
e a lista só enxerga linhas gravadas com `history_visible`.

Quando o histórico está visível, a linha guarda `title`, `prompt` (a
descrição) e `schema` em claro. O `canonical_payload` continua apagado no
terminal; a cópia comprimida do payload só permanece no cache, com o TTL
próprio. `history:prune` apaga todas as solicitações terminais mais antigas que
`AI_HISTORY_RETENTION_DAYS` (90 dias por padrão), inclusive as que nunca foram
visíveis no histórico; `ClearHistory` apaga as `done`/`failed` da conta sob
demanda. Nenhum dos dois toca no `usage_ledger`
nem no `ai_cache`: cota e cache compartilhado continuam valendo.

Desligar o toggle impede novas gravações, mas não apaga as antigas. A lista da
API v1 é escopada pelo token e não depende do toggle da tela; o histórico web
é da conta e depende dele.

## Cache

- **Chave**: `prompt_hash` (MD5 do payload canônico). O payload comprimido e a
  resposta validada ficam em `ai_cache`.
- **TTL**: 30 dias (`AI_CACHE_TTL_DAYS`). A leitura ignora expirado; o comando
  `ai:prune-cache` apaga fisicamente.
- **Hit não debita** em nenhum caminho. No POST, a solicitação nasce `done`
  sem ledger; no job, a reserva é estornada.
- `hits` e `last_hit_at` existem para operação, não para cobrança.
- Um hit só vale se o contrato continuar válido. Há dois casos diferentes:
  hash divergente é **cache miss** e a linha é preservada; payload
  indecifrável ou contrato inválido é linha corrompida, que é apagada e deixa
  o pedido seguir para o provedor.
- O cache é **global**, não por conta e não por token; qualquer conta pode
  receber a mesma resposta, e o `locale` faz parte do hash, então idiomas
  diferentes não se misturam.
- O admin poda pela tela de Cache de IA, que só vê metadados (hash truncado,
  modelo, versão do prompt, tokens, acertos e datas). Prompt, schema,
  `canonical_payload` e resposta nunca aparecem nessa tela.

## API pública v1

Duas portas para a mesma ação, com autenticações diferentes:

| Porta | Autenticação | Quem consome | Escopo da leitura |
| ----- | ------------ | ------------ | ----------------- |
| `/api/ai/queries` | Sessão + CSRF (grupo `web`) | A tela do AI SQL Builder | Solicitação da conta (`user_id`) |
| `/api/v1/sql-queries` | `Authorization: Bearer` (Sanctum PAT, ability `ai:query`) | Cliente externo servidor-a-servidor | Solicitação do **token** (`api_token_id`) |

Os cinco endpoints da v1 são:

| Método | Caminho | Para que serve |
| ------ | ------- | -------------- |
| `GET` | `/api/v1/me` | Identifica a conta e o estado da cota. |
| `GET` | `/api/v1/output-targets` | Lista os alvos habilitados e os dialetos aceitos. |
| `GET` | `/api/v1/sql-queries` | Lista as consultas do token, com paginação. |
| `POST` | `/api/v1/sql-queries` | Cria a consulta; responde 200 no cache e 202 na fila. |
| `GET` | `/api/v1/sql-queries/{ulid}` | Consulta o estado; devolve `poll_url` enquanto não termina. |

O PAT é criado em `/settings/api-tokens`; o texto puro aparece uma vez e o
banco guarda só o hash. Um token sem a ability `ai:query` responde 403, e um
token não enxerga consultas criadas por outro token nem pela tela (404). As
duas portas passam por `StartAiQuery`, portanto têm a mesma validação, a mesma
cota e o mesmo cache.

Os limites de taxa, o envelope de erro e os detalhes de contrato estão em
[API.md](API.md) e [openapi.yaml](openapi.yaml); os números vivem em
`config/api.php`. O 402 vem da cota do plano, não do limitador, e o 429 traz
`Retry-After`.

## Fila e tempos

| Etapa | Tempo | Onde |
| ----- | ----- | ---- |
| Requisição ao provedor | 60 s (connect 10 s) | `config/ai.php` (`AI_TIMEOUT`) |
| Job | 90 s, 2 tentativas, backoff 5 s/15 s | `ProcessAiQuery` |
| Worker | 120 s | Supervisor (`deploy/supervisor/devtools-worker.conf`) |
| `retry_after` da fila | 150 s | `DB_QUEUE_RETRY_AFTER` |

A ordem é obrigatória: `retry_after` menor que o timeout do job faria o mesmo
job ser entregue a outro worker enquanto o primeiro ainda trabalha. Se o
`.env` de produção mantiver um valor antigo, um job de 90 s pode rodar duas
vezes — por isso o [RUNBOOK.md](RUNBOOK.md) cobra `DB_QUEUE_RETRY_AFTER=150` e
`queue:restart` no deploy.

`ai:reconcile` cobre o worker que morreu sem executar `failed()`:
solicitações presas em `queued|processing` além do timeout do job mais a folga
(`reconcile_grace_seconds`, 30 s por padrão) são marcadas **`failed` com
`error_code = timeout`** e têm a reserva estornada.
Não existe status `timeout`; `timeout` é um código de falha. Sem o
reconciliador, a reserva ficaria debitada para sempre e o dedupe bloquearia
pedidos idênticos até o TTL.

## Estados e falhas da IA

| Estado | Significado | Terminal? |
| ------ | ----------- | --------- |
| `queued` | Criada, reservada e aguardando o job. | Não |
| `processing` | O worker pegou e pode estar falando com o provedor. | Não |
| `done` | Liquidada com resposta (inclusive cache hit e pedido de esclarecimento). | Sim |
| `failed` | Reserva estornada e `error_code` gravado. | Sim |

| Código | Quando aparece | Efeito |
| ------ | -------------- | ------ |
| `provider_unavailable` | Provedor indisponível, erro de rede ou resposta inválida do provedor. | Estorna a reserva. |
| `unsafe_sql` | O Gate do SQL recusou a saída do alvo `sql`. | Estorna a reserva. |
| `invalid_contract` | O JSON não passou no contrato. | Estorna a reserva. |
| `timeout` | O job estourou o tempo ou o `ai:reconcile` fechou a solicitação. | Estorna a reserva. |
| `internal` | Payload inválido, exceção inesperada ou falha interna do fluxo de IA. | Estorna a reserva. |

`invalid_response` é um **motivo** de `ProviderException` usado no log, não um
`error_code` gravado: quando o provedor devolve algo inválido, a solicitação
termina como `provider_unavailable`. `AiFailureCode::InvalidResponse` existe no
enum, mas nenhum caminho o grava. `needs_clarification` é `done` e consome a
reserva.

## Pagamentos e webhooks

O dinheiro entra por **quatro caminhos**: venda manual (ADR-027), Checkout Pro
avulso, Preapproval (ADR-045) e confirmação de uma intenção de assinatura
(ADR-034, ADR-054). Os dois caminhos online usam a mesma rota
`POST /subscribe/checkout`, com `mode=one_time` ou `mode=recurring`; não há
uma rota separada para recorrência.

**Não há conciliação de pagamentos.** O webhook é a única confirmação
automática; o retorno do navegador é só a porta de volta e não libera nada.
Não existe rotina que varra pagamentos aprovados no provedor para corrigir o
banco: um webhook perdido é uma anomalia para o admin, não algo que o sistema
descobre sozinho.

### Quatro caminhos

| Caminho | Como começa | O que cria | Confirmação |
| ------- | ----------- | ---------- | ----------- |
| Venda manual | Admin em `/admin/payments` | `RegisterPayment` grava a linha fiscal; com plano pago, `GrantPlan` abre a assinatura e vincula o pagamento. | Imediata, pelo próprio admin; e-mail é best-effort. |
| Checkout Pro | `/subscribe` → `POST /subscribe/checkout` com `mode=one_time` → `StartCheckout` | Sessão `checkout_sessions` com valor e moeda congelados; período só na aprovação. | Webhook `payment` aprovado → `ApproveGatewayPayment`. |
| Preapproval | A mesma rota com `mode=recurring` → `StartCheckout` | Sessão + preapproval no provedor; autorização não abre período. | Primeira cobrança autorizada e renovações → `ApproveGatewayPayment`. |
| Intenção de assinatura | Ação `confirm-payment` em `/admin/intents` | `ConfirmIntentPayment` registra o pagamento (que concede o plano) e marca a intenção como atendida, na mesma transação. | Imediata, pelo admin. |

### Fluxo online

O diagrama cobre os dois caminhos online; venda manual e intenção de assinatura
são registradas pelo admin, sem passar pelo provedor.

```text
navegador -> /subscribe
  Pagar agora ---------> POST /subscribe/checkout (mode=one_time)
  Renovação automática -> POST /subscribe/checkout (mode=recurring)
                              |
                              v
                    StartCheckout -> Mercado Pago
                              |
  retorno <-------------------+  (não confirma nada)
                              |
  Mercado Pago -> POST /webhooks/mercadopago
                              |
                    HMAC + unique (gateway, event_id)
                              |
                              v
                 webhook_events --fila database--> ProcessWebhookEvent
                                                          |
                                                          | busca o recurso na API
                                                          v
                                                   HandleWebhookEvent
                                                          |
                    +-------------------------------------+----------------------+
                    |                                     |                      |
                    v                                     v                      v
          ApproveGatewayPayment                  RefundGatewayPayment      ConfirmDonation
          (payment approved)                     (refund/chargeback)       (donation)
                    |                                     |                      |
                    v                                     v                      v
       OpenSubscriptionPeriod + RegisterPayment     RefundPayment        donations.paid
                    |
                    v
       checkout_sessions approved, payments, subscriptions
```

### Camadas do pagamento

| Camada | Arquivo | Responsabilidade | Não faz |
| ------ | ------- | ---------------- | ------- |
| Tela | `resources/js/pages/Subscribe.vue`, `Donate.vue`, `dashboard/Billing.vue` | Escolha do caminho, valores, QR, avisos de renovação e erro genérico. | Não confirma pagamento nem decide vigência. |
| Contrato | `app/Contracts/Payments/PaymentGateway.php` | Interface única do provedor (preferência, preapproval, Pix, consulta, estorno, cancelamento). | Não conhece Eloquent. |
| Provedor | `app/Services/Payments/MercadoPagoGateway.php` + `app/Support/Payments/` | HTTP fino, timeouts, `X-Idempotency-Key`, DTOs e exceção sem corpo. | Não grava banco nem decide acesso. |
| Checkout | `app/Actions/Payments/StartCheckout.php`, `CancelRecurring.php` | Regras de início, criação da sessão, chamada ao provedor e cancelamento de preapproval. | Não libera período. |
| Venda manual e intenção | `app/Actions/Admin/RegisterPayment.php`, `ConfirmIntentPayment.php`, `GrantPlan.php` | Registro fiscal, concessão do plano e vínculo pagamento → assinatura. | Não chamam o gateway. |
| Webhook | `MercadoPagoWebhookController` + `ProcessWebhookEvent` + `HandleWebhookEvent` | Assinar, guardar, enfileirar, buscar o recurso e rotear por tópico. | Não confia no corpo. |
| Aprovação | `ApproveGatewayPayment` | Confere valor/moeda com a sessão, abre período e grava o pagamento. | Não chama o provedor de novo. |
| Período | `OpenSubscriptionPeriod` | Empilha ou inicia o período conforme a assinatura vigente. | Não mexe em pagamento. |
| Estorno | `RefundGatewayPayment` + `RefundPayment` | No gateway, provedor primeiro; no manual, só estado local; depois assinatura e aviso. | Não devolve consumo no ledger. |
| Doação | `CreateDonation` + `ConfirmDonation` + `PixPayload` | Pix dinâmico/estático, confirmação idempotente e recibo. | Não entra em `payments` nem consome cota. |

### Um período por pagamento

`subscriptions` guarda **uma linha por período pago**, não uma linha esticada
por concessão. Cada pagamento aprovado abre uma linha nova, e não estica a
existente:

- Free não cria linha;
- mesmo plano empilha no maior `current_period_end` futuro daquele plano, ou
  começa agora se não houver período futuro;
- plano diferente começa agora.

A assinatura vigente é a que cobre o instante (`Subscription::scopeCurrent()`),
e `User::activeSubscription()` ordena por `current_period_start desc, id desc`.
`User::nextSubscription()` devolve a próxima linha futura, para a fatura
mostrar o que já foi pago sem confundir com o vigente. A cota acumulativa da
ADR-054 não depende da janela: ela soma os lotes de todas as assinaturas não
estornadas, e `window` serve só para a tela.

`StartCheckout` recusa recorrência duplicada no mesmo plano; nos demais casos,
cancela as preapprovals ativas antes de abrir a nova sessão (o provedor vem
primeiro; se ele recusar, nada muda no banco). O cancelamento grava
`renewal_canceled_at` e mantém o acesso até o fim do período pago.
`OpenSubscriptionPeriod` não encerra a linha anterior; `GrantPlan` encerra
quando o plano é Free ou diferente, mas o caminho de aprovação do gateway não.
Na prática, uma troca de plano pelo gateway pode deixar a linha antiga ativa
até `current_period_end`, creditando na cota acumulativa. Isso é o
comportamento atual, não uma invariante de produto.

A aprovação compara valor e moeda com a sessão congelada, não com o preço de
agora. O prazo `expires_at` da sessão é usado pela tela e por
`payments:expire`; `ApproveGatewayPayment` não o usa para recusar uma
aprovação tardia.

### Ciclo da preapproval

`StartCheckout` com `mode=recurring` cria a sessão e a preapproval no provedor;
o valor e a moeda ficam congelados na sessão, e o prazo padrão é de 60 minutos.
A autorização do pagador chega pelo webhook `subscription_preapproval`:
grava `authorized_at`, limpa `expires_at` e **não abre período**. A primeira
cobrança autorizada pode levar cerca de uma hora; é ela que abre o primeiro
período, pelo mesmo `ApproveGatewayPayment` de um pagamento avulso. As
renovações seguintes empilham um período novo na mesma preapproval.

Cancelar a renovação chama o provedor primeiro; `404`/`409` de "já cancelada"
contam como sucesso. O banco grava `renewal_canceled_at` e não muda `status`:
o acesso segue até o fim do período já pago. A falha de uma cobrança autorizada
grava `renewal_failed_at`, avisa a conta **uma vez por período** e não bloqueia
o acesso; não há dunning além disso. A aprovação seguinte limpa o campo e
rearma o aviso para um período futuro. Um estorno cancela a preapproval em
best-effort, depois de o dinheiro já ter voltado.

### Estornos

Há dois caminhos, e os dois terminam em `RefundPayment`:

- **Estorno pedido pelo admin**: no pagamento de gateway, `RefundGatewayPayment`
  chama o provedor primeiro. Se o provedor recusar, nada muda no banco.
  `404`/`409` de "já estornado" contam como sucesso. Depois, cancela a
  preapproval em best-effort (o dinheiro já voltou; uma falha ali não desfaz o
  estorno) e chama `RefundPayment`. No pagamento manual, o admin chama
  `RefundPayment` direto, sem provedor.
- **Estorno/chargeback vindo do provedor**: o webhook já traz o dinheiro de
  volta; `RefundGatewayPayment` é chamado com `notifyGateway: false` e só
  sincroniza o estado local.

`RefundPayment` marca o pagamento como `refunded` (só se estava `paid`),
encerra a assinatura vigente ou futura com `refunded_at`, notifica a conta uma
vez quando o pagamento estava pago e **não escreve no `usage_ledger`**. Estorno
de dinheiro não é devolução de consumo: quem devolve consumo é `GrantQuota`,
outro ato. Em uma assinatura
já vencida, o acesso já acabou, mas `refunded_at` tira o crédito de tokens da
linha na cota acumulativa. `payments` não guarda uma data de estorno; o estado
`refunded` e o `updated_at` são o rastro.

Uma doação estornada não tem `payments`: o webhook marca a própria doação como
`refunded`. O estorno de um pagamento de checkout não mexe em `donations`, e
vice-versa.

### Webhooks

A rota fica fora do locale e do CSRF, e a única autenticação é a assinatura
`x-signature` sobre `id`/`request-id`/`ts` (HMAC-SHA256, comparação em tempo
constante). Não há janela de timestamp: replay é contido pela chave única do
evento e pela idempotência das ações, enquanto retries legítimos continuam
aceitos. Sem segredo configurado o endpoint responde 503; assinatura inválida
responde 401.

O controller responde rápido: grava o payload cru em `webhook_events` (unique
`(gateway, event_id)`; replay devolve 200 `duplicate`) e enfileira
`ProcessWebhookEvent`. O job tem 3 tentativas, backoff `[10, 60, 300]` e
timeout de 60 s. Com 3 tentativas, só os 10 s e os 60 s chegam a ser usados; o
300 s é o próximo valor da lista, não uma espera que acontece. Ele busca o
recurso citado na API do provedor (`payment`, `preapproval` ou
`authorized_payment`) e age sobre o resultado real — o corpo do webhook nunca
libera acesso.

As ações são idempotentes por `gateway_ref`: o mesmo pagamento chegando por
`payment` e por `subscription_authorized_payment` não duplica linha, período
nem recibo. Tópico desconhecido fica `ignored`. O admin lê os eventos em
`/admin/webhooks`, com payload e erro, sem botão de reprocessar; a poda diária
apaga eventos com mais de 30 dias.

### Doações

`CreateDonation` usa **Pix dinâmico** quando o gateway está disponível
(`POST /v1/payments`, QR do provedor, referência `don_<ULID>`, expiração do
provedor ou 60 min quando ele não informa); sem credenciais, cai no **Pix
estático** (`PixPayload` EMV + CRC16 montado localmente, QR em SVG, sem
confirmação automática). O dinâmico exige um e-mail de pagador; sem o e-mail da doação, usa
`DONATIONS_PAYER_EMAIL` ou `MAIL_FROM_ADDRESS` e, se nem isso existir, a doação
falha. O estático usa a chave, o titular e a cidade de `config/donations.php`.

A doação vive fora de `payments`: não entra em fatura e não consome cota.
`ConfirmDonation` confere valor e moeda com a linha gravada e só transita
`pending -> paid` uma vez, disparando o recibo quando há e-mail e
`DONATIONS_SEND_RECEIPTS` está ligado. A página de status, pública por ULID,
mostra valor, estado e datas, nunca os dados de quem doou. A expiração é
resolvida também na leitura (`effectiveStatus()`): um Pix dinâmico pendente
vencido já aparece vencido antes da varredura, e o Pix estático
(`gateway = manual`) nunca expira. `payments:expire` só mexe em `pending`
(checkouts e doações).

### Operação

- Requisição ao provedor de pagamento: 20 s (connect 5 s); job de webhook:
  60 s, 3 tentativas; checkout: 60 min; QR dinâmico de doação: o prazo do
  provedor ou 60 min quando ele não informa.
- `payments:expire` roda a cada 5 min e só marca checkout e doação `pending`
  vencidos; `webhooks:prune --days=30` roda diariamente às 03:30;
  `history:prune` roda diariamente às 03:45 com 90 dias; `base64-images:prune`
  roda de hora em hora.
- As rotinas são cadastradas em `task_schedules` e executadas de forma
  assíncrona por `ExecuteCommand` (ADR-035). O guia de cron e o RUNBOOK são a
  fonte para instalar, conferir e rodar `schedule:run`.
- Nenhuma rotina apaga `payments`, `subscriptions` ou `donations`. As podas
  existentes (webhooks, histórico, cache de IA e Base64) só tocam as próprias
  tabelas e arquivos.
- A expiração é preguiçosa: `effectiveStatus()` resolve o estado na leitura,
  então a tela não depende de a varredura já ter passado.

## Segurança HTTP

`SecurityHeaders` roda em toda resposta HTML: gera um nonce novo, entrega ao
`@vite` e ao script inline do tema e monta a CSP. Script de terceiro só entra
se estiver na lista (GA e Turnstile); estilo aceita `'unsafe-inline'` por causa
do bloco de cor do papel e do tema do shadcn-vue, e
`object-src`/`frame-ancestors` fecham o que não é usado. O modo vem de
`CSP_MODE`: `enforce` em produção, `report-only` para diagnosticar e `off` em
local.

HSTS só é emitido em requisição segura. Atrás do proxy, `TRUSTED_PROXIES`
decide se `X-Forwarded-Proto` vale — sem ele o Laravel veria `http` e o cookie
`secure`/canonical sairiam errados. `URL::forceScheme('https')` em produção
garante que canonical, OG, sitemap e robots nunca anunciem `http`.

Turnstile fica no cadastro e valida no servidor (`siteverify`, timeout de 5 s).
A chave secreta é o interruptor: sem `TURNSTILE_SECRET_KEY`, o widget não
renderiza e a regra passa — desativado é explícito, nunca um meio-termo. Com o
segredo configurado, a `TURNSTILE_SITE_KEY` precisa estar presente para o
widget aparecer, e qualquer falha (token, rede ou resposta inesperada) fecha o
cadastro. Os detalhes operacionais estão em [RUNBOOK.md](RUNBOOK.md) e na
ADR-052.

## O que nunca sai daqui

- **Schema e descrição não vão para log.** Logs carregam id, tokens,
  `error_code`, `reason` e classe da exceção; o corpo do provedor e o conteúdo
  de schema ou prompt não entram em log.
- **O corpo do provedor não vira exceção, banco ou resposta.** Só o contrato
  validado chega ao cliente.
- **O SQL não é executado** em nenhuma circunstância.
- **O cache guarda o schema comprimido por até 30 dias**; a solicitação apaga o
  `canonical_payload` ao terminar. A exceção é o histórico opt-in: com
  `history_visible`, `title`, `prompt` (descrição) e `schema` ficam em claro
  por até 90 dias, ou até a conta apagar.
- **A API da tela é de sessão** (cookie + CSRF, dono por `user_id`); a **API
  pública v1** usa Personal Access Token do Sanctum com a ability `ai:query` e
  escopa a leitura por `api_token_id`. O valor do token só aparece uma vez e o
  banco guarda apenas o hash.
- **Segredo do gateway não sai do `.env`.** `PaymentGatewayException` não
  carrega corpo de resposta nem token; a tela mostra mensagem genérica
  traduzida, e o log recebe o report sem credencial. O webhook secret também
  nunca aparece em log, resposta ou evento.
- **O payload do webhook é dado pessoal.** Fica em `webhook_events` por 30
  dias, visível só no admin, e a poda apaga apenas essa tabela. Nome e e-mail
  de doador só aparecem para o admin; a página de status, pública por ULID,
  mostra valor e estado, nunca os dados de quem doou.
- **O Base64 Image guarda o original e o `.txt` no servidor.** A retenção é de
  24 horas para Free/anônimo e 30 dias para pago; a linha de metadados
  permanece, e a exclusão manual apaga os arquivos. O conteúdo não entra em
  log nem no `usage_ledger`.
- **O Contato guarda PII no banco.** Nome, e-mail, mensagem, IP e user agent
  ficam em `contact_messages` e o e-mail enfileirado leva a mensagem para
  `CONTACT_EMAIL`; não há tela de admin para essas mensagens.
