# ADR-059: Consulta sob demanda e diagnostico de doacoes Pix

Status: Aceito
Data: 2026-10-27
Relaciona: ADR-017 (PaymentGateway), ADR-047 (webhooks), ADR-048 (doacoes Pix), ADR-049 (estorno)

## Contexto

Uma doacao Pix em producao apareceu com QR na tela e terminou **recusada** na confirmacao. O sistema nao tinha como explicar o motivo:

- o webhook recebia `status_detail` no `GatewayPayment`, mas gravava apenas `status = failed` e descartava o detalhe;
- `donations` nao guardava o que o provedor respondeu (status e detalhe);
- a pagina de status nao se atualizava e nao oferecia "Ja paguei" - quem pagava e voltava antes do webhook veria `pending` para sempre;
- a mensagem ao doador era sempre o aviso generico de falha;
- o prazo do QR vinha de `(int) env('DONATIONS_EXPIRES_MINUTES', 60)`: uma chave vazia no `.env` devolvia `''`, virava `0` e o piso `max(5, ...)` reduzia o QR a 5 minutos, curto demais para escanear e pagar.

Diagnostico no VPS mostrou `live_mode: true`, `collector_id` correto e todas as cinco doacoes `cancelled`/`expired` exatamente 4m59s depois da criacao - sintoma do prazo de 5 minutos, nao de recusa do pagador. O codigo precisava parar de esconder essa informacao.

## Decisao

1. **Persistir o status do provedor em `donations`**: colunas `gateway_status` (32) e `gateway_status_detail` (64), nullable, gravadas na criacao do Pix dinâmico, no webhook e na consulta sob demanda. Pix estatico fica com ambas `null` - nao ha provedor para consultar.
2. **Um unico caminho de sincronizacao**: `SyncDonationPayment` e o unico efeito local de um `GatewayPayment`; webhook, botao "Ja paguei" e comando passam por ele. A ordem e: gravar status/detalhe cru, tratar estorno, tratar aprovado (`ConfirmDonation` com valor, moeda e `paidAt`) e tratar `rejected`/`cancelled` apenas a partir de `pending`. Doacao ja resolvida nao regride.
3. **Webhook resolve a doacao antes do estorno**: o estorno de uma doacao tambem precisa gravar `status`/`status_detail`, entao o ramo de doacao vem antes do ramo generico de `syncRefund()`.
4. **`ConfirmDonation` aceita `pending` ou `expired`**: alinhado ao ADR-048 §9 - um Pix aprovado depois do vencimento local ainda confirma, porque o dinheiro entrou. O lock e a checagem dentro da transacao continuam sendo a idempotencia; nao ha guarda fora da transacao.
5. **Consulta sob demanda `POST /{locale}/donate/{ulid}/check`** ("Ja paguei"): o ULID e a capacidade, como na pagina de status. Le o pagamento em `GET /v1/payments/{gateway_ref}`, confere `external_reference = don_<ulid>` e so entao sincroniza. Doacao resolvida (`paid`/`refunded`/`failed`) nao gasta chamada; `manual`, sem `gateway_ref` ou sem credencial respondem com aviso. Rate limit nomeado por IP (`donations.check_per_minute`, default 5), com resposta em toast em vez de pagina 429.
6. **Auto-refresh da pagina de status**: `usePoll` a cada `donations.status_poll_ms` (default 15 s, `0` desliga), **somente** `pending` e **somente** banco local (`only: ['donation']`). Ele para quando a doacao sai de `pending` e nao chama o provedor. O polling nao e conciliacao.
7. **Motivo de recusa traduzido**: `DonationFailureReason::fromStatusDetail()` mapeia os codigos conhecidos (`rejected_high_risk`, `rejected_by_bank`/`bank_error`, `rejected_by_regulations`, `rejected_insufficient_data`, `rejected_by_biz_rule`) para chaves de traducao em cinco idiomas; o que nao estiver no mapa cai em `other`. O codigo cru nunca vai em prop publica - so o admin ve `gateway_status`/`gateway_status_detail`.
8. **Diagnostico no VPS**: `php artisan donations:check {ulid}` imprime o snapshot local, o que o provedor respondeu (`status`, `status_detail`, `live_mode`, meio, valor, datas, `collector_id` e e-mail do pagador **mascarado**) e sincroniza pelo mesmo caminho. **Escreve estado**; nao e somente leitura. Pagamento aprovado em doacao ja `failed` e mostrado como anomalia e nao reabre a doacao.
9. **PII fora do log**: o e-mail do pagador so existe no DTO de diagnostico (`GatewayPayment::payerEmail`) e no comando, mascarado. Nunca em log, prop Inertia, payload de webhook ou resposta HTTP.
10. **Prazo do QR endurecido**: `max(30, (int) (env('DONATIONS_EXPIRES_MINUTES') ?: 60))` - chave vazia cai em 60, e o piso de 30 impede um prazo curto demais. O webhook continua sendo o **unico caminho automatico** de confirmacao.

## Por que

- **O detalhe e o diagnostico**: `status` diz que falhou; `status_detail` diz por que. Sem ele, toda recusa vira a mesma mensagem e o suporte nao distingue banco, risco ou prazo.
- **Um caminho so**: webhook, botao e comando precisam contar a mesma historia. Duas implementacoes divergiriam no primeiro caso de borda - foi o que aconteceu com o estorno de doacao, que pulava a persistencia do detalhe.
- **Servidor traduz, cliente nao decide**: o codigo cru e o vocabulario do provedor. Traduzir no servidor mantem a regra em um lugar e impede que o codigo apareca para o doador.
- **Botao e comando, nao conciliador**: a confirmacao automatica continua sendo o webhook (ADR-047). O botao resolve o caso "paguei e a tela ainda nao sabe" sem criar um segundo processo que consome a API sem parar; o comando resolve o mesmo no VPS, com mais contexto.
- **Polling de banco**: reler a doacao a cada 15 s e barato e nao depende do provedor. Polling chamando a API gastaria cota e criaria uma conciliacao disfarcada de atualizacao de tela.
- **Rate limit por IP**: sem ele, o botao vira porta para consultar pagamentos em massa. A chave nomeada evita o contorno trivial de trocar o prefixo de idioma do `throttle:10,1`.
- **Recusa de conta nao e bug de codigo**: `rejected_by_bank`, `rejected_high_risk` e `rejected_by_regulations` exigem acao no banco do pagador ou na conta Mercado Pago. O dever do sistema e explicar, nao reverter.

## Consequencias

- `donations` ganhou duas colunas nullable; migration retrocompativel (codigo antigo as ignora), sem indice e sem backfill.
- Toda doacao passa a ter rastro do provedor mesmo quando o webhook chega fora de ordem ou repetido.
- Uma recusa pode ser explicada ao doador sem expor o codigo cru; o admin continua vendo o codigo para investigar.
- O botao gasta uma chamada ao provedor por clique e tem teto por IP; o comando nao tem teto proprio (e uso de operador).
- O polling nao substitui o webhook: sem notificacao, a tela continua `pending` ate o botao ou o comando.
- `failed` continua terminal: um pagamento aprovado encontrado depois disso e anomalia de dados, visivel no comando e no admin, sem reabertura automatica.
- Testes cobrem criacao, webhook, botao, comando, rate limit, motivo traduzido, polling e admin.

## Alternativas descartadas

- **Expor `status_detail` cru ao doador**: seria diagnostico sem traducao, com codigo em ingles no meio da pagina publica e sem utilidade para quem doou.
- **Conciliador em background**: um job varrendo doacoes pendentes chamaria a API sem parar, consumiria cota e criaria um segundo caminho de confirmacao concorrente com o webhook.
- **Polling da tela chamando o gateway**: daria a sensacao de tempo real, mas transformaria a pagina publica em fonte de trafego para a API e esconderia a falta do webhook.
- **Botao sem limite**: um script poderia consultar qualquer `gateway_ref` em massa; o ULID sozinho nao e barreira suficiente.
- **Gravar o detalhe so no webhook**: a doacao criada e nunca notificada nunca teria detalhe; a consulta sob demanda e o comando precisam do mesmo registro.
- **Reabrir `failed` no comando**: mascararia anomalia de dados e criaria uma transicao `failed -> paid` que o ADR-048 nao preve.
