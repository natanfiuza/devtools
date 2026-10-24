# Survey — Erro HTTP 400 na Doação Pix do Mercado Pago (2026-10-24)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "A pagina de doação com os valor foi exibida, mas esta retornando um erro quando confirmo a doação: `[2026-10-24 18:49:22] local.ERROR: Payments: donation.pix respondeu HTTP 400. {"exception":"[object] (App\\Exceptions\\Payments\\PaymentGatewayException(code: 0): Payments: donation.pix respondeu HTTP 400...`"
- **Tela / Modulo:** Doação Pix (`/{locale}/donate` -> `DonationController::store`, `CreateDonation::dynamic`, `MercadoPagoGateway::createPixDonation`, `PaymentGatewayException`, `config/donations.php`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada, 2 decisões de fronteira, inspeção completa de `CreateDonation`, `MercadoPagoGateway`, `PaymentGatewayException`, `config/donations.php`, `config/mail.php` e testes `DonationTest.php` / `MercadoPagoGatewayTest.php`.
- **Arquivos-chave lidos:** `config/donations.php`, `config/mail.php`, `app/Actions/Donations/CreateDonation.php`, `app/Actions/Payments/StartCheckout.php`, `app/Services/Payments/MercadoPagoGateway.php`, `app/Exceptions/Payments/PaymentGatewayException.php`, `tests/Feature/DonationTest.php`, `tests/Feature/MercadoPagoGatewayTest.php`.

## 2. Decisoes das rodadas

### Rodada 1 — Causa Raiz do HTTP 400 no Pix Dinâmico e Melhorias de Código
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Configuração do ambiente e dados enviados ao Mercado Pago | **Todas as configurações ajustadas conforme recomendado (`APP_ENV=production`, `APP_URL=https://devtools.natanfiuza.dev.br`, `DONATIONS_PAYER_EMAIL` distinto da conta recebedora do Mercado Pago e chave Pix ativa na conta recebedora).** |
| Q2 | Melhorias no código para evitar armadilhas de `payer_email`, `notification_url` e diagnosticar HTTP 400 do Mercado Pago | **Executar A + B + C: (A) Extrair e incluir códigos/causas estruturadas (`cause[].code` / `cause[].description` / `error`) no contexto da `PaymentGatewayException` (acessível no log via `context()` do Laravel, sem alterar `getMessage()` e sem vazar corpo cru ou credenciais); (B) Corrigir o bug de ordem de carregamento de `config('mail.from.address')` em `config/donations.php` e em `CreateDonation::payerEmail()`; (C) Omitir `notification_url` quando a URL resolvida apontar para host local (`localhost`, `127.0.0.1`, `::1`) ou esquema não-HTTPS sem `PAYMENTS_WEBHOOK_URL` explícito, evitando que a API do Mercado Pago recuse a requisição com HTTP 400.** |

### Defaults assumidos (nao contestados)
- O método `getMessage()` de `PaymentGatewayException` permanece `"Payments: {$operation} respondeu HTTP {$status}."` (preservando 100% da compatibilidade com testes existentes e ADR-045), enquanto os códigos de causa estruturados (`gateway_error`, `gateway_causes`) entram via método `context(): array` nativo do handler de exceções do Laravel, aparecendo automaticamente no JSON do `storage/logs/laravel.log`.

## 3. Relatorio de fatos levantados
### Fato 1 — Bug de ordem alfabética em `config/donations.php`
- Em `config/donations.php` (linha 37), estava definido `'payer_email' => env('DONATIONS_PAYER_EMAIL', config('mail.from.address'))`.
- O Laravel carrega os arquivos de `config/` em ordem alfabética (`donations.php` antes de `mail.php`). No instante em que `donations.php` é carregado, `config('mail.from.address')` retorna sempre `null`. Além disso, quando `DONATIONS_PAYER_EMAIL=` existe vazio no `.env`, `env()` retorna `""` e ignora o default.

### Fato 2 — Rejeição HTTP 400 do Mercado Pago por `notification_url` local/HTTP ou `payer.email` igual ao recebedor
- No log apresentado (`[2026-10-24 18:49:22] local.ERROR`), o ambiente estava com `APP_ENV=local` (e `APP_URL=http://localhost:8000` ou HTTP não público). O Mercado Pago exige que `notification_url` em `POST /v1/payments` seja uma URL HTTPS pública válida (não aceita `localhost`/`127.0.0.1` nem `http://`), respondendo `400 Bad Request` (`notification_url must be valid / https`).
- Também responde `400 Bad Request` se `payer.email` for o mesmo e-mail da conta Mercado Pago dona do `MERCADO_PAGO_ACCESS_TOKEN` ou se a conta não tiver chave Pix cadastrada.

### Fato 3 — Observabilidade segura via `PaymentGatewayException::context()` e Descoberta da Causa Real (`code: 23` — `date_of_expiration`)
- No Laravel, qualquer exceção que implemente `public function context(): array` adiciona automaticamente esses metadados estruturados ao JSON do log quando `report($exception)` é chamado, sem poluir `$exception->getMessage()`.
- Graças à instrumentação em `context()`, o log de produção revelou imediatamente `"gateway_causes":[{"code":"23","description":""}]`.
- Na API do Mercado Pago (`POST /v1/payments`), o código **`23`** significa: *"The following parameters must be valid date and format (`yyyy-MM-dd'T'HH:mm:ss.SSSZ` or `yyyy-MM-dd'T'HH:mm:ssz`): `date_of_expiration`"*.
- O método `$request->expiresAt->toIso8601String()` do Carbon em `MercadoPagoGateway` gerava `2026-10-24T21:00:05+00:00` (sem milissegundos `.000`) ou `...Z`, que o parser estrito do Mercado Pago rejeita com `code: 23`. Alterando para `$date->format('Y-m-d\TH:i:s.vP')` (`2026-10-24T21:00:05.000+00:00`), o formato passa a conter obrigatoriamente os 3 dígitos de milissegundos e o offset explícito exigidos pela API do Mercado Pago.

### Fato 4 — Fuso horário `-04:00` retornado pelo Mercado Pago em `date_of_expiration` vs `DATETIME` sem timezone no MySQL/MariaDB
- Após a criação com sucesso do Pix na API do Mercado Pago, a resposta de `POST /v1/payments` devolve `date_of_expiration` no fuso horário padrão dos servidores do Mercado Pago (`-04:00`, ex.: `2026-10-24T20:24:00.000-04:00`, que equivale a `21:24:00` em `America/Recife` `-03:00`).
- Em `MercadoPagoGateway::date()`, `Date::parse($value)` criava uma instância Carbon preservando o fuso `-04:00`. Quando o Eloquent gravava esse objeto na coluna `expires_at` (`DATETIME` sem fuso no MySQL/MariaDB), o Eloquent formatava apenas `Y-m-d H:i:s` (`20:24:00`), descartando o `-04:00`!
- Ao redirecionar para `/pt-br/donate/{ulid}`, o Eloquent lia `20:24:00` do banco assumindo `app.timezone` (`America/Recife`, `-03:00`). Como em Recife já eram `20:24:01` (`-03:00`), `$donation->expires_at->isPast()` dava `true` imediatamente (1 hora antes do real!), exibindo *"O prazo do QR passou e nada foi cobrado"*.
- Convertendo para `->setTimezone((string) config('app.timezone', 'UTC'))` dentro de `MercadoPagoGateway::date()`, tanto `expires_at` quanto `paid_at` (`date_approved`) são normalizados para `America/Recife` antes de irem ao banco.
