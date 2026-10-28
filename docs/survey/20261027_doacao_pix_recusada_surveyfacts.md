# Survey — Doação Pix recusada no Mercado Pago (2026-10-27)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "Fiz toda a configuração para usar o mercado pago, na parte de doação esta exibindo o qrcode mas na hora de confirmar o pix a transação e recusada. O que esta errado? O mercado pago tem um MCP para testar e fazer esta integração voce pode dar uma olhada: https://mcp.mercadopago.com/mcp"
- **Tela / Modulo:** Doacao Pix (`/{locale}/donate` -> `DonationController::store`, `CreateDonation::dynamic`, `MercadoPagoGateway::createPixDonation`, pagina de status `DonationStatus.vue`) e confirmacao (`MercadoPagoWebhookController`, `HandleWebhookEvent`, `ConfirmDonation`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 3 rodadas, 10 decisoes de fronteira, inspecao de gateway, actions, controller, schema, frontend, i18n, rotas e testes.
- **Arquivos-chave lidos:** `app/Services/Payments/MercadoPagoGateway.php`, `app/Actions/Donations/CreateDonation.php`, `app/Actions/Donations/ConfirmDonation.php`, `app/Actions/Payments/HandleWebhookEvent.php`, `app/Http/Controllers/DonationController.php`, `app/Http/Controllers/Admin/PaymentController.php`, `app/Models/Donation.php`, `app/Enums/DonationStatus.php`, `app/Support/Payments/GatewayPayment.php`, `app/Support/Payments/GatewayPixCharge.php`, `database/migrations/2026_10_21_000005_create_donations_table.php`, `resources/js/pages/DonationStatus.vue`, `resources/js/pages/admin/Payments.vue`, `resources/js/types/donations.ts`, `resources/js/composables/useRoutes.ts`, `routes/web.php`, `bootstrap/app.php`, `config/donations.php`, `config/payments.php`, `.env.example`, `lang/pt_BR/donations.php`, `docs/guides/mercado-pago.md`, `docs/ARQUITETURA.md`, `docs/adr/ADR-047/048`, `tests/Feature/{Donation,MercadoPagoGateway,AdminDonations,TranslationsParity}Test.php`.

## 2. Decisoes das rodadas

### Rodada 1 — Onde a recusa acontece e como obter evidencia
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Onde a recusa aparece | **No app/banco do pagador** ao confirmar o Pix; a criacao do QR funciona. |
| Q2 | Ambiente e credencial | **Producao com token `APP_USR` de producao** (nao sandbox). |
| Q3 | Quem paga | **Outra pessoa** (nao o dono da conta recebedora) — descarta autopagamento. |
| Q4 | Uso do MCP do Mercado Pago | **Conectar via OAuth** em `https://mcp.mercadopago.com/mcp` para documentacao/qualidade/simulacao. |

### Rodada 2 — Como tornar a causa visivel
| # | Decisao | Resolucao |
|---|---|---|
| Q5 | Evidencia da causa | **Consultar a API do MP pelo pagamento** (`GET /v1/payments/{gateway_ref}`) com o token de producao e reportar `status`, `status_detail`, `payment_method_id`, `collector_id`, `live_mode`. |
| Q6 | Atualizacao da pagina de status | **Auto-refresh (~15 s, so banco local) + botao "Ja paguei"** que consulta o provedor sob demanda, com rate limit. |
| Q7 | O que persistir | **`gateway_status` + `gateway_status_detail` apenas em `donations`**, gravados na criacao, no webhook e na consulta; migration retrocompativel. |
| Q8 | Mensagem ao doador | **Mensagem especifica por motivo**, mapeada do `status_detail` em 5 idiomas, com fallback generico; codigo cru nunca aparece ao doador (so no admin). |

### Rodada 3 — Operacao e ritmo
| # | Decisao | Resolucao |
|---|---|---|
| Q9 | Comando de diagnostico | **Sim, `php artisan donations:check {ulid}`** — resolve o `gateway_ref`, consulta o MP, grava status/detalhe e imprime diagnostico com e-mail mascarado. |
| Q10 | Ritmo do trabalho | **Parar apos o diagnostico** para revisao antes de mexer no codigo. |

### Defaults assumidos (nao contestados)
- A sincronizacao sob demanda nao altera a regra de confirmacao: `ConfirmDonation` continua sendo a unica autoridade de `paid`, com `lockForUpdate()` e conferencia de valor/moeda.
- O webhook continua sendo o **unico caminho automatico**; nao ha conciliador em cron.
- `donations:check` grava estado (nao e somente leitura); a consulta crua via `curl` continua sendo o diagnostico read-only.
- Nenhuma credencial, corpo cru ou e-mail completo vai para log, prompt de MCP ou transcript.

## 3. Relatorio de fatos levantados

### Fato 1 — A recusa nao e da criacao do Pix
`POST /v1/payments` responde com QR (`point_of_interaction.transaction_data.qr_code`/`qr_code_base64`) e a doacao nasce `pending`. A recusa informada pelo usuario acontece na etapa seguinte (app do banco ou analise do MP), portanto o motivo esta no `status`/`status_detail` do pagamento, nao no payload de criacao.

### Fato 2 — O app descarta o motivo da recusa
`MercadoPagoGateway::payment()` ja mapeia `status_detail` para `GatewayPayment::$statusDetail`. Em `HandleWebhookEvent::donationPayment()`, o status `rejected`/`cancelled` apenas marca `failed` quando a doacao esta `pending`; o `statusDetail` nunca e persistido nem exposto. A tabela `donations` nao possui coluna para isso.

### Fato 3 — O ramo de estorno ignora a doacao
`HandleWebhookEvent::payment()` verifica `isRefunded()` antes de resolver a doacao; `syncRefund()` cobre a doacao estornada sem gravar status/detalhe. Qualquer refatoracao precisa resolver a doacao antes do ramo de estorno.

### Fato 4 — Doacao vencida nao confirma, apesar da ADR-048
`ConfirmDonation::confirm()` so aceita `DonationStatus::Pending`. A ADR-048 §9 (e o criterio 18 da F6) dizem que um pagamento aprovado depois do vencimento local ainda confirma. Hoje `pending|expired -> paid` nao funciona; o botao "Ja paguei" numa doacao vencida e o webhook tardio nao conseguiram confirmar.

### Fato 5 — A pagina de status e passiva
`DonationStatus.vue` nao tem polling nem botao de reconsulta; o QR aparece so com `status === pending`. O `DonationController::status()` so envia os dados da doacao, sem `canCheck`, motivo traduzido ou intervalo de polling.

### Fato 6 — Status `failed` usa mensagem unica
`lang/pt_BR/donations.php` tem `status.failed = "Doacao nao concluida"` e `status.failed_hint` generico. Nao existe mapa de `status_detail` para mensagens; o doador nao consegue distinguir recusa do banco, risco do MP, QR expirado etc.

### Fato 7 — A arquitetura declara nao haver conciliacao
`docs/ARQUITETURA.md` afirma que o webhook e a unica confirmacao automatica e que nao ha conciliacao de pagamentos. A consulta sob demanda e uma conciliacao pontual, iniciada pelo doador/comando: exige ADR nova (ADR-059) e emenda em ADR-047/048, sem criar job em background.

### Fato 8 — Infra do MCP
`https://mcp.mercadopago.com/mcp` usa transporte HTTP e exige OAuth. O servidor foi registrado em escopo de usuario (`claude mcp add --transport http --scope user mercadopago ...`) e aparece como `Needs authentication`; a autenticacao e feita no `/mcp` pelo usuario. As ferramentas uteis ao diagnostico sao `search_documentation`, `quality_checklist`, `quality_evaluation`, `notifications_history`, `notifications_history_diagnostics` e `simulate_webhook`. Nao ha ferramenta para ler um pagamento: a evidencia real vem de `GET /v1/payments/{id}` com o token de producao no VPS.

### Fato 9 — Limitacoes conhecidas do sandbox (nao se aplicam aqui)
Com token de teste, Pix da API legada permanece `pending`; a Orders API usa `payer.first_name: "APRO"` para aprovar. Como o usuario usa token `APP_USR` de producao, `live_mode` no pagamento confirma se a cobranca e real.

### Fato 10 — Padroes reaproveitaveis
- `Inertia::flash('toast', [...])` e o padrao de feedback do projeto (usado em `DonationController::refuse()`).
- `@inertiajs/vue3` v3 exporta `usePoll(interval, requestOptions?, { autoStart, background })`.
- Rotas com `{locale}` usam `URL::defaults` em `ResolveLocale`; o `{locale}` ocupa o primeiro parametro, por isso a busca e manual por ULID (sem binding implicito).
- `TranslationsParityTest` exige as mesmas folhas nos 5 idiomas.
- `throttle:10,1` e indexado pelo caminho; mudar o prefixo de idioma burlaria a chave — por isso o novo endpoint usa limiter nomeado com chave por IP.

## 4. Diagnostico de producao (2026-10-27)

Consulta a `GET /v1/payments/{gateway_ref}` das 5 ultimas doacoes `failed`:

| Doacao | Payment | status | status_detail | live_mode | Valor | Criado | Expira | Intervalo |
|---|---|---|---|---|---|---|---|---|
| 01M5ZS833NV9B9TDRX5ACRAE2R | 185379929543 | cancelled | expired | true | R$ 1,00 | 18:56:12 | 19:01:11 | 4m59s |
| 01M5ZPMMKM5C1SH4AJ2RWABVZH | 185371857881 | cancelled | expired | true | R$ 1,00 | 18:10:37 | 18:15:36 | 4m59s |
| 01M5ZNZYWNA2WCNNKKKSDCXJ7E | 185370442781 | cancelled | expired | true | R$ 1,00 | 17:59:20 | 18:04:19 | 4m59s |
| 01M5SYJJB1GD5RGW23JV9G5JRJ | 185009769205 | cancelled | expired | true | R$ 5,00 | 12:33:51 | 12:38:50 | 4m59s |
| 01M5R4GSQM7HGMWQXR7CMGP40P | 185973383152 | cancelled | expired | true | R$ 1,00 | 19:39:16 | 19:44:15 | 4m59s |

**Causa raiz:** o QR nasce com validade efetiva de **5 minutos**, e nao dos 60 configurados como padrao. O pagador confirma depois do prazo e o Mercado Pago cancela a cobranca como `expired`. Nao ha recusa de banco, risco ou regulacao; `live_mode: true` confirma producao e `external_reference`/`collector_id` estao corretos. Os pagamentos `cancelled` nao sao recuperaveis — e preciso gerar uma nova doacao.

**Por que 5 minutos:** `config/donations.php` declara `(int) env('DONATIONS_EXPIRES_MINUTES', 60)`, mas `env()` devolve `''` quando a chave existe vazia no `.env`; `(int) '' = 0` e `CreateDonation` aplica `max(5, 0) = 5`. E o mesmo padrao do bug anterior de `DONATIONS_PAYER_EMAIL=` vazio. Confirmar no VPS com `grep '^DONATIONS_EXPIRES_MINUTES' .env` e `config('donations.expires_minutes')`.

**Correcao imediata (operacional):** definir `DONATIONS_EXPIRES_MINUTES=60` no `.env` do VPS, rodar `php artisan optimize:clear && php artisan optimize && php artisan queue:restart` e gerar uma nova doacao; o novo pagamento deve expirar ~60 min depois de `date_created`.

**Endurecimento de codigo (a incluir no plano):** trocar para `(int) (env('DONATIONS_EXPIRES_MINUTES') ?: 60)`, aplicar um piso explicito (30 min) e validar que o valor efetivo nao cai silenciosamente para o minimo. O `date_of_expiration` devolvido pelo MP continua sendo a fonte da verdade gravada em `expires_at`.

**Observacao:** `payer.email` nao vem no `GET` desses Pix nao pagos; nao e a causa (a cobranca foi criada normalmente).
