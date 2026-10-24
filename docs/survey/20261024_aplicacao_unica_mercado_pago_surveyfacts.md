# Survey — Aplicação Única e Arquitetura de Integração do Mercado Pago (2026-10-24)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** `/plan /grill-with-docs No Mercado Pago e informado que e preciso de uma aplicação com o Tipo de Solução Checkout Transparente para PIX e Cartão a vista ( pagamento unico ) e outra aplicação com o Tipo Solução como Assinaturas para pagamentos recorrente. Veja esta documentação docs\referencias\documentacao_mercado_pago.md, e isto mesmo ? Eu vou ter que criar duas aplicações ? Uma para o PIX doação o pix avista e cartão a vista, e outra para assinaturas recorrentes.`
- **Tela / Módulo:** Integração Mercado Pago (`/subscribe`, `/{locale}/donate`, `POST /webhooks/mercadopago`, `app/Services/Payments/MercadoPagoGateway.php`, `docs/guides/mercado-pago.md` e `docs/referencias/documentacao_mercado_pago.md`)
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 2 rodadas, 3 decisões principais, inspeção completa dos ADRs financeiras (`ADR-045` a `ADR-049`), `config/payments.php`, `config/donations.php`, `MercadoPagoGateway.php`, `MercadoPagoWebhookController.php`, `HandleWebhookEvent.php` e `Subscribe.vue`.
- **Arquivos-chave lidos:**
  - `docs/referencias/documentacao_mercado_pago.md`
  - `docs/guides/mercado-pago.md`
  - `docs/adr/ADR-045-gateway-mercado-pago-checkout-pro-e-preapproval.md`
  - `docs/adr/ADR-048-doacoes-pix-dinamico-e-estatico.md`
  - `config/payments.php`
  - `config/donations.php`
  - `app/Services/Payments/MercadoPagoGateway.php`
  - `app/Http/Controllers/Webhooks/MercadoPagoWebhookController.php`
  - `app/Actions/Payments/StartCheckout.php`
  - `app/Actions/Payments/HandleWebhookEvent.php`
  - `resources/js/pages/Subscribe.vue`

## 2. Decisões das rodadas

### Rodada 1 — Estratégia de Aplicações no Painel do Mercado Pago e Fluxo de Checkout
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Estratégia de Aplicações no Painel do Mercado Pago (1 App vs. 2 Apps) | **Opção A (1 Aplicação única no MP):** Manter uma única aplicação no painel do Mercado Pago para atender aos três fluxos (Doação PIX transparente via `/v1/payments`, Pagamento único de plano via Checkout Pro `/checkout/preferences` e Assinatura recorrente via `/preapproval`), utilizando um único `MERCADO_PAGO_ACCESS_TOKEN` e um único `MERCADO_PAGO_WEBHOOK_SECRET` no `.env`. |
| Q2 | Experiência do Pagamento Único de Planos em `/subscribe` (Redirecionamento vs. Transparente) | **Manter a arquitetura atual (ADR-045 e ADR-048):** Doação em `/{locale}/donate` opera via PIX Dinâmico transparente (`POST /v1/payments` renderizando QR Code e Copia e Cola no próprio site), enquanto `/subscribe` opera por redirecionamento seguro (**Checkout Pro** para pagamento único via PIX/Cartão/Boleto e **Preapproval** para renovação automática no cartão), sem injetar scripts externos no navegador e preservando a CSP por nonce (`ADR-052`). |

### Rodada 2 — Alinhamento da Documentação Oficial e de Referência
| # | Decisão | Resolução |
|---|---|---|
| Q3 | Atualização do Guia Oficial e do Arquivo de Referência | **Opção A:** Atualizar tanto `docs/guides/mercado-pago.md` (adicionando FAQ explícito sobre 1 vs. 2 aplicações e detalhando o que selecionar no painel do Mercado Pago) quanto `docs/referencias/documentacao_mercado_pago.md` (adicionando o mapeamento exato entre a documentação oficial do Mercado Pago e os endpoints utilizados pelo DevTools). |

### Defaults assumidos (não contestados)
- Nenhuma alteração de código em PHP ou Vue/TypeScript é necessária, pois `MercadoPagoGateway` já implementa todos os endpoints REST sob uma única credencial `MERCADO_PAGO_ACCESS_TOKEN` e `MercadoPagoWebhookController` valida os três tópicos (`payment`, `subscription_preapproval`, `subscription_authorized_payment`) sob um único `MERCADO_PAGO_WEBHOOK_SECRET`.
- As decisões arquiteturais `ADR-045`, `ADR-047`, `ADR-048` e `ADR-052` permanecem integralmente válidas e inalteradas.

## 3. Relatório de fatos levantados

### Fato 1: Como funcionam as aplicações e o `Access Token` no Mercado Pago Developers
No painel do Mercado Pago Developers (`https://www.mercadopago.com.br/developers/panel/app`), a escolha de *"Qual produto você está integrando?"* durante a criação da aplicação (Checkout Pro, Checkout Transparente ou Assinaturas) serve apenas para personalizar a tela inicial de documentação e métricas do painel. Na API REST do Mercado Pago (`https://api.mercadopago.com`), o `Access Token` (`APP_USR-...`) autentica a conta vendedora (*Seller*) e possui permissão simultânea para todos os endpoints utilizados pelo projeto:
1. `POST /v1/payments` e `GET /v1/payments/{id}` (usados para criar o PIX Dinâmico transparente na página de doações `/{locale}/donate` e consultar pagamentos no webhook);
2. `POST /checkout/preferences` (usado no pagamento avulso em `/subscribe` para abrir o Checkout Pro com PIX, cartão à vista e boleto);
3. `POST /preapproval`, `GET /preapproval/{id}`, `PUT /preapproval/{id}` e `GET /authorized_payments/{id}` (usados na assinatura com renovação automática no cartão em `/subscribe` e no cancelamento em `/dashboard/billing`).

### Fato 2: Impacto dos Webhooks se fossem criadas duas aplicações
O endpoint `POST /webhooks/mercadopago` (`MercadoPagoWebhookController`) valida a assinatura HMAC-SHA256 (`x-signature`) usando a variável única `MERCADO_PAGO_WEBHOOK_SECRET`. Como cada aplicação criada no painel do Mercado Pago gera uma *Assinatura secreta* diferente, criar duas aplicações exigiria duas variáveis de segredo ou resultaria em erro `401 Unauthorized` para metade dos eventos. Em uma única aplicação no painel do Mercado Pago, na seção **Webhooks -> Configurar notificações**, é possível marcar simultaneamente os 3 tópicos (`Pagamentos`, `Planos e assinaturas` e `Faturas de assinaturas / Pagamentos autorizados de assinatura`) apontando para `https://devtools.natanfiuza.dev.br/webhooks/mercadopago` sob um único `MERCADO_PAGO_WEBHOOK_SECRET`.

### Fato 3: O que é Checkout Transparente vs. Checkout Pro no DevTools
- **Doações (`/{locale}/donate`):** Usa **Checkout Transparente de PIX** via API direta (`POST /v1/payments` com `payment_method_id = "pix"`), exibindo o QR Code e o código Copia e Cola diretamente na interface do DevTools (`DonationStatus.vue`).
- **Planos Avulsos (`/subscribe` -> Pagar agora):** Usa **Checkout Pro** (`POST /checkout/preferences`), redirecionando o usuário para o ambiente seguro do Mercado Pago para pagar com PIX, cartão ou boleto. Isso evita carregar SDK JS no navegador, mantém conformidade total com a `Content-Security-Policy` por nonce (`ADR-052`) e elimina escopo PCI no servidor.
- **Planos Recorrentes (`/subscribe` -> Renovação automática no cartão):** Usa **Assinaturas sem plano associado (`POST /preapproval`)**, também por redirecionamento (`init_point`), mantendo os preços e ciclos sob controle exclusivo da tabela `plans` do DevTools.
