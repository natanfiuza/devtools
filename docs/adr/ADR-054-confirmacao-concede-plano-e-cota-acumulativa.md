# ADR-054: Confirmacao de pagamento concede plano e cota acumulativa de tokens

Status: Aceito
Data: 2026-10-23
Relaciona: ADR-027 (venda manual), ADR-034 (intencao de assinatura), ADR-040 (reserva e liquidacao), ADR-046 (periodos empilhados), ADR-049 (ciclo da recorrencia e estorno)

## Contexto

Ate aqui, atender uma intencao de assinatura em `/admin/intents` (`POST /admin/intents/{intent}/fulfill`) apenas trocava o status da intencao para `fulfilled`, obrigando o admin a abrir a conta do usuario, conceder o plano manualmente e registrar o pagamento em passos separados. Alem disso, no registro manual de pagamento (`RegisterPayment`), a concessao do plano dependia de um checkbox opcional (`grant_plan`), o que permitia registrar pagamentos com `plan_id` mas com `subscription_id = null` (impedindo que o estorno encerrasse a assinatura correspondente).

Ao mesmo tempo, a cota de tokens (`Quota`) filtrava o livro-razao apenas dentro da janela do periodo vigente (`windowStart` a `windowEnd`) e reiniciava a franquia a cada ciclo, descartando o saldo nao utilizado de meses pagos anteriores e tratando o plano anual como um bloco unico em vez de creditos mensais acumulativos de 2.000.000 de tokens.

## Decisao

1. **Confirmar pagamento na intencao de assinatura (`ConfirmIntentPayment`)**:
   - Na tela `/admin/intents`, a acao principal de intencoes pendentes passa a ser **Confirmar pagamento** (`POST /admin/intents/{intent}/confirm-payment`), abrindo um dialogo pre-preenchido com o preco do plano solicitado (editavel), a forma de pagamento escolhida na intencao, data opcional e observacao opcional.
   - Em uma unica transacao (`ConfirmIntentPayment`), o sistema registra o pagamento em `payments`, concede/empilha o plano via `GrantPlan` vinculando `payments.subscription_id` a assinatura criada, marca a intencao como `fulfilled` e grava a auditoria `intent.payment_confirmed`.
2. **Todo pagamento manual com plano pago concede a assinatura**:
   - O checkbox opcional `grant_plan` deixa de existir nos formularios de pagamento manual (`/admin/payments` e `/admin/subscriptions`).
   - Em `RegisterPayment`, sempre que `$gateway === 'manual'`, `$plan !== null && ! $plan->isFree()` e `$subscription === null`, `GrantPlan` e executado automaticamente e `subscription_id` e gravado no pagamento. Pagamentos sem plano (`plan_id = null`) continuam apenas fiscais.
3. **Estorno encerra a assinatura vinculada (com fallback para pagamentos legados) e zera creditos**:
   - `subscriptions` ganha as colunas anulaveis `ended_at` (data em que a assinatura foi encerrada antecipadamente) e `refunded_at` (data em que a assinatura foi estornada).
   - `CancelActiveSubscriptions` (troca de plano ou queda para o Free) preenche `ended_at = now()` mantendo `refunded_at = null`.
   - `RefundPayment` preenche `ended_at` e `refunded_at = now()` na assinatura vinculada. Para pagamentos legados com `plan_id` preenchido mas `subscription_id = null`, busca como fallback a assinatura ativa ou futura da mesma conta e plano que nao possua outros pagamentos nao estornados vinculados e a encerra como estornada.
4. **Cota acumulativa de tokens (`Quota`)**:
   - Cada mes iniciado de uma assinatura paga nao estornada (`refunded_at === null`) credita `plan.monthly_tokens` (2.000.000 de tokens nos planos Pro).
   - No plano mensal (`interval = month`), cada linha cujo `current_period_start <= now()` credita 1 lote mensal.
   - No plano anual (`interval = year`), cada mes calendario ja iniciado desde `current_period_start` (`addMonthsNoOverflow($m) <= cutoff`, ate 12 meses) credita 1 lote mensal (ate 24.000.000 no ano), expondo `next_credit_at` com a data do proximo lote mensal quando aplicavel.
   - Assinaturas encerradas por troca de plano (`status = canceled`, `ended_at !== null`, `refunded_at === null`) preservam os lotes dos meses ja iniciados antes de `ended_at`. Assinaturas estornadas (`refunded_at !== null`) ou canceladas legadas sem `ended_at` creditam `0` tokens (eventual consumo excedente e abatido de creditos futuros, com `remaining` limitado a `>= 0`).
   - `limit()` nos planos pagos passa a ser a soma de todos os tokens creditados pelas assinaturas elegiveis mais concessoes manuais (`grant` em `usage_ledger`).
   - `used()` nos planos pagos passa a ser o total historico de tokens debitados menos estornos (`max(0, debits - refunds)`).
   - Quando a assinatura vence sem renovacao (`activeSubscription() === null`), a conta volta para o plano Free (que opera em `uses` vitalicios); o saldo historico de tokens permanece preservado e volta a ficar disponivel assim que a conta tiver nova assinatura paga ativa.

## Por que

- **Fluxo operacional em 1 passo**: confirmar o pagamento de uma intencao ja cumpre o objetivo de negocio (registrar a receita, liberar o plano vinculado ao pagamento e fechar a intencao) sem risco de esquecer o vinculo `subscription_id`.
- **Vinculo garantido para estorno**: eliminar o checkbox opcional `grant_plan` garante que todo pagamento de plano pago aponte para a assinatura que ele abriu, tornando o estorno deterministico.
- **Justica no acumulo de tokens**: quem paga mes a mes (ou anualmente mes a mes) acumula os tokens nao gastos enquanto mantiver assinatura ativa, e o estorno retira os creditos da assinatura estornada sem apagar o historico contabil.

## Consequencias

- Nova migration `2026_10_23_000001_add_ended_and_refunded_to_subscriptions_table.php` retrocompativel (colunas `nullable`).
- Novo caso `AdminActionType::SubscriptionIntentPaymentConfirmed` (`intent.payment_confirmed`) e rota `POST /admin/intents/{intent}/confirm-payment`.
- `Quota::toArray()` passa a incluir `next_credit_at` (ISO 8601 ou `null`), mantendo todos os campos existentes (`unit`, `limit`, `used`, `remaining`, `exhausted`, `window`).
