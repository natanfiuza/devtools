# Relatório — Doação PIX recusada: diagnóstico e observabilidade

- **Branch:** `develop_natan`
- **Data:** 2026-10-27
- **Origem:** sessão `/grill-with-docs` + plano aprovado em `docs/claude-code/plans/develop_natan/20261027_develop_natan_doacao_pix_recusada.md`
- **Decisão:** ADR-059 (`docs/adr/ADR-059-consulta-sob-demanda-e-diagnostico-de-doacoes-pix.md`)

## 1. Resumo

A página `/{locale}/donate` criava o Pix dinâmico com sucesso, mas a transação
aparecia **recusada** na confirmação. O diagnóstico em produção mostrou que a
recusa não vinha do pagador: o QR estava nascendo com **5 minutos** de validade
por causa de uma chave vazia no `.env` (`(int) '' = 0` e o piso antigo era 5),
e o sistema não tinha como explicar o motivo — o `status_detail` do provedor era
descartado.

A entrega endurece o prazo do QR, passa a persistir o status/detalhe do
provedor, unifica a sincronização da doação em uma única ação, adiciona consulta
sob demanda ("Já paguei" + `donations:check`), traduz o motivo da recusa ao
doador e expõe o código cru apenas no admin. O webhook continua sendo o **único
caminho automático** de confirmação: não há conciliação em cron.

## 2. Diagnóstico

### 2.1 Sintoma e evidência

- As cinco doações com falha estavam `cancelled`/`expired` no provedor, com
  `live_mode: true`, `collector_id 78128962` (conta correta) e
  `date_of_expiration` exatamente **4m59s** depois de `date_created`.
- O QR era exibido, portanto a criação (`POST /v1/payments`) funcionava; a
  "recusa" era o vencimento precoce.

### 2.2 Causa raiz

`config/donations.php` usava `(int) env('DONATIONS_EXPIRES_MINUTES', 60)`. Com a
chave **presente e vazia** no `.env`, `env()` devolve `''`, `(int) ''` vira `0`
e o piso antigo `max(5, 0)` reduzia o QR a 5 minutos — curto demais para
escanear e pagar.

### 2.3 Lacunas de observabilidade reveladas

- `HandleWebhookEvent` recebia `status_detail` no `GatewayPayment` mas gravava
  apenas `status = failed`, descartando o motivo.
- `donations` não guardava o que o provedor respondeu.
- A página de status não se atualizava e não oferecia "Já paguei".
- A mensagem ao doador era sempre o aviso genérico de falha.

## 3. O que foi implementado

### 3.1 Prazo do QR e configuração

- `config/donations.php`:
  - `expires_minutes` = `max(30, (int) (env('DONATIONS_EXPIRES_MINUTES') ?: 60))`
    — chave vazia cai em 60 e o piso subiu de 5 para 30 minutos;
  - `status_poll_ms` (`DONATIONS_STATUS_POLL_MS`, default 15 000; `0` desliga);
  - `check_per_minute` (`DONATIONS_CHECK_PER_MINUTE`, default 5).
- `.env.example` com as duas variáveis novas e o comentário do prazo.

### 3.2 Persistência do status do provedor

- Migration `2026_10_27_000001_add_gateway_status_to_donations_table.php`:
  `gateway_status` (32) e `gateway_status_detail` (64), nullable, sem default,
  sem índice e sem backfill — retrocompatível com workers antigos.
- `Donation` (fillable + `@property`) e `DonationFactory` com `null`.
- `GatewayPixCharge` ganhou `statusDetail` opcional.
- `GatewayPayment` ganhou os campos de diagnóstico `payerEmail`, `collectorId`,
  `liveMode`, `createdAt` e `expiresAt` — consumidos **somente** pelo comando,
  nunca logados nem serializados.
- `MercadoPagoGateway` mapeia `status_detail` na criação e os campos extras em
  `payment()`.
- `CreateDonation` grava status/detalhe no mesmo `forceFill()` do `gateway_ref`;
  Pix estático fica com ambos `null`.

### 3.3 Um único caminho de sincronização

- Novo enum `DonationSyncOutcome` (`Confirmed`, `Declined`, `Refunded`,
  `Unchanged`).
- Nova ação `SyncDonationPayment` — usada pelo webhook, pelo botão e pelo
  comando:
  1. grava `gateway_status`/`gateway_status_detail` (com `mb_substr`);
  2. `refunded`/`charged_back` → `refunded` por update condicional;
  3. `approved` → `ConfirmDonation` (valor, moeda e `paidAt`);
  4. `rejected`/`cancelled` → `failed` somente a partir de `pending`;
  5. demais status → `Unchanged`.
- `HandleWebhookEvent` resolve a doação **antes** do ramo de estorno (o estorno
  dela também precisa gravar o detalhe) e delega ao `SyncDonationPayment`; o
  ramo de doação saiu de `syncRefund()`.
- `ConfirmDonation` aceita `pending` **ou** `expired` (ADR-048 §9: dinheiro que
  entrou confirma), mantendo `lockForUpdate()` + checagem dentro da transação
  como idempotência.

### 3.4 Consulta sob demanda

- Rota `POST /{locale}/donate/{donation}/check` (`donate.check`), ULID na URL
  com `where` `[0-9A-HJKMNP-TV-Z]{26}`, CSRF mantido e throttle nomeado.
- `PaymentsServiceProvider` registra o limiter `donation-check` com chave
  explícita por IP (`donations.check_per_minute`), respondendo toast traduzido em
  vez de página 429.
- `DonationController@status()` publica `donation.canCheck`,
  `donation.failureReason` (string já traduzida, só em `failed`) e `pollMs`.
  `canCheck` = status efetivo `pending`/`expired` **e** gateway `mercadopago`
  **e** `gateway_ref` **e** token disponível.
- `DonationController@check()` recusa estado resolvido, consulta
  `GET /v1/payments/{gateway_ref}`, confere `external_reference = don_<ulid>`,
  sincroniza e responde com flash de toast (`success`/`info`/`error`/`warning`).
- `DonationFailureReason::fromStatusDetail()` mapeia `rejected_high_risk`,
  `rejected_by_bank`/`bank_error`, `rejected_by_regulations`,
  `rejected_insufficient_data`, `rejected_by_biz_rule` e fallback `other` para
  `donations.reasons.*`.
- Página `DonationStatus.vue`: `usePoll(props.pollMs, { only: ['donation'] },
  { autoStart: false, background: 'pause' })`, iniciado só em `pending`, parado
  ao sair do estado e no unmount; botão "Já paguei" com `aria-busy`, ícone
  girando e erro local em `aria-live`; `statusHint` usa o motivo traduzido
  quando existe.
- Tipos TS (`donations.ts`) e `useRoutes.donationCheck(ulid)` a partir da rota
  Wayfinder gerada.

### 3.5 Diagnóstico no VPS

- Novo comando `php artisan donations:check {ulid}`:
  - valida o ULID, imprime o snapshot local;
  - exige `gateway = mercadopago`, `gateway_ref` e token;
  - consulta o pagamento e confere `external_reference`;
  - imprime `status`, `status_detail`, `live_mode`, meio, valor, datas,
    `collector_id` e e-mail do pagador **mascarado** (`Str::mask`);
  - chama `SyncDonationPayment` e imprime o novo estado — **escreve estado**,
    não é somente leitura;
  - pagamento aprovado em doação já `failed` é mostrado como anomalia e não
    reabre a doação.

### 3.6 Admin

- `PaymentController@donationRows()` expõe `gatewayStatus` e
  `gatewayStatusDetail`.
- `AdminDonationRow` e o diálogo de `admin/Payments.vue` mostram os campos
  somente quando presentes, em monospace; o código cru é visível só no admin.

### 3.7 i18n

- `lang/{pt_BR,pt_PT,en,es,fr}/donations.php`: blocos `reasons.*` e `check.*`
  (button, loading, hint, success, pending, failed, refunded, already,
  unavailable, error, throttled), com paridade de folhas nos cinco idiomas.
- `lang/*/admin.php`: rótulos dos campos de status/detalhe do provedor.

## 4. Testes

### 4.1 Pest

| Arquivo | Casos | Resultado |
| --- | --- | --- |
| `tests/Feature/DonationCheckTest.php` | 12 | ok |
| `tests/Unit/DonationFailureReasonTest.php` | 10 | ok |
| `tests/Feature/DonationDiagnosticsCommandTest.php` | 10 | ok |
| `tests/Feature/DonationTest.php` (ampliado) | — | ok |
| `tests/Feature/MercadoPagoGatewayTest.php` (ampliado) | — | ok |
| `tests/Feature/AdminDonationsTest.php` (ampliado) | — | ok |

Cobrem: persistência na criação/webhook/estorno; doação vencida confirmada por
webhook; motivo traduzido sem expor `rejected_by_bank`; `canCheck` em cada
combinação; `pollMs`; endpoint (aprovado, pendente, recusado, erro de gateway,
valor divergente, referência divergente, rate limit por IP, ULID inválido e ULID
desconhecido); comando (diagnóstico, máscara de e-mail, token ausente, referência
divergente, anomalia, Pix estático); admin.

Correção de contrato descoberta durante os testes: um POST para ULID em formato
inválido não casa com o `where` da rota e cai no fallback, que é **GET-only** —
portanto responde **405**, não 404; o 404 fica com o ULID válido e desconhecido.

### 4.2 Vitest

- `resources/js/__tests__/donation-status.test.ts` — 8 testes: motivo no lugar
  do aviso genérico; aviso genérico sem motivo; botão só com `canCheck`; POST na
  rota correta com `aria-busy`/texto de carregamento/erro e retorno ao repouso;
  polling só em `pending` com `pollMs > 0`; `pollMs = 0` não inicia; estado
  resolvido não inicia; `pending → paid` para o polling; `stop()` no unmount.

### 4.3 Gates executados

```text
composer test        -> 750 testes, 750 passaram, 16.454 asserções (Pint ok, PHPStan 7 com 0 erros)
npm run test         -> 63 arquivos, 814 testes, 814 passaram
npm run types:check  -> vue-tsc limpo (Wayfinder regenerado com --with-form)
npm run lint:check   -> 0 erros (avisos de estilo pré-existentes no branch)
npm run format:check -> arquivos desta tarefa limpos; restam 116 arquivos
                        pré-existentes fora do padrão, de trabalhos anteriores
                        do branch (resources/js/tools/*, etc.), não tocados aqui
```

Os três arquivos desta tarefa que estavam fora do padrão
(`DonationStatus.vue`, `admin/Payments.vue` e `donation-status.test.ts`) foram
formatados; o `format:check` global segue acusando **apenas** arquivos
anteriores a esta tarefa.

> **Nota para revisão:** `DonationStatus.vue` e `admin/Payments.vue` já estavam
> fora do Prettier no `HEAD` do branch (confirmado rodando o Prettier sobre a
> versão do `HEAD`). Ao formatá-los, o diff inclui reindentação — o de
> `Payments.vue` fica maior que a mudança funcional, que se resume a expor
> `gatewayStatus`/`gatewayStatusDetail` no diálogo da doação.

## 5. Documentação

- **Novo** `docs/adr/ADR-059-consulta-sob-demanda-e-diagnostico-de-doacoes-pix.md`.
- **Emendas** em `ADR-047` (doação resolvida antes do estorno; `SyncDonationPayment`;
  webhook como único caminho automático) e `ADR-048` (`pending|expired -> paid`,
  colunas novas, motivo traduzido, polling/botão/comando, piso de 30 min).
- `docs/ARQUITETURA.md`: "não há conciliação automática", diagrama, tabela de
  camadas, seção de Doações e limites de operação.
- `docs/SPEC.md`: schema, comportamento, admin e nota da correção na F6.
- `docs/acceptance/FASE-6.md`: critérios 18 e 21 revisados e emenda com os
  critérios 28 a 32.
- `docs/glossario.md`: `status_detail`, "consulta sob demanda" e "motivo de
  recusa".
- `docs/guides/mercado-pago.md`: seção 8 com o fluxo "Já paguei", o comando, a
  leitura de `status_detail`/`live_mode`/`collector_id`, a tabela de leitura e o
  aviso de cache (`optimize:clear && optimize`, `queue:restart`); troubleshooting
  ampliado.
- `docs/RUNBOOK.md`: migration retrocompatível, variáveis novas e diagnóstico de
  doação com `donations:check`.

## 6. Passos operacionais no VPS (após o deploy)

```bash
cd /var/www/devtools
php artisan migrate --force
php artisan optimize:clear && php artisan optimize && php artisan queue:restart
php artisan route:list --name=donate
php artisan donations:check COLE_O_ULID   # reprocessa a doação afetada e revela o detalhe
```

Garantir no `.env` de produção:

```dotenv
DONATIONS_EXPIRES_MINUTES=60
DONATIONS_STATUS_POLL_MS=15000
DONATIONS_CHECK_PER_MINUTE=5
```

## 7. Riscos e limites

- **Recusa de conta não é bug de código**: `rejected_by_bank`,
  `rejected_high_risk` e `rejected_by_regulations` exigem ação no banco do
  pagador ou na conta Mercado Pago; o sistema passa a explicar, não a reverter.
- **`failed` continua terminal**: só `pending`/`expired` confirmam. Pagamento
  aprovado encontrado depois de `failed` é anomalia de dados — o comando mostra,
  não reabre.
- **Polling não é conciliação**: ele lê apenas o banco local; sem webhook, quem
  resolve é o botão ou o comando.
- **PII**: o e-mail do pagador existe só no DTO de diagnóstico e no comando
  mascarado; nunca em log, prop Inertia ou payload.
- **Cache de config**: com `bootstrap/cache/config.php` ativo o Laravel ignora o
  `.env`; toda mudança de variável exige `optimize:clear && optimize` e
  `queue:restart`.
- **Migration**: colunas nullable são seguras para workers antigos na janela de
  deploy; rodar a migration antes de o código novo escrever nelas (o deploy já
  faz isso na ordem).

## 8. Git

Nenhum commit ou push foi feito. O usuário revisa e aprova pelo `gitpr`.
