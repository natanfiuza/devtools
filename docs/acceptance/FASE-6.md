# Criterios de aceite - FASE 6 (pagamentos, doacao e webhooks)

**Aceite de codigo em 2026-10-21.** A F6 liga o dinheiro ao produto: Checkout
Pro avulso, Preapproval (renovacao automatica no cartao), webhooks assinados da
Mercado Pago, periodo empilhado por pagamento, estorno, cancelamento, falha de
renovacao e doacao por Pix (dinamico e estatico). O **smoke real com a Mercado
Pago** continua pendente por falta de credenciais e fecha como criterio 27, com
roteiro para o dono.

Fonte: `docs/SPEC.md` secao 7, `docs/ARQUITETURA.md` (secao "Pagamentos e
webhooks"), `docs/API.md` (secao "Webhook da Mercado Pago"), `docs/RUNBOOK.md` e
as ADRs 045 a 049, com emendas nas ADRs 027 e 044. A emenda de 2026-10-27
(ADR-059) revisa os criterios 18 e 21 e acrescenta os criterios 28 a 32.

## Escopo entregue

Contrato `PaymentGateway` e cliente HTTP da Mercado Pago (Checkout Pro,
Preapproval sem plano, Pix, consulta, estorno e cancelamento) sem SDK e sem SDK
de log de credencial; `checkout_sessions` separada de `payments` (fiscal);
`/subscribe` com tres caminhos (pagar agora, renovacao automatica e atendimento
manual); regras de inicio que cancelam a preapproval anterior; periodo novo por
pagamento em `subscriptions`, empilhado no fim do periodo pago; assinatura
vigente escolhida pela data; webhook `POST /webhooks/mercadopago` com assinatura
HMAC, idempotencia e busca do recurso antes de agir; aprovacao de checkout,
renovacao e doacao; cancelamento da renovacao com acesso ate o fim do periodo;
falha de renovacao avisada uma vez; estorno pelo gateway antes do banco;
`payments:expire` e `webhooks:prune`; doacao publica nos cinco idiomas com Pix
dinamico e fallback estatico; admin com aba Doacoes e eventos de webhook
somente leitura; sitemap e metas atualizados.

## Mapa das decisoes

| Decisao | Onde virou criterio |
| --- | --- |
| ADR-045: Checkout Pro para avulso, Preapproval sem plano para recorrencia, tres caminhos em `/subscribe` | 1, 2, 3, 4 |
| ADR-046: uma linha de `subscriptions` por periodo, empilhada no fim do periodo pago; assinatura vigente pela data; cota so no periodo corrente | 4, 8, 9, 15 |
| ADR-047: webhook assinado, payload antes da acao, busca do recurso, idempotencia e retencao de 30 dias | 10, 11, 12, 13, 14 |
| ADR-048: doacao publica com Pix dinamico e fallback estatico, status por ULID, recibo opcional | 17, 18, 19, 20, 21, 22 |
| ADR-049: cancelamento mantem acesso; falha avisada uma vez; estorno no gateway antes do banco | 5, 6, 7 |
| ADR-059: status/detalhe do provedor em `donations`, um unico `SyncDonationPayment`, consulta sob demanda, motivo traduzido e `donations:check` | 18, 21, 28 a 32 |
| Decisao de produto: autorizacao da Preapproval **nao** libera acesso; primeira cobranca autorizada libera | 2 |
| Decisao de produto: "Pagar agora" no mesmo plano cancela a renovacao; segunda recorrencia no mesmo plano e bloqueada | 4 |
| Decisao de produto: sem credenciais, checkout online escondido e Pix estatico como fallback | 3, 20, 25 |
| Decisao de produto: `/donate` indexavel; `/subscribe` e status de doacao `noindex` | 22 |

## Criterios

| # | Criterio | Como verificar |
| --- | --- | --- |
| 1 | "Pagar agora" cria uma preferencia do Checkout Pro (`POST /checkout/preferences`) com valor, moeda, `external_reference = chk_<ULID>` e retorno ao site, e manda o navegador ao `init_point`; a compra fica em `checkout_sessions`, nao em `payments`, e so vira receita fiscal na aprovacao | `CheckoutTest` ("cria a preferencia...", "mostra... estado efetivo"), `MercadoPagoGatewayTest` ("cria a preferencia...") |
| 2 | A renovacao automatica cria uma Preapproval **sem plano associado** (`POST /preapproval`), mensal ou a cada 12 meses, com o valor do plano em `auto_recurring`; a autorizacao do provedor **nao** libera cota - a assinatura nasce com a primeira cobranca autorizada aprovada, e a tela mostra "em processamento" | `CheckoutTest` ("cria a preapproval mensal ou anual sem liberar periodo"), `MercadoPagoGatewayTest` ("cria a preapproval mensal e anual..."), `GatewayPaymentTest` ("nao libera cota na autorizacao...", "aprova a primeira cobranca autorizada..."), `BillingRecurringTest` ("mostra o checkout pendente e o processamento...") |
| 3 | `/subscribe` e uma pagina de decisao unica: **Pagar agora**, **Renovacao automatica no cartao** e **Prefiro atendimento manual** (formulario de intencao preservado); sem credenciais do provedor os dois caminhos online ficam indisponiveis com aviso e o manual continua | `CheckoutTest` ("recusa o checkout online sem credenciais...", "lista os planos com renovacao ativa e esconde o checkout sem credenciais"), `Subscribe.vue`, `home.subscribe.*` nos cinco idiomas |
| 4 | Regras de inicio: recorrencia ativa no mesmo plano + "Pagar agora" cancela a preapproval antes de abrir o checkout avulso; uma segunda recorrencia no mesmo plano e bloqueada; recorrencia em plano diferente e cancelada antes de abrir o novo; sem recorrencia, mesmo plano empilha e plano diferente (ou Free) comeca agora | `CheckoutTest` ("cancela a preapproval antes de abrir pagamento avulso no mesmo plano", "cancela a preapproval do plano antigo ao trocar de plano", "bloqueia uma segunda renovacao no mesmo plano"), `SubscriptionPeriodTest` |
| 5 | O cliente cancela a renovacao em `/dashboard/billing`: o gateway e chamado primeiro (`PUT /preapproval/{id}` `status=canceled`), `404`/`409` contam como sucesso, `renewal_canceled_at` e gravado sem mudar o `status`, e o acesso segue ate `current_period_end`; recusa real do gateway nao altera nada no banco | `BillingRecurringTest` ("cancela no gateway e mantem o acesso...", "nao muda nada quando o gateway recusa...", "trata preapproval ja ausente..."), `CancelRecurring` |
| 6 | Falha de renovacao: a primeira cobranca recusada do periodo grava `renewal_failed_at` e envia `PaymentRenewalFailed` **uma vez**; uma aprovacao posterior limpa o estado; nao ha dunning alem do aviso na tela | `GatewayPaymentTest` ("avisa a falha de renovacao uma vez por periodo", "limpa a falha quando a renovacao e aprovada depois"), `HandleRenewalFailure` |
| 7 | Estorno: a acao do admin chama a API de reembolso (`POST /v1/payments/{id}/refunds`) **antes** de `RefundPayment`; `404`/`409` de "ja estornado" contam como sucesso; falha do gateway deixa tudo como estava; webhooks `refunded`/`charged_back` sincronizam pelo mesmo action **sem** chamar a API de novo; periodo futuro estornado nao derruba o corrente; pagamento manual nao fala com o gateway | `AdminPaymentRefundTest` (4 testes), `GatewayPaymentTest` ("sincroniza estorno vindo do webhook...", "nao libera nada para pagamento pendente..."), `SubscriptionPeriodTest` ("estorna pagamento de periodo futuro sem derrubar o periodo corrente") |
| 8 | Cada pagamento aprovado abre **uma linha nova** em `subscriptions`: mesmo plano empilha no maior `current_period_end` futuro, plano diferente ou sem periodo comeca agora, Free nao cria linha; a linha vigente e a que cobre o instante e uma linha futura nao abre franquia nem consome a atual | `SubscriptionPeriodTest` (13 testes), `OpenSubscriptionPeriod`, `Subscription::scopeCurrent()`, `User::activeSubscription()`/`nextSubscription()` |
| 9 | Datas de periodo usam `addMonthNoOverflow`/`addYearNoOverflow`: 31/01 termina em 28/02 (ou 29/02 em ano bissexto) e 29/02 + 1 ano termina em 28/02; a cota le o periodo corrente e ignora consumo anterior a ele | `SubscriptionPeriodTest` ("termina o mes sem overflow...", "termina o ano sem overflow...", "conta a cota so dentro do periodo corrente..."), `QuotaTest` |
| 10 | O webhook fica em `POST /webhooks/mercadopago`, fora do prefixo de idioma, de `auth` e do CSRF; autentica por `x-signature` (`ts=<ts>,v1=<hex>`) sobre o manifesto exato `id:<data.id minusculo>;request-id:<x-request-id>;ts:<ts>;` com HMAC-SHA256 e `hash_equals`; sem segredo responde 503; assinatura ausente/invalida ou `data.id` ausente responde 401; nao ha janela de timestamp | `MercadoPagoWebhookTest` (6 testes), `MercadoPagoSignature`, `bootstrap/app.php` (`validateCsrfTokens` exceto `webhooks/mercadopago`), `routes/webhooks.php` |
| 11 | O evento e gravado **antes** de agir: payload cru completo em `webhook_events`, unique `(gateway, event_id)`, 201 para evento novo e 200 para replay sem novo job; topico desconhecido fica `ignored`; o payload e retido 30 dias e a poda apaga so essa tabela | `MercadoPagoWebhookTest` ("guarda o payload completo...", "responde replay do mesmo evento..."), `PaymentMaintenanceTest` ("poda apenas eventos de webhook antigos", "nunca trata zero dias como apagar tudo"), `PruneWebhookEvents` |
| 12 | O corpo do webhook **nunca** e fonte de verdade: `ProcessWebhookEvent` sempre busca o recurso na API (`/v1/payments/{id}`, `/preapproval/{id}`, `/authorized_payments/{id}`) e age pelo resultado real; os efeitos por topico sao os da tabela do `docs/API.md` | `GatewayPaymentTest` (todos), `MercadoPagoWebhookTest`, `HandleWebhookEvent` |
| 13 | Aprovacao idempotente: unique `(gateway, gateway_ref)` impede pagamento duplicado; o mesmo pagamento chegando por `payment` e por `subscription_authorized_payment` nao duplica linha, periodo nem recibo; valor e moeda sao conferidos com a sessao (checkout) ou com o primeiro pagamento (renovacao/doacao) e divergencia nao libera nada; pagamento sem sessao correspondente deixa o evento `failed` com erro | `GatewayPaymentTest` ("nao duplica pagamento nem periodo...", "recusa valor ou moeda divergentes...", "ignora pagamento aprovado sem checkout correspondente", "marca o evento como falho..."), `DonationTest` |
| 14 | O job do webhook tolera falha transitoria: 3 tentativas, backoff 10/60/300 s, timeout 60 s; falha persistente deixa o evento `failed` com a mensagem (sem corpo de resposta nem segredo), visivel no admin; evento `processed` nao e reprocessado | `ProcessWebhookEvent`, `GatewayPaymentTest` ("nao reprocessa evento ja concluido"), `AdminWebhookEventsTest` |
| 15 | A sessao de checkout tem estado efetivo (`pending`, `approved`, `failed`, `expired`, `canceled`), expira em 60 min (`PAYMENTS_CHECKOUT_EXPIRES_MINUTES`), e o retorno ao site nao confirma nada (so mostra o estado); a pagina `/subscribe` mostra o checkout pendente; `GET .../return` so abre para o dono | `CheckoutTest` ("marca a sessao como falha...", "devolve a sessao... somente para o dono", "mostra... estado efetivo"), `BillingRecurringTest` ("nao mostra checkout pendente fora do prazo"), `CheckoutSession::effectiveStatus()` |
| 16 | `payments:expire` marca como vencidos checkouts e doacoes pendentes com prazo estourado, aceita `--dry-run` sem alterar nada e nunca toca pagamento pago/estornado; o Pix estatico (`gateway = manual`) nunca vence; a expiracao tambem e resolvida na leitura | `PaymentMaintenanceTest` ("vence checkouts e doacoes pendentes...", "conta no dry-run..."), `DonationTest` ("vence o pix dinamico na leitura, mas nao o estatico"), `ExpirePayments` |
| 17 | `/donate` e publica nos cinco idiomas, aceita doador anonimo (nome/e-mail opcionais), lista os valores sugeridos de `config/donations.php` dentro do intervalo e aceita valor livre entre o minimo e o maximo; a criacao e limitada por minuto | `DonationTest` ("atende a pagina de doacao nos cinco idiomas", "lista so os valores sugeridos dentro do intervalo", "preenche nome e e-mail da conta logada", "recusa valor fora do intervalo", "limita a criacao de doacoes por minuto") |
| 18 | Com credenciais, a doacao usa **Pix dinamico** (`POST /v1/payments`, `payment_method_id=pix`, `external_reference = don_<ULID>`, QR/base64/comprovante, expiracao de 60 min, `gateway_status`/`gateway_status_detail` gravados, e-mail do doador ou fallback); falha do provedor marca a doacao `failed`; o webhook confirma uma unica vez `pending\|expired -> paid` conferindo valor/moeda; `refunded`/`charged_back` marca `refunded` (gravando o detalhe), e recusa/venda expirada marca `failed`/`expired`; pagamento aprovado depois do prazo local ainda confirma | `DonationTest` (Pix dinamico, fallback de e-mail, falha, webhook, estorno, recusa, "confirma pelo webhook uma doacao vencida...", "nao confirma nem manda recibo duas vezes..."), `CreateDonation`, `SyncDonationPayment`, `ConfirmDonation` |
| 19 | O recibo de doacao sai **so** na transicao `pending -> paid`, quando ha e-mail de doador e `DONATIONS_SEND_RECEIPTS` esta ligado, no idioma gravado na doacao; doacao anonima e valida sem recibo | `DonationTest` ("confirma a doacao uma vez e envia o recibo uma vez", "nao manda recibo para doacao anonima nem com o envio desligado"), `DonationReceipt` |
| 20 | Sem credenciais, a doacao cai no **Pix estatico** montado localmente (`PixPayload` EMV + CRC16, QR em SVG embutido), com aviso explicito de que nao ha confirmacao automatica; sem gateway e sem Pix estatico a pagina fica informativa e o POST e recusado | `DonationTest` ("gera o pix estatico...", "deixa a pagina em modo informativo...", "recusa a doacao quando nao ha gateway nem pix estatico"), `PixPayloadTest` (7 testes) |
| 21 | A pagina de status da doacao e publica pelo ULID, nao mostra nome/e-mail do doador, esconde o QR depois de paga ou vencida e e `noindex`; enquanto `pending` ela se atualiza sozinha a cada `DONATIONS_STATUS_POLL_MS` (so banco local) e oferece **Ja paguei** quando ha o que consultar; o admin ve os dados completos na aba Doacoes | `DonationTest` ("mostra o qr estatico na pagina de status sem dados do doador", "esconde o qr de uma doacao ja paga", "oferece a consulta sob demanda...", "publica o ritmo do auto-refresh..."), `donation-status.test.ts`, `AdminDonationsTest` ("mostra os dados que a pagina publica nao mostra"), `Seo` (`noindex`) |
| 22 | SEO: `/donate` e indexavel, entra no sitemap e no hreflang como as paginas do site; `/subscribe` e o status de doacao sao `noindex`; o rodape leva a doacao | Testes de sitemap/SEO da fase, `Sitemap::PAGES`, `Seo::NOINDEX`, `app.footer.donate` |
| 23 | Admin: Pagamentos ganha a aba **Doacoes** (lista, busca por nome/e-mail/ULID, filtro de status com expiracao preguicosa e detalhe); `/admin/webhooks` lista os eventos com payload completo e filtros, **somente leitura** (escrita responde 405); as duas telas recusam quem nao e admin; a aba Doacoes e a unica carregada sob demanda | `AdminDonationsTest` (5 testes), `AdminWebhookEventsTest` (4 testes) |
| 24 | Toda string visivel vem do i18n nos cinco idiomas: novo namespace `donations`, chaves em `home.subscribe`, `account.billing`, `admin` e `mail`; a paridade de chaves e o veto ao separador `\|` continuam valendo | `TranslationsParityTest`, `DonationTest` (cinco idiomas), `lang/{en,pt_BR,pt_PT,fr,es}/` |
| 25 | Configuracao e segredo: preco, moeda, prazos, limites de doacao e textos vem de `config/payments.php`, `config/donations.php` e `.env` (nada fixo no codigo); `PaymentGatewayException` nao carrega corpo nem token; segredo do webhook e access token nunca aparecem em log, resposta, evento do admin ou documento; sem credenciais o checkout online some e o Pix estatico assume | `MercadoPagoGatewayTest` ("recusa chamadas sem credenciais"), `config/payments.php`, `config/donations.php`, `.env.example`, `PaymentGatewayException` |
| 26 | Gates da fase e nenhum commit/push do assistente | `composer test`, `npm run test`, `types:check`, `lint:check`, `format:check`, `build`, `build:ssr`, `route:list`, os dois `--dry-run`; `git status` e historico |
| 27 | Smoke real com a Mercado Pago | **Pendente**: sem credenciais no ambiente. Roteiro do dono abaixo; nenhum teste automatizado substitui o provedor real |

## Emenda (ADR-059, 2026-10-27)

Uma recusa de Pix em producao mostrou que a F6 criava o QR mas nao sabia
explicar o que o provedor respondia. A emenda fecha essa lacuna sem mudar o
desenho do webhook: ele continua sendo o **unico caminho automatico** de
confirmacao.

| # | Criterio | Como verificar |
| --- | --- | --- |
| 28 | `donations` guarda `gateway_status` e `gateway_status_detail` (nullable) na criacao do Pix dinamico, no webhook e na consulta sob demanda; Pix estatico fica com ambos `null`; o webhook resolve a doacao **antes** do ramo de estorno, entao o estorno dela tambem grava o detalhe | `DonationTest` ("cria o pix dinamico...", webhooks de pendente/recusa/estorno), `AdminDonationsTest` (`gatewayStatus`/`gatewayStatusDetail`), migration `add_gateway_status_to_donations_table` |
| 29 | Webhook, botao **Ja paguei** e `donations:check` passam pelo mesmo `SyncDonationPayment`: grava status/detalhe, trata `refunded`/`charged_back`, confirma `approved` e so marca `failed` a partir de `pending`; doacao resolvida nao regride | `DonationTest`, `DonationCheckTest`, `DonationDiagnosticsCommandTest`, `app/Actions/Donations/SyncDonationPayment.php` |
| 30 | `POST /{locale}/donate/{ulid}/check` existe com o ULID como capacidade, recusa doacao resolvida/estatica/sem referencia/sem credencial, confere `external_reference = don_<ulid>` e o limite e nomeado por IP (`DONATIONS_CHECK_PER_MINUTE`, default 5), com resposta em toast em vez de 429 | `DonationCheckTest` (12 testes, inclui rate limit e formato invalido), `PaymentsServiceProvider`, `DonationController@check` |
| 31 | O motivo da recusa chega ao doador traduzido (`DonationFailureReason` -> `donations.reasons.*`) nos cinco idiomas; o codigo cru de `status_detail` so aparece no admin | `DonationFailureReasonTest`, `DonationTest` ("mostra o motivo da recusa sem expor o codigo do provedor"), `TranslationsParityTest` |
| 32 | `php artisan donations:check {ulid}` imprime snapshot local, resposta do provedor (`status`, `status_detail`, `live_mode`, meio, valor, datas, `collector_id` e e-mail **mascarado**) e sincroniza; **escreve estado**; nao imprime token nem e-mail completo. O prazo do QR virou `max(30, (int) (env('DONATIONS_EXPIRES_MINUTES') ?: 60))`, entao chave vazia vale 60 e nunca menos de 30 minutos | `DonationDiagnosticsCommandTest` (10 testes), `CheckDonation`, `config/donations.php` |

## Evidencias dos gates (2026-10-21)

```text
composer test        -> 560 testes, 560 passaram, 12.902 assercoes (Pint ok, PHPStan 7 com 0 erros)
npm run test         -> 55 arquivos, 749 testes, 749 passaram
npm run types:check  -> vue-tsc limpo (Wayfinder regenerado com --with-form)
npm run lint:check   -> 0 erros (9.943 avisos de estilo pre-existentes; a fase nao adiciona erro)
npm run format:check -> "All matched files use Prettier code style!"
npm run build        -> ok (cliente)
npm run build:ssr    -> ok (cliente + bootstrap/ssr/ssr.js)
migrate:fresh --seed -> SQLite dedicado storage/smoke_f6.sqlite: 32 migrations + seed, ok
route:list           -> subscribe (4 rotas), donate (3), webhooks (2), dashboard/billing (2), admin/payments (3)
payments:expire --dry-run -> 0 checkout(s) e 0 doacao(oes) venceriam
webhooks:prune --dry-run  -> 0 evento(s) com mais de 30 dia(s) seriam removidos
```

O `migrate:fresh --seed` e os dois `--dry-run` rodaram contra o SQLite
dedicado, nunca contra o banco `devtools` (que continua sem as migrations da
F6). A suite completa roda em SQLite `:memory:` (`phpunit.xml`).

Arquivos de teste novos ou ampliados na fase, com os casos que sustentam o mapa
acima:

| Arquivo | Casos |
| --- | --- |
| `tests/Feature/CheckoutTest.php` | 11 |
| `tests/Feature/MercadoPagoWebhookTest.php` | 6 |
| `tests/Feature/GatewayPaymentTest.php` | 15 |
| `tests/Feature/BillingRecurringTest.php` | 8 |
| `tests/Feature/SubscriptionPeriodTest.php` | 13 |
| `tests/Feature/DonationTest.php` | 24 |
| `tests/Feature/PaymentMaintenanceTest.php` | 6 |
| `tests/Feature/MercadoPagoGatewayTest.php` | 9 |
| `tests/Feature/AdminDonationsTest.php` | 5 |
| `tests/Feature/AdminWebhookEventsTest.php` | 4 |
| `tests/Feature/AdminPaymentRefundTest.php` | 4 |
| `tests/Feature/PaymentNotificationsTest.php` | 15 (area de pagamento) |
| `tests/Unit/PixPayloadTest.php` | 7 |

`BillingTest`, `QuotaTest`, `AdminBillingTest` e `AccountPanelTest` foram
atualizados para o periodo empilhado e continuam verdes na suite completa.

### Ajustes feitos durante a verificacao

Nenhum bloqueio de produto foi encontrado; os ajustes foram de contrato de
teste e de tipagem estatica:

- `AdminPanelTest` passou a esperar **seis** formas de pagamento: a F6
  acrescentou `credit_card` e `boleto` ao enum, e o formulario manual lista o
  enum inteiro.
- `admin-panel.test.ts` passou a esperar as **nove** secoes do painel, com
  `Eventos de webhook` entre Pagamentos e Auditoria.
- `DonationController@store` monta o array da action chave a chave, porque
  `validated()` devolve `mixed` e o PHPStan 7 cobra o formato declarado por
  `CreateDonation`.
- `PaymentController` declarou os genericos de `LengthAwarePaginator` nas duas
  abas.
- `resources/js/__tests__/account-panel.test.ts` foi normalizado pelo Prettier.

## Smoke real da Mercado Pago (criterio 27 - pendente)

Nao ha credenciais da Mercado Pago no ambiente desta sessao, entao o smoke real
nao foi executado. Tudo o que depende do provedor esta coberto por `Http::fake()`
e pelo contrato do gateway; o roteiro abaixo valida o que nenhum teste toca:
token, sandbox, webhook de verdade e o tempo da primeira cobranca recorrente.

**Nunca imprimir, colar em issue ou gravar em documento** o access token, o
webhook secret ou o payload de um webhook com dado pessoal. O `.env` local e o
unico lugar das credenciais.

### Preparacao

1. Conta de vendedor no sandbox da Mercado Pago; criar **duas contas de teste**
   de comprador (a mesma conta do vendedor nao pode pagar).
2. Expor o site por HTTPS publico (tunel tipo `cloudflared`/`ngrok`).
3. No `.env` local: `MERCADO_PAGO_ACCESS_TOKEN`, `MERCADO_PAGO_WEBHOOK_SECRET`
   (painel da MP, Webhooks) e `PAYMENTS_WEBHOOK_URL=https://<tunel>/webhooks/mercadopago`
   (ou `APP_URL` publico). `PAYMENTS_GATEWAY=mercadopago`.
4. `php artisan config:clear && php artisan optimize`; subir
   `php artisan queue:work` e garantir o cron do agendador
   (`php artisan schedule:run` a cada minuto, RUNBOOK secao 5).
5. No painel da MP, cadastrar a URL de notificacao e os topicos `payment`,
   `subscription_preapproval` e `subscription_authorized_payment`.

### Matriz

| Passo | Acao | Esperado |
| --- | --- | --- |
| 1 | Conta Free: `/subscribe`, escolher plano e **Pagar agora** | redireciona ao Checkout Pro; `checkout_sessions` pendente |
| 2 | Pagar com **Pix** | webhook 201; pagamento aprovado; periodo aberto; fatura e cota do plano na conta |
| 3 | Repetir com **cartao** (outra conta de teste) | mesmo resultado; `payment.method = credit_card` |
| 4 | **Renovacao automatica** mensal no cartao | preapproval autorizada; tela em processamento, **sem** cota liberada |
| 5 | Esperar a primeira `subscription_authorized_payment` (pode levar ~1h) | webhook abre o periodo; conta sai de processamento |
| 6 | **Renovacao automatica** anual | preapproval com `frequency=12`; mesmo comportamento |
| 7 | Em `/dashboard/billing`, **Cancelar renovacao** | preapproval `canceled` na MP; `renewal_canceled_at` gravado; acesso ate o fim do periodo |
| 8 | Cartao recusado na renovacao | `renewal_failed_at`, e-mail `PaymentRenewalFailed` **uma vez**; acesso atual intacto |
| 9 | Admin: **Estornar** um pagamento de gateway | estorno na MP primeiro; `payments.status = refunded`; assinatura encerrada quando cabia |
| 10 | Reenviar o mesmo webhook (replay) | 200; nenhum pagamento, periodo, recibo ou evento duplicado |
| 11 | Doacao em `/donate` com Pix dinamico | QR do provedor; status muda para pago pelo webhook; recibo uma vez se houver e-mail |
| 12 | Doacao sem e-mail (anonima) | sem recibo; status publico nao mostra doador |
| 13 | Remover o token temporariamente | checkout online some, Pix estatico assume a doacao, `/subscribe` oferece atendimento manual |
| 14 | Conferir `/admin/webhooks` e `storage/logs/laravel.log` | eventos com payload e erro; **nenhum** token ou segredo no log |
| 15 | `php artisan webhooks:prune --dry-run` | conta os eventos com mais de 30 dias, sem apagar |

O passo 5 pode exigir uma cobranca de teste antecipada pela MP; o site nao
fabrica a primeira cobranca. O passo 9 deve ser feito com um pagamento real de
sandbox, nunca com o banco de producao.

## Fora de escopo e riscos

- **Smoke real pendente**: sem credenciais, o criterio 27 fica aberto; nenhuma
  afirmacao deste aceite diz que a MP real foi exercitada.
- **Agendador**: `payments:expire` e `webhooks:prune` vivem em
  `task_schedules` e so rodam com o cron do `schedule:run`; sem ele, a
  expiracao continua correta na leitura, mas a poda de 30 dias nao acontece
  (RUNBOOK secao 5).
- **Pix estatico** nao confirma sozinho: e fallback de contingencia e a tela
  diz isso; a confirmacao manual continua sendo do admin.
- **Preapproval sem plano**: o valor e o periodo sao nossos; uma mudanca de
  preco nao altera uma preapproval ja autorizada - o admin cancela e o cliente
  autoriza de novo.
- **Historico de consultas e hardening** continuam na F7; a F6 nao mexe neles.
- **Nenhum commit ou push**: o usuario revisa e aprova pelo `gitpr`.
