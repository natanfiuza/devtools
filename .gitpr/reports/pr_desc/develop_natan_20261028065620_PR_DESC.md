# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona consulta sob demanda e diagnostico de doacao Pix
```

---

## 🎯 Resumo

O doador ficava sem resposta quando o Pix era recusado: o webhook do Mercado Pago chegava, a doacao ia para `failed`, e a pagina de status so dizia "nao concluida" — sem motivo e sem forma de conferir se o pagamento tinha sido aprovado. Em paralelo, quem pagava segundos antes do webhook era processado precisava esperar (ou recarregar a pagina no escuro).

Esta mudanca da suporte ao caminho "paguei e quero saber agora" e explica a recusa:

- botao **"Ja paguei"** na pagina de status, que consulta o provedor sob demanda (limitado por IP) e sincroniza o estado;
- **motivo da recusa traduzido** para o doador (`rejected_by_bank` → "O banco recusou o Pix..."), mantendo o codigo cru apenas no banco e no admin;
- comando **`donations:check {ulid}`** para diagnostico em producao (status do provedor, `live_mode`, recebedor, e-mail mascarado);
- padronizacao do efeito do pagamento em um unico caminho (`SyncDonationPayment`), usado por webhook, botao e comando — antes o webhook e a consulta podiam divergir.

Aproveita para corrigir um bug silencioso de configuracao: `DONATIONS_EXPIRES_MINUTES` vazio no `.env` virava `0`, e o QR era criado com 5 minutos de validade.

## 🛠️ Mudanças Técnicas

- **`SyncDonationPayment` (novo action)**: ponto unico de efeito local de um pagamento consultado. Grava `gateway_status`/`gateway_status_detail` antes de qualquer transicao; aprovado delega ao `ConfirmDonation` (lock, valor e moeda); recusa/estorno usam `update` condicional para um aviso atrasado nao rebaixar doacao ja paga.
- **`ConfirmDonation`**: guarda passa a aceitar `pending|expired → paid` (ADR-048), permitindo confirmar Pix aprovado no provedor depois do vencimento local.
- **`HandleWebhookEvent`**: remove a logica duplicada de aprovado/recusado/estornado e passa a delegar ao `SyncDonationPayment`; a doacao e resolvida antes do estorno para que o detalhe do provedor seja gravado tambem nesse caso.
- **`DonationController@check` + rota `POST /{locale}/donate/{donation}/check`**: valida ULID, so consulta doacao aberta com cobranca no provedor, confere `external_reference` (`don_{ulid}`) antes de sincronizar e devolve toast (info/warning/error) via flash Inertia.
- **Rate limit nomeado `donation-check`**: por IP (nao por rota) — o prefixo de idioma mudava a URI e contornava o `throttle` anterior.
- **`donations:check {ulid}` (novo comando)**: consulta o pagamento, imprime snapshot local x provedor (status, detalhe, `live_mode`, recebedor, e-mail mascarado) e roda a mesma sincronizacao. **Escreve estado.** Nunca imprime token nem e-mail completo; revela a anomalia `aprovado no provedor + failed local` sem reabrir a doacao.
- **`DonationFailureReason` (novo enum)**: mapeia `status_detail` do provedor para uma chave de traducao; codigo desconhecido cai em `other`.
- **Pagina `DonationStatus.vue`**: auto-refresh via `usePoll` (apenas `donation`, pausa em background, so while `pending`), botao "Ja paguei" com estado de carregamento/erro, e motivo da recusa no lugar do aviso generico.
- **Admin de doacoes**: exibe `gatewayStatus` e `gatewayStatusDetail` (cru) no detalhe — diagnostico que a pagina publica nao mostra.
- **`MercadoPagoGateway`/DTOs**: `GatewayPayment` ganha `payerEmail`, `collectorId`, `liveMode`, `createdAt`, `expiresAt`; `GatewayPixCharge` ganha `statusDetail`.
- **Testes**: `DonationCheckTest`, `DonationDiagnosticsCommandTest`, `DonationFailureReasonTest`, `donation-status.test.ts` e casos novos em `DonationTest`/`AdminDonationsTest`/`MercadoPagoGatewayTest` (incluindo confirmacao de doacao vencida e vazamento do codigo cru para o HTML).

## ⚠️ Impacto/Avisos

- **Banco de dados**: nova migration `2026_10_27_000001_add_gateway_status_to_donations_table` adiciona `gateway_status` (varchar 32) e `gateway_status_detail` (varchar 64) em `donations`, ambas nullable. Registros antigos e Pix estatico ficam com `null`. Rodar `php artisan migrate` no deploy.
- **Novas variaveis de ambiente** (ver `.env.example`):
  - `DONATIONS_STATUS_POLL_MS` (padrao `15000`; `0` desliga o auto-refresh);
  - `DONATIONS_CHECK_PER_MINUTE` (padrao `5`, teto por IP/minuto do botao "Ja paguei").
- **Mudanca de comportamento em `DONATIONS_EXPIRES_MINUTES`**: agora tem piso de 30 minutos e valor vazio cai em 60. Deployments com valor configurado abaixo de 30 passarao a gerar QR com 30 minutos.
- **Nova migration em producao**: para forcar o motivo da recusa em doacoes ja existentes, rodar `php artisan donations:check {ulid}` nas afetadas (o comando **escreve estado**; nao e somente leitura).
- **Nova dependencia operacional**: o botao "Ja paguei" chama a API do Mercado Pago com credencial de producao; sem `MERCADOPAGO_ACCESS_TOKEN` o botao nao aparece (`canCheck=false`) e a pagina segue funcionando apenas com o webhook.
- **Sem breaking change de contrato publico**: `DonationStatusProps` ganha `pollMs`, `donation.canCheck` e `donation.failureReason`; o codigo cru do provedor continua restrito ao admin.

close #105

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_15_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)