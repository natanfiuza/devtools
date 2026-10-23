# Survey — Confirmar pagamento concede plano, estorno cancela e cota acumulativa (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "Quando um usuario faz uma solicitacao de pagamento eu confirmo o pagamento ele deve conceder o plano automaticamente junto com o registro de pagamento, hoje eu tenho que entrar em Conta depois clicar em no botao Abrir ir no card Assinatura e no select Conceder plano selecionar um plano e depois clicar no botao Conceder plano. Quando eu estornar o pagamento o plano concedido deve ser cancelado. Verifique tambem o plano mensal, ele paga por cota de tokens cada pagamento mensal o usuario recebe uma cota de 2000000 de tokens acumulativos."
- **Tela / Modulo:** `/admin/intents` (`admin/Intents.vue`), `/admin/subscriptions` aba Solicitacoes e Pagamentos (`admin/Subscriptions.vue`), `/admin/payments` (`admin/Payments.vue`); `App\Actions\Admin\{RegisterPayment,RefundPayment,GrantPlan}`, `App\Actions\Subscriptions\{OpenSubscriptionPeriod,CancelActiveSubscriptions}`, `App\Support\Quota`, `App\Http\Controllers\Admin\{SubscriptionIntentController,PaymentController}`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 2 rodadas, 11 decisoes; leitura de actions, controllers, telas Vue, migrations, ADR-012/027/046 e glossario.
- **Arquivos-chave lidos:** `app/Actions/Admin/*.php`, `app/Actions/Subscriptions/*.php`, `app/Actions/Payments/ApproveGatewayPayment.php`, `app/Support/Quota.php`, `app/Models/Plan.php`, `app/Enums/{LedgerEntryType,PaymentMethod,SubscriptionIntentPaymentMethod}.php`, `app/Http/Controllers/Admin/{PaymentController,SubscriptionIntentController,SubscriptionController}.php`, `resources/js/pages/admin/{Subscriptions,Payments,Intents}.vue`, `database/migrations/*subscriptions*`, `docs/adr/ADR-012`, `ADR-027`, `ADR-046`, `docs/glossario.md`.

## 2. Decisoes das rodadas

### Rodada 1 — Fluxo de confirmacao, estorno e modelo da cota
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Confirmar pagamento da solicitacao | **"Atender" vira "Confirmar pagamento"**: dialogo pre-preenchido (valor = preco do plano, editavel; forma = a da intencao; data = hoje). Numa transacao: registra pagamento, concede plano vinculado ao pagamento e marca intencao `fulfilled`. Nova action `ConfirmIntentPayment`. |
| Q2 | Registro manual com plano | **Plano escolhido = plano concedido sempre**; checkbox `grant_plan` removido. Sem plano = pagamento avulso. Cortesia continua em "Conceder plano" na conta. Revisa ADR-027 (novo ADR). |
| Q3 | Estorno de pagamentos legados sem vinculo | **Fallback**: estorno cancela a assinatura vigente/futura do mesmo plano que nao esteja vinculada a nenhum outro pagamento. |
| Q4 | Modelo da cota acumulativa | **Direito calculado na leitura**: saldo = soma do creditado pelos periodos pagos ja iniciados e nao estornados (+ concessoes) menos todo o consumo pago. Sem cron, sem coluna de saldo. Substitui a "franquia por periodo" (ADR-046) — novo ADR. |
| Q5 | Saldo quando a assinatura vence | **Saldo fica guardado** e volta a valer com nova assinatura; sem plano pago, a conta segue as regras do Free. |
| Q6 | Pro anual | **`monthly_tokens` por mes iniciado** dentro do periodo anual, acumulativo (24M ao fim do ano). |

### Rodada 2 — Detalhes do calculo, historico, contrato e UI
| # | Decisao | Resolucao |
|---|---|---|
| Q7 | Cancelamento por troca de plano vs estorno | **Migration retrocompativel**: `subscriptions.ended_at` (todo cancelamento) e `subscriptions.refunded_at` (so estorno). Linha estornada vale 0; demais valem `monthly_tokens` x meses iniciados ate `min(agora, fim do periodo, ended_at)`. |
| Q8 | Historico anterior ao deploy | **Retroativo**: periodos antigos ativos/vencidos entram na soma; linhas `canceled` legadas sem `ended_at` valem 0. |
| Q9 | Contrato `Quota::toArray()` / API v1 | **Mesmas chaves, nova semantica** (`limit` = total creditado, `used` = consumo total, `remaining` = diferenca >= 0, `window` = periodo vigente) **+ chave aditiva `next_credit_at`**. Atualizar `docs/API.md` e `docs/openapi.yaml`. |
| Q10 | Estorno de periodo ja consumido | **Excesso abatido do proximo credito**; `remaining` nunca negativo. |
| Q11 | Onde fica "Confirmar pagamento" | **Nas duas telas** (`admin/Intents.vue` e aba Solicitacoes de `admin/Subscriptions.vue`) via componente compartilhado `ConfirmIntentPaymentDialog.vue`; so para intencao `pending`. |

### Defaults assumidos (nao contestados)
- Free continua contando usos vitalicios (coluna `uses`), sem relacao com o saldo de tokens.
- Concessao manual de tokens (`GrantQuota`, tipo `grant`) soma ao creditado e tambem acumula.
- Fluxo do gateway (Mercado Pago) ja vincula `subscription` ao pagamento; so ganha `refunded_at`/`ended_at` pelos mesmos pontos (`RefundPayment`, `CancelActiveSubscriptions`).
- Toda acao continua auditada em `admin_actions` (nova acao `subscription_intent.payment_confirmed` ou reuso de `payment.registered` + `subscription_intent.fulfilled`).
- Strings novas nos 5 idiomas; nenhum valor de tokens fixo no codigo (vem de `plans.monthly_tokens`).

## 3. Relatorio de fatos levantados

### "Atender" nao faz nada alem do status
`Admin\SubscriptionIntentController::fulfill` apenas grava `status = fulfilled`, `handled_by`, `handled_at` e audita. Nenhum pagamento nem assinatura e criado.

### Conceder junto com o registro e opcional por desenho (ADR-027)
`RegisterPayment` aceita `grantPlan`; `Subscriptions.vue` envia `grant_plan: true` por padrao, `Payments.vue` envia `false`. A ADR-027 justificava a separacao por cortesia e "pagou quem ja tinha acesso" — o segundo caso hoje e coberto pelo empilhamento (ADR-046).

### Por que o estorno nao cancelava o plano
`RefundPayment` so cancela `payments.subscription_id`. Plano concedido pela tela da conta (`UserActionController::grantPlan`) nao vincula o pagamento, entao o estorno nao encontra assinatura.

### Cota atual nao acumula e o anual esta subdimensionado
`Quota::limit()` devolve `plan.monthly_tokens` e `used()` filtra o ledger pela janela do periodo vigente. Sobras somem na virada; pagamento antecipado so empilha periodo futuro. Para o Pro anual a janela e de 12 meses com o mesmo `monthly_tokens` — 2M para o ano inteiro.

### Esquema de `subscriptions`
Colunas: `status`, `current_period_start`, `current_period_end`, `renewal_canceled_at`, `renewal_failed_at`. Nao ha registro de quando/por que uma linha foi cancelada — motivo das colunas `ended_at` e `refunded_at`.

### Mapeamento de forma de pagamento
`SubscriptionIntentPaymentMethod` (`pix`, `credit_card`, `boleto`) e subconjunto de `PaymentMethod` — conversao direta por `value`.

### Pontos de consumo de `Quota`
`StartAiQuery`, `GrantQuota`, `BillingController`, `OverviewController`, `Admin\UserController`, `AiQueryController`, `Api\MeController`, `SqlQueryController`, `AiToolProps` — todos leem pela mesma classe, entao a mudanca fica concentrada em `Quota`.
