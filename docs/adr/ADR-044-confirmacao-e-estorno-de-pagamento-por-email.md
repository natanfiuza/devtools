# ADR-044: Confirmacao e estorno de pagamento por e-mail

Status: Aceito
Data: 2026-10-20
Relaciona: ADR-027 (venda manual), ADR-028 (anonimizacao preserva pagamento), ADR-019 (filas), ADR-026 (idioma da conta)

## Contexto

O registro e o estorno de pagamento sao atos do admin (`RegisterPayment` e `RefundPayment`, atras de `/admin/payments`) e nao avisavam a conta: o unico e-mail do fluxo era o aviso ao admin quando uma intencao de assinatura era enviada (ADR-034).

O pedido original falava em "usuario solicitante de assinatura", papel que nao existe no dominio. O pedido fica em `subscription_intents`, sem vinculo com `payments`; o pagamento pertence a uma conta (`payments.user`). O destinatario natural e a conta dona do pagamento.

A infraestrutura de e-mail transacional ja existia: Notifications `ShouldQueue` com canal `mail`, textos em `lang/{locale}/mail.php`, layout unico com o wordmark `devtools_` e idioma da conta via `HasLocalePreference` (ADR-026). O pagamento e registro fiscal e sobrevive a anonimizacao (ADR-028); a conta pode estar anonimizada ou soft-deleted.

## Decisao

1. **Duas Notifications na fila**, em `app/Notifications`: `PaymentConfirmed` e `PaymentRefunded`, ambas `ShouldQueue` e canal `mail`, com `MailMessage` sobre o layout `notifications::email`.
2. **Destinatario e a conta do pagamento** (`payments.user`): e-mail `users.email`, idioma `users.locale`. O admin nao recebe copia.
3. **O gatilho vive na action**, depois de persistir:
   - `RegisterPayment` avisa todo pagamento registrado, com ou sem plano/assinatura;
   - `RefundPayment` avisa somente quando o status muda de `paid` para `refunded`; estorno repetido nao envia segundo e-mail.
4. **Conteudo detalhado**:
   - confirmacao: valor, forma de pagamento, data, plano e fim do periodo quando houver, e botao para Faturas;
   - estorno: valor, forma e data do pagamento original, data do estorno, plano, e a linha de encerramento da assinatura **somente** quando foi esta acao que a encerrou;
   - sem anexo, sem nota do admin e sem fim de periodo no estorno.
5. **Best-effort**: o envio fica em `try/catch` com `report()`. Falha de e-mail nunca desfaz nem impede o registro fiscal. Conta anonimizada (`anonymized_at`) ou soft-deleted (`payment->user` nulo) nao recebe.
6. **Sem migration**: nao existe coluna `refunded_at`; a data do estorno no e-mail e o `now()` capturado pela action, e o rastro de auditoria continua em `admin_actions`.
7. **Textos e formato**: bloco `payment.confirmed.*` e `payment.refunded.*` nos cinco `lang/*/mail.php`; rotulos de forma reutilizam `account.billing.methods.*`; dinheiro e datas saem de `App\Support\Format`, no idioma corrente (o da conta no job).
8. **Testes**: `Notification::fake()` para destinatario, idioma, flags e guardas; transporte `array` para conferir o corpo renderizado; `AdminBillingTest` passa a dublar o canal.

## Por que

- **Notification e nao Mailable**: reaproveita fila, idioma da conta, `mail.php` e o layout existente, sem um segundo caminho de e-mail no projeto.
- **Action e nao controller**: a action e o ponto de entrada do dominio. Quando o gateway chegar na F6, o pagamento nascera pelo mesmo caminho e o aviso acompanha sem duplicar regra.
- **Best-effort**: o e-mail e consequencia do registro fiscal, nao condicao dele. Bloquear o admin porque o SMTP falhou inverteria a prioridade.
- **Guardas de anonimizacao e soft delete**: a conta apagada nao deve ser contatada, e o endereco anonimizado e um placeholder invalido.
- **Estorno so na transicao**: evita aviso duplicado e mantem a mensagem verdadeira; a linha de encerramento depende de a assinatura ainda estar vigente.
- **Conteudo detalhado**: serve de comprovante para a pessoa e de referencia no suporte, sem expor campo interno (`note`).

## Consequencias

- Nenhuma migration e nenhuma coluna nova; o estorno continua sendo um estado do pagamento.
- Falha ao enfileirar vira `report()` e a acao segue; falha no worker segue o retry da fila (`--tries=2`) e termina em `failed_jobs` (ADR-019).
- Plano e periodo sao lidos das relacoes vivas (nome e fim atuais), nao de um snapshot do momento do pagamento.
- Conta suspensa continua recebendo o aviso: a politica aprovada exclui apenas anonimizada e excluida.
- Um novo `PaymentMethod` exigira rotulo em `account.billing.methods.*` nos cinco idiomas.
- Testes de faturamento que nao sao de e-mail precisam de `Notification::fake()` para nao enviar de verdade com a fila `sync`.
- `TranslationsParityTest` passa a cobrir as chaves novas automaticamente.
- Dois estornos concorrentes do mesmo pagamento podem duplicar o aviso; a corrida ja existe no `RefundPayment` e nao foi alterada nesta decisao.

## Alternativas descartadas

- **Mailable avulso**: duplicaria fila, idioma e estrutura de layout; a Notification ja resolve tudo.
- **Evento/listener ou observer**: esconderia o disparo de quem le a action e dificultaria o teste do gatilho.
- **Disparar no controller**: o gateway da F6 nao passa pelo controller; a regra ficaria fora do dominio.
- **Avisar tambem o admin**: o destinatario aprovado e a conta; o admin ja tem a tela e a auditoria.
- **Coluna `refunded_at`**: migration sem uso imediato; `admin_actions` ja registra o ato e o e-mail usa o instante da action.
- **Anexar comprovante ou incluir a nota do admin**: expoe observacao interna e aumenta a complexidade do envio.
- **Abortar a acao quando o e-mail falha**: transformaria um aviso em pre-condicao do registro fiscal.
