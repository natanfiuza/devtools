# Relatório — Clarificação da Aplicação Única e Fluxos do Mercado Pago (2026-10-24)

## 1. Resumo da Tarefa
Esclarecimento arquitetural (via `/grill-with-docs` e `/plan`) e atualização da documentação oficial do projeto sobre a necessidade de criar **apenas uma única aplicação** no painel do Mercado Pago Developers para operar simultaneamente todos os fluxos de pagamento do DevTools (Doações por PIX Transparente, Pagamento Único de Plano via Checkout Pro e Assinaturas Recorrentes via Preapproval).

- **Branch:** `develop_natan`
- **Data:** `2026-10-24`

## 2. Conclusão Técnica Principal
1. **Apenas 1 Aplicação no Painel do Mercado Pago:**
   - O campo *"Qual produto você está integrando?"* no formulário de criação de aplicação do Mercado Pago Developers (*Checkout Pro*, *Checkout Transparente* ou *Assinaturas*) é apenas uma categorização de interface do painel.
   - O `Access Token` (`APP_USR-...`) gerado pertence à conta recebedora (*Seller*) e autentica chamadas na API REST para todos os endpoints usados por `App\Services\Payments\MercadoPagoGateway`:
     - `POST /v1/payments` e `GET /v1/payments/{id}` (Doação PIX Dinâmico transparente em `/{locale}/donate`);
     - `POST /checkout/preferences` (Pagamento único de plano Pro via Checkout Pro com PIX, Cartão à vista e Boleto em `/subscribe`);
     - `POST /preapproval`, `GET /preapproval/{id}`, `PUT /preapproval/{id}` e `GET /authorized_payments/{id}` (Renovação automática no cartão em `/subscribe` e cancelamento em `/dashboard/billing`).
2. **Unicidade do Segredo de Webhook (`MERCADO_PAGO_WEBHOOK_SECRET`):**
   - Na mesma aplicação do Mercado Pago, na seção **Webhooks -> Configurar notificações**, marcam-se simultaneamente os 3 tópicos (`Pagamentos`, `Planos e assinaturas` e `Faturas de assinaturas / Pagamentos autorizados de assinatura`), gerando uma única **Assinatura secreta** que valida todas as notificações em `POST /webhooks/mercadopago` (`MercadoPagoWebhookController`).

## 3. Arquivos Criados e Atualizados
- **Survey (`/grill-with-docs`):** `docs/survey/20261024_aplicacao_unica_mercado_pago_surveyfacts.md`
- **Plano (`/plan`):** `docs/gemini/plans/develop_natan/20261024_develop_natan_clarificacao_aplicacao_unica_mercado_pago.md`
- **Guia Oficial:** `docs/guides/mercado-pago.md` (adicionado item no FAQ explicando por que usar 1 única aplicação em vez de 2 e detalhado o comportamento do seletor de produto no Passo 2).
- **Documento de Referência:** `docs/referencias/documentacao_mercado_pago.md` (adicionado quadro de resumo prático mapeando os 3 fluxos do DevTools e os links oficiais).
- **Relatório:** `docs/gemini/reports/develop_natan/20261024_develop_natan_clarificacao_aplicacao_unica_mercado_pago.md`
