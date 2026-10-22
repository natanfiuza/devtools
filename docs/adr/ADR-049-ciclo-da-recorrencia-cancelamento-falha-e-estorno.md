# ADR-049: Ciclo da recorrencia - cancelamento, falha e estorno

Status: Aceito
Data: 2026-10-21
Relaciona: ADR-027 (venda manual), ADR-040 (reserva e liquidacao), ADR-045 (Preapproval), ADR-046 (periodos empilhados), ADR-047 (webhooks)

## Contexto

A Preapproval autoriza o provedor a cobrar o cartao todo mes (ou a cada 12 meses). Depois da autorizacao, a vida da assinatura tem quatro momentos que o produto precisa tratar sem mentir para o cliente:

- **cancelamento pelo cliente**: parar de cobrar sem tirar o que ja foi pago;
- **falha de renovacao**: o cartao recusou; a conta continua ate o fim do periodo, mas a pessoa precisa saber;
- **estorno**: devolver o dinheiro e desfazer o que aquele pagamento concedeu;
- **expiracao/limpeza**: checkouts e doacoes pendentes vencidos, eventos antigos.

Ate a F6, o admin concedia plano e o unico cancelamento era encerrar a assinatura local. Com o gateway, toda acao tem dois lados - o provedor e o banco - e a ordem entre eles decide se o estado local pode mentir.

## Decisao

1. **Cancelar nao tira acesso**: `POST /dashboard/billing/cancel-recurring` (`CancelRecurring`) cancela a preapproval no provedor e grava `renewal_canceled_at` nas linhas ativas da mesma preapproval, **sem mudar `status`**. O periodo pago segue ate `current_period_end`; a fatura deixa de esperar renovacao. `404`/`409` (ja cancelada) contam como sucesso.
2. **Gateway primeiro, sempre que o efeito e no provedor**: cancelamento, estorno e troca de plano chamam a API **antes** de mexer no banco. Se o provedor recusar de verdade, nada muda localmente - melhor continuar como estava do que achar que cancelou e levar a cobranca.
3. **Falha de renovacao avisa uma vez por periodo**: `HandleRenewalFailure` grava `renewal_failed_at` na primeira cobranca recusada do periodo e envia `PaymentRenewalFailed` uma unica vez (best-effort, pulando conta anonimizada). Tentativas seguintes do provedor nao geram novo e-mail. A aprovacao seguinte limpa o campo e rearma o aviso para um periodo futuro. Nao ha dunning, bloqueio imediato nem cobranca extra: o acesso segue ate o fim do periodo pago.
4. **A tela reflete o estado**: billing mostra a renovacao cancelada, a falha da ultima tentativa, o checkout pendente e a proxima linha ja paga; o admin ve o mesmo nas telas de assinaturas e de eventos.
5. **Estorno em duas camadas**: `RefundGatewayPayment` chama `POST /v1/payments/{id}/refunds` e, so depois, `RefundPayment`. `404`/`409` de "ja estornado" contam como sucesso. O cancelamento da preapproval vem **depois** do estorno e e best-effort: o dinheiro ja voltou, uma falha ali vai para o log e nao desfaz nada.
6. **Webhook de estorno nao chama a API de novo**: em `refunded`/`charged_back` o provedor ja devolveu; `RefundGatewayPayment` roda com `notifyGateway: false` e so sincroniza o estado local, tentando cancelar a preapproval.
7. **Estorno de periodo futuro nao derruba o atual**: `RefundPayment` encerra a linha vinculada ao pagamento (inclusive futura) e mantem o periodo vigente de outro pagamento. Historico nao e apagado.
8. **Admin estorna pelo mesmo caminho**: `PaymentController@refund` usa `RefundGatewayPayment` quando `gateway != manual` e o `RefundPayment` direto na venda manual. Nao existe estorno de gateway que mude so o banco.
9. **Troca de plano com recorrencia**: a preapproval anterior e cancelada antes (falha aborta a troca); a assinatura local encerra no fim do periodo pago e a nova comeca agora. **Pagar agora** com recorrencia ativa no mesmo plano cancela a preapproval antes de abrir o checkout avulso; **iniciar uma segunda recorrencia no mesmo plano** e bloqueada (ADR-045, tabela do SPEC secao 7).
10. **Vencimento e poda nao tocam dinheiro**: `payments:expire` (cada 5 min) so marca `pending -> expired` em `checkout_sessions` e `donations`; `webhooks:prune --days=30` apaga somente `webhook_events`. Nem pagamento, nem assinatura, nem doacao sao apagados por rotina.
11. **Falha do provedor nao vira sucesso local**: `PaymentGatewayException` nao carrega corpo nem token; a tela mostra erro generico traduzido, o log recebe o report sem segredo e o estado local fica como estava.

## Por que

- **Acesso comprado e acesso pago**: cancelar para a cobranca futura, nao para o que ja foi pago. Cortar na hora puniria quem cancelou no primeiro dia.
- **Ordem provedor-primeiro**: o efeito externo e o que nao da para desfazer. Gravar local primeiro e depois falhar no provedor criaria uma assinatura que o banco diz cancelada e o cartao continua cobrando.
- **Best-effort no cancelamento pos-estorno**: o dinheiro ja voltou; bloquear o estorno por causa de um cancelamento que falhou inverteria a prioridade.
- **Um aviso por periodo**: o provedor pode tentar varias vezes em poucos dias; um e-mail por tentativa viraria spam e treinaria a pessoa a ignorar.
- **Sem dunning**: o produto nao tem cobranca retroativa nem bloqueio progressivo; o periodo pago e o prazo, e a renovacao simplesmente nao acontece.
- **Nada apaga dinheiro por rotina**: registros fiscais e periodos sao a historia financeira; limpeza existe so para estado efemero (checkout, doacao pendente, payload de webhook).
- **Mesmo caminho para admin e webhook**: uma unica acao de estorno evita que o admin e o provedor produzam estados diferentes.

## Consequencias

- `subscriptions` ganha `renewal_canceled_at` e `renewal_failed_at`; a linha cancelada continua `active` ate o fim do periodo, e a leitura (`current()`) segue funcionando.
- Clientes que cancelam e depois voltam abrem um checkout novo; a preapproval antiga ja esta cancelada e nao e reaproveitada.
- Um pagamento estornado no painel some da receita liquida mas permanece no historico; a assinatura vinculada e encerrada.
- A falha de renovacao depende do webhook `subscription_authorized_payment`; sem ele, a tela nao avisa (e o admin ve o evento faltando).
- `payments:expire` e `webhooks:prune` precisam estar agendados no `task_schedules`, senao o estado gravado diverge do que a tela mostra e a tabela de eventos cresce.
- Testes cobrem cancelamento com acesso preservado, falha do gateway sem mudanca local, aviso unico, limpeza na aprovacao, ordem API -> local, estorno duplicado e webhook de estorno.

## Alternativas descartadas

- **Encerrar a assinatura no ato do cancelamento**: simples, mas tira acesso ja pago e gera reembolso que ninguem pediu.
- **Gravar local primeiro e sincronizar depois**: criaria divergencia silenciosa quando o provedor recusasse.
- **E-mail a cada tentativa recusada**: spam; o cliente nao distingue tentativa de falha final.
- **Dunning com bloqueio progressivo**: complexidade e regra comercial que o produto nao tem.
- **Estorno so local, sem API**: o dinheiro nao voltaria.
- **Estorno chamando a API de novo a partir do webhook**: o provedor ja estornou; a segunda chamada seria ruido ou erro.
- **Apagar linhas antigas de assinatura/pagamento na poda**: destruiria a historia fiscal e de faturamento.
