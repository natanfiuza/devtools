# ADR-046: Periodos empilhados e a assinatura que cobre agora

Status: Aceito
Data: 2026-10-21
Relaciona: ADR-027 (venda manual), ADR-034 (intencao de assinatura), ADR-040 (reserva e liquidacao), ADR-045 (Checkout Pro e Preapproval)

## Contexto

Ate a F6, `subscriptions` tinha uma linha por concessao e a leitura considerava "vigente" a ultima linha ativa sem periodo vencido. O admin concedia plano e a linha era esticada; renovar era uma decisao manual. Com o gateway, o pagamento pode chegar **antes** do fim do periodo atual (renovacao antecipada) e tambem **depois** (renovacao atrasada), e a cota e contada dentro do periodo da assinatura.

Esticar a linha existente a cada pagamento tinha dois efeitos ruins:

- o inicio do periodo se perdia e a franquia de tokens reiniciava no meio do ciclo;
- um pagamento antecipado virava "periodo atual" na leitura, mesmo sem valer agora.

Alem disso, a regra de "qual assinatura vale" precisava ser a mesma para cota, plano efetivo, fatura e painel - e nao uma por tela.

## Decisao

1. **Um periodo pago = uma linha de `subscriptions`**. Renovacao e concessao de mesmo plano criam linha nova; a linha anterior nunca e esticada.
2. **Empilhamento**: `OpenSubscriptionPeriod` procura o maior `current_period_end` futuro daquele plano e comeca o novo periodo ali. Sem periodo futuro, comeca agora. O fim sai de `Plan::periodEndFrom()` com `addMonthNoOverflow()`/`addYearNoOverflow()` - 31/01 vira o ultimo dia do mes seguinte, e 29/02 em ano bissexto nao escorrega para marco.
3. **Renovacao atrasada** (o pagamento chega depois do fim): o periodo comeca em `now()`, porque nao ha periodo futuro para empilhar. O buraco entre o fim antigo e o novo nao e cobrado.
4. **A assinatura vigente e a que cobre agora**: `Subscription::isCurrent()`/`scopeCurrent()` exigem status ativo e `current_period_start <= now() < current_period_end` (inicio e fim nulos contam como abertos). `User::activeSubscription()` ordena por `current_period_start desc, id desc`.
5. **`User::nextSubscription()`** devolve a proxima linha futura, para fatura e billing mostrarem renovacao ja paga sem confundi-la com a vigente.
6. **`GrantPlan` reescrito sobre as mesmas actions**: Free ou plano diferente cancelam tudo e comecam agora; mesmo plano cria linha nova com `gateway = manual`; se a linha atual tiver preapproval, o cancelamento no provedor vem antes de assumir (falha aborta sem mudanca local).
7. **Cota por periodo**: `Quota` continua calculando do ledger, mas a janela e o periodo da assinatura que cobre agora. Uma linha futura nao abre franquia nova nem consome a atual.
8. **Painel e faturas distinguem atual e proxima**: `BillingController` devolve as duas; o admin ve o periodo em `admin/subscriptions`, a fatura mostra as linhas.

## Por que

- **Honestidade da franquia**: cada pagamento compra um ciclo com inicio e fim proprios; reiniciar a cota no meio do ciclo por causa de uma renovacao antecipada seria entregar duas franquias por um pagamento.
- **Leitura unica**: `activeSubscription()` e o unico ponto que decide "o que vale agora"; cota, plano efetivo e telas leem dai, e nao cada uma com sua regra.
- **Fila de periodos**: uma linha por periodo deixa o historico auditavel, o estorno cancelar a linha certa e a renovacao atrasada nao reescrever o passado.
- **`NoOverflow`**: datas de cobranca reais caem em 31/01 e 29/02; a regra de calendario precisa ser a mesma que o provedor usa.
- **Sem coluna de estado "expirada"**: o vencimento e resolvido na leitura, como ja era (glossario: Assinatura), e nao exige job para trocar estado.

## Consequencias

- `subscriptions` passa a ter mais linhas por conta; consultas de admin e billing ordenam por periodo, nao por `id` isolado.
- `GrantPlan` mudou de comportamento: conceder o mesmo plano **nao** estica o periodo, cria o proximo. Testes de `BillingTest`, `QuotaTest`, `AdminBillingTest` e `AccountPanelTest` foram atualizados.
- `RefundPayment` cancela a linha vinculada quando ela ainda vale (inclusive futura), sem apagar historico; o periodo atual de outro pagamento nao cai.
- Renovacao atrasada deixa um intervalo sem assinatura; a conta volta ao Free nesse intervalo, e o novo periodo comeca na data do pagamento.
- Data de inicio futura e uma linha `active` que ainda nao vale: qualquer tela que liste "assinaturas ativas" precisa usar `current()` para nao mostra-la como vigente.
- Testes de periodo cobrem 31/01, 29/02, virada de ano, periodo futuro, renovacao antecipada e atrasada (`SubscriptionPeriodTest`).

## Alternativas descartadas

- **Esticar a linha existente**: simples, mas perde o inicio do ciclo e reinicia a franquia no meio; foi o comportamento antigo.
- **Uma linha por conta com `renewals` filhas**: mais normalizado, mas toda leitura passaria a fazer join e o estorno teria que escolher a renovacao certa dentro da linha.
- **Coluna `status = expired` gravada por job**: exige varredura e cria janela de leitura errada entre o vencimento e o job; a leitura ja resolve.
- **Somar periodos no ato do pagamento e guardar so o fim**: perde o historico de ciclos e a data de cada cobranca no tempo.
- **`addMonth()` simples**: 31/01 vira 03/03 e a cobranca escorrega; o provedor usa calendario, nao dias corridos.
