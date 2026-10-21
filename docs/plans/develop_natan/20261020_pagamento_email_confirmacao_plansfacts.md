# Plano - E-mail de confirmacao e de estorno de pagamento

Sessao de planejamento e grill (`grill-with-docs`). Branch `develop_natan`.
Tres rodadas de perguntas (**Q1 a Q4**, **Q5 a Q8** e **Q9**), todas respondidas pelo usuario.
Fatos levantados em `docs/survey/20261020_pagamento_email_confirmacao_surveyfacts.md`.
Plano de implementacao (CLAUDE.md): `docs/claude-code/plans/develop_natan/20261020_develop_natan_pagamento_email_confirmacao.md`.

## Contexto

Quando um pagamento e registrado, a conta que pagou deve receber um e-mail de confirmacao. O pedido original falava em "usuario solicitante de assinatura", mas esse papel nao existe no dominio: pagamento e assinatura pertencem a uma conta (`User`) e o pedido fica em `subscription_intents`, sem vinculo com o pagamento. A conta do pagamento e o destinatario.

O escopo aprovado inclui tambem o estorno: a mesma historia tem duas metades, e o aviso de que o dinheiro voltou e a assinatura terminou deve chegar a conta.

Hoje o registro so acontece manualmente pelo admin (`RegisterPayment`) e o estorno (`RefundPayment`); nao ha gateway (F6). Nenhum e-mail e enviado a conta nesses dois atos.

## Decisoes (tres rodadas de grill)

| # | Pergunta | Decisao |
|---|---|---|
| Q1 | Destinatario | Conta do pagamento (`payments.user`): `users.email`, idioma `users.locale` |
| Q2 | Escopo do disparo | Todo pagamento registrado, com ou sem plano/assinatura |
| Q3 | Estorno | Tambem envia e-mail |
| Q4 | Conteudo da confirmacao | Detalhado: valor, forma, data, plano e fim do periodo quando houver; botao Faturas; sem anexo e sem nota do admin |
| Q5 | Mecanismo | Laravel Notifications (`ShouldQueue` + `MailMessage`), nao Mailable |
| Q6 | Ponto de disparo | Dentro das actions `RegisterPayment` e `RefundPayment`, apos persistir |
| Q7 | Falha e anonimizacao | Best-effort: `try/catch` + `report()`; conta anonimizada ou `user` nulo (soft delete) nao recebe |
| Q8 | Conteudo do estorno | Espelhado: valor, forma/data original, data do estorno, plano; linha de assinatura encerrada so quando esta acao a encerrou |
| Q9 | Documentacao | Glossario + SPEC + ADR-044 |

## Arquivos a criar ou modificar

### 1. Notificacoes

| Arquivo | Mudanca |
|---|---|
| `app/Notifications/PaymentConfirmed.php` | **Novo**: `ShouldQueue`, canal `mail`, `MailMessage` com saudacao, intro, valor, forma, data, plano e fim do periodo quando houver, botao Faturas |
| `app/Notifications/PaymentRefunded.php` | **Novo**: `ShouldQueue`, canal `mail`, `MailMessage` com valor estornado, forma/data original, data do estorno, plano e linha de assinatura encerrada somente quando `canceledSubscription` |

### 2. Actions

| Arquivo | Mudanca |
|---|---|
| `app/Actions/Admin/RegisterPayment.php` | Apos criar o pagamento, `notifyConfirmation()` best-effort: pula conta anonimizada ou `user` nulo, `try/catch` + `report()` |
| `app/Actions/Admin/RefundPayment.php` | Captura `$wasPaid` e `$canceledSubscription`; notifica somente quando o status muda de `paid` para `refunded`, com o mesmo tratamento best-effort |

### 3. Traducoes

| Arquivo | Mudanca |
|---|---|
| `lang/{en,pt_BR,pt_PT,fr,es}/mail.php` | Bloco `payment.confirmed.*` e `payment.refunded.*` com as mesmas folhas nos 5 idiomas (paridade) |

### 4. Testes

| Arquivo | Mudanca |
|---|---|
| `tests/Feature/PaymentNotificationsTest.php` | **Novo**: envio a conta, locale, renderizacao em `fr`, plano/periodo presentes e ausentes, anonimizada, soft delete, estorno `paid -> refunded`, assinatura ja encerrada, estorno repetido, render do estorno, best-effort |
| `tests/Feature/AdminBillingTest.php` | `Notification::fake()` no `beforeEach` para isolar os testes existentes de faturamento |

### 5. Documentacao

| Arquivo | Mudanca |
|---|---|
| `docs/SPEC.md` | Secao 7: registro manual e estorno avisam a conta por e-mail, no idioma dela (ADR-044); conta anonimizada/excluida nao recebe |
| `docs/glossario.md` | Nova entrada `Confirmacao de pagamento` |
| `docs/adr/ADR-044-confirmacao-e-estorno-de-pagamento-por-email.md` | **Novo**: decisao, por que, consequencias e alternativas descartadas |

## Verificacao Planejada

1. `php artisan test tests/Feature/PaymentNotificationsTest.php`
2. `php artisan test tests/Feature/AdminBillingTest.php tests/Feature/TranslationsParityTest.php`
3. `composer test` (Pint, PHPStan e Pest)
4. `npm run test` (Vitest)
5. Conferencia manual com `MAIL_MAILER=log`: registrar e estornar em `/admin/payments`, ler o e-mail em `storage/logs/laravel.log`, testar conta com locale `fr`.

Sem commit e sem push: o usuario commita pelo gitpr.
