# Relatório — Tutorial de Integração do Mercado Pago e Atualização da Regra de Acentuação (2026-10-24)

## 1. Resumo da Tarefa

- **Data:** 2026-10-24
- **Branch:** `develop_natan`
- **Solicitação:**
  1. Conduzir sessão `/grill-with-docs` e `/plan` para o guia de integração do Mercado Pago.
  2. Responder o que precisa ser configurado, se é necessário instalar algum SDK e se o Mercado Pago pode ser usado para receber doação por PIX.
  3. Salvar o tutorial completo em `docs/guides/mercado-pago.md`.
  4. Alterar a regra em `GEMINI.md` e `docs/SPEC.md` para que toda a documentação em `docs/` seja escrita com acentuação correta em português.

---

## 2. Respostas Técnicas Consolidadas

1. **Precisa instalar algum SDK?**
   - **Não.** A integração da Fase 6 (`App\Services\Payments\MercadoPagoGateway`) foi construída sobre o cliente HTTP nativo do Laravel (`Illuminate\Support\Facades\Http`), sem `mercadopago/dx-php` e sem `@mercadopago/sdk-js`.
   - O checkout avulso usa **Checkout Pro** por redirecionamento (`init_point`) e a recorrência usa **Preapproval** também por redirecionamento (`init_point`), sem scripts de terceiros no navegador (preservando a CSP com nonce).
   - O QR Code do modo de contingência (Pix Estático) usa `bacon/bacon-qr-code`, que já está instalado no `composer.json`.

2. **O Mercado Pago pode ser usado para receber doação por PIX?**
   - **Sim, nativamente.** Na página pública `/{locale}/donate` (`CreateDonation` + `ConfirmDonation`, ADR-048):
     - **Com `MERCADO_PAGO_ACCESS_TOKEN` configurado (Pix Dinâmico):** Cria a cobrança via `POST /v1/payments` (`payment_method_id = "pix"`, `external_reference = "don_<ULID>"`), exibe o QR Code PNG oficial e o código Copia e Cola do Mercado Pago (expiração de 60 minutos), confirma automaticamente via webhook `POST /webhooks/mercadopago` (`pending -> paid`) e envia recibo por e-mail (`DonationReceipt`) se o doador informou e-mail.
     - **Requisitos críticos documentados no guia:** A conta recebedora do Mercado Pago precisa ter uma **chave Pix ativa cadastrada** no próprio Mercado Pago, e a variável `DONATIONS_PAYER_EMAIL` (ou `MAIL_FROM_ADDRESS`) precisa ter um e-mail válido para cobrir doações anônimas (já que a API do Mercado Pago exige `payer.email`).
     - **Sem `MERCADO_PAGO_ACCESS_TOKEN` (Fallback de Pix Estático):** Monta localmente o BR Code EMV (`PixPayload` + `PixQrCode` em SVG) a partir de `DONATIONS_PIX_KEY`, `DONATIONS_PIX_HOLDER` e `DONATIONS_PIX_CITY`, sem confirmação automática.

---

## 3. Arquivos Criados e Modificados

| Arquivo | Ação | Detalhes |
|---|---|---|
| `docs/guides/mercado-pago.md` | Criado | Guia completo de integração com o Mercado Pago: FAQ sobre SDK e Doação PIX, arquitetura dos 3 fluxos (Checkout Pro, Preapproval e Doação Pix Dinâmico vs Estático), pré-requisito de chave Pix na conta MP, passo a passo no painel *Suas integrações* (credenciais de teste/produção e Webhooks com assinatura secreta `x-signature`), todas as variáveis `.env` (`config/payments.php` e `config/donations.php`), processos de fila/scheduler, testes no Sandbox com contas de teste e túnel HTTPS, troubleshooting (incluindo explicação do `404` no botão "Simular notificação" do painel MP) e rotação de chaves sem downtime. |
| `GEMINI.md` | Editado | Atualizada a regra de convenção de escrita para determinar que tanto as strings de interface quanto a documentação do repositório (`docs/`) usam acentuação correta em português. |
| `docs/SPEC.md` | Editado | Atualizada a convenção de acentuação nas seções 3 e 11.1, e incluídos `login Google` e `Mercado Pago` na lista de guias operacionais (`docs/guides/`) da seção 10. |
| `docs/RUNBOOK.md` | Editado | Adicionadas referências diretas para `docs/guides/mercado-pago.md` na seção 1 (bloco `.env` de produção) e na seção 7 (Rotação de chaves — Mercado Pago). |
| `README.md` | Editado | Adicionado link direto para `docs/guides/` na lista de documentação do topo. |
| `docs/survey/20261024_guia_mercado_pago_surveyfacts.md` | Criado | Levantamento completo da sessão `/grill-with-docs` com os fatos inspecionados no código e as decisões das rodadas. |
| `docs/gemini/plans/develop_natan/20261024_develop_natan_guia_integracao_mercado_pago.md` | Criado | Plano detalhado da tarefa conforme regra do `GEMINI.md`. |
| `docs/gemini/reports/develop_natan/20261024_develop_natan_guia_integracao_mercado_pago.md` | Criado | Este relatório final de entrega. |

---

## 4. Conformidade com as Regras do Projeto

- **Zero alteração em código de runtime:** Apenas arquivos de documentação (`.md`) foram criados ou atualizados.
- **Zero segredos expostos:** Todos os exemplos utilizam placeholders seguros.
- **Git intacto:** Nenhum `git commit` ou `git push` foi executado (conforme regra do `GEMINI.md`).
