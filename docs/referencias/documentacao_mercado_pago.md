# Referências da Documentação Oficial — Mercado Pago

> **Resumo Prático para o DevTools (Apenas 1 Aplicação no Mercado Pago):**
> Você **NÃO precisa criar duas aplicações** no painel do Mercado Pago. **Uma única aplicação** (com um único `MERCADO_PAGO_ACCESS_TOKEN` e um único `MERCADO_PAGO_WEBHOOK_SECRET`) atende simultaneamente aos 3 fluxos já implementados no projeto (veja o passo a passo completo em [`docs/guides/mercado-pago.md`](../guides/mercado-pago.md)):
> 1. **Doação por PIX (`/{locale}/donate`):** Usa **Checkout Transparente via API de Pagamentos** (`POST /v1/payments` com `payment_method_id: "pix"`), renderizando o QR Code e o Copia e Cola diretamente na tela do nosso site ([ADR-048](../adr/ADR-048-doacoes-pix-dinamico-e-estatico.md)).
> 2. **Plano Pro — Pagamento Único (`/subscribe` -> Pagar agora):** Usa **Checkout Pro** (`POST /checkout/preferences`), redirecionando o usuário para o ambiente seguro do Mercado Pago para pagar via **PIX à vista, Cartão à vista ou Boleto**, sem injetar scripts externos no navegador e preservando a CSP ([ADR-045](../adr/ADR-045-gateway-mercado-pago-checkout-pro-e-preapproval.md), [ADR-052](../adr/ADR-052-seguranca-http-csp-por-nonce-hsts-e-turnstile.md)).
> 3. **Plano Pro — Renovação Automática (`/subscribe` -> Renovação automática no cartão):** Usa **Assinaturas sem plano associado** (`POST /preapproval`), com preços e ciclos controlados pela nossa tabela `plans` ([ADR-045](../adr/ADR-045-gateway-mercado-pago-checkout-pro-e-preapproval.md)).

---

Sim — dá para se guiar bem por estas documentações oficiais (elas separam os conceitos de **pagamento único** x **recorrência** na documentação, mas todas as APIs abaixo funcionam com o `Access Token` da **mesma aplicação**):

## 1) Pagamento único no seu site (Pix / cartão) — **Checkout Transparente & Checkout Pro**
Se você quer oferecer **Pix** e **cartão** em pagamento “avulso” (sem renovação automática):

- **No DevTools (Doações PIX em `/{locale}/donate`):** usamos a API direta de pagamentos (`POST /v1/payments`) para gerar o QR Code Pix de forma transparente na nossa própria página.
- **No DevTools (Planos avulsos em `/subscribe`):** usamos o **Checkout Pro** (`POST /checkout/preferences`) para que o usuário escolha Pix, cartão à vista ou boleto na tela hospedada pelo Mercado Pago.

Links de referência da documentação oficial do Mercado Pago:

- Visão geral (Checkout Transparente com **Orders**):  
  [Checkout Transparente — Overview (Orders)](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/overview)

- Página que mostra os **meios de pagamento** que você pode integrar no site (inclui **cartões**, **Pix** e **boleto**):  
  [Meios de pagamento para websites (cartões, Pix, boleto)](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/websites/introduction)

- Como **testar Pix** no Checkout Transparente (Orders):  
  [Realizar uma compra teste com Pix](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-test/pix)

Observação: se você está começando agora, a própria doc indica a preferência por **Orders** (e não Payments) para novas integrações de Checkout Transparente puro. Se quiser ver a comparação:  
[Modelo de integração (Orders x Payments)](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-model)

## 2) Renovação automática (cartão / saldo) — **Assinaturas (Subscriptions)**
Para cobrança recorrente (mensal/anual renovando automaticamente), a doc é a de **Assinaturas** (no DevTools usamos **Assinaturas sem plano associado** via `POST /preapproval` na mesma aplicação do Mercado Pago):

- Visão geral:  
  [Assinaturas — Overview](https://www.mercadopago.com.br/developers/pt/docs/subscriptions/overview)

- Conceitos (diferença entre **plano de assinatura** e **assinatura/preapproval**):  
  [Glossário de Assinaturas](https://www.mercadopago.com.br/developers/pt/docs/subscriptions/resources/glossary)

- Recorrência “sob medida” por assinante (sem um plano fixo no Mercado Pago — **modelo usado pelo DevTools no ADR-045**):  
  [Assinaturas sem plano associado — Introdução](https://www.mercadopago.com.br/developers/pt/docs/subscriptions/integration-configuration/subscription-no-associated-plan/introduction)

## (Opcional) SDKs
*(Nota: O DevTools não utiliza SDKs externos — todas as chamadas são feitas via cliente HTTP nativo do Laravel em `App\Services\Payments\MercadoPagoGateway`).*
- [SDKs oficiais — Overview](https://www.mercadopago.com.br/developers/pt/docs/sdks-library/overview)
