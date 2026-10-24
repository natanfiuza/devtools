# Relatório de Tarefa — Correção e Diagnóstico do HTTP 400 na Doação Pix (Mercado Pago)

- **Data:** 2026-10-24
- **Branch:** `develop_natan`
- **Survey:** `docs/survey/20261024_erro_400_doacao_pix_surveyfacts.md`
- **Plano:** `docs/gemini/plans/develop_natan/20261024_develop_natan_erro_400_doacao_pix.md`

---

## 1. Resumo do Problema e Causa Raiz

Ao confirmar uma doação na página `/{locale}/donate`, a aplicação registrava:
```text
[2026-10-24 18:49:22] local.ERROR: Payments: donation.pix respondeu HTTP 400.
```

A inspeção revelou quatro fatores:
1. **Ordem de carregamento de `config/donations.php`:** A linha `'payer_email' => env('DONATIONS_PAYER_EMAIL', config('mail.from.address'))` tentava ler `config('mail.from.address')` antes de `config/mail.php` ser carregado pelo Laravel (ordem alfabética `d` antes de `m`), resultando em `null` ou string vazia quando `DONATIONS_PAYER_EMAIL=` estava vazio no `.env`.
2. **Rejeição da `notification_url` com `localhost` / loopback:** O log mostrava `local.ERROR` no servidor (`/var/www/devtools`). Quando `APP_URL` aponta para `localhost` ou `127.0.0.1` sem `PAYMENTS_WEBHOOK_URL` configurada, o Mercado Pago recusa o `POST /v1/payments` com `HTTP 400 Bad Request` (`notification_url must be valid`).
3. **Bloqueio de autofinanciamento pelo Mercado Pago (`payer.email` == e-mail da conta recebedora):** Quando o usuário testa logado com o mesmo e-mail da conta Mercado Pago dona do `MERCADO_PAGO_ACCESS_TOKEN` (ou configura esse mesmo e-mail em `DONATIONS_PAYER_EMAIL`), a API do Mercado Pago retorna `HTTP 400` (*"The payer cannot be the same as the collector"*).
4. **Falta de contexto estruturado no log e Causa Real revelada (`code: 23` em `date_of_expiration`):**
   - Após instrumentar `PaymentGatewayException::context()`, o log de produção revelou `"gateway_causes":[{"code":"23","description":""}]`.
   - Na API do Mercado Pago (`POST /v1/payments`), o erro `code: 23` é: *"The following parameters must be valid date and format (`yyyy-MM-dd'T'HH:mm:ss.SSSZ`): `date_of_expiration`"*.
   - `MercadoPagoGateway` chamava `$request->expiresAt->toIso8601String()`, que no Carbon formata sem milissegundos (`Y-m-d\TH:i:sP`, ex.: `2026-10-24T21:00:05+00:00`), sendo recusado pelo validador do Mercado Pago. Substituído por `$this->iso8601($date)` usando `$date->format('Y-m-d\TH:i:s.vP')` (`2026-10-24T21:00:05.000+00:00`) tanto em `createPixDonation` quanto em `createPreference`.

---

## 2. Alterações Realizadas

1. **Contexto estruturado em `PaymentGatewayException` e `MercadoPagoGateway`:**
   - Adicionadas as propriedades `gatewayError` e `gatewayCauses` e o método `context(): array` em `PaymentGatewayException`, permitindo que o logger do Laravel grave automaticamente no JSON do `storage/logs/laravel.log` o código e a descrição técnica da causa retornada pelo Mercado Pago, mantendo `getMessage()` inalterado e sem vazar tokens ou dados sensíveis.
2. **Correção do fallback de `payer_email` (`config/donations.php`):**
   - Alterado para `'payer_email' => env('DONATIONS_PAYER_EMAIL') ?: env('MAIL_FROM_ADDRESS')`, evitando a dependência da ordem de carregamento de `config/mail.php` e tratando strings vazias no `.env`.
3. **Proteção contra `notification_url` de loopback (`CreateDonation` e `StartCheckout`):**
   - Quando `PAYMENTS_WEBHOOK_URL` não está definido e a rota `webhooks.mercadopago` resolve para um host de loopback (`localhost`, `127.0.0.1`, `::1`, `0.0.0.0`) fora dos testes automatizados, `webhookUrl()` retorna `null`, omitindo `notification_url` do payload para que a criação do QR Code Pix e do Checkout funcione mesmo em ambiente local sem túnel.
4. **Documentação e Testes:**
   - Atualizada a tabela de Troubleshooting em `docs/guides/mercado-pago.md` com as 4 causas do `HTTP 400` e como inspecionar `gateway_error` e `gateway_causes` no `laravel.log`.
   - Adicionado teste em `tests/Feature/MercadoPagoGatewayTest.php` cobrindo a extração de `gatewayError`, `gatewayCauses` e `context()`.
5. **Normalização de fuso horário (`-04:00` -> `app.timezone`) em `MercadoPagoGateway::date()`:**
   - O Mercado Pago retorna `date_of_expiration` e `date_approved` com offset `-04:00` (horário de Nova York/Manaus). Como a coluna `expires_at` no MySQL/MariaDB é `DATETIME` (sem offset), gravar um Carbon com timezone `-04:00` salvava a hora parede `-04:00` (1 hora atrás de `America/Recife` `-03:00`). Como o prazo do Pix é de 60 minutos, a doação nascia instantaneamente expirada ao ser lida em `America/Recife` (`expires_at->isPast() === true`).
   - Ajustado `MercadoPagoGateway::date()` para chamar `->setTimezone((string) config('app.timezone', 'UTC'))`, garantindo que toda data vinda da API do Mercado Pago seja convertida para o fuso da aplicação antes de ser persistida no banco.
