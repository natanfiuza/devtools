# Verificar F4, fechar F5 e implementar F6 (pagamentos, doação e webhooks)

## Context

O pedido original era verificar a FASE 4 (`docs/acceptance/FASE-4.md`) e seguir para a FASE 5. Os fatos do repositório mostram que:

- **FASE 4 = Auth**, registrada como concluída por backfill em 2026-10-20, com 15 critérios e dois carry-overs (2FA sem teste dedicado; `users.appearance` somente leitura).
- **FASE 5 = AI SQL Builder**, implementada e aceita em 2026-10-20. O único critério aberto é o **#20 — smoke real com DeepSeek**; as variáveis `AI_*` agora estão preenchidas no `.env` local.
- A documentação da F5 ficou defasada: o histórico de consultas foi adiantado (commit `7f0fc2d`, `history_visible => true` e tela `dashboard/History`), embora SPEC e aceite ainda digam F7; e `HistoryController` lê `result['summary']`, campo inexistente no contrato do LLM.
- A FASE 6 (gateway Mercado Pago, renovação, doação, webhooks) é praticamente greenfield: existem catálogo de planos, assinaturas, pagamentos manuais, cota, e-mails e telas, mas não há `PaymentGateway`, cliente MP, `/webhooks`, `checkout_sessions`, `donations` nem `webhook_events`.

Decisão do usuário nesta sessão: auditoria completa da F4, verificar e fechar a F5 com o smoke real completo e, na mesma sessão, implementar a F6 com as decisões abaixo, parando ao final para revisão.

## Decisões fechadas

### Verificação
- F4: auditoria completa (gates + mapa critério→código/teste), corrigir só o que for bloqueante, evidências no `FASE-4.md` e no relatório.
- F5: reauditar os 19 critérios de código, rodar todos os gates e executar o roteiro completo de smoke com DeepSeek real em **banco dedicado** (SQLite `storage/smoke.sqlite`), com worker, HTTP/sessão/CSRF reais, contas de teste, evidências no aceite e limpeza; banco de dev intocado; chave nunca impressa.
- Corrigir `HistoryController` (resumo derivado de campos reais + filtro `history_visible`) e atualizar F5/SPEC sobre o histórico adiantado (opt-in, retenção de 90 dias e apagar histórico seguem na F7).
- `plan_required` não é gap: ADR-011 diz que existe para fase futura e hoje é nulo.

### F6 — produto
- Checkout avulso via **Checkout Pro Preferences API** (`POST /checkout/preferences`, redirect `init_point`), Pix/cartão/boleto.
- Recorrência via **Preapproval sem plano associado** (`POST /preapproval`, `auto_recurring`, mensal ou 12 meses), cartão apenas.
- `/subscribe` vira página de decisão única: **Pagar agora**, **Renovação automática no cartão**, **Prefiro atendimento manual** (formulário de intenção preservado).
- Checkout pendente em nova tabela **`checkout_sessions`**; `payments` continua fiscal (`paid|refunded`) e faturas só mostram pagamentos confirmados.
- Renovação e período: **nova linha de `subscriptions` por período**, empilhada no fim do período anterior; `activeSubscription()`/`Quota` passam a escolher a assinatura que cobre “agora”; `GrantPlan` de mesmo plano também cria linha nova por período (unificação).
- Troca de plano com recorrência ativa cancela a preapproval anterior na MP, encerra a assinatura local no fim do período pago e abre a nova agora.
- Recorrência: a autorização da preapproval **não** libera cota; a assinatura nasce só quando o primeiro `subscription_authorized_payment` vier aprovado (pode levar ~1h). A tela mostra “pagamento em processamento”.
- “Pagar agora” com recorrência ativa no mesmo plano **cancela a preapproval** antes de abrir o checkout avulso; o novo período é empilhado e evita cobrança dupla. Iniciar uma segunda recorrência no mesmo plano é bloqueado.
- Cancelamento da recorrência pelo usuário em `/dashboard/billing`: `PUT /preapproval/{id}` `status=canceled`, para a renovação e mantém acesso até `current_period_end`.
- Falha de renovação: e-mail na **primeira tentativa rejeitada do período** + aviso na tela de billing até pagamento aprovado ou fim do período; sem dunning além disso.
- Estorno de gateway: ação do admin chama a API de reembolso da MP e depois `RefundPayment`; webhooks de `refunded/charged_back` também sincronizam pelo mesmo action; cancela preapproval quando houver.
- Admin: aba **Doações** dentro de Pagamentos (lista/detalhe/filtro) e tela **read-only de eventos de webhook**; sem admin de `output_targets`/IA na F6.
- Doação: `/donate` pública com sugestões R$ 5/10/25/50 + valor livre R$ 1–1.000 em `config/donations.php`; **Pix dinâmico** via Payments API com QR + copia e cola, `donations` no banco, webhook confirma; doador pode ser anônimo; **recibo por e-mail opcional**; sem credenciais MP, fallback para **Pix estático** de `config/donations.php`.
- Webhooks: rota `/webhooks/mercadopago` fora do locale, CSRF-exempt, assinatura `x-signature` (HMAC-SHA256 do manifesto `id/request-id/ts`, comparação em tempo constante), idempotência com **payload completo** em `webhook_events` por **30 dias** + comando de poda; o recurso é sempre buscado na API da MP antes de agir. Tópicos reais: `payment`, `subscription_preapproval`, `subscription_authorized_payment`.
- SEO: `/donate` indexável (sitemap + hreflang); `/subscribe` `noindex`.
- Sem credenciais MP: checkout online escondido com fallback manual; doação em Pix estático. Smoke real da MP fica como critério pendente no `FASE-6.md`, com roteiro para o dono.

## Arquivos críticos

- Aceites: `docs/acceptance/FASE-4.md`, `docs/acceptance/FASE-5.md`, novo `docs/acceptance/FASE-6.md`.
- AI/histórico: `app/Http/Controllers/Account/HistoryController.php`, `app/Actions/Ai/StartAiQuery.php`, `app/Models/AiRequest.php`, tests de `AccountPanelTest.php`.
- Assinaturas/cota: `app/Models/User.php` (`activeSubscription`, `effectivePlan`), `app/Models/Subscription.php` (`isCurrent`, `scopeCurrent`), `app/Support/Quota.php`, `app/Actions/Admin/GrantPlan.php`, `RegisterPayment.php`, `RefundPayment.php`, `app/Models/Plan.php` (`periodEndFrom`).
- Checkout: novo `app/Contracts/Payments/PaymentGateway.php`, `app/Services/Payments/MercadoPagoGateway.php`, `app/Support/Payments/*`, `config/payments.php`, `config/donations.php`.
- Rotas/HTTP: `routes/web.php` (subscribe/checkout/donate), `routes/admin.php` (doações/eventos), nova `routes/webhooks.php` ou grupo em `web.php`; `bootstrap/app.php` (CSRF exempt).
- UI: `resources/js/pages/Subscribe.vue`, `PricingSection.vue`, `dashboard/Billing.vue`, novo `pages/Donate.vue`, `pages/admin/Payments.vue` (aba doações), novo `pages/admin/WebhookEvents.vue`.
- i18n: `lang/{en,pt_BR,pt_PT,fr,es}/` (`home.php`, `account.php`, `admin.php`, `mail.php`, novo `donations.php`), `config/locales.php`.
- Docs: `docs/SPEC.md`, `docs/API.md`, `docs/ARQUITETURA.md`, `docs/RUNBOOK.md`, `docs/glossario.md`, `docs/adr/ADR-045+`, `CHANGELOG.md`.

## Execução

### 0. Preparação e artefatos da sessão

- Branch `develop_natan`, data 2026-10-21. **Nenhum commit ou push** em nenhum momento; o dono revisa e aprova pelo `gitpr`.
- Criar após sair do plan mode:
  - `docs/plans/develop_natan/20261021_fase4_fase5_fase6_pagamentos_plansfacts.md` (decisões e fatos da sessão);
  - `docs/survey/20261021_fase4_fase5_fase6_pagamentos_surveyfacts.md` (levantamento e evidências);
  - `docs/claude-code/plans/develop_natan/20261021_develop_natan_fase4_fase5_fase6_pagamentos.md` (este plano);
  - `docs/claude-code/reports/develop_natan/20261021_develop_natan_fase4_fase5_fase6_pagamentos.md` (relatório final).
- Checkpoints internos (F4, F5 e cada bloco da F6) rodam os testes do bloco; a **parada para revisão é uma só, ao final**, como pedido.

### 1. Auditoria da F4 (Auth)

1. Rodar o baseline e registrar números exatos: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`, `npm run format:check`, `npm run build`, `npm run build:ssr`.
2. Mapear os 15 critérios para código/teste e executar os testes direcionados:

| Critérios | Evidência |
|---|---|
| 1-4 | `AuthenticationTest`, `RegistrationTest`, `LocalizedAuthPagesTest`, `EmailVerificationTest`, `VerificationNotificationTest`, `PasswordResetTest`, `PasswordConfirmationTest`, `SocialLoginTest`, `SetPasswordTest`, `TranslationsParityTest` |
| 5-6 | `config/fortify.php`, migration de 2FA, `AppServiceProvider` (`Passkeys::ignoreRoutes()`); 2FA segue sem teste dedicado (carry-over) e passkeys seguem desligadas |
| 7-8 | `SecurityTest`, `ProfileUpdateTest`, `ProfilePhotoTest`, `AppearanceTest`, `AccountPanelTest` |
| 9 e 13 | `AdminAccountActionsTest`, `AdminRoleTest`, `AdminPanelTest`, `AdminImpersonationTest` |
| 10-12 | `NewsletterTest`, `LegalPagesTest`, `LocaleRoutingTest`, `MailLocaleTest` |
| 14-15 | Gates acima + `git status`/histórico; nenhum commit feito |

3. Conferir os carry-overs no código: `users.appearance` somente leitura, 2FA habilitado sem teste dedicado, passkeys desligadas, `verified` nas rotas autenticadas (o POST de `/subscribe` usa só `auth`+`active` de propósito).
4. Corrigir **somente** o que for bloqueante; qualquer correção ganha teste de regressão. Fora isso, a F4 não recebe implementação nova.
5. Acrescentar ao `docs/acceptance/FASE-4.md` a seção “Auditoria 2026-10-21” com o mapa acima, os números dos gates e os carry-overs preservados.

### 2. Fechamento da F5 (AI SQL Builder)

#### 2.1 Reauditoria de código

- Rodar os gates e revalidar os 19 critérios de código pelos testes já existentes (`AiApiTest`, `AiActionsTest`, `AiToolPropsTest`, `CanonicalizerTest`, `AiCacheRepositoryTest`, `PromptLoaderTest`, `ProcessAiQueryTest`, `DeepSeekProviderTest`, `ContractValidatorTest`, `SqlGuardTest`, `AiCommandsTest`, testes Vitest de AI, `TranslationsParityTest`, testes de home/SEO/sitemap).
- Confirmar que `AI_BASE_URL`, `AI_MODEL` e `AI_API_KEY` estão preenchidas **sem imprimir valores**.

#### 2.2 Smoke real com DeepSeek (fecha o critério 20)

- Banco dedicado `storage/smoke.sqlite` (`pdo_sqlite` disponível), com `DB_CONNECTION=sqlite`, `DB_DATABASE=<caminho absoluto>`, `QUEUE_CONNECTION=database`, `MAIL_MAILER=array` e `CACHE_STORE=file` passados por variável de processo; **nunca** `migrate:fresh` no banco `devtools`.
- `migrate:fresh --seed --force` no banco de smoke; criar via tinker contas free verificada, paga (GrantPlan), outra conta e não verificada, com senha de teste; nenhum e-mail real.
- Subir `php artisan serve` e `queue:work --tries=2 --timeout=90` com as mesmas variáveis. Login Fortify real com cookie jar e CSRF (`X-XSRF-TOKEN`), sem atalhos de sessão.
- Executar a matriz:

| Passo | Ação | Esperado |
|---|---|---|
| 1 | Login da conta Free verificada | sessão válida |
| 2 | POST schema DDL válido | 202, `queued`, cache miss, uso 1/5 |
| 3 | GET até estado terminal | 200, `done`, SQL `SELECT`/`WITH`, tokens |
| 4 | Repetir o mesmo POST | 200, `cache_hit=true`, nenhuma linha nova no ledger |
| 5 | Schema/descrição acima do limite | 422 |
| 6 | Conta paga, 7 POSTs rápidos e distintos | 429 com `Retry-After` |
| 7 | Conta Free com 5 payloads distintos e 6º POST | 402 |
| 8 | Conta paga: reserva, ajuste no sucesso e estorno no erro | ledger líquido coerente |
| 9 | Worker com `AI_API_KEY=invalid-key-for-smoke` (variável de processo, sem editar `.env`) para uma consulta paga | job falha, estorno integral, ledger líquido 0; worker bom religado depois |
| 10 | GET de consulta de outra conta | 404 |
| 11 | POST sem sessão | 401 |
| 12 | POST de conta não verificada | 403 |
| 13 | POST com cookie e sem `X-XSRF-TOKEN` | 419 |
| 14 | GET da tool como visitante | 200 com CTA de login |
| 15 | Procurar `SMOKE_MARKER_20261021` no `laravel.log` e no log do worker | nenhuma ocorrência |
| 16 | Conferir `public/og/ai-sql-builder.png`, metas e sitemap | arquivo e URLs corretos |
| 17 | Revisão visual | screenshots desktop/mobile com Edge headless (`msedge.exe --headless --screenshot`) da página de visitante + markup dos estados; revisão visual autenticada fica registrada como checagem final do dono |

- Antes do smoke, anotar o offset de `storage/logs/laravel.log`; escanear apenas o trecho novo. Não apagar logs existentes e não registrar a chave em lugar nenhum.
- Evidência no `docs/acceptance/FASE-5.md`: banco usado, caixa de cada passo, ULIDs, deltas de ledger, resultado do estorno, resultado da busca nos logs e confirmação do cleanup.
- Cleanup: parar `serve`/worker, remover `storage/smoke.sqlite`, cookie jar e arquivos temporários; `php artisan config:clear`; banco de dev intocado.

#### 2.3 Correção do histórico

- `HistoryController`: filtrar `history_visible = true` e derivar o resumo de campos reais, nesta ordem: `clarification` (quando `needs_clarification`), primeira `assumption`, `tables_used` unidas por vírgula, primeira linha não vazia de `sql`; limitar a 120 caracteres com `Str::limit`. Remover a leitura de `result['summary']`.
- Atualizar `tests/Feature/AccountPanelTest.php`: fake sem `summary`, resumo derivado correto, linha com `history_visible=false` ausente e isolamento entre contas.
- Atualizar `docs/acceptance/FASE-5.md`, `docs/SPEC.md` (seções 6 e 9) e `CHANGELOG.md`: o histórico foi entregue antecipadamente em 2026-10-21 (commit `7f0fc2d`), com `history_visible=true`; opt-in, retenção de 90 dias e apagar histórico continuam na F7.

#### 2.4 Fechamento

- Rodar novamente todos os gates; anexar a “Revalidação 2026-10-21” à FASE-5, marcar o critério 20 como concluído e remover a pendência da chave. As evidências de 2026-10-20 permanecem como registro histórico.

### 3. Implementação da F6

#### 3.1 Domínio de períodos e schema

Migrations aditivas (sem drop/rename):

| Migration | Conteúdo |
|---|---|
| `add_renewal_state_to_subscriptions_table` | `renewal_canceled_at`, `renewal_failed_at`; índice `(gateway, gateway_ref)` |
| `add_gateway_columns_to_payments_table` | `gateway_method` nullable; unique `(gateway, gateway_ref)` (hoje todos os `gateway_ref` são nulos; conferir duplicatas antes) |
| `create_checkout_sessions_table` | `id`, `ulid` unique, `user_id`, `plan_id`, `mode`, `amount_cents`, `currency`, `status`, `gateway`, `gateway_ref`, `gateway_payment_id`, `payment_id`, `subscription_id`, `init_point`, `authorized_at`, `expires_at`, `approved_at`, timestamps; índices por usuário/status/expiração |
| `create_webhook_events_table` | `id`, `gateway`, `event_id`, `topic`, `action`, `resource_id`, `request_id`, `status`, `attempts`, `payload` JSON, `error`, `processed_at`, timestamps; unique `(gateway, event_id)` |
| `create_donations_table` | `id`, `ulid` unique, `user_id` nullable, `name`, `email`, `locale`, `amount_cents`, `currency`, `status`, `gateway`, `gateway_ref`, `qr_code`, `qr_code_base64`, `ticket_url`, `expires_at`, `paid_at`, timestamps; unique `(gateway, gateway_ref)` |
| `seed_payment_maintenance_schedules` | `task_schedules`: `payments:expire` a cada 5 min (`*/5 * * * *`) e `webhooks:prune` diário às 03:30 com `--days=30` |

- Enums: `CheckoutMode` (`one_time|recurring`), `CheckoutStatus` (`pending|approved|failed|expired|canceled`), `DonationStatus` (`pending|paid|expired|refunded|failed`), `WebhookEventStatus` (`pending|processing|processed|failed|ignored`); `PaymentMethod` ganha `CreditCard='credit_card'` e `Boleto='boleto'`.
- Models: `CheckoutSession` (route key `ulid`), `Donation` (route key `ulid`), `WebhookEvent`; casts e relações.
- Correções de período:
  - `Plan::periodEndFrom()` usa `addMonthNoOverflow()`/`addYearNoOverflow()` (31/01 e ano bissexto).
  - `Subscription::isCurrent()`/`scopeCurrent()` exigem status ativo, início nulo ou `<= now`, fim nulo ou `> now`.
  - `User::activeSubscription()` escolhe a linha que **cobre agora**, ordenando por `current_period_start desc, id desc`; adicionar `nextSubscription()` para a próxima linha futura.
  - `OpenSubscriptionPeriod` centraliza: Free não cria linha; mesmo plano empilha no maior fim futuro; plano diferente ou sem período começa agora.
  - `CancelActiveSubscriptions` encerra todas as linhas ativas (inclusive futuras), cancelando antes cada preapproval distinta ainda não marcada como cancelada (falha aborta e nada muda no local), com opção de exceção.
  - `GrantPlan` reescrito sobre essas duas actions: Free/diferente cancelam tudo; mesmo plano cria linha nova (nunca estende a linha atual), gateway `manual`; se a linha atual tiver preapproval, cancela na MP antes de assumir (falha aborta sem mudança local).
  - `RefundPayment` cancela a linha vinculada quando ela ainda vale (inclusive futura), sem apagar histórico nem notificar duas vezes.
- Testes: `SubscriptionPeriodTest` (31/01, 29/02, virada de ano, período futuro não vigente, cota por período, renovação antecipada/atrasada, GrantPlan nos três casos), atualizando `BillingTest`, `QuotaTest`, `AdminBillingTest` e `AccountPanelTest`.

#### 3.2 Configuração e cliente Mercado Pago

- `config/payments.php`: `gateway`, `mercadopago.access_token`, `webhook_secret`, `base_url`, `timeout`, `connect_timeout`, `checkout.expires_minutes`, `back_url`, `webhook_url`, `statement_descriptor`.
- `config/donations.php`: sugeridos `[500,1000,2500,5000]`, mínimo 100, máximo 100000, moeda, `static_pix.key/holder/city`, `payer_email` de fallback, `send_receipts`.
- `.env.example`: `MERCADO_PAGO_ACCESS_TOKEN=`, `MERCADO_PAGO_WEBHOOK_SECRET=`, `MERCADO_PAGO_BASE_URL=https://api.mercadopago.com`, `PAYMENTS_CHECKOUT_EXPIRES_MINUTES=60`, `DONATIONS_PIX_KEY=`, `DONATIONS_PIX_HOLDER=`, `DONATIONS_PIX_CITY=`, `DONATIONS_SEND_RECEIPTS=true`. Nenhum valor real.
- Contrato `App\Contracts\Payments\PaymentGateway`: `isAvailable`, `createPreference`, `createPreapproval`, `createPixDonation`, `payment`, `preapproval`, `authorizedPayment`, `refund`, `cancelPreapproval`.
- `MercadoPagoGateway` com `Http::withToken()`, timeouts, `X-Idempotency-Key` estável, sem SDK; DTOs em `app/Support/Payments/`; `PaymentGatewayException` sem vazar corpo/token; `PaymentMethodMapper`; `MercadoPagoConfiguration`; provider registrando o contrato.
- Endpoints exatos: `POST /checkout/preferences` (Checkout Pro), `POST /preapproval`, `POST /v1/payments` (Pix), `GET /v1/payments/{id}`, `GET /preapproval/{id}`, `GET /authorized_payments/{id}`, `POST /v1/payments/{id}/refunds`, `PUT /preapproval/{id}` com `status=canceled`.

#### 3.3 Checkout e `/subscribe`

- `StartCheckout` cria `checkout_sessions` pendente (valor e moeda capturados, expiração de 60 min), chama o gateway e devolve o `init_point`; falha marca a sessão como `failed` e mostra erro seguro.
- Regras de início:

| Situação | Ação |
|---|---|
| Recorrência ativa no mesmo plano + “Pagar agora” | cancelar a preapproval (falha aborta) e abrir checkout avulso; período novo empilha na aprovação |
| Recorrência ativa no mesmo plano + nova recorrência | bloquear com explicação; não abrir segunda preapproval |
| Recorrência ativa em plano diferente | cancelar a preapproval antes; abrir o novo checkout; novo período começa agora na aprovação |
| Sem recorrência, mesmo plano | abrir checkout; período novo empilha |
| Sem recorrência, plano diferente ou nenhum | abrir checkout; período novo começa agora |

- Rotas `POST /subscribe/checkout` (auth+active) e `GET /subscribe/checkout/{checkoutSession:ulid}/return` (auth+active, dono), com `Inertia::location($initPoint)` para o redirect externo. O retorno não confirma nada: mostra “pagamento em processamento”.
- `Subscribe.vue` com três caminhos na mesma página: **Pagar agora**, **Renovação automática no cartão** e **Prefiro atendimento manual** (formulário atual preservado). Sem credenciais, os caminhos online ficam desabilitados com aviso e o manual continua; strings em `home.subscribe.*` e paridade nos cinco idiomas.
- Testes `CheckoutTest` com `Http::fake()`: preferência, preapproval, corpo mensal/anual, `init_point`, bloqueios, cancelamento prévio, ausência de credenciais e retorno.

#### 3.4 Webhooks, aprovação e idempotência

- `routes/webhooks.php` incluído em `routes/web.php`; `POST /webhooks/mercadopago` (`webhooks.mercadopago`), fora de `auth` e do locale; `bootstrap/app.php` isenta `webhooks/mercadopago` do CSRF.
- Assinatura `x-signature` (`ts`, `v1`) + `x-request-id` + `data.id`; manifesto exato `id:{data.id em minúsculas};request-id:{x-request-id};ts:{ts};`; HMAC-SHA256 com o segredo e `hash_equals`. Sem segredo configurado: 503; assinatura inválida/ausente: 401. Sem janela de timestamp (a idempotência protege replay e não quebra retries).
- Persistir o payload completo em `webhook_events`, responder 200/201 rápido e despachar `ProcessWebhookEvent` após o commit. Duplicata `(gateway, event_id)` responde 200 sem novo job; evento com falha pode ser reprocessado.
- Tópicos reais: `payment`, `subscription_preapproval`, `subscription_authorized_payment`. O corpo traz `type`/`action`; o recurso é **sempre** buscado na API antes de agir:

| Tópico | Recurso | Ação |
|---|---|---|
| `payment` | `/v1/payments/{id}` | aprovado: checkout avulso ou doação; `refunded`/`charged_back`: sincronizar estorno local; pendente/rejeitado: registrar sem liberar cota |
| `subscription_preapproval` | `/preapproval/{id}` | `authorized`: registra a autorização, **não** libera cota; `canceled`: marca renovação cancelada |
| `subscription_authorized_payment` | `/authorized_payments/{id}` | aprovado: primeira assinatura ou renovação empilhada; `recycling`/pagamento rejeitado: falha de renovação |
| desconhecido | — | `ignored` |

- `ApproveGatewayPayment`: localiza a sessão pelo `external_reference` (`chk_<ulid>`) ou pela preapproval; valida valor/moeda contra a sessão (primeira cobrança) ou contra o primeiro pagamento da preapproval (renovações); idempotência por `(gateway, gateway_ref)`; abre o período via `OpenSubscriptionPeriod`; grava o pagamento por `RegisterPayment` estendido (`gateway`, `gatewayRef`, `subscription`); limpa `renewal_failed_at`; marca a sessão aprovada e notifica.
- A preapproval autorizada **não** ativa a assinatura; a UI só sai de “processando” quando o primeiro pagamento aprovado chega (pode levar ~1h).
- Testes `MercadoPagoWebhookTest` + `GatewayPaymentTest`: assinatura inválida/ausente, duplicata, fetch do recurso, cada tópico, aprovação idempotente, valor/moeda divergentes, CSRF isento, job com retry/falha.

#### 3.5 Recorrência, cancelamento, falha, estorno e expiração

- `CancelRecurring`: chama a MP primeiro; 404/409 “já cancelada” conta como sucesso; grava `renewal_canceled_at` nas linhas ativas da mesma preapproval sem mudar `status`; acesso segue até o fim do período pago. Falha real aborta sem alterar o local.
- `HandleRenewalFailure`: primeira falha do período grava `renewal_failed_at` e envia `PaymentRenewalFailed` (uma vez); aprovação posterior limpa o estado.
- `RefundGatewayPayment`: gateway primeiro (`POST /v1/payments/{id}/refunds`, ou 404/409 já estornado), depois `RefundPayment`; falha deixa tudo como estava. Depois tenta cancelar a preapproval e reporta falha sem desfazer o estorno. Webhooks `refunded`/`charged_back` usam o mesmo action sem chamar a API de novo. Admin `PaymentController@refund` passa a usar esse action quando `gateway != manual`.
- `payments:expire {--dry-run}`: marca sessões e doações pendentes vencidas como `expired` (a leitura também expira de forma preguiçosa); `webhooks:prune {--days=30} {--dry-run}`: apaga eventos antigos, nunca pagamentos/assinaturas/doações.
- Billing: `BillingController` devolve `renewalCanceledAt`, `renewalFailedAt`, `renewable`, `lastCheckout` e a próxima linha; `Billing.vue` ganha “Cancelar renovação” (confirmação + erro de gateway), avisos de renovação cancelada/falha e checkout pendente. Rota `POST /dashboard/billing/cancel-recurring`.
- Testes: cancelamento com acesso preservado, falha do gateway sem mudança local, aviso único, limpeza na aprovação, ordem API→local no estorno, estorno duplicado, webhook de estorno, expiração e prune.

#### 3.6 Doações

- `PixPayload` (EMV + CRC16-CCITT) para o fallback estático; QR SVG com `bacon/bacon-qr-code` (já instalado via Fortify; declarar `^3.0` explicitamente no `composer.json`), renderizado como data URI. Nada de nova dependência npm.
- `DonationController` (`index`, `store`, `status`), `CreateDonation`, `ConfirmDonation`, `StoreDonationRequest`; rotas no grupo `{locale}`: `GET /donate` (`donate`), `POST /donate` (`donate.store`, `throttle:10,1`), `GET /donate/{donation:ulid}` (`donate.status`).
- Com gateway: cria `donations` pendente, chama `POST /v1/payments` com `payment_method_id=pix`, `external_reference=don_<ulid>`, `notification_url`, e-mail do doador ou fallback de config; grava `qr_code`/`qr_code_base64`/`ticket_url`; redireciona para a página de status. Sem gateway: Pix estático de `config/donations.php` com QR e copia-e-cola, deixando explícito que não há confirmação automática.
- Doador anônimo permitido; e-mail opcional; recibo `DonationReceipt` (`ShouldQueue`) só na transição `pending → paid` e se `send_receipts`; usa o `locale` gravado na doação. Webhook aprovado confirma uma vez após validar valor/moeda; `refunded`/`charged_back` marca `refunded`; `rejected`/`expired`/`cancelled` marca `failed`/`expired`.
- Página de status pública por ULID, **sem** nome/e-mail do doador, `noindex`. Admin vê os dados completos na aba.
- SEO: `Sitemap::PAGES` recebe `donate`; `Seo::pageMeta` ganha título/descrição de `donations.*`; `/subscribe` já é `noindex`; `donate.status` entra em `NOINDEX`; rodapé usa `app.footer.donate`.
- Testes: página nos cinco idiomas, sitemap/hreflang, valores sugeridos/livre, Pix fake, fallback estático, QR/copia-e-cola, status noindex, confirmação idempotente, valor divergente, recibo uma vez, estorno e expiração.

#### 3.7 Admin, i18n, docs e aceite

- Admin: `PaymentController@index` ganha `tab=payments|donations` (aba Doações com busca, filtro de status e detalhe); `Admin\WebhookEventController@index` + `admin/WebhookEvents.vue` read-only com filtros e payload completo; item “Webhook events” no `AdminLayout`; tipos em `types/admin.ts`; sem UI de IA nesta fase.
- i18n: novo namespace `donations` em `config/locales.php` e `lang/*/donations.php`; novas chaves em `home.subscribe.*`, `account.billing.*`, `admin.*` e `mail.*` nos cinco idiomas; `TranslationsParityTest` cedo e no fim.
- Docs: ADR-045 (gateway/Checkout Pro/Preapproval), ADR-046 (períodos empilhados e assinatura que cobre agora), ADR-047 (webhooks: assinatura, idempotência, fetch-before-fulfill e retenção), ADR-048 (doações Pix dinâmico/estático), ADR-049 (ciclo da recorrência: cancelamento, falha, estorno); atualizar `docs/SPEC.md`, `docs/API.md`, `docs/ARQUITETURA.md`, `docs/RUNBOOK.md`, `docs/glossario.md`, `CHANGELOG.md`.
- `docs/acceptance/FASE-6.md` com critérios, mapa das decisões, evidências dos testes e o **smoke real da MP como critério pendente** (sem credenciais): roteiro para o dono com túnel HTTPS, sandbox, Pix/cartão/boleto, preapproval mensal/anual, cancelamento, estorno, replay de webhook e doação; nunca imprimir tokens.
- Nenhum segredo em chat, git, logs, issues ou docs.

### 4. Verificação final

| Gate | Critério |
|---|---|
| `composer test` | Pest + Pint + PHPStan verdes; incluir testes novos de F4/F5/F6 |
| `npm run test` | Vitest verde; incluir Subscribe/Donate/Billing/admin |
| `npm run types:check` | `vue-tsc` limpo com Wayfinder regenerado |
| `npm run lint:check` / `format:check` | 0 erros; Prettier ok |
| `npm run build` / `build:ssr` | ambos ok; SSR sem `window` no setup |
| `migrate:fresh --seed --force` | em banco isolado de smoke, nunca no `devtools` |
| `route:list` | rotas de checkout, webhooks, donate e admin presentes |
| `payments:expire --dry-run` / `webhooks:prune --dry-run` | executam sem alterar dados |
| Smoke F5 | matriz completa, evidência no FASE-5 e cleanup |
| Testes F6 | `Http::fake`, webhooks assinados, idempotência, períodos, cota, estornos, doações, i18n |
| Smoke MP real | pendente no FASE-6, com roteiro do dono |

### 5. Riscos e casos de borda

- **31/01 e ano bissexto**: `NoOverflow`; testes em 2026, 2028 e 29/02.
- **Períodos empilhados**: linha futura não pode virar “vigente” nem reiniciar cota; admin/billing precisam distinguir atual e próxima.
- **Webhook fora de ordem/replay**: unique de evento, fetch do recurso e ações idempotentes por `gateway_ref`.
- **Retry da MP**: responder 2xx rápido; falha transitória do fetch reenfileira; falha permanente vai para `failed` e aparece no admin.
- **Assinatura**: segredo ausente, `data.id` alfanumérico minúsculo, request-id ausente; não usar o corpo da notificação para liberar cota.
- **Valor/moeda**: validar sempre contra a sessão/doação/primeiro pagamento; divergência não libera nada.
- **Estorno/cancelamento**: gateway primeiro; falha não pode mentir no estado local. Estorno de período futuro não derruba o período atual.
- **Preço alterado/plano desativado**: o valor cobrado fica no pagamento; não conceder período novo de plano desativado, registrar a anomalia.
- **Conta anonimizada/excluída**: sem e-mail, registro fiscal preservado; doação pode ser anônima.
- **Pix estático**: sem confirmação automática; texto explícito. Doação dinâmica aceita pagamento aprovado depois da expiração local.
- **Migrations**: somente aditivas; unique de `payments.gateway_ref` conferido antes do deploy; schedule rows não destrutivas.
- **Fila**: `DB_QUEUE_RETRY_AFTER=150` e worker reiniciado no deploy.
- **SEO**: `/donate` indexável com hreflang; `/subscribe` e status de doação `noindex`.
- **Histórico**: filtro `history_visible` esconde linhas antigas; docs registram a entrega antecipada e o que resta para F7.

### 6. Critério de conclusão

- F4 auditada e evidências no `FASE-4.md`; nenhum bloqueio aberto.
- F5 com os 19 critérios revalidados e o critério 20 (smoke real) concluído; histórico corrigido e docs alinhados.
- F6 implementada com migrations, gateway, checkout, webhooks, recorrência, estornos, doações, admin, i18n, SEO, docs e testes; `FASE-6.md` criado com o smoke real da MP pendente apenas por falta de credenciais.
- Todos os gates verdes; relatório final em `docs/claude-code/reports/develop_natan/`.
- **Parar para revisão do dono.** Nenhum commit ou push.
