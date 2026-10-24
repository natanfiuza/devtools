# Plano de Implementação — Correção e Diagnóstico do HTTP 400 na Doação Pix (Mercado Pago)

- **Data:** 2026-10-24
- **Branch:** `develop_natan`
- **Survey de Referência:** `docs/survey/20261024_erro_400_doacao_pix_surveyfacts.md`

---

## 1. Objetivo

Corrigir as causas de erro `HTTP 400` ao criar cobranças Pix dinâmicas (`POST /v1/payments`) e sessões de checkout no Mercado Pago, resolvendo:
1. O bug de fallback de `payer_email` em `config/donations.php` e `CreateDonation`.
2. O envio de `notification_url` inválida para a API do Mercado Pago quando a aplicação roda sob `localhost` / `127.0.0.1` (sem `PAYMENTS_WEBHOOK_URL` configurada).
3. A falta de contexto técnico seguro nos logs quando o Mercado Pago responde `4xx`/`5xx`, expondo `gateway_error` e `gateway_causes` via `PaymentGatewayException::context()`.

---

## 2. Etapas de Implementação

### Etapa 1: Contexto estruturado seguro em `PaymentGatewayException` e `MercadoPagoGateway`
- **Arquivos:**
  - `app/Exceptions/Payments/PaymentGatewayException.php`
  - `app/Services/Payments/MercadoPagoGateway.php`
- **O que fazer:**
  - Adicionar em `PaymentGatewayException` as propriedades opcionais `public readonly ?string $gatewayError = null` e `public readonly array $gatewayCauses = []`, além do método `public function context(): array` que retorna `operation`, `status_code`, `gateway_error` e `gateway_causes` (quando presentes) para o logger do Laravel, mantendo `$this->getMessage()` intacto (`"Payments: {$operation} respondeu HTTP {$status}."`).
  - Em `MercadoPagoGateway::send()`, quando `! $response->successful()`, extrair de forma segura (sem nunca incluir tokens ou dados de pagador) o código `error` (string curta) e a lista `cause` (`code` e `description` truncados em 120 caracteres) do JSON de erro do Mercado Pago e passá-los para `PaymentGatewayException::fromStatus()`.

### Etapa 2: Correção do fallback de `payer_email` em `config/donations.php` e `CreateDonation`
- **Arquivos:**
  - `config/donations.php`
  - `app/Actions/Donations/CreateDonation.php`
- **O que fazer:**
  - Em `config/donations.php`, alterar `'payer_email' => env('DONATIONS_PAYER_EMAIL')` (sem chamar `config('mail.from.address')` durante o bootstrap de `config/donations.php`, pois `donations.php` carrega antes de `mail.php` em ordem alfabética).
  - Em `CreateDonation::payerEmail()`, buscar na ordem:
    1. `$donation->email` (se preenchido);
    2. `config('donations.payer_email')` (se string não vazia);
    3. `config('mail.from.address')` (se string não vazia e diferente do placeholder inválido `hello@example.com` caso haja outro, ou mantendo como fallback válido se configurado).

### Etapa 3: Validação de `notification_url` pública em `MercadoPagoGateway`
- **Arquivo:**
  - `app/Services/Payments/MercadoPagoGateway.php`
- **O que fazer:**
  - Criar método privado `notificationUrl(?string $url): ?string` em `MercadoPagoGateway` que verifica se a URL é aceita pela API do Mercado Pago (deve ser uma URL válida cujo host **não** seja `localhost`, `127.0.0.1` ou `::1`).
  - Se o ambiente for local (`http://localhost:8000`) e o desenvolvedor não tiver configurado um túnel em `PAYMENTS_WEBHOOK_URL`, `MercadoPagoGateway` omite o campo `notification_url` do payload enviado ao Mercado Pago (ou mantém quando for um host real como `app.test` nos testes ou `devtools.natanfiuza.dev.br` em produção), evitando que o Mercado Pago rejeite a criação do Pix/Checkout com `HTTP 400 Bad Request` (`notification_url must be valid`).

### Etapa 4: Atualização do Guia `docs/guides/mercado-pago.md` e Testes
- **Arquivos:**
  - `docs/guides/mercado-pago.md`
  - `tests/Feature/MercadoPagoGatewayTest.php`
  - `tests/Feature/DonationTest.php`
- **O que fazer:**
  - Documentar na tabela de Troubleshooting de `docs/guides/mercado-pago.md` as 4 causas exatas do `HTTP 400` em `donation.pix`:
    1. E-mail do pagador (`payer.email` ou `DONATIONS_PAYER_EMAIL`) igual ao e-mail da conta recebedora do Mercado Pago (autofinanciamento bloqueado pelo MP);
    2. Conta recebedora sem chave Pix ativa cadastrada no Mercado Pago;
    3. `APP_URL` apontando para `localhost` ou sem HTTPS válido;
    4. Como ler os campos `gateway_error` e `gateway_causes` que agora aparecem no `storage/logs/laravel.log`.
  - Adicionar testes unitários/feature garantindo que `PaymentGatewayException::context()` popula os códigos de causa sem alterar `getMessage()`, que `localhost` é omitido em `notification_url`, e que o fallback de `payer_email` funciona.
