# Relatorio - guia do cron e FASE 7 (historico, API publica, hardening, SEO, admin e docs)

**Data:** 2026-10-22
**Branch:** `develop_natan`
**Escopo:** publicar o guia operacional do cron e concluir a FASE 7 do SPEC:
historico opt-in, API publica v1, hardening HTTP, SEO final, telas
administrativas residuais, parada de leitura/escrita da coluna legada e
documentacao final.

Nenhum commit ou push foi feito; o dono revisa e aprova pelo `gitpr`.

## 1. Entregavel 1 - guia do cron

`docs/guides/cron.md` explica as tres pecas que mantem producao viva:

- **cron de deploy** (`deploy/deploy-instaled.sh`, a cada minuto): busca o
  `main`, instala dependencias por hash, builda Vite/SSR em release, troca os
  symlinks, roda `migrate --force`, `optimize` e `queue:restart`;
- **cron do agendador** (`php artisan schedule:run`, a cada minuto): le
  `task_schedules` e enfileira `ExecuteCommand`;
- **worker do supervisor** (`devtools-worker`, dois `queue:work database`):
  executa IA e os comandos agendados.

O guia cobre por que cada peca e obrigatoria, o `flock` do deploy, a fila e
suas tentativas/timeouts, o que acontece sem cada cron, como instalar e
verificar, problemas comuns e o mapa de arquivos. Tambem documenta que
`composer dev`/`php artisan dev` **nao** sobem o agendador e que
`last_run_at` nao e atualizado.

Correcoes que vieram junto:

- `docs/RUNBOOK.md`: comando instalado (`deploy-instaled.sh`),
  `accounts:purge-deleted` e `history:prune` na tabela do agendador,
  `webhooks:prune` alinhado a `30 3 * * *`, checklist de producao e nota sobre
  `last_run_at`.
- `CLAUDE.md`: a linha do deploy passou a apontar a copia instalada.

## 2. FASE 7 - o que foi entregue

### Historico

- Migration aditiva `users.history_enabled` (default `false`) com backfill por
  `whereExists` em `ai_requests.history_visible = true`; o default de
  `ai_requests.history_visible` continua `false`.
- `StartAiQuery` grava `history_visible` a partir de `$user->history_enabled`
  nas duas criacoes (fila e cache hit).
- Tela de historico somente para plano pago: Free recebe `locked` e CTA, sem
  consultar linhas; paginacao de 20; toggle global, retencao de 90 dias vinda
  de `AI_HISTORY_RETENTION_DAYS`, e "apagar historico".
- `ClearHistory` apaga apenas linhas terminais (`done`/`failed`) da conta;
  `history:prune` (diario as 03:45, semeado em `task_schedules`) remove
  terminais antigos e aceita `--days`/`--dry-run`.
- Exportacao inclui o historico sem segredo; anonimizacao apaga `ai_requests`
  e revoga todos os tokens Sanctum.
- i18n nos cinco idiomas para toggle, retencao, exclusao e estado bloqueado.

### API publica v1

- Sanctum com Personal Access Token, ability `ai:query`, sem sessao/CSRF;
  migration aditiva `ai_requests.api_token_id`.
- Rotas em `/api/v1`: `POST/GET /sql-queries`, `GET /sql-queries/{ulid}`,
  `GET /me` e `GET /output-targets`, todas com `auth:sanctum`, `active`,
  `verified` e `abilities:ai:query`.
- Consultas escopadas pelo token (`api_token_id`): outro token da mesma conta,
  consulta da web ou conta alheia respondem 404. A lista aceita filtro
  `status`.
- Mesma cota do plano via `usage_ledger` (402 quando esgotada) e rate limits
  por token em `config/api.php` (POST 30/min e 300/h; leitura 120/min), com
  `Retry-After` no 429. CORS fechado por padrao, sem credenciais.
- Tela `settings/api-tokens` para criar (valor em texto puro mostrado uma
  vez), listar e revogar; nome unico por conta.
- `docs/API.md` e `docs/openapi.yaml`; sem pagina publica `/docs`.
- `ForceJsonResponse` em `api/*` garante o envelope mesmo sem `Accept`
  (achado no smoke; ver ajustes).

### Hardening

- `SecurityHeaders` gera nonce por resposta, injeta em `Vite::useCspNonce` e
  emite CSP em `enforce`, `report-only` ou `off`; `style-src` mantem
  `'unsafe-inline'` por causa do tema/shadcn-vue.
- `Strict-Transport-Security` so em requisicao segura, com
  `HSTS_MAX_AGE`/`includeSubDomains`; `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy` e `X-Frame-Options: DENY`.
- TrustProxies configuravel por `TRUSTED_PROXIES`, lido a cada request;
  `forceScheme('https')` quando configurado; cookie de sessao `secure` por
  padrao em producao, mantendo `http_only` e `same_site=lax`.
- Turnstile apenas no cadastro, siteverify via `Http` com timeout e falha
  fechada; sem chaves o widget nao aparece e o cadastro segue (aviso no
  RUNBOOK).

### SEO final

- `Seo::NOINDEX` cobre settings, seguranca, aparencia, desafio 2FA e tokens de
  API.
- JSON-LD em `app.blade.php`: Organization + WebSite na home;
  SoftwareApplication + BreadcrumbList nas tools; nada em pagina `noindex`.
- `twitter:title`, `twitter:description`, `twitter:image` e `twitter:image:alt`
  completos; canonical/OG/sitemap em HTTPS com TrustProxies.
- 404 customizada com componente `Error`, layout publico, status 404,
  `noindex`, sem card/hreflang/JSON-LD e CTA para a home.

### Admin residual

- `admin/output-targets`: catalogo somente leitura com idioma, dialetos e
  plano exigido; PATCH alterna apenas `enabled` com auditoria
  `OutputTargetUpdated`.
- `admin/ai-cache`: somente metadados (hash truncado, modelo, versao, tokens,
  hits, datas, expirado), filtro `valid`/`expired` e poda das expiradas com
  auditoria `AiCachePruned`. Prompt, schema, payload e resposta nunca chegam
  as props.
- Sem newsletter ou campanhas.

### Coluna legada `usage_ledger.ai_request_id`

- `UsageLedger`, `AiLedger` e a factory deixaram de ler/escrever a coluna; o
  ledger continua debitando normalmente e a coluna permanece nula.
- O `DROP` **nao** entrou nesta entrega: um worker antigo entre `migrate` e
  `queue:restart` ainda poderia inserir a coluna. A migration destrutiva fica
  para o deploy seguinte, documentada no RUNBOOK/SPEC.

### Documentacao

- `docs/SPEC.md`, `docs/RUNBOOK.md`, `docs/ARQUITETURA.md`,
  `docs/glossario.md`, `CHANGELOG.md`, `docs/acceptance/FASE-7.md` e
  `docs/guides/cron.md`.
- ADRs 050 (API v1), 051 (historico opt-in/retencao/exclusao), 052
  (headers/CSP/proxies/Turnstile) e 053 (JSON-LD/metas/404), com emendas nas
  ADRs 028, 040 e 042.
- `README.md` e o rotulo/comentario "Docs entra na F7" foram corrigidos.

## 3. Gates finais (2026-10-22)

```text
composer test        -> 654 testes, 654 passaram, 13.962 assercoes (Pint ok, PHPStan 7 com 0 erros)
npm run test         -> 57 arquivos, 771 testes, 771 passaram
npm run types:check  -> vue-tsc limpo (Wayfinder regenerado)
npm run lint:check   -> 0 erros (10.513 avisos de estilo pre-existentes)
npm run format:check -> "All matched files use Prettier code style!"
npm run build        -> ok (20,42 s; aviso apenas de chunk > 500 kB)
npm run build:ssr    -> ok (15,79 s; bootstrap/ssr/ssr.js gerado)
migrate:fresh --seed -> SQLite dedicado storage/smoke_f7.sqlite: 36 migrations + seed ok
                        (9 alvos, 3 planos, 4 rotinas)
route:list           -> api/v1 (me, output-targets, sql-queries index/store/show),
                        settings/api-tokens (3), dashboard/history (3),
                        admin/output-targets (2), admin/ai-cache (2)
schedule:list        -> 4 closures ativas; task_schedules com
                        accounts:purge-deleted dailyAt:03:00, payments:expire */5,
                        webhooks:prune 30 3, history:prune 45 3
dry-runs             -> history:prune 0, ai:prune-cache 0, payments:expire 0/0,
                        webhooks:prune 0
smoke local          -> CSP nonce no header e no HTML (32 chars, iguais na mesma
                        resposta), HSTS/XCTO/Referrer-Policy/Permissions-Policy/
                        X-Frame-Options, sem HSTS em HTTP direto; canonical/OG em
                        https; home com Organization + WebSite; 404 com componente
                        Error e noindex; /register sem cf-turnstile; API 401 sem
                        Accept, /me 200, /output-targets 200 (9 alvos),
                        /sql-queries 200 e POST invalido 422 no envelope
```

## 4. Ajustes feitos durante a verificacao

- **Smoke da API sem `Accept`**: `GET /api/v1/me` sem o cabecalho devolvia 500,
  porque o `Authenticate` montava o redirect de login com `{locale}` antes do
  handler de excecoes. `ForceJsonResponse` passou a fixar
  `Accept: application/json` em `api/*`; o teste "401 JSON mesmo sem o
  cabecalho Accept" cobre o caso.
- **Pint**: espacos em `bootstrap/app.php` e fim de linha nos
  `lang/*/admin.php` foram corrigidos antes da suite final.
- **PHPStan 7**: removido `instanceof` sempre verdadeiro em
  `ResolvesApiIdentity` e nullsafe desnecessario em `ApiSqlQueryResource`.
- **Vitest**: `account-panel.test.ts` passou a esperar o item "Tokens de API"
  no grupo de settings; 27/27 no arquivo e 771/771 na suite.
- **ESLint**: `inertiaApp.ts` teve o `no-fallthrough` resolvido agrupando
  `DonationStatus`/`Error` no mesmo `return` do layout publico.
- **Prettier**: seis arquivos foram normalizados.
- **AdminPanelTest**: comparacao de `actionTypes` com
  `AdminActionType::cases()` em vez de numero fixo.
- **Cache de IA sem alvo**: a tabela nao tem coluna de alvo; derivar o alvo
  exigiria decodificar `canonical_payload`, proibido pelo projeto. A tela
  exibe so metadados e a poda, com o desvio registrado em
  `docs/acceptance/FASE-7.md` e no codigo.

## 5. Riscos e observacoes

- **Smoke real da Mercado Pago** continua pendente da F6 por falta de
  credenciais; nao bloqueia a F7 e o roteiro esta em
  `docs/acceptance/FASE-6.md`.
- **CSP**: `style-src` usa `'unsafe-inline'` por causa do tema e do
  shadcn-vue; um host externo novo exige atualizar a politica. Rollback
  operacional: `CSP_MODE=report-only`.
- **TrustProxies**: sem `TRUSTED_PROXIES=127.0.0.1` no VPS, HSTS, canonical e
  rate limit por IP ficam errados; o RUNBOOK traz o checklist.
- **Turnstile**: desativado sem chaves; com chaves, indisponibilidade da
  Cloudflare bloqueia cadastros (falha fechada).
- **Retencao**: varredura diaria; uma linha pode viver ate ~24 h alem do
  prazo. A exclusao manual nao apaga consulta em voo.
- **Coluna legada**: o `DROP` fica para o deploy seguinte, quando nenhum
  worker antigo referenciar a coluna.
- **Banco `devtools`**: nao recebeu migrations nem `migrate:fresh`; a
  verificacao usou SQLite dedicado.

## 6. Limpeza

`storage/smoke_f7.sqlite`, o log do servidor local e os arquivos temporarios
de smoke foram removidos; `php artisan config:clear` foi executado. Nenhum
segredo, token ou schema foi impresso em chat, log, git ou documento. Nenhum
commit ou push.
