# Survey — Guia de Integração do Mercado Pago (2026-10-24)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** Criar um tutorial de como integrar o Mercado Pago (o que precisa ser configurado, se precisa instalar algum SDK, salvar em `docs/guides/` e responder se o Mercado Pago pode ser usado para receber doação por PIX), além de atualizar a regra de acentuação em `GEMINI.md` e `docs/SPEC.md` para que a documentação em `docs/` passe a ser escrita com acentuação normal em português.
- **Tela / Módulo:** Pagamentos, Assinaturas, Doações e Webhooks (`/subscribe`, `/{locale}/donate`, `/dashboard/billing`, `/webhooks/mercadopago`, `/admin/payments`, `/admin/webhooks`, `app/Services/Payments/MercadoPagoGateway.php`, `app/Actions/Payments/*`, `app/Actions/Donations/*`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 2 rodadas, 4 decisões (Q1, Q2, Q3 e confirmação Q3.1), inspeção autônoma de código, configs e ADRs.
- **Arquivos-chave lidos:**
  - `config/payments.php`, `config/donations.php`, `.env.example`, `composer.json`, `package.json`
  - `routes/webhooks.php`, `app/Http/Controllers/Webhooks/MercadoPagoWebhookController.php`, `app/Support/Payments/MercadoPagoSignature.php`
  - `app/Contracts/Payments/PaymentGateway.php`, `app/Services/Payments/MercadoPagoGateway.php`
  - `app/Actions/Payments/StartCheckout.php`, `app/Actions/Payments/HandleWebhookEvent.php`
  - `app/Actions/Donations/CreateDonation.php`, `app/Actions/Donations/ConfirmDonation.php`, `app/Support/Payments/PixPayload.php`, `app/Support/Payments/PixQrCode.php`, `app/Http/Controllers/DonationController.php`
  - `docs/SPEC.md`, `docs/RUNBOOK.md`, `docs/ARQUITETURA.md`, `docs/API.md`, `docs/acceptance/FASE-6.md`
  - `docs/adr/ADR-045-gateway-mercado-pago-checkout-pro-e-preapproval.md`, `ADR-047-webhooks-assinatura-idempotencia-e-retencao.md`, `ADR-048-doacoes-pix-dinamico-e-estatico.md`, `ADR-049-ciclo-da-recorrencia-cancelamento-falha-e-estorno.md`
  - `docs/guides/google-auth.md`, `docs/guides/github-auth.md`, `docs/guides/google-analytics.md`, `docs/guides/cron.md`

## 2. Decisões das rodadas

### Rodada 1 — Estrutura, escopo e convenções do guia
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Nome do arquivo e referências cruzadas | **`docs/guides/mercado-pago.md` + adicionar links em `docs/RUNBOOK.md`, `docs/SPEC.md` (seção 10) e `README.md`** |
| Q2 | Escopo do tutorial | **Completo (Operação no painel Mercado Pago + Arquitetura sem SDK + Doação Pix Dinâmico vs Estático + Variáveis `.env` + Sandbox/Túnel HTTPS/Smoke + Troubleshooting e Rotação de chaves)** |
| Q3 | Convenção de acentuação | **Atualizar `GEMINI.md` e `docs/SPEC.md` para que a documentação do repositório (`docs/`) use acentuação correta em português** |

### Rodada 2 — Confirmação da regra de acentuação
| # | Decisão | Resolução |
|---|---|---|
| Q3.1 | Aplicação imediata da acentuação no novo guia | **Confirmado (Opção A): alterar a regra em `GEMINI.md` e `docs/SPEC.md` e escrever `docs/guides/mercado-pago.md`, survey, plano e relatório com acentuação normal em português** |

### Defaults assumidos (não contestados)
- Nenhum código de runtime PHP/Vue precisa ser alterado (a integração da Fase 6 já está completa e testada na suíte).
- Nenhum segredo real deve constar na documentação (apenas placeholders seguros como `APP_USR-...`).
- Não é necessário novo ADR nem novo termo no glossário, pois toda a modelagem de domínio de pagamentos e doações já está consolidada nas ADRs 045 a 049 e em `docs/glossario.md`.

## 3. Relatório de fatos levantados

### Fato 1 — Zero SDK externo para o Mercado Pago
- O projeto **não utiliza** o SDK oficial PHP (`mercadopago/dx-php`) nem o SDK JS (`@mercadopago/sdk-js`).
- A comunicação é feita por `App\Services\Payments\MercadoPagoGateway` usando o cliente HTTP nativo do Laravel (`Illuminate\Support\Facades\Http`), com `connect_timeout` de 5 s, `timeout` de 20 s, `Bearer Token` (`MERCADO_PAGO_ACCESS_TOKEN`) e cabeçalho `X-Idempotency-Key` determinístico por operação (`chk_<ulid>`, `don_<ulid>`, `refund_<id>`, `cancel_<id>`).
- Como o checkout avulso usa **Checkout Pro** por redirecionamento (`init_point`) e a recorrência usa **Preapproval** também por redirecionamento (`init_point`), nenhuma tag `<script>` do Mercado Pago entra no navegador, mantendo a Content-Security-Policy (CSP por nonce, ADR-052) intacta.
- O único pacote auxiliar relacionado a pagamento no `composer.json` é `bacon/bacon-qr-code` (`^3.0`), já instalado e usado por `App\Support\Payments\PixQrCode` exclusivamente para desenhar o QR Code SVG do Pix estático (fallback sem credenciais).

### Fato 2 — Doação por PIX no Mercado Pago (Dinâmico vs Estático)
- A rota `/{locale}/donate` suporta recebimento de doação por PIX pelo Mercado Pago de forma nativa (`CreateDonation` + `ConfirmDonation`, ADR-048):
  1. **Modo Online (Pix Dinâmico via Mercado Pago):** Quando `MERCADO_PAGO_ACCESS_TOKEN` está preenchido no `.env`, `CreateDonation` chama `POST https://api.mercadopago.com/v1/payments` com `payment_method_id = "pix"`, `external_reference = "don_<ULID>"`, `notification_url` e `date_of_expiration` (padrão 60 min). O Mercado Pago devolve `qr_code` (Copia e Cola EMV), `qr_code_base64` (imagem PNG do QR) e `ticket_url`. Quando o doador paga, o webhook `POST /webhooks/mercadopago` recebe o evento `payment`, busca o pagamento na API (`GET /v1/payments/{id}`), valida valor e moeda, muda o status de `pending` para `paid` e dispara o recibo `DonationReceipt` (se o doador informou e-mail e `DONATIONS_SEND_RECEIPTS=true`).
  2. **Armadilhas críticas do Pix Dinâmico no Mercado Pago:**
     - A conta Mercado Pago recebedora **precisa ter uma chave Pix cadastrada** no próprio Mercado Pago (sem chave Pix ativa na conta MP, a API `/v1/payments` recusa a criação de cobranças `payment_method_id: "pix"`).
     - A API do Mercado Pago exige `payer.email` em toda chamada a `POST /v1/payments`. Como o site permite doação anônima (sem e-mail), `CreateDonation` usa `config('donations.payer_email')` (`DONATIONS_PAYER_EMAIL`, que tem fallback para `MAIL_FROM_ADDRESS`). Em produção, `DONATIONS_PAYER_EMAIL` (ou `MAIL_FROM_ADDRESS`) deve ser um e-mail válido e diferente de vazio, senão doações anônimas falham com `DonationException::payerMissing()`.
  3. **Modo Contingência (Pix Estático local):** Quando `MERCADO_PAGO_ACCESS_TOKEN` está vazio, se `DONATIONS_PIX_KEY`, `DONATIONS_PIX_HOLDER` e `DONATIONS_PIX_CITY` estiverem configurados, o sistema monta localmente o payload BR Code EMV (`PixPayload` com CRC16-CCITT e `txid` igual ao ULID da doação) e renderiza o QR Code SVG via `PixQrCode`. Não há confirmação automática por webhook neste modo.

### Fato 3 — Webhooks assinados, fila e agendador
- Endpoint único fora do prefixo de idioma e isento de CSRF: `POST /webhooks/mercadopago` (`MercadoPagoWebhookController`).
- Se `MERCADO_PAGO_WEBHOOK_SECRET` estiver vazio, o endpoint responde `503 Service Unavailable`.
- Valida a assinatura HMAC-SHA256 no cabeçalho `x-signature` (`ts=<ts>,v1=<hmac>`) sobre o manifesto exato `id:<data.id em minusculas>;request-id:<x-request-id>;ts:<ts>;` em tempo constante (`hash_equals` em `MercadoPagoSignature`).
- Tópicos tratados no job `ProcessWebhookEvent` (`HandleWebhookEvent`):
  - `payment` (No painel do Mercado Pago: **Pagamentos** / `payment`)
  - `subscription_preapproval` (No painel do Mercado Pago: **Planos e assinaturas** / `subscription_preapproval`)
  - `subscription_authorized_payment` (No painel do Mercado Pago: **Faturas de assinaturas / Pagamentos autorizados** / `subscription_authorized_payment`)
- Requer worker da fila (`devtools-worker` / `php artisan queue:work`) rodando para processar `ProcessWebhookEvent` e agendador (`schedule:run`) para `payments:expire` (a cada 5 min) e `webhooks:prune --days=30` (diário às 03:30).
