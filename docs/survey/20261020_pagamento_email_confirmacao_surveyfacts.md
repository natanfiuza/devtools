# Levantamento - E-mail de confirmacao e de estorno de pagamento

Data: 2026-10-20
Branch: develop_natan
Pedido original: quando um pagamento for registrado, enviar e-mail de confirmacao do pagamento ao usuario solicitante de assinatura.
Plano: `docs/plans/develop_natan/20261020_pagamento_email_confirmacao_plansfacts.md`
Plano (CLAUDE.md): `docs/claude-code/plans/develop_natan/20261020_develop_natan_pagamento_email_confirmacao.md`
Relatorio: `docs/claude-code/reports/develop_natan/20261020_develop_natan_pagamento_email_confirmacao.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

Branch `develop_natan`. Stack Laravel 13 + PHP 8.3 + Inertia 3 + Vue 3 + TypeScript estrito, i18n em 5 idiomas (`pt_BR`, `pt_PT`, `en`, `es`, `fr`), Pest 4 + Vitest 5. Nenhum e-mail era enviado a conta no registro ou no estorno de pagamento.

Termo do pedido: "usuario solicitante de assinatura" nao existe no repositorio. O dominio tem **intencao de assinatura** (`subscription_intents`, o pedido) e a **conta** (`User`), dona de `payments` e `subscriptions`. Nao ha vinculo entre pagamento e intencao.

## 2. Fatos levantados

### 2.1. Registro e estorno de pagamento

- Unico caminho de criacao de pagamento: `Admin\PaymentController::store` (`app/Http/Controllers/Admin/PaymentController.php:83-139`) chama `App\Actions\Admin\RegisterPayment`.
- `RegisterPayment` (`app/Actions/Admin/RegisterPayment.php:28-52`) grava `payments` com `gateway = manual`, `status = paid`, `paid_at` (informado ou `now()`) e, opcionalmente, chama `GrantPlan` antes (concessao separada do registro fiscal, ADR-027).
- O pagamento e um registro fiscal e sobrevive a anonimizacao (ADR-028).
- Estorno: `Admin\PaymentController::refund` (`:144-157`) chama `App\Actions\Admin\RefundPayment` (`app/Actions/Admin/RefundPayment.php:26-39`), que muda `paid` para `refunded` e encerra a assinatura vinculada apenas se ela ainda e vigente (`isCurrent()`).
- Nao existe gateway, webhook, `PaymentGateway` ou `webhook_events`; Mercado Pago e F6 (SPEC linha 183, ADR-017).
- Status de pagamento: apenas `paid` e `refunded`. Nao ha `pending`/`approved`/`failed`.
- Nao existe coluna `refunded_at`; o rastro de auditoria fica em `admin_actions` (`payment.registered`, `payment.refunded`).

### 2.2. Destinatario, idioma e dados disponiveis

- `payments.user_id`, `subscriptions.user_id` e `subscription_intents.user_id` apontam para `User`; nao ha cliente/tenant/cobranca separado.
- E-mail da conta: `users.email` (unico campo). Idioma: `users.locale`; `User` implementa `HasLocalePreference` e `preferredLocale()` devolve `locale` ou o fallback `en`.
- `User` usa `SoftDeletes` e tem `anonymized_at`/`isAnonymized()`; a relacao `Payment::user` aplica o global scope, entao fica `null` para conta excluida.
- `Payment` tem `plan()`, `subscription()`; `Format::money()`, `Format::dateTime()` e `Format::date()` formatam no locale corrente da requisicao/job.
- Rotulos de forma de pagamento existem nos 5 idiomas em `lang/*/account.php` (`account.billing.methods.{pix,transfer,cash,other}`).
- Botao de Faturas: `route('dashboard.billing')` (`routes/web.php:104`), sem prefixo de locale.

### 2.3. Infraestrutura de e-mail

- Notifications existentes: `App\Notifications\VerifyEmail`, `ResetPassword` e `SubscriptionIntentSubmitted` (todas `ShouldQueue`, canal `mail`, textos em `lang/{locale}/mail.php`).
- Mailable existente: apenas `App\Mail\NewsletterConfirmation`.
- A base `Illuminate\Notifications\Notification` ja aplica `SerializesModels`; basta `implements ShouldQueue` + `use Queueable`.
- Laravel aplica o locale da conta ao enviar a notificacao (`NotificationSender`/`SendQueuedNotifications`), inclusive no caminho enfileirado.
- Layout unico `notifications::email` com wordmark `devtools_` e `MailBrand::line()` para introducoes com `:app` (DESIGN.md linha 105).
- Fila `database`; worker Supervisor `--tries=2 --timeout=120`; testes com `QUEUE_CONNECTION=sync` e `MAIL_MAILER=array`.
- `TranslationsParityTest` exige as mesmas folhas nos 5 `lang/*/mail.php`.

### 2.4. Documentacao e terminologia

- SPEC secao 7 (linha 161) descreve a venda manual e o e-mail ao admin na submissao da intencao; nao define e-mail de pagamento a conta.
- Glossario tem "Pagamento manual" e "Intencao de assinatura", mas nao "Confirmacao de pagamento".
- ADRs relevantes: ADR-027 (venda manual, concessao e registro separados), ADR-028 (anonimizacao preserva pagamentos), ADR-019 (filas), ADR-026 (idioma da conta = idioma dos e-mails).
- Docs sao escritos em portugues sem acentos; ADRs usam `Status`, `Data`, `Decisao`, `Por que`, `Consequencias`, `Alternativas descartadas`.

### 2.5. Testes e convencoes

- Pest 4, `tests/Feature`, `RefreshDatabase`, `Notification::fake()` + `assertSentTo`.
- `NotificationFake` cobre `ShouldQueue` via `sendNow` e expoe o locale preferido no 4o argumento do callback (validado no vendor).
- `MailLocaleTest` usa o transporte `array` (`app('mailer')->getSymfonyTransport()->messages()`) para ler assunto e corpo renderizados.
- `AdminBillingTest` exercita registro e estorno via HTTP sem `Notification::fake()`; passa a disparar e-mail com fila `sync`.

## 3. Decisoes do grill

| # | Pergunta | Decisao |
|---|---|---|
| Q1 | Destinatario | Conta do pagamento (`payments.user`), e-mail `users.email`, idioma `users.locale` |
| Q2 | Escopo do disparo | Todo pagamento registrado, com ou sem plano/assinatura |
| Q3 | Estorno | Tambem envia e-mail |
| Q4 | Conteudo da confirmacao | Detalhado: valor, forma, data, plano e fim do periodo quando houver; botao Faturas; sem anexo e sem nota do admin |
| Q5 | Mecanismo | Laravel Notifications (`ShouldQueue` + `MailMessage`), nao Mailable |
| Q6 | Ponto de disparo | Dentro das actions `RegisterPayment` e `RefundPayment`, apos persistir |
| Q7 | Falha e anonimizacao | Best-effort: `try/catch` + `report()`; conta anonimizada ou `user` nulo nao recebe |
| Q8 | Conteudo do estorno | Espelhado: valor, forma/data original, data do estorno, plano; linha de assinatura encerrada so quando esta acao a encerrou |
| Q9 | Documentacao | Glossario + SPEC + ADR-044 |

## 4. Lacunas e bordas identificadas

- Conta soft-deleted: `payment->user` e `null`; o envio deve ser pulado.
- Conta anonimizada: `anonymized_at` preenchido; e-mail placeholder `removido+{id}@removido.invalid`; o envio deve ser pulado.
- Estorno repetido: o status nao muda; nao deve sair segundo e-mail.
- Sem `refunded_at`: a data do estorno no e-mail e o `now()` da acao.
- Plano e periodo sao relacoes vivas, nao snapshots do momento do pagamento.
- Falha de e-mail nunca pode desfazer nem impedir o registro fiscal.
