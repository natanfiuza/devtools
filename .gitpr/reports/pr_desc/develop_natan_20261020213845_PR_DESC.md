# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: notifica confirmação e estorno de pagamento por e-mail
```

---

## 🎯 Resumo

Quem paga (ou tem o pagamento estornado) passa a ser avisado por e-mail. Antes, o admin registrava um pagamento ou aplicava um estorno e a conta dona do pagamento só descobria o ocorrido ao entrar em "Faturas" — o que gerava dúvidas de cobrança, pedidos de suporte e contestação de estornos não comunicados.

Esta mudança implementa a ADR-044: o aviso é disparado pela própria action de faturamento, no idioma da conta, e é **best-effort** — o registro fiscal é gravado antes do disparo e uma falha de e-mail não desfaz o pagamento nem derruba o admin.

## 🛠️ Mudanças Técnicas

- Adiciona as notificações `PaymentConfirmed` e `PaymentRefunded`, ambas `ShouldQueue`, com canal `mail` e conteúdo localizado (valor, método, datas, plano e link para o dashboard de faturamento).
- `RegisterPayment` passa a disparar `PaymentConfirmed` após criar o pagamento, retornando o modelo criado.
- `RefundPayment` passa a disparar `PaymentRefunded`, com a flag `canceledSubscription` indicando se o estorno encerrou a assinatura — evitando afirmar que a assinatura terminou quando ela já estava encerrada. A data do estorno é capturada na action, já que não existe coluna `refunded_at`.
- Envio protegido por `try/catch` com `report()`: falhas são registradas, não propagadas.
- Contas anonimizadas ou excluídas não recebem aviso (o e-mail é enviado ao titular do pagamento, nunca ao admin).
- Estorno repetido não reenvia o e-mail: o status só muda na primeira vez.
- Adiciona as chaves `mail.payment.*` nos idiomas `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Corrige o docblock de `Payment::$user` para `User|null`.
- Novo `tests/Feature/PaymentNotificationsTest.php` cobre destinatário, idioma, renderização condicional e resiliência a falha do dispatcher; `AdminBillingTest` passa a usar `Notification::fake()` para isolar os testes de faturamento.

## ⚠️ Impacto/Avisos

- **Sem alterações de schema**: nenhuma migration nova. A data de estorno é o instante capturado em memória pela action, não persistida.
- **Sem novas variáveis de ambiente ou dependências.**
- **Requer fila ativa**: as notificações são `ShouldQueue`; sem worker rodando os e-mails ficam pendentes. Falha no enfileiramento é silenciosa para o usuário admin (apenas logada).
- **Conteúdo em 5 idiomas**: novas chaves de tradução precisam ser mantidas em sincronia em futuras alterações.
- **Privacidade/LGPD**: o disparo respeita `anonymized_at` e contas removidas — comportamento coberto por testes.
- Documentação afetada: `docs/SPEC.md`, `docs/glossario.md` e ADR-044.

close #50

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_13_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)