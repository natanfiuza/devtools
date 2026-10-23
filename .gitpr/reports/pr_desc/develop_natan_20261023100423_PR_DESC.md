# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: confirma pagamento de intenção e torna cota acumulativa
```

---

## 🎯 Resumo

Implementa a ADR-054, que fecha o ciclo de cobrança manual do admin e revisa a ADR-027. Antes, registrar um pagamento e conceder acesso eram atos independentes (`grant_plan` opcional), o que permitia pagamento sem plano, plano sem pagamento e estorno que não tirava o acesso. Agora o pagamento manual com plano pago **concede o plano automaticamente e fica vinculado à assinatura**, tornando o estorno capaz de encerrar o acesso de verdade. Em paralelo, a cota dos planos pagos deixa de ser "janela do período corrente" e passa a ser **acumulativa**: cada mês iniciado credita `monthly_tokens` (até 12 no anual), o saldo transita entre meses e o consumo histórico é abatido, com estornos zerando o crédito da linha.

Para o negócio: uma única ação "Recebi o dinheiro" na tela de intenções registra pagamento, concede o plano e marca a solicitação como atendida; estornos passam a remover tokens de fato, e o saldo do cliente não é mais perdido na virada de mês.

## 🛠️ Mudanças Técnicas

- **`ConfirmIntentPayment` (novo)**: em uma transação, registra o pagamento via `RegisterPayment`, vincula à assinatura concedida e marca a intenção como `Fulfilled`, gravando `handled_by`/`handled_at`.
- **`RegisterPayment`**: remove o parâmetro `grantPlan`; com `gateway = manual` e plano pago, a assinatura é concedida e vinculada ao pagamento automaticamente. Cortesia sem dinheiro continua via `GrantPlan`.
- **`RefundPayment`**: usa `Subscription::end(refunded: true)` para encerrar acesso; em períodos já vencidos grava apenas `refunded_at`; adiciona `unlinkedSubscription()` para localizar a assinatura correspondente em pagamentos legados sem `subscription_id`.
- **`Subscription::end(bool $refunded)`**: centraliza o encerramento, gravando `ended_at` (idempotente) e, opcionalmente, `refunded_at`. Aplicado também em `CancelActiveSubscriptions` e `AnonymizeUser`.
- **`Quota` reescrito**: `limit()` soma lotes mensais de assinaturas não estornadas (ativas ou com `ended_at`) por meses iniciados (`startedMonthsFor`) mais concessões `grant`; `used()` soma débitos menos `refund`; `nextCreditAt()` calcula o próximo lote (mês dentro de anual ou início do próximo período).
- **`DecimalInput` (novo support)**: extrai `normalize()` e adiciona `toCents()`, removendo a lógica duplicada do `PaymentController` e reutilizando no `SubscriptionIntentController`.
- **Backend admin**: nova rota `POST admin/intents/{intent}/confirm-payment` e método `SubscriptionIntentController::confirmPayment`; `AdminActionType::SubscriptionIntentPaymentConfirmed`; exposição de `planPrice`/`planAmount` nas linhas de intenção; `nextCreditAt` adicionado aos payloads de conta e admin.
- **Frontend**: novo `ConfirmIntentPaymentDialog.vue` (pré-preenche valor do plano, exibe forma de pagamento, envia valor/data/nota); botão "Cumprir" substituído por "Confirmar pagamento" em `Intents.vue` e `Subscriptions.vue`; checkbox `grant_plan` removido de `Payments.vue` e `Subscriptions.vue`.
- **Tipos**: `AdminIntentRow` ganha `planPrice`/`planAmount` e `document` passa a aceitar `null`; `nextCreditAt` adicionado a `AccountQuota`, `AdminQuota` e `Quota` do AI SQL Builder.
- **i18n (en, es, fr, pt_BR, pt_PT)**: novas chaves de confirmação de pagamento/erro e texto revisado de `grant_plan_help`.
- **OpenAPI**: campo `next_credit_at` (nullable, date-time) no schema de cota.
- **Testes**: casos novos para confirmação de intenção, estorno legado sem vínculo, acúmulo entre períodos sucessivos, crédito mensal de anual, remoção de crédito por estorno, retorno ao Free e reativação do saldo acumulado.

## ⚠️ Impacto/Avisos

- **Banco de dados**: migration `2026_10_23_000001` adiciona `subscriptions.ended_at` e `subscriptions.refunded_at` (nullable, sem backfill, compatível com deploy sem downtime). Linhas `canceled` antigas sem `ended_at` **creditam 0** — sem migração de dados históricos, esses clientes precisam recontratar para voltar a acumular.
- **Breaking change interno**: o parâmetro `grantPlan` de `RegisterPayment` foi removido e o campo `grant_plan` deixou de ser aceito em `POST admin/payments`. Qualquer chamador/integração que dependa disso precisa ser ajustado.
- **Mudança de comportamento**: registrar pagamento com plano pago agora **sempre** concede assinatura; não há mais como registrar cobrança sem conceder acesso pela tela de pagamentos.
- **Regra de cota**: a semântica mudou de "consumo do período corrente" para "consumo histórico acumulado"; relatórios, telas e expectativas de saldo precisam considerar o novo modelo. Estorno passa a debitar tokens retroativamente.
- **Sem novas variáveis de ambiente ou dependências** adicionadas.


close #70

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_12_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)