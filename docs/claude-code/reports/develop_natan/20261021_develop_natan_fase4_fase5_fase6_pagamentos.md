# Relatorio - FASE 4 (auditoria), FASE 5 (fechamento) e FASE 6 (pagamentos)

**Data:** 2026-10-21
**Branch:** `develop_natan`
**Escopo:** auditar a FASE 4 (Auth), fechar a FASE 5 (AI SQL Builder) com o
smoke real do DeepSeek e implementar a FASE 6 (pagamentos, doacao e webhooks).

Nenhum commit ou push foi feito; o dono revisa e aprova pelo `gitpr`.

## 1. FASE 4 - auditoria

Reauditoria completa de fechamento, sem implementacao nova. Nenhum bloqueio
encontrado; os 15 criterios continuam atendidos.

- Testes direcionados: **209 testes, 8.371 assercoes**, todos verdes.
- Carry-overs conferidos no codigo: `users.appearance` somente leitura; 2FA
  habilitado no Fortify sem teste dedicado; passkeys desligadas; middleware
  `verified` nas rotas autenticadas (o POST de `/subscribe` usa so `auth` +
  `active`, por decisao de produto).
- Evidencia registrada em `docs/acceptance/FASE-4.md` ("Auditoria 2026-10-21").

## 2. FASE 5 - fechamento

- Os 19 criterios de codigo foram reauditados pelos testes da fase.
- O criterio 20 (smoke real com DeepSeek) foi concluido: matriz de 17 passos em
  SQLite dedicado `storage/smoke.sqlite`, com `php artisan serve`, worker,
  login Fortify real, cookie jar e CSRF; nenhuma sessao foi forjada.
  Evidencias (ULIDs, deltas de ledger, estorno, 429/402/422/419, busca no log)
  estao em `docs/acceptance/FASE-5.md`, secao "Revalidacao 2026-10-21".
- `HistoryController` corrigido: passou a filtrar `history_visible = true` e a
  derivar o resumo de campos reais (`clarification`, premissa, tabelas, SQL),
  removendo a leitura de `result['summary']`.
- Docs de F5 e SPEC alinhados: o historico foi adiantado em 2026-10-21
  (somente leitura); opt-in, retencao de 90 dias e "apagar historico" seguem na
  F7.

## 3. FASE 6 - o que foi entregue

### Dominio e banco

- Seis migrations aditivas: estado de renovacao e indices em `subscriptions`;
  `gateway_method` e unique `(gateway, gateway_ref)` em `payments`;
  `checkout_sessions`; `webhook_events`; `donations`; semeadura das rotinas
  `payments:expire` e `webhooks:prune`.
- Enums novos: `CheckoutMode`, `CheckoutStatus`, `DonationStatus`,
  `WebhookEventStatus`; `PaymentMethod` ganhou `credit_card` e `boleto`.
- Models novos: `CheckoutSession`, `Donation`, `WebhookEvent`.
- Periodo empilhado: `OpenSubscriptionPeriod` centraliza a regra (Free nao cria
  linha; mesmo plano empilha no maior fim futuro; plano diferente comeca agora).
  `Subscription::scopeCurrent()`, `User::activeSubscription()` e
  `nextSubscription()`; `Plan::periodEndFrom()` usa `NoOverflow`.
- `GrantPlan` reescrito sobre as actions de periodo; `RefundPayment` encerra a
  linha vinculada sem apagar historico.

### Gateway

- Contrato `App\Contracts\Payments\PaymentGateway` e implementacao
  `MercadoPagoGateway` com HTTP fino, timeouts (20 s/5 s), idempotency key,
  DTOs em `app/Support/Payments/` e `PaymentGatewayException` sem corpo nem
  token.
- Endpoints: Checkout Pro (`POST /checkout/preferences`), Preapproval sem plano
  (`POST /preapproval`, mensal ou 12 meses), Pix (`POST /v1/payments`), consulta
  de pagamento/preapproval/cobranca autorizada, estorno
  (`POST /v1/payments/{id}/refunds`) e cancelamento
  (`PUT /preapproval/{id}`).

### Checkout e assinatura

- `/subscribe` virou pagina de decisao: **Pagar agora**, **Renovacao automatica
  no cartao** e **Prefiro atendimento manual**. Sem credenciais, os caminhos
  online ficam indisponiveis com aviso e o manual continua.
- Regras de inicio: "Pagar agora" com recorrencia no mesmo plano cancela a
  preapproval antes; segunda recorrencia no mesmo plano e bloqueada;
  recorrencia de outro plano e cancelada antes; periodos empilham.
- A autorizacao da preapproval **nao** libera acesso; o periodo nasce com a
  primeira `subscription_authorized_payment` aprovada, e a tela mostra
  processamento.
- Cancelamento em `/dashboard/billing`: gateway primeiro, `404`/`409` contam
  como sucesso, acesso ate `current_period_end`.
- Falha de renovacao: `renewal_failed_at` + `PaymentRenewalFailed` **uma vez**
  por periodo; aprovacao posterior limpa.

### Webhooks

- `POST /webhooks/mercadopago`, fora do locale, de `auth` e do CSRF.
- Assinatura `x-signature` (`ts`, `v1`) sobre o manifesto exato
  `id:<data.id minusculo>;request-id:<x-request-id>;ts:<ts>;`, HMAC-SHA256 e
  `hash_equals`; 503 sem segredo; 401 invalida; sem janela de timestamp.
- Payload cru em `webhook_events`, unique `(gateway, event_id)`, 201/200,
  job `ProcessWebhookEvent` (3 tentativas, backoff 10/60/300 s, timeout 60 s)
  com busca do recurso antes de agir.
- Topicos: `payment`, `subscription_preapproval`,
  `subscription_authorized_payment`; os demais ficam `ignored`.
- Retencao de 30 dias; `webhooks:prune` apaga so essa tabela.

### Doacoes

- `/{locale}/donate` publica nos cinco idiomas, doador anonimo permitido.
- Pix dinamico quando ha credenciais (`don_<ULID>`, QR, copia e cola,
  expiracao de 60 min, e-mail fallback); confirmacao idempotente pelo webhook.
- Fallback de Pix estatico com `PixPayload` (EMV + CRC16) e QR SVG, sem
  confirmacao automatica e com aviso explicito.
- Recibo opcional so na transicao `pending -> paid`, uma vez.
- Pagina de status publica por ULID, sem dados do doador e `noindex`.

### Admin, i18n e SEO

- Pagamentos com abas **Pagamentos** e **Doacoes** (busca, filtro, detalhe).
- `/admin/webhooks` somente leitura, com payload completo e filtros.
- Namespace `donations` nos cinco idiomas; chaves novas em `home.subscribe`,
  `account.billing`, `admin` e `mail`; `TranslationsParityTest` verde.
- `/donate` indexavel (sitemap + hreflang); `/subscribe` e status de doacao
  `noindex`.

### Documentacao

- ADRs 045 (gateway/Checkout Pro/Preapproval), 046 (periodos empilhados),
  047 (webhooks), 048 (doacoes Pix) e 049 (ciclo da recorrencia).
- `docs/SPEC.md`, `docs/API.md`, `docs/ARQUITETURA.md`, `docs/RUNBOOK.md`,
  `docs/glossario.md`, `CHANGELOG.md` e `docs/acceptance/FASE-6.md`.
- RUNBOOK ganhou o cron obrigatorio do `schedule:run` - sem ele,
  `payments:expire` e `webhooks:prune` nao rodam.

## 4. Gates finais (2026-10-21)

```text
composer test        -> 560 testes, 560 passaram, 12.902 assercoes (Pint ok, PHPStan 7 com 0 erros)
npm run test         -> 55 arquivos, 749 testes, 749 passaram
npm run types:check  -> vue-tsc limpo (Wayfinder regenerado)
npm run lint:check   -> 0 erros (9.943 avisos de estilo pre-existentes)
npm run format:check -> Prettier ok
npm run build        -> ok
npm run build:ssr    -> ok
migrate:fresh --seed -> SQLite dedicado storage/smoke_f6.sqlite: 32 migrations + seed, ok
route:list           -> subscribe (4), donate (3), webhooks (2), dashboard/billing (2), admin/payments (3)
payments:expire --dry-run -> 0 checkout(s) e 0 doacao(oes)
webhooks:prune --dry-run  -> 0 evento(s) com mais de 30 dias
```

Testes novos/ampliados da F6 (127 casos declarados): `CheckoutTest` (11),
`MercadoPagoWebhookTest` (6), `GatewayPaymentTest` (15),
`BillingRecurringTest` (8), `SubscriptionPeriodTest` (13), `DonationTest` (24),
`PaymentMaintenanceTest` (6), `MercadoPagoGatewayTest` (9),
`AdminDonationsTest` (5), `AdminWebhookEventsTest` (4),
`AdminPaymentRefundTest` (4), `PaymentNotificationsTest` (15) e
`PixPayloadTest` (7).

### Ajustes da verificacao

- `AdminPanelTest`: `options.methods` de 4 para 6 (novas formas do enum).
- `admin-panel.test.ts`: nove secoes no menu, com Eventos de webhook.
- `DonationController@store`: array tipado chave a chave para o PHPStan 7.
- `PaymentController`: genericos de `LengthAwarePaginator` nas duas abas.
- `account-panel.test.ts`: normalizado pelo Prettier.

## 5. Pendente: smoke real da Mercado Pago

Sem credenciais no ambiente, o smoke real nao foi executado. O criterio 27 do
`docs/acceptance/FASE-6.md` traz o roteiro do dono: tunel HTTPS, contas de teste
do sandbox, Pix/cartao/boleto, preapproval mensal/anual, primeira cobranca
autorizada, cancelamento, recusa, estorno, replay de webhook, doacao dinamica e
fallback estatico. Regra permanente: token e segredo nunca aparecem em chat,
log, git, issue ou documento.

## 6. Riscos e observacoes

- O agendador depende do cron `php artisan schedule:run` (RUNBOOK secao 5); sem
  ele, a expiracao continua correta na leitura, mas a poda nao roda.
- Pix estatico nao confirma sozinho - e contingencia, e a tela diz isso.
- Uma preapproval ja autorizada nao acompanha mudanca de preco; o admin cancela
  e o cliente autoriza de novo.
- Perdoar/estornar periodo futuro nao derruba o periodo corrente.
- O banco `devtools` nao recebeu migrations nem `migrate:fresh`; o smoke usou
  SQLite dedicado.

## 7. Limpeza

`storage/smoke_f6.sqlite` removido; `php artisan config:clear` executado;
nenhum segredo foi impresso ou gravado; nenhum commit ou push.
