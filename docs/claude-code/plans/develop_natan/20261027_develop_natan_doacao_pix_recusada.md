# Plano — Doação PIX recusada no Mercado Pago: diagnóstico e observabilidade

- **Branch:** `develop_natan`
- **Data:** 2026-10-27
- **Origem:** sessão `/grill-with-docs` (survey em `docs/survey/20261027_doacao_pix_recusada_surveyfacts.md`)
- **Status:** aprovado e implementado (Fases 1 a 5 concluídas; ver relatório em `docs/claude-code/reports/develop_natan/`).

## Contexto

Em produção, a página `/{locale}/donate` cria o Pix dinâmico com sucesso (`POST /v1/payments` responde e o QR aparece), mas o pagador vê a transação **recusada** ao confirmar. A recusa não acontece na criação da cobrança: ela acontece depois, no app do banco ou na análise do Mercado Pago.

Hoje o sistema não consegue dizer o motivo:

- `HandleWebhookEvent` recebe `status_detail` no `GatewayPayment`, mas grava apenas `status = failed` e descarta o detalhe.
- A tabela `donations` não tem coluna para o status do provedor.
- `DonationStatus.vue` não atualiza sozinha e não oferece “Já paguei”.
- A mensagem ao doador é sempre `donations.status.failed_hint`.

**Resultado esperado:** descobrir a causa real com a API do Mercado Pago e, na sequência, tornar o motivo visível ao doador e ao admin, com reconsulta sob demanda.

## Decisões fechadas no grill

1. Evidência vem de `GET https://api.mercadopago.com/v1/payments/{gateway_ref}` com o token de produção no VPS.
2. Auto-refresh da página de status (~15 s, só banco local) + botão “Já paguei” que consulta o provedor sob demanda, com rate limit.
3. Persistir `gateway_status` e `gateway_status_detail` apenas em `donations`, gravados na criação, no webhook e na consulta.
4. Motivo traduzido por código (`status_detail`) em 5 idiomas; código cru nunca vai ao doador, só ao admin.
5. Comando `donations:check {ulid}` para diagnosticar no VPS.
6. **Parar após o diagnóstico** para revisão antes de mexer no código.

---

## Fase 0 — Diagnóstico (executa agora e PARA)

### 0.1 Conectar o MCP do Mercado Pago

```bash
claude mcp add --transport http --scope user mercadopago https://mcp.mercadopago.com/mcp
```

Depois `/mcp` → `mercadopago` → concluir o OAuth no navegador. Usar o MCP apenas para documentação, `quality_checklist`/`quality_evaluation` e `simulate_webhook`. **Nunca** colar token, e-mail de pagador ou corpo cru em prompt de MCP.

### 0.2 Localizar a doação que falhou

Se o link `/{locale}/donate/{ulid}` não estiver à mão, listar as últimas falhas sem PII:

```bash
cd /var/www/devtools
php artisan tinker --execute='App\Models\Donation::query()->where("status","failed")->latest("id")->limit(5)->get(["ulid","amount_cents","currency","gateway","gateway_ref","created_at"])->each(fn ($d) => printf("%s | %d %s | %s | ref=%s | %s\n", $d->ulid, $d->amount_cents, $d->currency, $d->gateway, $d->gateway_ref ?? "-", $d->created_at));'
```

### 0.3 Consultar o pagamento no provedor

Token só em variável de shell; nunca `config:show`, `cat .env` ou `set -x`.

```bash
cd /var/www/devtools

PAYMENT_ID=$(php artisan tinker --execute='echo App\Models\Donation::query()->where("ulid","COLE_O_ULID")->value("gateway_ref");' 2>/dev/null | tail -n 1)
TOKEN=$(php artisan tinker --execute='echo config("payments.mercadopago.access_token");' 2>/dev/null | tail -n 1)
test -n "$TOKEN" && echo "token carregado" || echo "token vazio"

curl -sS --fail-with-body \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "https://api.mercadopago.com/v1/payments/$PAYMENT_ID" \
| jq '{
    id, status, status_detail, live_mode, payment_type_id, payment_method_id,
    transaction_amount, date_created, date_of_expiration, collector_id,
    external_reference,
    payer_email_masked: ((.payer.email // "") | if . == "" then null else (.[0:1] + "***@" + (split("@")[1] // "")) end)
  }'

unset TOKEN
```

### 0.4 Conferir o webhook recebido (sem payload)

```bash
php artisan tinker --execute='App\Models\WebhookEvent::query()->where("resource_id","COLE_O_PAYMENT_ID")->get(["id","topic","status","attempts","error","created_at"])->each(fn ($e) => printf("event=%d topic=%s status=%s attempts=%d error=%s at=%s\n", $e->id, $e->topic ?? "-", $e->status->value, $e->attempts, $e->error ?? "-", $e->created_at));'
```

### 0.5 Leitura do resultado

| `status` | `status_detail` | Leitura provável |
|---|---|---|
| `pending` | `pending_waiting_transfer` | QR gerado e não pago; a recusa foi no app do banco do pagador |
| `rejected` | `rejected_by_bank` / `bank_error` | Banco do pagador recusou (limite, saldo, bloqueio, regra noturna) |
| `rejected` | `rejected_high_risk` | Análise de risco do Mercado Pago |
| `rejected` | `rejected_by_regulations` | Regras regulatórias / situação da conta recebedora |
| `rejected` | `rejected_by_biz_rule` | Regra de negócio da conta Mercado Pago |
| `rejected` | `rejected_insufficient_data` | Dados do pagador insuficientes |
| qualquer | — e `live_mode: false` | Token de teste em produção |
| qualquer | — e `transaction_amount` diferente | Divergência de valor |

Também reportar: `date_of_expiration` vs. `date_created` (QR expirado), `collector_id` e o domínio do e-mail do pagador mascarado.

**Entregar:** tabela com snapshot local + resposta do gateway + evento de webhook, a causa provável e o que ela exige (ação no código ou na conta Mercado Pago). **Parar aqui.**

---

## Fase 1 — Persistir o status do provedor

- **Migration** `database/migrations/2026_10_27_000001_add_gateway_status_to_donations_table.php`: `gateway_status` string(32) nullable e `gateway_status_detail` string(64) nullable. Sem default, sem índice, sem `after()`. Retrocompatível: código antigo ignora as colunas; rollback só depois de o código novo sair.
- `app/Models/Donation.php`: adicionar as duas colunas ao atributo `#[Fillable]` e aos `@property`.
- `database/factories/DonationFactory.php`: defaults `null`.
- `app/Support/Payments/GatewayPixCharge.php`: último parâmetro opcional `?string $statusDetail = null`.
- `app/Support/Payments/GatewayPayment.php`: campos opcionais de diagnóstico ao final (`payerEmail`, `collectorId`, `liveMode`, `createdAt`, `expiresAt`). **Só o comando consome**; nunca logar, dar dump ou serializar este DTO.
- `app/Services/Payments/MercadoPagoGateway.php`: mapear `status_detail` na criação e, em `payment()`, `payer.email`, `collector_id`, `live_mode`, `date_created` e `date_of_expiration`.
- `app/Actions/Donations/CreateDonation.php`: gravar `gateway_status`/`gateway_status_detail` no mesmo `forceFill()` que salva `gateway_ref` e o QR. Pix estático fica com ambos `null`.

## Fase 2 — Um único caminho de sincronização

- **Novo** `app/Enums/DonationSyncOutcome.php`: `Confirmed`, `Declined`, `Refunded`, `Unchanged`.
- **Novo** `app/Actions/Donations/SyncDonationPayment.php` — usado pelo webhook, pelo botão e pelo comando:
  1. grava `gateway_status`/`gateway_status_detail` (com `mb_substr`);
  2. `refunded`/`charged_back` → `refunded` via update condicional atômico (não rebaixa `paid`);
  3. `approved` → delega ao `ConfirmDonation` (com valor, moeda e `paidAt`);
  4. `rejected`/`cancelled` → `failed` somente se ainda `pending`, via update condicional;
  5. demais status → `Unchanged`.
- `app/Actions/Payments/HandleWebhookEvent.php`: trocar `ConfirmDonation` por `SyncDonationPayment`; **resolver a doação antes** do ramo de estorno. Remover o ramo de doação de `syncRefund()`, que fica inalcançável.
- `app/Actions/Donations/ConfirmDonation.php`: aceitar `Pending` **ou** `Expired` (ADR-048 §9). O `lockForUpdate()` + checagem dentro da transação continuam sendo a idempotência.
- **Novo** `app/Console/Commands/CheckDonation.php` — `donations:check {ulid}`:
  - valida ULID, imprime snapshot local;
  - exige `gateway = mercadopago`, `gateway_ref` e token;
  - consulta o pagamento, confere `external_reference` (`don_<ulid>`);
  - imprime `status`, `status_detail`, `live_mode`, meio de pagamento, valor, datas, `collector_id` e e-mail do pagador **mascarado** (`Str::mask`);
  - chama `SyncDonationPayment` e imprime o novo estado. Grava estado (não é somente leitura).

## Fase 3 — Página de status: auto-refresh, botão e motivo

- `routes/web.php`: dentro do grupo `{locale}`, após `donate.status`:
  ```php
  Route::post('donate/{donation}/check', [DonationController::class, 'check'])
      ->where('donation', '[0-9A-HJKMNP-TV-Z]{26}')
      ->middleware('throttle:donation-check')
      ->name('donate.check');
  ```
  CSRF permanece; ULID é a capacidade; sem binding implícito.
- `PaymentsServiceProvider`: `RateLimiter::for('donation-check', ...)` com `Limit::perMinute(config('donations.check_per_minute'))->by('donation-check:'.$request->ip())`. Resposta do limite: flash `donations.check.throttled` + `back()`.
- `config/donations.php`: `status_poll_ms` (`DONATIONS_STATUS_POLL_MS`, default 15000; 0 desliga) e `check_per_minute` (`DONATIONS_CHECK_PER_MINUTE`, default 5).
- `.env.example`: as duas variáveis com comentário.
- `DonationController::status()`: props `donation.canCheck` e `donation.failureReason` (string já traduzida, `null` fora de `failed`) + `pollMs` no topo. `canCheck` = status efetivo `pending`/`expired` **e** gateway `mercadopago` **e** `gateway_ref` **e** token disponível.
- `DonationController::check()`: busca por ULID, recusa estado já resolvido, consulta o gateway, confere `external_reference`, chama `SyncDonationPayment`, captura `PaymentGatewayException`/`PaymentVerificationException`, flash de toast e redirect para `donate.status`.
- **Novo** `app/Enums/DonationFailureReason.php`: `fromStatusDetail()` mapeando `rejected_high_risk`, `rejected_by_bank`/`bank_error`, `rejected_by_regulations`, `rejected_insufficient_data`, `rejected_by_biz_rule` e fallback `other`; `translationKey()` devolve `donations.reasons.*`.
- `resources/js/types/donations.ts`: `canCheck`, `failureReason` e `pollMs`.
- `resources/js/composables/useRoutes.ts`: `donationCheck(ulid)` a partir da rota Wayfinder gerada.
- `DonationStatus.vue`: `usePoll(props.pollMs, { only: ['donation'] }, { autoStart: false, background: 'pause' })`; inicia com `pending`, para ao mudar de estado, `stop()` no unmount; botão “Já paguei” só com `canCheck`, desabilitado/`aria-busy` durante a consulta; `statusHint` usa `failureReason` quando presente.
- i18n nos 5 idiomas: `reasons.*` e `check.*`.

## Fase 4 — Admin

- `PaymentController::donationRows()`: expor `gatewayStatus` e `gatewayStatusDetail`.
- `resources/js/types/admin.ts`: campos no `AdminDonationRow`.
- `resources/js/pages/admin/Payments.vue`: linhas no diálogo da doação, somente quando presentes, em monospace.

## Fase 5 — Documentação

- **Novo** `docs/adr/ADR-059-consulta-sob-demanda-e-diagnostico-de-doacoes-pix.md`.
- Emendas em ADR-047/048: `pending|expired -> paid`.
- ARQUITETURA, SPEC, acceptance F6, glossário, guia do Mercado Pago, RUNBOOK.
- Artefatos obrigatórios: plano, relatório, `plansfacts` e `surveyfacts`.

## Testes

**Pest**

- `tests/Unit/DonationFailureReasonTest.php`: mapa de códigos + fallback.
- `tests/Feature/DonationTest.php`: persistência na criação; webhook grava detalhe de recusa/pendente/estorno; vencida aprovada confirma; motivo traduzido sem código cru; `canCheck`; `pollMs`.
- `tests/Feature/DonationCheckTest.php`: aprova → paga; pendente → inalterado; recusa → falha; vencida paga no prazo → confirma; paga/estornada não consulta; estática e sem `gateway_ref` recusam; erro do gateway; valor divergente; referência divergente; rate limit; ULID inválido.
- `tests/Feature/DonationDiagnosticsCommandTest.php`: diagnóstico com e-mail mascarado; sincroniza aprovada; doação inexistente/sem token; não imprime token nem e-mail completo; referência divergente.
- `tests/Feature/MercadoPagoGatewayTest.php`: `status_detail` na criação e campos de diagnóstico.
- `tests/Feature/AdminDonationsTest.php`: expõe status/detalhe.
- `Http::fake` + `Http::preventStrayRequests()`; flashes via `assertInertiaFlash`.

**Vitest**

- `resources/js/__tests__/donation-status.test.ts`: motivo; botão; POST e carregamento; polling (início, parada, unmount, `pollMs = 0`).

## Verificação

```bash
composer test
php artisan test tests/Feature/DonationTest.php tests/Feature/DonationCheckTest.php \
  tests/Feature/DonationDiagnosticsCommandTest.php tests/Feature/AdminDonationsTest.php \
  tests/Feature/MercadoPagoGatewayTest.php tests/Unit/DonationFailureReasonTest.php
npm run types:check
npm run test -- donation-status
npm run lint:check && npm run format:check
```

VPS após o deploy:

```bash
cd /var/www/devtools
php artisan migrate --force
php artisan optimize:clear && php artisan optimize && php artisan queue:restart
php artisan route:list --name=donate
php artisan donations:check COLE_O_ULID
```

## Riscos e limites

- Cache de config após editar `.env`.
- PII: e-mail do pagador só no DTO de diagnóstico e no comando mascarado.
- `failed` não volta para `paid`; anomalia de dados exige decisão à parte.
- Polling para em `expired`; atualização tardia fica com o botão e o webhook.
- Migration nullable segura para workers antigos.
- Recusas de banco/risco/regulação não são corrigíveis por código.
- Nenhum commit/push: o usuário aprova cada commit pelo gitpr.
