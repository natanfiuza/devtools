# Plano — Doacao Pix recusada: diagnostico e observabilidade (2026-10-27)

> Plano de execucao da sessao de grill (`/grill-with-docs`), aprovado pelo usuario. Branch `develop_natan`.

## Objetivo

Descobrir por que o Pix de doacao e recusado na confirmacao (o QR e criado com sucesso) e tornar o motivo visivel ao doador e ao admin, com reconsulta sob demanda — sem mudar a regra de confirmacao por webhook.

## Fase 0 — Diagnostico (executa e para)

1. Conectar o MCP `mercadopago` (HTTP, OAuth) — registrar em escopo de usuario e autenticar pelo `/mcp`.
2. No VPS, localizar a doacao com falha (ULID, valor, `gateway_ref`) sem PII.
3. Consultar `GET https://api.mercadopago.com/v1/payments/{gateway_ref}` com o token de producao, sem expor o token, o corpo cru ou o e-mail completo.
4. Conferir `webhook_events` por `resource_id` (status, tentativas, erro), sem payload.
5. Reportar `status`, `status_detail`, `live_mode`, `collector_id`, valor, datas, dominio do e-mail mascarado e a causa provavel. **Parar para revisao.**

Leitura esperada: `pending/pending_waiting_transfer` = recusa no banco do pagador; `rejected_by_bank` = banco; `rejected_high_risk` = risco do MP; `rejected_by_regulations` = conta/regulacao; `live_mode=false` = token de teste; valor divergente = erro de dados.

## Fase 1 — Persistir status do provedor

- Migration retrocompativel: `gateway_status` (32) e `gateway_status_detail` (64), nullable, sem indice/default em `donations`.
- `Donation` fillable + `@property`; factory com `null`.
- `GatewayPixCharge` recebe `statusDetail` opcional.
- `GatewayPayment` recebe campos de diagnostico opcionais (`payerEmail`, `collectorId`, `liveMode`, `createdAt`, `expiresAt`) — somente o comando consome; nunca logar nem serializar.
- `MercadoPagoGateway`: mapear `status_detail` na criacao e os campos extras em `payment()`.
- `CreateDonation`: gravar status/detalhe no mesmo `forceFill()` do `gateway_ref`.

## Fase 2 — Caminho unico de sincronizacao

- `DonationSyncOutcome` (`Confirmed`, `Declined`, `Refunded`, `Unchanged`).
- `SyncDonationPayment`: grava status/detalhe; estorno -> `refunded` (update condicional); aprovado -> `ConfirmDonation`; recusado -> `failed` somente se `pending`; demais -> inalterado.
- `HandleWebhookEvent`: resolve a doacao **antes** do ramo de estorno e delega ao `SyncDonationPayment`; remove o ramo de doacao de `syncRefund()`.
- `ConfirmDonation`: aceita `Pending` ou `Expired`; idempotencia continua no `lockForUpdate()`.
- `php artisan donations:check {ulid}`: consulta, confere `external_reference`, imprime diagnostico com e-mail mascarado e sincroniza.

## Fase 3 — Pagina de status

- `POST /{locale}/donate/{ulid}/check` (`donate.check`), CSRF, ULID na URL, `throttle:donation-check` com chave por IP.
- `config/donations.php`: `status_poll_ms` (15 s, 0 desliga) e `check_per_minute` (5).
- `DonationController::status()`: `canCheck`, `failureReason` traduzido e `pollMs`.
- `DonationController::check()`: guarda de estado, consulta, sincroniza, flash de toast e redirect.
- `DonationFailureReason`: mapa de `status_detail` -> `donations.reasons.*` (high_risk, bank_rejected, regulations, insufficient_data, business_rule, other).
- `DonationStatus.vue`: `usePoll` so em `pending` (sem chamar o gateway), botao "Ja paguei" quando `canCheck`, estado de carregamento e `aria-live`.
- i18n nos 5 idiomas (`reasons.*` e `check.*`).

## Fase 4 — Admin

- `PaymentController::donationRows()` expoe `gatewayStatus`/`gatewayStatusDetail`.
- Tipo `AdminDonationRow` e dialogo de `admin/Payments.vue` mostram os campos em monospace (codigo cru so no admin).

## Fase 5 — Documentacao

- ADR-059 (consulta sob demanda, persistencia do detalhe, webhook como unico caminho automatico) e emendas em ADR-047/048 (`pending|expired -> paid`).
- ARQUITETURA, SPEC, acceptance F6, glossario, guia do Mercado Pago, RUNBOOK.
- Artefatos obrigatorios de plano/relatorio da sessao.

## Testes

- Unit: mapa de `DonationFailureReason`.
- Feature: persistencia na criacao/webhook/estorno, doacao vencida confirmada, motivo traduzido sem codigo cru, `canCheck`, `pollMs`, endpoint de consulta (aprovado/pendente/recusado/erro/valor divergente/referencia divergente/rate limit/ULID invalido), comando, admin e gateway.
- Vitest: motivo, botao, POST, polling (inicio, parada, unmount, `pollMs=0`).

## Verificacao

`composer test`; testes alvo; `npm run types:check`; `npm run test -- donation-status`; lint/format. No VPS: `migrate --force`, `optimize:clear && optimize`, `queue:restart`, `route:list --name=donate`, `donations:check <ulid>` na doacao afetada.

## Riscos

Cache de config; PII do pagador restrita ao comando mascarado; `failed` nao volta a `paid`; polling para em `expired` (botao cobre); migration nullable segura para workers antigos; recusas de banco/risco nao sao corrigiveis por codigo.
