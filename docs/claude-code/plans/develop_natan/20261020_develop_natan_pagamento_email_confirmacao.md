# Plano de implementação — E-mail de confirmação e de estorno de pagamento

- **Data:** 2026-10-20
- **Branch:** `develop_natan`
- **Referência:** `docs/survey/20261020_pagamento_email_confirmacao_surveyfacts.md`, `docs/plans/develop_natan/20261020_pagamento_email_confirmacao_plansfacts.md`, ADR-027, ADR-028, ADR-044

## Contexto

O pedido é enviar um e-mail de confirmação quando um pagamento é registrado. Não existe "usuário solicitante de assinatura" no domínio: o pagamento pertence a uma conta (`payments.user`) e a intenção de assinatura (`subscription_intents`) não tem vínculo com ele. O destinatário é a conta do pagamento. O escopo aprovado inclui também o estorno.

## Decisões fechadas no grill

| # | Decisão |
|---|---|
| Q1 | Destinatário: conta do pagamento (`users.email`), idioma da conta (`users.locale`) |
| Q2 | Todo pagamento registrado dispara, com ou sem plano/assinatura |
| Q3 | Estorno também envia e-mail |
| Q4 | Confirmação detalhada: valor, forma, data, plano e fim do período quando houver; botão Faturas; sem anexo e sem nota do admin |
| Q5 | Laravel Notifications (`ShouldQueue` + `MailMessage`), não Mailable |
| Q6 | Disparo dentro de `RegisterPayment` e `RefundPayment`, após persistir |
| Q7 | Best-effort: `try/catch` + `report()`; conta anonimizada ou soft-deleted não recebe |
| Q8 | Estorno espelhado; linha de assinatura encerrada só quando a ação a encerrou |
| Q9 | Glossário + SPEC + ADR-044 |

## Blocos de execução

1. **Artefatos**: salvar survey em `docs/survey/` e planos em `docs/plans/develop_natan/` e `docs/claude-code/plans/develop_natan/`.
2. **Traduções**: adicionar `payment.confirmed.*` e `payment.refunded.*` nos cinco `lang/*/mail.php`, com paridade de folhas.
3. **Notificações**: criar `App\Notifications\PaymentConfirmed` e `App\Notifications\PaymentRefunded`; usar `MailBrand::line()` para a introdução, `Format::money/dateTime/date`, rótulos `account.billing.methods.*` e `route('dashboard.billing')`.
4. **Actions**: `RegisterPayment` e `RefundPayment` notificam após persistir, com `try/catch` + `report()`; guardas para `user` nulo e `isAnonymized()`; estorno só notifica quando `$wasPaid` e passa o flag `canceledSubscription`.
5. **Testes**: criar `tests/Feature/PaymentNotificationsTest.php`; adicionar `Notification::fake()` ao `beforeEach` de `AdminBillingTest`.
6. **Documentação**: atualizar `docs/SPEC.md` e `docs/glossario.md`; criar `docs/adr/ADR-044-confirmacao-e-estorno-de-pagamento-por-email.md`.
7. **Verificação**: `php artisan test` (arquivos novos e afetados), `composer test`, `npm run test`; conferência manual com `MAIL_MAILER=log`.
8. **Relatório**: `docs/claude-code/reports/develop_natan/20261020_develop_natan_pagamento_email_confirmacao.md`. Sem commit e sem push.

## Critérios de aceite

- Registrar um pagamento em `/admin/payments` gera e-mail para a conta, no idioma da conta, com valor, forma, data e, quando houver, plano e fim do período.
- Estornar um pagamento `paid` gera e-mail; estorno repetido não gera segundo e-mail.
- Conta anonimizada ou soft-deleted não recebe e-mail, mas o pagamento/estorno é persistido normalmente.
- Falha de enfileiramento não derruba a ação administrativa.
- `TranslationsParityTest`, `PaymentNotificationsTest` e `AdminBillingTest` passam; `composer test` e `npm run test` verdes.
