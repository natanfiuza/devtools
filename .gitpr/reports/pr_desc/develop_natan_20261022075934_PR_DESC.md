# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona pagamentos Mercado Pago, API v1 e histórico opt-in
```

---

## 🎯 Resumo

Entrega as fases F4 a F7 do produto, fechando o ciclo de monetização e exposição da plataforma:

- **Pagamentos e assinaturas** via Mercado Pago (checkout avulso, preapproval recorrente e Pix), separando a *intenção de pagar* (`checkout_sessions`) do *registro fiscal* (`payments`), com períodos empilhados em vez de sobrescritos.
- **Doações Pix** (dinâmico via gateway com fallback estático EMV/CRC16), página pública, status por ULID e recibo por e-mail.
- **API pública v1** autenticada por Bearer token Sanctum (ability `ai:query`), com polling, cotas por token/IP e OpenAPI.
- **Histórico de IA opt-in** com retenção configurável, exclusão pelo usuário e gate por plano pago.
- **Hardening**: CSP com nonce, HSTS, proxies confiáveis, Turnstile fail-closed, webhooks com validação HMAC e idempotência, 404 customizada e SEO revisado.

O objetivo é transformar o produto em SaaS cobrável e integrável, sem comprometer o registro fiscal já existente nem vazar dados sensíveis em logs.

## 🛠️ Mudanças Técnicas

**Pagamentos e assinaturas**
- Contrato `PaymentGateway`, DTOs e `MercadoPagoGateway` (HTTP direto, timeouts, idempotency key, sem log de token/corpo), `PaymentMethodMapper` e `MercadoPagoSignature` (HMAC em tempo constante).
- Modelo `CheckoutSession` separa intenção de pagamento aprovado; novo modelo `WebhookEvent` com payload completo, deduplicação e job `ProcessWebhookEvent` (retries/backoff).
- Actions: `StartCheckout`, `ApproveGatewayPayment` (idempotência por `(gateway, gateway_ref)` + índice único e validação de valor/moeda), `CancelRecurring`, `HandleRenewalFailure`, `RefundGatewayPayment`, `HandleWebhookEvent`.
- `GrantPlan`, `RegisterPayment` e `RefundPayment` passam a suportar gateway; `OpenSubscriptionPeriod` e `CancelActiveSubscriptions` centralizam abertura/encerramento; `Subscription::isCurrent()`/`isScheduled()` e `Plan::periodEndFrom()` com `addMonthNoOverflow`/`addYearNoOverflow`.

**Doações**
- Modelo `Donation`, `CreateDonation` (Pix dinâmico ou fallback estático com `PixPayload`/`PixQrCode`), `ConfirmDonation` idempotente com conferência de valor/moeda e recibo por e-mail; página de status por ULID.

**API pública v1**
- Rotas `api/v1` token-only via Sanctum (ability `ai:query`): `MeController`, `OutputTargetController` e `SqlQueryController` (POST 200/202 + polling, listagem escopada por `ai_requests.api_token_id`).
- Rate limiters por token/IP em `ApiServiceProvider`, `ForceJsonResponse` (401/422 sempre JSON) e `docs/openapi.yaml`.
- Gestão de tokens em `settings` com exibição única em texto puro (flash) e revogação escopada ao dono; `StartAiQuery` recebe `apiTokenId`, deduplica por token e respeita `history_enabled`.

**Histórico e conta**
- `users.history_enabled`, toggle otimista, `ClearHistory` restrito a estados terminais, middleware `EnsureHistoryHasPaidPlan` e comando `history:prune`.
- Exportação de dados inclui consultas (schema/descrição só enquanto o payload canônico existe); anonimização remove histórico e tokens, preservando `usage_ledger`.

**Segurança, admin e infra**
- `SecurityHeaders` com CSP nonce, HSTS, `TrustProxies` configurável, regra `ValidTurnstile` (fail-closed), 404 Inertia com `noindex` e exclusão de CSRF para o webhook.
- Telas admin: cache de IA (metadados + poda), alvos de saída (liga/desliga), eventos de webhook (leitura) e aba de doações em pagamentos.
- Comandos de manutenção `payments:expire` e `webhooks:prune` (dry-run, janela de dias, proteção contra 0 dias e preservação de dados fiscais).
- SEO: Twitter Card completa, JSON-LD por request via `StructuredData` (omitido em páginas `noindex`), donate no sitemap e `fallback` que não intercepta `api/*`.
- i18n em pt_BR/pt_PT/en/es/fr para doações, checkout, renovação, tokens, admin e erros; remoção do link de docs do menu.

**Banco de dados**
- Migrações: `checkout_sessions`, `webhook_events`, `donations`, `personal_access_tokens`, colunas `renewal_*`, `gateway_method`, `api_token_id`, `history_enabled`, índices únicos e seeds idempotentes em `task_schedules`.

**Testes**
- Feature: `CheckoutTest`, `GatewayPaymentTest`, `DonationTest`, `AdminDonationsTest`, `AdminPaymentRefundTest`, `AdminWebhookEventsTest`, `AdminAiCacheTest`, `AdminOutputTargetsTest`, `Api/V1/*`, `BillingRecurringTest`, `HistoryMaintenanceTest`, `TurnstileTest`, `ErrorPageTest`.
- Unitários: `MercadoPagoGateway`, webhook HMAC/deduplicação, `PixPayload` (CRC16-CCITT/BR Code), períodos empilhados, CSP/HSTS/TrustProxies e tokens Sanctum.
- Front: Vitest para `api-tokens-settings`, `turnstile-widget`, `account-panel`, `admin-panel`, `subscribe` e `auth-register`.

## ⚠️ Impacto/Avisos

- **Banco de dados**: novas tabelas (`checkout_sessions`, `webhook_events`, `donations`, `personal_access_tokens`) e colunas em `users`/`payments`/`ai_requests`/assinaturas, com índices únicos — requer `php artisan migrate`.
- **Variáveis de ambiente**: novas chaves obrigatórias para habilitar checkout (`MERCADO_PAGO_*`, `MERCADO_PAGO_WEBHOOK_SECRET`, `TURNSTILE_*`, `AI_HISTORY_RETENTION_DAYS`). Sem token o checkout fica oculto; sem secret o webhook responde **503**.
- **Dependências**: `laravel/sanctum ^4.3` e `bacon/bacon-qr-code ^3.0`.
- **Mudança de comportamento**: períodos de assinatura passam a ser **empilhados em linhas separadas** (não sobrescritos); `Subscription::isCurrent()` agora exige início de período.
- **Segurança**: CSP em modo enforce pode afetar scripts inline legados; cookies de sessão passam a `secure` em produção e `same_site=lax` no retorno do checkout.
- **Exposição**: nova API pública `/api/v1` autenticada por Bearer token — revisar escopo de abilities e rate limits antes do rollout.
- **Operação**: agendar `payments:expire`, `webhooks:prune` e `history:prune` no scheduler.


close #58

---

[![GitPR](https://img.shields.io/badge/GitPR-14_errors_%C2%B7_276_warnings-red)](https://gitpr.natanfiuza.dev.br/)