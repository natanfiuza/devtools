# Plano — Confirmar pagamento concede plano, estorno cancela e cota acumulativa

**Data:** 2026-10-23 · **Branch:** `develop_natan`
**Survey:** `docs/survey/20261023_confirmar_pagamento_e_cota_acumulativa_surveyfacts.md`

## 1. Banco (migration retrocompativel)
- `database/migrations/2026_10_23_000001_add_ended_and_refunded_to_subscriptions_table.php`: `ended_at` e `refunded_at` (timestamp, nullable) em `subscriptions`. Sem backfill (Q8).
- `App\Models\Subscription`: casts + `@property`.

## 2. Backend
1. **`CancelActiveSubscriptions`** e todo ponto que grava `status = canceled`: gravar `ended_at = now()`.
2. **`RefundPayment`**:
   - linha vinculada: `status = canceled`, `ended_at = now()`, `refunded_at = now()`;
   - **fallback (Q3)**: sem `subscription_id` e com `plan_id`, procurar assinatura vigente/futura do mesmo plano e da mesma conta que nao seja `subscription_id` de outro pagamento; cancelar e marcar `refunded_at`.
3. **`RegisterPayment`**: com plano pago, conceder sempre (`GrantPlan`) e vincular; parametro `grantPlan` removido (gateway continua passando `subscription`).
4. **`PaymentController::store`**: remover `grant_plan` da validacao.
5. **Nova action `App\Actions\Admin\ConfirmIntentPayment`**: em `DB::transaction`, chama `RegisterPayment` (valor, forma da intencao, plano, data, nota) e marca a intencao `fulfilled` (`handled_by`, `handled_at`). Recusa intencao que nao esteja `pending`.
6. **`Admin\SubscriptionIntentController::confirmPayment`** + rota `POST admin/intents/{intent}/confirm-payment` (`admin.intents.confirm-payment`); valida `amount`, `paid_at`, `note`; audita (`AdminActionType::SubscriptionIntentPaymentConfirmed`). Rota `fulfill` mantida por compatibilidade, mas sai da UI.
7. **`App\Support\Quota`** (Q4–Q10):
   - `credited()`: soma, sobre as assinaturas pagas da conta sem `refunded_at` e (status ativo **ou** `ended_at` preenchido), de `plan.monthly_tokens` x meses iniciados entre `current_period_start` e `min(now, current_period_end, ended_at)`; + `grant`/`refund` de tokens do ledger.
   - `used()`: debitos de tokens de todo o historico (sem janela).
   - `limit()` = `credited()`; `remaining()` = `max(0, limit - used)`.
   - Plano efetivo para liberar uso: assinatura vigente; sem ela, Free (usos) — saldo guardado (Q5).
   - Free inalterado.
   - `nextCreditAt()`: proximo inicio de mes do periodo vigente, ou inicio do proximo periodo ja pago; nulo sem assinatura.
   - `toArray()`: mesmas chaves + `next_credit_at`.
   - Contagem de "meses iniciados" com `addMonthsNoOverflow` a partir do inicio do periodo (coerente com ADR-046).
8. **`GrantQuota`**: sem mudanca de regra (soma ao creditado).

## 3. Frontend
- `resources/js/components/admin/ConfirmIntentPaymentDialog.vue` (Dialog shadcn-vue): valor (pre-preenchido com preco do plano), forma (somente leitura), data, nota; envia para `admin.intents.confirmPayment`.
- `admin/Intents.vue` e aba Solicitacoes de `admin/Subscriptions.vue`: botao "Confirmar pagamento" para `pending` (substitui "Atender").
- `admin/Payments.vue` e aba Pagamentos de `admin/Subscriptions.vue`: remover checkbox `grant_plan`; texto de ajuda "Ao escolher um plano, ele e concedido automaticamente".
- Props das intencoes ganham `planPrice` (formatado) e `planPriceAmount` (decimal) para pre-preencher.
- Telas de cota (billing, overview, tool props): rotulo "saldo acumulado" e "proximo credito em".
- Tipos TS (`types/admin.ts` ou equivalente) atualizados.

## 4. i18n (5 idiomas: en, pt_BR, pt_PT, fr, es)
- `admin.intents.confirm_payment*`, `admin.toasts.intent_payment_confirmed`, `admin.payments.plan_grants_help`, `account.quota.accumulated`, `account.quota.next_credit`, textos de auditoria.

## 5. Docs
- **ADR-054** `docs/adr/ADR-054-confirmacao-concede-plano-e-cota-acumulativa.md` (revisa ADR-027 e ADR-046).
- `docs/glossario.md`: atualizar "Cota", "Franquia de tokens", "Assinatura vigente"; novos "Saldo acumulado de tokens", "Confirmar pagamento (intencao)".
- `docs/SPEC.md` (secao de planos/cota), `docs/API.md`, `docs/openapi.yaml` (`next_credit_at`).

## 6. Testes (Pest)
- `ConfirmIntentPaymentTest`: confirma -> pagamento `paid` com `subscription_id`, assinatura vigente, intencao `fulfilled`, auditoria, e-mail de confirmacao; recusa intencao nao pendente.
- `RefundPayment`: vinculado marca `refunded_at`/`ended_at`; fallback legado cancela assinatura sem vinculo; nao derruba periodo de outro pagamento.
- `RegisterPayment`: plano sempre concedido; sem plano nao cria assinatura.
- `QuotaTest`: acumulo entre meses; renovacao antecipada; anual credita por mes iniciado; estorno remove credito e excesso abate do proximo; troca de plano conserva meses iniciados; vencido guarda saldo e volta com nova assinatura; legado `canceled` sem `ended_at` vale 0; `next_credit_at`.
- Ajustar testes existentes afetados (`AdminBillingTest`, `BillingTest`, `SubscriptionPeriodTest`, `PaymentNotificationsTest`, API v1).
- Vitest: dialogo de confirmacao e remocao do checkbox.
- Fim: `composer test`, `npm run test`, `npm run types:check`; relatorio em `docs/gemini/reports/develop_natan/`.
