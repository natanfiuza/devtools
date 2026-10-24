# Plano — Clarificação da Aplicação Única e Fluxos do Mercado Pago na Documentação (2026-10-24)

## 1. Objetivo
Documentar de forma clara e definitiva em `docs/guides/mercado-pago.md` e em `docs/referencias/documentacao_mercado_pago.md` que **não é necessário criar duas aplicações no painel do Mercado Pago**: uma única aplicação fornece o `MERCADO_PAGO_ACCESS_TOKEN` e o `MERCADO_PAGO_WEBHOOK_SECRET` capazes de operar simultaneamente os três fluxos do DevTools (Doação PIX Transparente, Pagamento Único de Plano via Checkout Pro e Assinatura Recorrente via Preapproval).

## 2. Contexto Arquitetural Validado (Grill)
1. **Aplicação Única no Mercado Pago (`Q1`):**
   - O seletor de produto no painel do Mercado Pago (*Checkout Pro*, *Checkout Transparente* ou *Assinaturas*) ao criar a aplicação não bloqueia endpoints na API REST.
   - O mesmo `Access Token` (`APP_USR-...`) autentica chamadas para `POST /v1/payments` (PIX Transparente de doação), `POST /checkout/preferences` (Checkout Pro avulso) e `POST /preapproval` (Assinatura recorrente).
   - Na tela de **Webhooks** dessa única aplicação, marcam-se os 3 eventos (`Pagamentos`, `Planos e assinaturas` e `Faturas de assinaturas / Pagamentos autorizados de assinatura`), gerando um único `MERCADO_PAGO_WEBHOOK_SECRET`.
2. **Fluxos do DevTools (`Q2`):**
   - **Doação (`/{locale}/donate`):** Checkout Transparente de PIX (`POST /v1/payments`), exibindo QR Code e Copia e Cola na própria tela do site.
   - **Plano Pro — Pagamento Único (`/subscribe` -> Pagar agora):** Checkout Pro por redirecionamento (`POST /checkout/preferences`), aceitando PIX à vista, Cartão à vista e Boleto na página hospedada do Mercado Pago (sem SDK JS no cliente, preservando a CSP `ADR-052`).
   - **Plano Pro — Renovação Automática (`/subscribe` -> Renovação automática no cartão):** Assinaturas sem plano associado (`POST /preapproval`), por redirecionamento.

## 3. Passos de Implementação

### Passo 1: Atualizar `docs/guides/mercado-pago.md`
- Adicionar no bloco **1. Perguntas Rápidas (FAQ)** a pergunta:
  - *"Preciso criar duas aplicações no Mercado Pago (uma para PIX/Checkout à vista e outra para Assinaturas recorrentes)?"*
  - Responder explicando que **não (basta 1 única aplicação)**, detalhando por que o `Access Token` e o `Webhook Secret` únicos atendem aos 3 fluxos (`POST /v1/payments`, `POST /checkout/preferences` e `POST /preapproval`) e por que criar duas aplicações geraria dois segredos de webhook conflitantes.
- Reforçar no **Passo 2: Criar a Aplicação no Painel de Desenvolvedores** que mesmo selecionando *Checkout Pro* (ou *Checkout Transparente*), todas as APIs REST (`/v1/payments`, `/checkout/preferences` e `/preapproval`) e todos os tópicos de Webhook ficam disponíveis na mesma aplicação.

### Passo 2: Atualizar `docs/referencias/documentacao_mercado_pago.md`
- Adicionar no topo do arquivo de referência uma seção **"Resumo Prático para o DevTools (Você precisa de apenas 1 Aplicação no Mercado Pago)"**, mapeando claramente:
  1. Como o DevTools usa cada parte da documentação do Mercado Pago (Doação PIX Transparente via `/v1/payments`, Pagamento Único de Plano via Checkout Pro `/checkout/preferences`, e Recorrência via Assinaturas sem plano `/preapproval`);
  2. Por que apenas **1 aplicação** deve ser criada no painel do Mercado Pago (1 `MERCADO_PAGO_ACCESS_TOKEN` + 1 `MERCADO_PAGO_WEBHOOK_SECRET`), com link direto para o guia completo `docs/guides/mercado-pago.md`.

### Passo 3: Relatório Final
- Gerar o relatório detalhado da tarefa em `docs/gemini/reports/develop_natan/20261024_develop_natan_clarificacao_aplicacao_unica_mercado_pago.md`.
