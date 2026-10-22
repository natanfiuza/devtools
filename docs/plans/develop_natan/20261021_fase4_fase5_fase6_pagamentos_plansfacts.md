# F4 - auditoria, F5 - fechamento e F6 - pagamentos

Sessao de grill (skill `grill-with-docs`), pedido: "Verifique se a fase 4
(docs/acceptance/FASE-4.md) foi concluida e depois continue para a proxima fase
5". Branch `develop_natan`, em 2026-10-21. Rodadas de perguntas (Q1 a Q17),
todas respondidas pelo usuario, e plano aprovado antes da execucao.

**Por que F4 + F5 + F6 na mesma sessao**: a F4 (Auth) esta registrada como
concluida por backfill em 2026-10-20, mas nunca passou por auditoria de codigo;
a F5 (AI SQL Builder) esta aceita em 2026-10-20 com um unico criterio aberto
(#20, smoke real com DeepSeek); a F6 (pagamentos) e a proxima fase nao
construida. O usuario escolheu auditar a F4, fechar a F5 com o smoke real e
seguir para a F6 na mesma sessao, parando uma unica vez ao final para revisao.

**Decisoes das rodadas**: auditoria completa da F4 (gates + mapa
criterio->codigo/teste, corrigir so bloqueios); F5 com smoke real completo em
banco dedicado `storage/smoke.sqlite`; corrigir `HistoryController` e alinhar
docs sobre o historico adiantado; F6 com Checkout Pro (Preferences API) para
avulso, Preapproval sem plano para recorrencia, `/subscribe` com tres caminhos,
`checkout_sessions` separada de `payments`, uma linha de `subscriptions` por
periodo empilhada, autorizacao da preapproval nao libera acesso (so o primeiro
`subscription_authorized_payment` aprovado), "Pagar agora" com recorrencia ativa
no mesmo plano cancela a preapproval antes, cancelamento pelo usuario, falha de
renovacao com e-mail unico, estorno via API da MP, doacao Pix dinamico com
fallback estatico, admin com aba de doacoes e tela de webhooks, SEO de `/donate`.
Sem credenciais MP, o smoke real da MP fica pendente no `FASE-6.md`.

Plano de execucao aprovado (este arquivo), survey em
`docs/survey/20261021_fase4_fase5_fase6_pagamentos_surveyfacts.md` e copia no
padrao do CLAUDE.md em
`docs/claude-code/plans/develop_natan/20261021_develop_natan_fase4_fase5_fase6_pagamentos.md`.

---

# Verificar F4, fechar F5 e implementar F6 (pagamentos, doacao e webhooks)

## Context

O pedido original era verificar a FASE 4 (`docs/acceptance/FASE-4.md`) e seguir
para a FASE 5. Os fatos do repositorio mostram que:

- **FASE 4 = Auth**, registrada como concluida por backfill em 2026-10-20, com
  15 criterios e dois carry-overs (2FA sem teste dedicado; `users.appearance`
  somente leitura).
- **FASE 5 = AI SQL Builder**, implementada e aceita em 2026-10-20. O unico
  criterio aberto e o **#20 - smoke real com DeepSeek**; as variaveis `AI_*`
  agora estao preenchidas no `.env` local.
- A documentacao da F5 ficou defasada: o historico de consultas foi adiantado
  (commit `7f0fc2d`, `history_visible => true` e tela `dashboard/History`),
  embora SPEC e aceite ainda digam F7; e `HistoryController` le
  `result['summary']`, campo inexistente no contrato do LLM.
- A FASE 6 (gateway Mercado Pago, renovacao, doacao, webhooks) e praticamente
  greenfield: existem catalogo de planos, assinaturas, pagamentos manuais, cota,
  e-mails e telas, mas nao ha `PaymentGateway`, cliente MP, `/webhooks`,
  `checkout_sessions`, `donations` nem `webhook_events`.

Decisao do usuario nesta sessao: auditoria completa da F4, verificar e fechar a
F5 com o smoke real completo e, na mesma sessao, implementar a F6 com as
decisoes abaixo, parando ao final para revisao.

## Decisoes fechadas

### Verificacao
- F4: auditoria completa (gates + mapa criterio->codigo/teste), corrigir so o
  que for bloqueante, evidencias no `FASE-4.md` e no relatorio.
- F5: reauditar os 19 criterios de codigo, rodar todos os gates e executar o
  roteiro completo de smoke com DeepSeek real em **banco dedicado** (SQLite
  `storage/smoke.sqlite`), com worker, HTTP/sessao/CSRF reais, contas de teste,
  evidencias no aceite e limpeza; banco de dev intocado; chave nunca impressa.
- Corrigir `HistoryController` (resumo derivado de campos reais + filtro
  `history_visible`) e atualizar F5/SPEC sobre o historico adiantado (opt-in,
  retencao de 90 dias e apagar historico seguem na F7).
- `plan_required` nao e gap: ADR-011 diz que existe para fase futura e hoje e
  nulo.

### F6 - produto
- Checkout avulso via **Checkout Pro Preferences API** (`POST
  /checkout/preferences`, redirect `init_point`), Pix/cartao/boleto.
- Recorrencia via **Preapproval sem plano associado** (`POST /preapproval`,
  `auto_recurring`, mensal ou 12 meses), cartao apenas.
- `/subscribe` vira pagina de decisao unica: **Pagar agora**, **Renovacao
  automatica no cartao**, **Prefiro atendimento manual** (formulario de
  intencao preservado).
- Checkout pendente em nova tabela **`checkout_sessions`**; `payments` continua
  fiscal (`paid|refunded`) e faturas so mostram pagamentos confirmados.
- Renovacao e periodo: **nova linha de `subscriptions` por periodo**, empilhada
  no fim do periodo anterior; `activeSubscription()`/`Quota` passam a escolher a
  assinatura que cobre "agora"; `GrantPlan` de mesmo plano tambem cria linha nova
  por periodo (unificacao).
- Troca de plano com recorrencia ativa cancela a preapproval anterior na MP,
  encerra a assinatura local no fim do periodo pago e abre a nova agora.
- Recorrencia: a autorizacao da preapproval **nao** libera cota; a assinatura
  nasce so quando o primeiro `subscription_authorized_payment` vier aprovado
  (pode levar ~1h). A tela mostra "pagamento em processamento".
- "Pagar agora" com recorrencia ativa no mesmo plano **cancela a preapproval**
  antes de abrir o checkout avulso; o novo periodo e empilhado e evita cobranca
  dupla. Iniciar uma segunda recorrencia no mesmo plano e bloqueado.
- Cancelamento da recorrencia pelo usuario em `/dashboard/billing`: `PUT
  /preapproval/{id}` `status=canceled`, para a renovacao e mantem acesso ate
  `current_period_end`.
- Falha de renovacao: e-mail na **primeira tentativa rejeitada do periodo** +
  aviso na tela de billing ate pagamento aprovado ou fim do periodo; sem dunning
  alem disso.
- Estorno de gateway: acao do admin chama a API de reembolso da MP e depois
  `RefundPayment`; webhooks de `refunded/charged_back` tambem sincronizam pelo
  mesmo action; cancela preapproval quando houver.
- Admin: aba **Doacoes** dentro de Pagamentos (lista/detalhe/filtro) e tela
  **read-only de eventos de webhook**; sem admin de `output_targets`/IA na F6.
- Doacao: `/donate` publica com sugestoes R$ 5/10/25/50 + valor livre R$ 1-1.000
  em `config/donations.php`; **Pix dinamico** via Payments API com QR + copia e
  cola, `donations` no banco, webhook confirma; doador pode ser anonimo;
  **recibo por e-mail opcional**; sem credenciais MP, fallback para **Pix
  estatico** de `config/donations.php`.
- Webhooks: rota `/webhooks/mercadopago` fora do locale, CSRF-exempt,
  assinatura `x-signature` (HMAC-SHA256 do manifesto `id/request-id/ts`,
  comparacao em tempo constante), idempotencia com **payload completo** em
  `webhook_events` por **30 dias** + comando de poda; o recurso e sempre buscado
  na API da MP antes de agir. Topicos reais: `payment`,
  `subscription_preapproval`, `subscription_authorized_payment`.
- SEO: `/donate` indexavel (sitemap + hreflang); `/subscribe` `noindex`.
- Sem credenciais MP: checkout online escondido com fallback manual; doacao em
  Pix estatico. Smoke real da MP fica como criterio pendente no `FASE-6.md`,
  com roteiro para o dono.

## Arquivos criticos

- Aceites: `docs/acceptance/FASE-4.md`, `docs/acceptance/FASE-5.md`, novo
  `docs/acceptance/FASE-6.md`.
- AI/historico: `app/Http/Controllers/Account/HistoryController.php`,
  `app/Actions/Ai/StartAiQuery.php`, `app/Models/AiRequest.php`, tests de
  `AccountPanelTest.php`.
- Assinaturas/cota: `app/Models/User.php` (`activeSubscription`,
  `effectivePlan`), `app/Models/Subscription.php` (`isCurrent`, `scopeCurrent`),
  `app/Support/Quota.php`, `app/Actions/Admin/GrantPlan.php`,
  `RegisterPayment.php`, `RefundPayment.php`, `app/Models/Plan.php`
  (`periodEndFrom`).
- Checkout: novo `app/Contracts/Payments/PaymentGateway.php`,
  `app/Services/Payments/MercadoPagoGateway.php`, `app/Support/Payments/*`,
  `config/payments.php`, `config/donations.php`.
- Rotas/HTTP: `routes/web.php` (subscribe/checkout/donate), `routes/admin.php`
  (doacoes/eventos), nova `routes/webhooks.php` ou grupo em `web.php`;
  `bootstrap/app.php` (CSRF exempt).
- UI: `resources/js/pages/Subscribe.vue`, `PricingSection.vue`,
  `dashboard/Billing.vue`, novo `pages/Donate.vue`, `pages/admin/Payments.vue`
  (aba doacoes), novo `pages/admin/WebhookEvents.vue`.
- i18n: `lang/{en,pt_BR,pt_PT,fr,es}/` (`home.php`, `account.php`, `admin.php`,
  `mail.php`, novo `donations.php`), `config/locales.php`.
- Docs: `docs/SPEC.md`, `docs/API.md`, `docs/ARQUITETURA.md`, `docs/RUNBOOK.md`,
  `docs/glossario.md`, `docs/adr/ADR-045+`, `CHANGELOG.md`.

## Execucao

### 0. Preparacao e artefatos da sessao

- Branch `develop_natan`, data 2026-10-21. **Nenhum commit ou push** em nenhum
  momento; o dono revisa e aprova pelo `gitpr`.
- Criar apos sair do plan mode:
  - `docs/plans/develop_natan/20261021_fase4_fase5_fase6_pagamentos_plansfacts.md`
    (decisoes e fatos da sessao);
  - `docs/survey/20261021_fase4_fase5_fase6_pagamentos_surveyfacts.md`
    (levantamento e evidencias);
  - `docs/claude-code/plans/develop_natan/20261021_develop_natan_fase4_fase5_fase6_pagamentos.md`
    (este plano);
  - `docs/claude-code/reports/develop_natan/20261021_develop_natan_fase4_fase5_fase6_pagamentos.md`
    (relatorio final).
- Checkpoints internos (F4, F5 e cada bloco da F6) rodam os testes do bloco; a
  **parada para revisao e uma so, ao final**, como pedido.

### 1. Auditoria da F4 (Auth)

1. Rodar o baseline e registrar numeros exatos: `composer test`,
   `npm run test`, `npm run types:check`, `npm run lint:check`,
   `npm run format:check`, `npm run build`, `npm run build:ssr`.
2. Mapear os 15 criterios para codigo/teste e executar os testes direcionados:

| Criterios | Evidencia |
|---|---|
| 1-4 | `AuthenticationTest`, `RegistrationTest`, `LocalizedAuthPagesTest`, `EmailVerificationTest`, `VerificationNotificationTest`, `PasswordResetTest`, `PasswordConfirmationTest`, `SocialLoginTest`, `SetPasswordTest`, `TranslationsParityTest` |
| 5-6 | `config/fortify.php`, migration de 2FA, `AppServiceProvider` (`Passkeys::ignoreRoutes()`); 2FA segue sem teste dedicado (carry-over) e passkeys seguem desligadas |
| 7-8 | `SecurityTest`, `ProfileUpdateTest`, `ProfilePhotoTest`, `AppearanceTest`, `AccountPanelTest` |
| 9 e 13 | `AdminAccountActionsTest`, `AdminRoleTest`, `AdminPanelTest`, `AdminImpersonationTest` |
| 10-12 | `NewsletterTest`, `LegalPagesTest`, `LocaleRoutingTest`, `MailLocaleTest` |
| 14-15 | Gates acima + `git status`/historico; nenhum commit feito |

3. Conferir os carry-overs no codigo: `users.appearance` somente leitura, 2FA
   habilitado sem teste dedicado, passkeys desligadas, `verified` nas rotas
   autenticadas (o POST de `/subscribe` usa so `auth`+`active` de proposito).
4. Corrigir **somente** o que for bloqueante; qualquer correcao ganha teste de
   regressao. Fora isso, a F4 nao recebe implementacao nova.
5. Acrescentar ao `docs/acceptance/FASE-4.md` a secao "Auditoria 2026-10-21"
   com o mapa acima, os numeros dos gates e os carry-overs preservados.

### 2. Fechamento da F5 (AI SQL Builder)

#### 2.1 Reauditoria de codigo

- Rodar os gates e revalidar os 19 criterios de codigo pelos testes ja
  existentes (`AiApiTest`, `AiActionsTest`, `AiToolPropsTest`,
  `CanonicalizerTest`, `AiCacheRepositoryTest`, `PromptLoaderTest`,
  `ProcessAiQueryTest`, `DeepSeekProviderTest`, `ContractValidatorTest`,
  `SqlGuardTest`, `AiCommandsTest`, testes Vitest de AI, `TranslationsParityTest`,
  testes de home/SEO/sitemap).
- Confirmar que `AI_BASE_URL`, `AI_MODEL` e `AI_API_KEY` estao preenchidas
  **sem imprimir valores**.

#### 2.2 Smoke real com DeepSeek (fecha o criterio 20)

- Banco dedicado `storage/smoke.sqlite` (`pdo_sqlite` disponivel), com
  `DB_CONNECTION=sqlite`, `DB_DATABASE=<caminho absoluto>`,
  `QUEUE_CONNECTION=database`, `MAIL_MAILER=array` e `CACHE_STORE=file` passados
  por variavel de processo; **nunca** `migrate:fresh` no banco `devtools`.
- `migrate:fresh --seed --force` no banco de smoke; criar via tinker contas free
  verificada, paga (GrantPlan), outra conta e nao verificada, com senha de teste;
  nenhum e-mail real.
- Subir `php artisan serve` e `queue:work --tries=2 --timeout=90` com as mesmas
  variaveis. Login Fortify real com cookie jar e CSRF (`X-XSRF-TOKEN`), sem
  atalhos de sessao.
- Executar a matriz:

| Passo | Acao | Esperado |
|---|---|---|
| 1 | Login da conta Free verificada | sessao valida |
| 2 | POST schema DDL valido | 202, `queued`, cache miss, uso 1/5 |
| 3 | GET ate estado terminal | 200, `done`, SQL `SELECT`/`WITH`, tokens |
| 4 | Repetir o mesmo POST | 200, `cache_hit=true`, nenhuma linha nova no ledger |
| 5 | Schema/descricao acima do limite | 422 |
| 6 | Conta paga, 7 POSTs rapidos e distintos | 429 com `Retry-After` |
| 7 | Conta Free com 5 payloads distintos e 6o POST | 402 |
| 8 | Conta paga: reserva, ajuste no sucesso e estorno no erro | ledger liquido coerente |
| 9 | Worker com `AI_API_KEY=invalid-key-for-smoke` (variavel de processo, sem editar `.env`) para uma consulta paga | job falha, estorno integral, ledger liquido 0; worker bom religado depois |
| 10 | GET de consulta de outra conta | 404 |
| 11 | POST sem sessao | 401 |
| 12 | POST de conta nao verificada | 403 |
| 13 | POST com cookie e sem `X-XSRF-TOKEN` | 419 |
| 14 | GET da tool como visitante | 200 com CTA de login |
| 15 | Procurar `SMOKE_MARKER_20261021` no `laravel.log` e no log do worker | nenhuma ocorrencia |
| 16 | Conferir `public/og/ai-sql-builder.png`, metas e sitemap | arquivo e URLs corretos |
| 17 | Revisao visual | screenshots desktop/mobile com Edge headless (`msedge.exe --headless --screenshot`) da pagina de visitante + markup dos estados; revisao visual autenticada fica registrada como checagem final do dono |

- Antes do smoke, anotar o offset de `storage/logs/laravel.log`; escanear apenas
  o trecho novo. Nao apagar logs existentes e nao registrar a chave em lugar
  nenhum.
- Evidencia no `docs/acceptance/FASE-5.md`: banco usado, caixa de cada passo,
  ULIDs, deltas de ledger, resultado do estorno, resultado da busca nos logs e
  confirmacao do cleanup.
- Cleanup: parar `serve`/worker, remover `storage/smoke.sqlite`, cookie jar e
  arquivos temporarios; `php artisan config:clear`; banco de dev intocado.

#### 2.3 Correcao do historico

- `HistoryController`: filtrar `history_visible = true` e derivar o resumo de
  campos reais, nesta ordem: `clarification` (quando `needs_clarification`),
  primeira `assumption`, `tables_used` unidas por virgula, primeira linha nao
  vazia de `sql`; limitar a 120 caracteres com `Str::limit`. Remover a leitura de
  `result['summary']`.
- Atualizar `tests/Feature/AccountPanelTest.php`: fake sem `summary`, resumo
  derivado correto, linha com `history_visible=false` ausente e isolamento entre
  contas.
- Atualizar `docs/acceptance/FASE-5.md`, `docs/SPEC.md` (secoes 6 e 9) e
  `CHANGELOG.md`: o historico foi entregue antecipadamente em 2026-10-21
  (commit `7f0fc2d`), com `history_visible=true`; opt-in, retencao de 90 dias e
  apagar historico continuam na F7.

#### 2.4 Fechamento

- Rodar novamente todos os gates; anexar a "Revalidacao 2026-10-21" a FASE-5,
  marcar o criterio 20 como concluido e remover a pendencia da chave. As
  evidencias de 2026-10-20 permanecem como registro historico.

### 3. Implementacao da F6

#### 3.1 Dominio de periodos e schema

Migrations aditivas (sem drop/rename):

| Migration | Conteudo |
|---|---|
| `add_renewal_state_to_subscriptions_table` | `renewal_canceled_at`, `renewal_failed_at`; indice `(gateway, gateway_ref)` |
| `add_gateway_columns_to_payments_table` | `gateway_method` nullable; unique `(gateway, gateway_ref)` (hoje todos os `gateway_ref` sao nulos; conferir duplicatas antes) |
| `create_checkout_sessions_table` | `id`, `ulid` unique, `user_id`, `plan_id`, `mode`, `amount_cents`, `currency`, `status`, `gateway`, `gateway_ref`, `gateway_payment_id`, `payment_id`, `subscription_id`, `init_point`, `authorized_at`, `expires_at`, `approved_at`, timestamps; indices por usuario/status/expiracao |
| `create_webhook_events_table` | `id`, `gateway`, `event_id`, `topic`, `action`, `resource_id`, `request_id`, `status`, `attempts`, `payload` JSON, `error`, `processed_at`, timestamps; unique `(gateway, event_id)` |
| `create_donations_table` | `id`, `ulid` unique, `user_id` nullable, `name`, `email`, `locale`, `amount_cents`, `currency`, `status`, `gateway`, `gateway_ref`, `qr_code`, `qr_code_base64`, `ticket_url`, `expires_at`, `paid_at`, timestamps; unique `(gateway, gateway_ref)` |
| `seed_payment_maintenance_schedules` | `task_schedules`: `payments:expire` a cada 5 min (`*/5 * * * *`) e `webhooks:prune` diario as 03:30 com `--days=30` |

- Enums: `CheckoutMode` (`one_time|recurring`), `CheckoutStatus`
  (`pending|approved|failed|expired|canceled`), `DonationStatus`
  (`pending|paid|expired|refunded|failed`), `WebhookEventStatus`
  (`pending|processing|processed|failed|ignored`); `PaymentMethod` ganha
  `CreditCard='credit_card'` e `Boleto='boleto'`.
- Models: `CheckoutSession` (route key `ulid`), `Donation` (route key `ulid`),
  `WebhookEvent`; casts e relacoes.
- Correcoes de periodo:
  - `Plan::periodEndFrom()` usa `addMonthNoOverflow()`/`addYearNoOverflow()`
    (31/01 e ano bissexto).
  - `Subscription::isCurrent()`/`scopeCurrent()` exigem status ativo, inicio nulo
    ou `<= now`, fim nulo ou `> now`.
  - `User::activeSubscription()` escolhe a linha que **cobre agora**, ordenando
    por `current_period_start desc, id desc`; adicionar `nextSubscription()` para
    a proxima linha futura.
  - `OpenSubscriptionPeriod` centraliza: Free nao cria linha; mesmo plano
    empilha no maior fim futuro; plano diferente ou sem periodo comeca agora.
  - `CancelActiveSubscriptions` encerra todas as linhas ativas (inclusive
    futuras), cancelando antes cada preapproval distinta ainda nao marcada como
    cancelada (falha aborta e nada muda no local), com opcao de excecao.
  - `GrantPlan` reescrito sobre essas duas actions: Free/diferente cancelam tudo;
    mesmo plano cria linha nova (nunca estende a linha atual), gateway `manual`;
    se a linha atual tiver preapproval, cancela na MP antes de assumir (falha
    aborta sem mudanca local).
  - `RefundPayment` cancela a linha vinculada quando ela ainda vale (inclusive
    futura), sem apagar historico nem notificar duas vezes.
- Testes: `SubscriptionPeriodTest` (31/01, 29/02, virada de ano, periodo futuro
  nao vigente, cota por periodo, renovacao antecipada/atrasada, GrantPlan nos
  tres casos), atualizando `BillingTest`, `QuotaTest`, `AdminBillingTest` e
  `AccountPanelTest`.

#### 3.2 Configuracao e cliente Mercado Pago

- `config/payments.php`: `gateway`, `mercadopago.access_token`,
  `webhook_secret`, `base_url`, `timeout`, `connect_timeout`,
  `checkout.expires_minutes`, `back_url`, `webhook_url`,
  `statement_descriptor`.
- `config/donations.php`: sugeridos `[500,1000,2500,5000]`, minimo 100, maximo
  100000, moeda, `static_pix.key/holder/city`, `payer_email` de fallback,
  `send_receipts`.
- `.env.example`: `MERCADO_PAGO_ACCESS_TOKEN=`, `MERCADO_PAGO_WEBHOOK_SECRET=`,
  `MERCADO_PAGO_BASE_URL=https://api.mercadopago.com`,
  `PAYMENTS_CHECKOUT_EXPIRES_MINUTES=60`, `DONATIONS_PIX_KEY=`,
  `DONATIONS_PIX_HOLDER=`, `DONATIONS_PIX_CITY=`,
  `DONATIONS_SEND_RECEIPTS=true`. Nenhum valor real.
- Contrato `App\Contracts\Payments\PaymentGateway`: `isAvailable`,
  `createPreference`, `createPreapproval`, `createPixDonation`, `payment`,
  `preapproval`, `authorizedPayment`, `refund`, `cancelPreapproval`.
- `MercadoPagoGateway` com `Http::withToken()`, timeouts, `X-Idempotency-Key`
  estavel, sem SDK; DTOs em `app/Support/Payments/`; `PaymentGatewayException`
  sem vazar corpo/token; `PaymentMethodMapper`; `MercadoPagoConfiguration`;
  provider registrando o contrato.
- Endpoints exatos: `POST /checkout/preferences` (Checkout Pro),
  `POST /preapproval`, `POST /v1/payments` (Pix), `GET /v1/payments/{id}`,
  `GET /preapproval/{id}`, `GET /authorized_payments/{id}`,
  `POST /v1/payments/{id}/refunds`, `PUT /preapproval/{id}` com
  `status=canceled`.

#### 3.3 Checkout e `/subscribe`

- `StartCheckout` cria `checkout_sessions` pendente (valor e moeda capturados,
  expiracao de 60 min), chama o gateway e devolve o `init_point`; falha marca a
  sessao como `failed` e mostra erro seguro.
- Regras de inicio:

| Situacao | Acao |
|---|---|
| Recorrencia ativa no mesmo plano + "Pagar agora" | cancelar a preapproval (falha aborta) e abrir checkout avulso; periodo novo empilha na aprovacao |
| Recorrencia ativa no mesmo plano + nova recorrencia | bloquear com explicacao; nao abrir segunda preapproval |
| Recorrencia ativa em plano diferente | cancelar a preapproval antes; abrir o novo checkout; novo periodo comeca agora na aprovacao |
| Sem recorrencia, mesmo plano | abrir checkout; periodo novo empilha |
| Sem recorrencia, plano diferente ou nenhum | abrir checkout; periodo novo comeca agora |

- Rotas `POST /subscribe/checkout` (auth+active) e `GET
  /subscribe/checkout/{checkoutSession:ulid}/return` (auth+active, dono), com
  `Inertia::location($initPoint)` para o redirect externo. O retorno nao confirma
  nada: mostra "pagamento em processamento".
- `Subscribe.vue` com tres caminhos na mesma pagina: **Pagar agora**,
  **Renovacao automatica no cartao** e **Prefiro atendimento manual** (formulario
  atual preservado). Sem credenciais, os caminhos online ficam desabilitados com
  aviso e o manual continua; strings em `home.subscribe.*` e paridade nos cinco
  idiomas.
- Testes `CheckoutTest` com `Http::fake()`: preferencia, preapproval, corpo
  mensal/anual, `init_point`, bloqueios, cancelamento previo, ausencia de
  credenciais e retorno.

#### 3.4 Webhooks, aprovacao e idempotencia

- `routes/webhooks.php` incluido em `routes/web.php`; `POST
  /webhooks/mercadopago` (`webhooks.mercadopago`), fora de `auth` e do locale;
  `bootstrap/app.php` isenta `webhooks/mercadopago` do CSRF.
- Assinatura `x-signature` (`ts`, `v1`) + `x-request-id` + `data.id`; manifesto
  exato `id:{data.id em minusculas};request-id:{x-request-id};ts:{ts};`;
  HMAC-SHA256 com o segredo e `hash_equals`. Sem segredo configurado: 503;
  assinatura invalida/ausente: 401. Sem janela de timestamp (a idempotencia
  protege replay e nao quebra retries).
- Persistir o payload completo em `webhook_events`, responder 200/201 rapido e
  despachar `ProcessWebhookEvent` apos o commit. Duplicata `(gateway, event_id)`
  responde 200 sem novo job; evento com falha pode ser reprocessado.
- Topicos reais: `payment`, `subscription_preapproval`,
  `subscription_authorized_payment`. O corpo traz `type`/`action`; o recurso e
  **sempre** buscado na API antes de agir:

| Topico | Recurso | Acao |
|---|---|---|
| `payment` | `/v1/payments/{id}` | aprovado: checkout avulso ou doacao; `refunded`/`charged_back`: sincronizar estorno local; pendente/rejeitado: registrar sem liberar cota |
| `subscription_preapproval` | `/preapproval/{id}` | `authorized`: registra a autorizacao, **nao** libera cota; `canceled`: marca renovacao cancelada |
| `subscription_authorized_payment` | `/authorized_payments/{id}` | aprovado: primeira assinatura ou renovacao empilhada; `recycling`/pagamento rejeitado: falha de renovacao |
| desconhecido | - | `ignored` |

- `ApproveGatewayPayment`: localiza a sessao pelo `external_reference`
  (`chk_<ulid>`) ou pela preapproval; valida valor/moeda contra a sessao
  (primeira cobranca) ou contra o primeiro pagamento da preapproval (renovacoes);
  idempotencia por `(gateway, gateway_ref)`; abre o periodo via
  `OpenSubscriptionPeriod`; grava o pagamento por `RegisterPayment` estendido
  (`gateway`, `gatewayRef`, `subscription`); limpa `renewal_failed_at`; marca a
  sessao aprovada e notifica.
- A preapproval autorizada **nao** ativa a assinatura; a UI so sai de
  "processando" quando o primeiro pagamento aprovado chega (pode levar ~1h).
- Testes `MercadoPagoWebhookTest` + `GatewayPaymentTest`: assinatura
  invalida/ausente, duplicata, fetch do recurso, cada topico, aprovacao
  idempotente, valor/moeda divergentes, CSRF isento, job com retry/falha.

#### 3.5 Recorrencia, cancelamento, falha, estorno e expiracao

- `CancelRecurring`: chama a MP primeiro; 404/409 "ja cancelada" conta como
  sucesso; grava `renewal_canceled_at` nas linhas ativas da mesma preapproval sem
  mudar `status`; acesso segue ate o fim do periodo pago. Falha real aborta sem
  alterar o local.
- `HandleRenewalFailure`: primeira falha do periodo grava `renewal_failed_at` e
  envia `PaymentRenewalFailed` (uma vez); aprovacao posterior limpa o estado.
- `RefundGatewayPayment`: gateway primeiro (`POST /v1/payments/{id}/refunds`, ou
  404/409 ja estornado), depois `RefundPayment`; falha deixa tudo como estava.
  Depois tenta cancelar a preapproval e reporta falha sem desfazer o estorno.
  Webhooks `refunded`/`charged_back` usam o mesmo action sem chamar a API de
  novo. Admin `PaymentController@refund` passa a usar esse action quando
  `gateway != manual`.
- `payments:expire {--dry-run}`: marca sessoes e doacoes pendentes vencidas como
  `expired` (a leitura tambem expira de forma preguiçosa); `webhooks:prune
  {--days=30} {--dry-run}`: apaga eventos antigos, nunca
  pagamentos/assinaturas/doacoes.
- Billing: `BillingController` devolve `renewalCanceledAt`, `renewalFailedAt`,
  `renewable`, `lastCheckout` e a proxima linha; `Billing.vue` ganha "Cancelar
  renovacao" (confirmacao + erro de gateway), avisos de renovacao
  cancelada/falha e checkout pendente. Rota `POST
  /dashboard/billing/cancel-recurring`.
- Testes: cancelamento com acesso preservado, falha do gateway sem mudanca
  local, aviso unico, limpeza na aprovacao, ordem API->local no estorno, estorno
  duplicado, webhook de estorno, expiracao e prune.

#### 3.6 Doacoes

- `PixPayload` (EMV + CRC16-CCITT) para o fallback estatico; QR SVG com
  `bacon/bacon-qr-code` (ja instalado via Fortify; declarar `^3.0` explicitamente
  no `composer.json`), renderizado como data URI. Nada de nova dependencia npm.
- `DonationController` (`index`, `store`, `status`), `CreateDonation`,
  `ConfirmDonation`, `StoreDonationRequest`; rotas no grupo `{locale}`:
  `GET /donate` (`donate`), `POST /donate` (`donate.store`, `throttle:10,1`),
  `GET /donate/{donation:ulid}` (`donate.status`).
- Com gateway: cria `donations` pendente, chama `POST /v1/payments` com
  `payment_method_id=pix`, `external_reference=don_<ulid>`, `notification_url`,
  e-mail do doador ou fallback de config; grava
  `qr_code`/`qr_code_base64`/`ticket_url`; redireciona para a pagina de status.
  Sem gateway: Pix estatico de `config/donations.php` com QR e copia-e-cola,
  deixando explicito que nao ha confirmacao automatica.
- Doador anonimo permitido; e-mail opcional; recibo `DonationReceipt`
  (`ShouldQueue`) so na transicao `pending -> paid` e se `send_receipts`; usa o
  `locale` gravado na doacao. Webhook aprovado confirma uma vez apos validar
  valor/moeda; `refunded`/`charged_back` marca `refunded`;
  `rejected`/`expired`/`cancelled` marca `failed`/`expired`.
- Pagina de status publica por ULID, **sem** nome/e-mail do doador, `noindex`.
  Admin ve os dados completos na aba.
- SEO: `Sitemap::PAGES` recebe `donate`; `Seo::pageMeta` ganha titulo/descricao
  de `donations.*`; `/subscribe` ja e `noindex`; `donate.status` entra em
  `NOINDEX`; rodape usa `app.footer.donate`.
- Testes: pagina nos cinco idiomas, sitemap/hreflang, valores sugeridos/livre,
  Pix fake, fallback estatico, QR/copia-e-cola, status noindex, confirmacao
  idempotente, valor divergente, recibo uma vez, estorno e expiracao.

#### 3.7 Admin, i18n, docs e aceite

- Admin: `PaymentController@index` ganha `tab=payments|donations` (aba Doacoes
  com busca, filtro de status e detalhe); `Admin\WebhookEventController@index` +
  `admin/WebhookEvents.vue` read-only com filtros e payload completo; item
  "Webhook events" no `AdminLayout`; tipos em `types/admin.ts`; sem UI de IA
  nesta fase.
- i18n: novo namespace `donations` em `config/locales.php` e
  `lang/*/donations.php`; novas chaves em `home.subscribe.*`,
  `account.billing.*`, `admin.*` e `mail.*` nos cinco idiomas;
  `TranslationsParityTest` cedo e no fim.
- Docs: ADR-045 (gateway/Checkout Pro/Preapproval), ADR-046 (periodos
  empilhados e assinatura que cobre agora), ADR-047 (webhooks: assinatura,
  idempotencia, fetch-before-fulfill e retencao), ADR-048 (doacoes Pix
  dinamico/estatico), ADR-049 (ciclo da recorrencia: cancelamento, falha,
  estorno); atualizar `docs/SPEC.md`, `docs/API.md`, `docs/ARQUITETURA.md`,
  `docs/RUNBOOK.md`, `docs/glossario.md`, `CHANGELOG.md`.
- `docs/acceptance/FASE-6.md` com criterios, mapa das decisoes, evidencias dos
  testes e o **smoke real da MP como criterio pendente** (sem credenciais):
  roteiro para o dono com tunel HTTPS, sandbox, Pix/cartao/boleto, preapproval
  mensal/anual, cancelamento, estorno, replay de webhook e doacao; nunca imprimir
  tokens.
- Nenhum segredo em chat, git, logs, issues ou docs.

### 4. Verificacao final

| Gate | Criterio |
|---|---|
| `composer test` | Pest + Pint + PHPStan verdes; incluir testes novos de F4/F5/F6 |
| `npm run test` | Vitest verde; incluir Subscribe/Donate/Billing/admin |
| `npm run types:check` | `vue-tsc` limpo com Wayfinder regenerado |
| `npm run lint:check` / `format:check` | 0 erros; Prettier ok |
| `npm run build` / `build:ssr` | ambos ok; SSR sem `window` no setup |
| `migrate:fresh --seed --force` | em banco isolado de smoke, nunca no `devtools` |
| `route:list` | rotas de checkout, webhooks, donate e admin presentes |
| `payments:expire --dry-run` / `webhooks:prune --dry-run` | executam sem alterar dados |
| Smoke F5 | matriz completa, evidencia no FASE-5 e cleanup |
| Testes F6 | `Http::fake`, webhooks assinados, idempotencia, periodos, cota, estornos, doacoes, i18n |
| Smoke MP real | pendente no FASE-6, com roteiro do dono |

### 5. Riscos e casos de borda

- **31/01 e ano bissexto**: `NoOverflow`; testes em 2026, 2028 e 29/02.
- **Periodos empilhados**: linha futura nao pode virar "vigente" nem reiniciar
  cota; admin/billing precisam distinguir atual e proxima.
- **Webhook fora de ordem/replay**: unique de evento, fetch do recurso e acoes
  idempotentes por `gateway_ref`.
- **Retry da MP**: responder 2xx rapido; falha transitoria do fetch reenfileira;
  falha permanente vai para `failed` e aparece no admin.
- **Assinatura**: segredo ausente, `data.id` alfanumerico minusculo, request-id
  ausente; nao usar o corpo da notificacao para liberar cota.
- **Valor/moeda**: validar sempre contra a sessao/doacao/primeiro pagamento;
  divergencia nao libera nada.
- **Estorno/cancelamento**: gateway primeiro; falha nao pode mentir no estado
  local. Estorno de periodo futuro nao derruba o periodo atual.
- **Preco alterado/plano desativado**: o valor cobrado fica no pagamento; nao
  conceder periodo novo de plano desativado, registrar a anomalia.
- **Conta anonimizada/excluida**: sem e-mail, registro fiscal preservado;
  doacao pode ser anonima.
- **Pix estatico**: sem confirmacao automatica; texto explicito. Doacao dinamica
  aceita pagamento aprovado depois da expiracao local.
- **Migrations**: somente aditivas; unique de `payments.gateway_ref` conferido
  antes do deploy; schedule rows nao destrutivas.
- **Fila**: `DB_QUEUE_RETRY_AFTER=150` e worker reiniciado no deploy.
- **SEO**: `/donate` indexavel com hreflang; `/subscribe` e status de doacao
  `noindex`.
- **Historico**: filtro `history_visible` esconde linhas antigas; docs registram
  a entrega antecipada e o que resta para F7.

### 6. Criterio de conclusao

- F4 auditada e evidencias no `FASE-4.md`; nenhum bloqueio aberto.
- F5 com os 19 criterios revalidados e o criterio 20 (smoke real) concluido;
  historico corrigido e docs alinhados.
- F6 implementada com migrations, gateway, checkout, webhooks, recorrencia,
  estornos, doacoes, admin, i18n, SEO, docs e testes; `FASE-6.md` criado com o
  smoke real da MP pendente apenas por falta de credenciais.
- Todos os gates verdes; relatorio final em
  `docs/claude-code/reports/develop_natan/`.
- **Parar para revisao do dono.** Nenhum commit ou push.
