# Plano — Tutorial de Integração do Mercado Pago e Atualização da Regra de Acentuação (2026-10-24)

> Plano gerado após sessão `/grill-with-docs` na branch `develop_natan`.

## 1. Objetivo

1. Criar o guia operacional e arquitetural completo `docs/guides/mercado-pago.md` explicando:
   - Se é necessário instalar algum SDK (não: cliente HTTP fino já implementado e `bacon/bacon-qr-code` já presente no projeto).
   - Como funciona o recebimento de doações por PIX pelo Mercado Pago (Pix Dinâmico com confirmação automática via webhook vs fallback de Pix Estático local) e os requisitos obrigatórios (`DONATIONS_PAYER_EMAIL` para doador anônimo e chave Pix ativa na conta do Mercado Pago).
   - O passo a passo completo no painel **Suas integrações** do Mercado Pago (criação da aplicação, credenciais de Teste/Sandbox e Produção, configuração dos Webhooks e obtenção do `MERCADO_PAGO_WEBHOOK_SECRET`).
   - Todas as variáveis de ambiente (`.env`) de pagamentos (`config/payments.php`) e doações (`config/donations.php`).
   - Como testar localmente (contas de teste comprador/vendedor no Sandbox, túnel HTTPS para webhooks, worker de fila e matriz de smoke).
   - Resolução de problemas (Troubleshooting), operação no painel `/admin` e rotação de chaves sem downtime.
2. Atualizar a regra de escrita da documentação em `GEMINI.md` e `docs/SPEC.md` (seções 3 e 11.1) para determinar que toda a documentação em `docs/` seja escrita em português **com acentuação correta**.
3. Referenciar `docs/guides/mercado-pago.md` em `docs/RUNBOOK.md`, `docs/SPEC.md` (seção 10) e `README.md`.

## 2. Arquivos a criar ou modificar

| Arquivo | Ação | Descrição |
|---|---|---|
| `docs/survey/20261024_guia_mercado_pago_surveyfacts.md` | Criar | Persistência automática do levantamento da sessão `/grill-with-docs`. |
| `docs/gemini/plans/develop_natan/20261024_develop_natan_guia_integracao_mercado_pago.md` | Criar | Este plano de implementação. |
| `docs/guides/mercado-pago.md` | Criar | Tutorial completo de configuração, arquitetura, webhooks, assinaturas e doação PIX no Mercado Pago. |
| `GEMINI.md` | Editar | Atualizar a convenção de escrita para usar acentuação correta na documentação do repositório (`docs/`). |
| `docs/SPEC.md` | Editar | Atualizar a convenção de acentuação (seções 3 e 11.1) e incluir `mercado-pago.md` na lista de `docs/guides/` (seção 10). |
| `docs/RUNBOOK.md` | Editar | Adicionar link para `guides/mercado-pago.md` nas seções 1 (`.env` de produção) e 7 (Rotação de chaves - Mercado Pago). |
| `README.md` | Editar | Adicionar link para os guias operacionais (`docs/guides/`) e seção rápida de pagamentos/Mercado Pago local. |
| `docs/gemini/reports/develop_natan/20261024_develop_natan_guia_integracao_mercado_pago.md` | Criar | Relatório detalhado da entrega conforme regra do `GEMINI.md`. |

## 3. Estrutura detalhada de `docs/guides/mercado-pago.md`

1. **Visão Geral e Perguntas Rápidas (FAQ Inicial)**
   - Precisa instalar algum SDK? (Não — explicação do `MercadoPagoGateway` sobre `Http` do Laravel e `bacon/bacon-qr-code` já instalado).
   - O Mercado Pago pode ser usado para receber doação por PIX? (Sim — explicação do Pix Dinâmico via `POST /v1/payments` com confirmação automática por webhook e recibo por e-mail, mais o fallback de Pix Estático EMV).
2. **Princípios e Arquitetura no DevTools**
   - Os 3 fluxos atendidos pelo Mercado Pago: Checkout Pro avulso (`/checkout/preferences`), Recorrência sem plano (`/preapproval` + `/authorized_payments`) e Doação Pix Dinâmico (`/v1/payments`).
   - Busca antes de cumprir (*fetch-before-fulfill*, ADR-047) e fila (`ProcessWebhookEvent`).
   - Degradação elegante sem credenciais (atendimento manual em `/subscribe` e Pix Estático em `/{locale}/donate`).
3. **Pré-requisitos na Conta do Mercado Pago**
   - Cadastro de chave Pix na conta Mercado Pago (obrigatório para o endpoint de Pix Dinâmico funcionar).
   - Dados de negócio / nome na fatura (`PAYMENTS_STATEMENT_DESCRIPTOR`).
4. **Passo a Passo no Painel do Mercado Pago (Suas Integrações)**
   - Passo 1: Criar a aplicação no painel de desenvolvedores (<https://www.mercadopago.com.br/developers/panel/app>).
   - Passo 2: Obter o `Access Token` de Teste (Sandbox) e de Produção (`MERCADO_PAGO_ACCESS_TOKEN`).
   - Passo 3: Configurar os Webhooks (`https://devtools.natanfiuza.dev.br/webhooks/mercadopago`), selecionar os tópicos (`Pagamentos` / `payment`, `Planos e assinaturas` / `subscription_preapproval`, `Faturas de assinaturas` / `subscription_authorized_payment`) e copiar a chave secreta (`MERCADO_PAGO_WEBHOOK_SECRET`).
5. **Configuração no Arquivo `.env`**
   - Bloco completo de variáveis de `config/payments.php` e `config/donations.php`.
   - Explicação detalhada de cada variável (incluindo `DONATIONS_PAYER_EMAIL` para doações anônimas e `DONATIONS_PIX_*` para contingência).
   - Comandos para aplicar a configuração (`php artisan optimize:clear && php artisan optimize` e `php artisan queue:restart`).
6. **Processos Obrigatórios em Servidor (Fila e Agendador)**
   - Worker (`devtools-worker`) para `ProcessWebhookEvent` e `DonationReceipt`.
   - Cron do agendador (`schedule:run`) para `payments:expire` e `webhooks:prune`.
7. **Como Testar Localmente (Sandbox + Túnel HTTPS)**
   - Contas de teste no Mercado Pago (vendedor vs comprador).
   - Expondo o webhook local via `cloudflared` ou `ngrok` (`PAYMENTS_WEBHOOK_URL`).
   - Roteiro passo a passo de teste (Checkout Pro, Preapproval, Doação PIX Dinâmica, Doação PIX Estática, Cancelamento e Estorno).
8. **Resolução de Problemas (Troubleshooting) e Rotação de Chaves**
   - Tabela e diagnóstico de erros comuns (`503` e `401` no webhook, Pix recusado na criação, checkout online oculto, evento `failed` em `/admin/webhooks`).
   - Passo a passo de rotação de `MERCADO_PAGO_ACCESS_TOKEN` e `MERCADO_PAGO_WEBHOOK_SECRET` sem perder notificações.
9. **Mapa de Arquivos no Código**
   - Tabela de referência rápida apontando para contratos, gateway, actions, controllers, jobs, configs e ADRs.

## 4. Verificação

- Revisar todos os links relativos nos arquivos Markdown editados.
- Confirmar que nenhum comando git (`commit`/`push`) foi executado.
- Gerar relatório final em `docs/gemini/reports/develop_natan/20261024_develop_natan_guia_integracao_mercado_pago.md`.
