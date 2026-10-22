# Guia do cron + FASE 7 (API pública, histórico, SEO final, hardening e docs)

## Context

O pedido atual tem duas partes: criar um guia em `docs/guides` explicando como o cron funciona e por que ele é obrigatório em produção; e avançar para a FASE 7, a última fase do SPEC.

A F6 (pagamentos, doações e webhooks) está implementada e aceita, com um único critério pendente: o smoke real da Mercado Pago, que depende de credenciais e permanece no `docs/acceptance/FASE-6.md`. A F7 fecha o que o SPEC deixou para o fim: histórico (opt-in, retenção de 90 dias, planos pagos e "apagar histórico"), API pública completa, SEO final, hardening, o resíduo de admin (`output_targets` e cache de IA), a coluna legada `usage_ledger.ai_request_id` e a documentação final.

O código confirma as lacunas: o histórico é somente leitura e abre para qualquer conta; `StartAiQuery` grava `history_visible => true` em toda solicitação; não há retenção nem exclusão; não há `routes/api.php` nem Sanctum; não há CSP, HSTS, TrustProxies ou Turnstile; o `NOINDEX` não cobre `profile.edit`/`security.edit`/`appearance.edit` nem `two-factor.login`; não há JSON-LD, 404 customizada ou `twitter:title/description/image`; e `output_targets`/`ai_cache` não têm tela.

## Decisões fechadas nesta sessão

| Tema | Decisão |
|---|---|
| API pública | Sanctum PAT, endpoints assíncronos `POST/GET /api/v1/sql-queries`, lista paginada, `GET /api/v1/me` e `GET /api/v1/output-targets`; mesma cota do plano + rate limits próprios; documentação em `docs/API.md` + `docs/openapi.yaml`, sem página pública `/docs`. |
| Histórico | Toggle global na tela de Histórico, desligado por padrão; só planos pagos veem a tela (Free vê CTA de upgrade); linhas existentes preservadas; retenção de 90 dias; "apagar histórico" apaga tudo do usuário; incluir no export e na anonimização. |
| Hardening | Headers de segurança + CSP enforce por nonce, HSTS, XCTO, Referrer-Policy, Permissions-Policy, frame-ancestors; TrustProxies configurável; cookies seguros em produção; Turnstile no cadastro. |
| SEO final | Correção de noindex (settings e 2FA), JSON-LD Organization/WebSite na home e SoftwareApplication/BreadcrumbList nas tools, Twitter cards completos e 404 customizada com noindex. Sem `lastmod` e sem FAQ/Offer. |
| Coluna legada | Duas etapas: F7 para de ler/escrever `ai_request_id`; o DROP fica para o deploy seguinte. |
| Admin | Telas somente leitura de `output_targets` (com toggle `enabled`) e de `ai_cache` (com poda das expiradas). Sem newsletter/campanhas. |
| Docs | Sem página pública de documentação; remover o rótulo/comentário "Docs entra na F7". |

Regras que valem em toda a fase: nenhum commit ou push; migrations retrocompatíveis (deploy por cron a cada minuto); strings visíveis via i18n nos cinco idiomas; nunca logar schema/prompt; nenhum segredo em chat, git, log, issue ou documento.

---

## Entregável 1 — `docs/guides/cron.md`

Guia em português sem acentos, no formato dos guias existentes (`github-auth.md`, `google-analytics.md`): `# Guia - ...`, seções numeradas `##`, tabelas, blocos bash/cron e mapa de arquivos. Conteúdo:

1. **Visão geral** — os dois crons independentes mais o supervisor:

| Peça | Linha/comando | Sem ela |
|---|---|---|
| Deploy | `* * * * * /var/www/devtools/deploy/deploy-instaled.sh` | nenhum código novo chega ao servidor |
| Agendador | `* * * * * cd /var/www/devtools && php artisan schedule:run` | `payments:expire`, `webhooks:prune`, `accounts:purge-deleted` e `history:prune` não rodam |
| Worker | supervisor `devtools-worker` com 2 `queue:work database` | consultas de IA e comandos agendados ficam parados na fila |

2. **O cron de deploy** — por que a cópia instalada (`deploy-instaled.sh`) e não `deploy/deploy.sh`; o que o script faz na ordem (fetch/pull `--ff-only`, dependências por hash, build Vite/SSR em release, troca atômica de symlinks, `migrate --force`, `optimize`, `queue:restart`, reinício do SSR); o `flock` que impede execuções empilhadas; por que a cada minuto (o `git fetch` sai rápido quando o `main` não mudou); troca manual da cópia conforme `deploy/deploy-instaled.md`.
3. **O cron do agendador** — `bootstrap/app.php` lê `task_schedules` e agenda `ExecuteCommand` na fila `database`; mapeamento de expressões (`dailyAt:`, `hourly`, `everyMinute`, cron); timezone `America/Recife`; tabela das rotinas semeadas, incluindo `accounts:purge-deleted` (que falta no RUNBOOK atual), `payments:expire`, `webhooks:prune` e a nova `history:prune`; comandos que não estão em `task_schedules` (`ai:prune-cache`, `ai:reconcile`); `last_run_at` nunca é atualizado e não há tela de agendamentos; o loop de registro engole exceções e loga `[Scheduler] Agendamento ignorado`.
4. **A fila e o supervisor** — por que `queue:restart` no deploy; `DB_QUEUE_RETRY_AFTER=150`; as propriedades do job vencem o CLI (`ExecuteCommand` tries=1/timeout=0; `ProcessAiQuery` tries=2/timeout=90; `ProcessWebhookEvent` tries=3/timeout=60); sem worker, os jobs de agendamento acumulam.
5. **Por que isso é obrigatório em produção** — cenários concretos (pagamento vencido que só expira na leitura, webhooks acumulando, contas excluídas sem anonimização, consultas de IA eternamente `queued`); no local, `composer dev`/`php artisan dev` sobe servidor, fila e Vite, mas **não** sobe o agendador; para testar rotinas localmente, `php artisan schedule:work` em outro terminal.
6. **Instalação e verificação** — `crontab -l` no usuário certo (o mesmo do clone, por causa do `.git`), PATH explícito do deploy, `supervisorctl status`, `php artisan schedule:list`, dry-runs, logs (`deploy.log`, `schedule.log`, `worker.log`, `laravel.log`).
7. **Problemas comuns** — tabela: nada é deployado (cron de outro usuário, trava, PHP fora do PATH), rotina não rodou (worker parado, `ExecuteCommand` tries=1), duas linhas duplicadas, `last_run_at` vazio, erro de permissão no `.git`.
8. **Mapa de arquivos** — `deploy/deploy.sh`, `deploy/deploy-instaled.sh`, `deploy/deploy-instaled.md`, `deploy/supervisor/devtools-worker.conf`, `bootstrap/app.php`, `app/Models/TaskSchedule.php`, `app/Jobs/ExecuteCommand.php`, migrations de seed, comandos, `docs/RUNBOOK.md`.

Corrigir também o `docs/RUNBOOK.md` e o `CLAUDE.md` (linha 14 diz `deploy/deploy.sh`; o cron real executa `deploy-instaled.sh`).

---

## Entregável 2 — FASE 7

### 0. Preparação

- Branch `develop_natan`, data 2026-10-22. **Nenhum commit/push**; parada única para revisão ao final.
- Criar após o plan mode:
  - `docs/plans/develop_natan/20261022_cron_fase7_plansfacts.md`
  - `docs/survey/20261022_cron_fase7_surveyfacts.md`
  - `docs/claude-code/plans/develop_natan/20261022_develop_natan_cron_fase7.md`
  - `docs/claude-code/reports/develop_natan/20261022_develop_natan_cron_fase7.md` (ao final)
- Checkpoints internos por bloco rodam os testes do bloco; a parada para revisão é uma só.

### 1. Histórico

#### Schema e modelo

- Migration aditiva `add_history_enabled_to_users_table`: `boolean('history_enabled')->default(false)`.
- Backfill na mesma migration: `history_enabled = true` para usuários que já têm ao menos uma linha `ai_requests.history_visible = true` (subquery `whereExists`), preservando o histórico existente.
- `User`: incluir `history_enabled` no `#[Fillable]` e no cast `boolean`.
- Não alterar o default de `ai_requests.history_visible` (permanece `false`); o valor passa a vir do toggle.

#### Regras de backend

- `StartAiQuery`: gravar `history_visible => $user->history_enabled` nas duas criações (cache hit e fila). Aceitar um `?int $apiTokenId = null` e gravar `api_token_id` (ver bloco 2).
- `HistoryController@index`: se `$user->effectivePlan()->isFree()`, devolver `locked: true` sem consultar linhas; senão listar como hoje (`history_visible = true`, paginação 20). Props novas: `enabled`, `locked`, `retentionDays` (de `config('ai.history.retention_days')`), `upgradeUrl` (`route('pricing')`).
- Novas rotas no grupo `auth`+`verified`+`active` de `routes/web.php`:
  - `PATCH dashboard/history/preference` → `dashboard.history.preference` (atualiza `history_enabled`).
  - `DELETE dashboard/history` → `dashboard.history.destroy` (apaga histórico).
  - Ambos exigem plano pago; Free recebe 403.
- Request `UpdateHistoryPreferenceRequest` valida `history_enabled` booleano.
- Ação `App\Actions\Ai\ClearHistory`: apaga as linhas do usuário com status terminal (`done`/`failed`, via `AiRequestStatus::isTerminal()`), nunca `queued`/`processing` em voo; devolve a contagem.
- Semântica: desligar o toggle só impede novas gravações; as linhas antigas continuam visíveis até "apagar histórico" (privacidade explícita na UI).

#### Retenção

- `config/ai.php`: `'history' => ['retention_days' => (int) env('AI_HISTORY_RETENTION_DAYS', 90)]`.
- Comando `app/Console/Commands/PruneHistory.php`: `history:prune {--days=} {--dry-run}`, default de config, apaga somente linhas terminais com `created_at < now()->subDays($days)`; `usage_ledger` e `ai_cache` ficam intactos (cota/contabilidade e cache compartilhado).
- Migration de seed `2026_10_22_000007_seed_history_maintenance_schedule.php`: insere `history:prune` em `task_schedules` com `45 3 * * *`, sem parâmetros (o default vem do config); idempotente por `command`.

#### Conta e privacidade

- `AnonymizeUser`: apagar `ai_requests` do usuário e revogar todos os tokens Sanctum (`$user->tokens()->delete()`).
- `ExportUserData`: incluir `ai_requests` (id, alvo, `schema_source`, dialeto, status, schema, descrição, resultado, tokens, `cache_hit`, `created_at`), sem qualquer segredo.
- Atualizar `docs/acceptance/FASE-4.md`/ADR-028 apenas se o contrato de exportação mudar de forma relevante; o ADR-028 já promete "o que ele gerou".

#### UI e i18n

- `resources/js/pages/dashboard/History.vue`: switch do opt-in, nota de retenção, botão "Apagar histórico" com diálogo de confirmação e estado bloqueado para Free com CTA para `/pricing`.
- `resources/js/types/account.ts`: novos props (`enabled`, `locked`, `retentionDays`, `upgradeUrl`).
- `resources/js/composables/useAccountNav.ts`: item "Tokens de API" (bloco 2).
- `lang/{en,pt_BR,pt_PT,fr,es}/account.php`: `history.toggle.*`, `history.retention`, `history.delete.*`, `history.locked.*`, `nav.api_tokens`. `TranslationsParityTest` cobre.

#### Testes

- `tests/Feature/AccountPanelTest.php`: Free bloqueado; pago lista; toggle off esconde novas; toggle on mantém antigas; exclusão apaga só terminais; retenção preserva ledger; isolamento entre contas.
- Novo `tests/Feature/Ai/HistoryMaintenanceTest.php`: `history:prune` (dry-run, default de config, linha recente intacta, `queued` intacta), seed idempotente.
- `tests/Feature/Settings/ExportDataTest.php` (ou equivalente) e `AnonymizeUserTest`: exporta histórico; anonimização apaga `ai_requests` e tokens.
- Vitest `account-panel.test.ts`: switch, confirmação de exclusão, estado bloqueado.

### 2. API pública v1

#### Sanctum e schema

- `composer require laravel/sanctum`; publicar a migration `create_personal_access_tokens_table` (aditiva); `User` passa a usar `HasApiTokens`; `config/sanctum.php` com guard `web`, expiração nula (tokens revogáveis) e `stateful` vazio.
- Migration aditiva `add_api_token_id_to_ai_requests_table`: `foreignId('api_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete()` + índice. Nulo para consultas da web; preenchido nas da API.
- `bootstrap/app.php`: registrar `api: __DIR__.'/../routes/api.php'` em `withRouting`; alinhar o envelope de erro de `api/ai/*` para `api/*` (o `ApiError` já cobre 402/422/429/401/403/404).

#### Contrato

Rotas em `routes/api.php`, prefixo `v1`, middleware `auth:sanctum`, `active`, `verified`, `abilities:ai:query` (aliases registrados no bootstrap):

| Método | Rota | Resposta |
|---|---|---|
| POST | `/api/v1/sql-queries` | 200 em cache hit / 202 na fila, corpo no contrato do `AiQueryResource` + `poll_url` absoluto |
| GET | `/api/v1/sql-queries/{ulid}` | status/resultado da consulta criada por aquele token |
| GET | `/api/v1/sql-queries` | lista paginada (20) das consultas daquele token, filtro opcional `status` |
| GET | `/api/v1/me` | conta, plano e cota (`Quota::toArray`) |
| GET | `/api/v1/output-targets` | alvos `enabled`, com `slug`, `label`, `language`, `supported_dialects` |

- Controllers em `app/Http/Controllers/Api/V1/` (`MeController`, `OutputTargetController`, `SqlQueryController`).
- Reutilizar `StoreAiQueryRequest` (mesmas regras), `StartAiQuery`, `Quota`, `ApiError` e o `AiQueryResource` (um wrapper `ApiSqlQueryResource` acrescenta `poll_url`/`created_at`; a web não muda).
- `store` passa `api_token_id = currentAccessToken()->id`; `show`/`index` filtram por esse id, então um token não lê consultas de outro token do mesmo usuário (404).
- Consultas da API também seguem `history_enabled` para a tela web, mas a lista da API é escopada pelo token (independe do toggle, pois é o cliente que as criou).

#### Cotas e limites

- Mesma cota do plano via `usage_ledger` (402 `quota_exceeded` quando esgotada); cache hit não debita.
- `config/api.php`: `rate_limits.post_per_minute` (30), `post_per_hour` (300), `read_per_minute` (120), todos por token; env `API_RATE_*`.
- Limiters em `AppServiceProvider`/novo `ApiServiceProvider` (`api-sql-post`, `api-read`), aplicados por rota; 429 com `Retry-After` pelo envelope.
- `config/cors.php` publicado: `paths => ['api/*']`, `allowed_origins` de `API_ALLOWED_ORIGINS` (vazio por padrão), `supports_credentials => false`. Cliente servidor-a-servidor não depende de CORS.

#### Gestão de tokens

- `Settings\ApiTokenController` + rotas no grupo `auth`+`verified`+`active` de `routes/settings.php`:
  - `GET settings/api-tokens` (`api-tokens.index`): lista id, nome, abilities, `last_used_at`, `created_at`.
  - `POST settings/api-tokens` (`api-tokens.store`): nome único por usuário; `createToken($name, ['ai:query'])`; flash com o token em texto puro **uma vez**.
  - `DELETE settings/api-tokens/{token}` (`api-tokens.destroy`): só tokens do próprio usuário; revoga.
- `resources/js/pages/settings/ApiTokens.vue`: criar, revelar/copiar uma vez, revogar com confirmação; item em `useAccountNav`; tipos em `resources/js/types/settings.ts`; i18n `settings.api_tokens.*` nos cinco idiomas.
- Revogação também na anonimização da conta (bloco 1).

#### Documentação

- `docs/API.md`: seção "API pública v1" completa (auth Bearer, endpoints, exemplos, paginação, erros, cotas, rate limits, versionamento/depreciação) e remoção do aviso "fora de escopo ate a F7"; seção de webhooks permanece.
- `docs/openapi.yaml`: OpenAPI 3.1 com security scheme bearer, schemas e exemplos.
- ADR-050: "API pública v1 com Sanctum, ability `ai:query`, cota do plano e rate limits por token".
- Glossário: PAT, ability, rate limit, API pública, versão.

#### Testes

- `tests/Feature/Api/V1/SqlQueryApiTest.php`: sem token 401; token sem ability 403; POST 202/200; polling; lista escopada por token; token de outro usuário 404; validação 422; cota 402; rate limit 429; conta suspensa 403; alvos só `enabled`; nenhum schema/prompt em log.
- `tests/Feature/Settings/ApiTokenSettingsTest.php`: criação mostra o token uma vez; nome duplicado 422; revogação; não revoga token alheio; página noindex (bloco SEO).
- `tests/Feature/Api/V1/ApiErrorEnvelopeTest.php`: formato do envelope em 401/403/404/422/402/429.

### 3. Hardening

#### Headers e CSP

- `config/security.php`: `csp.mode` (`enforce`/`report-only`/`off`, default `enforce`), `hsts.max_age`, `hsts.include_subdomains`, `trusted_proxies` (de `TRUSTED_PROXIES`, default vazio), `force_https` (default produção).
- `app/Http/Middleware/SecurityHeaders.php`, registrado globalmente em `bootstrap/app.php`:
  - Gera nonce (`Str::random(32)`), chama `Vite::useCspNonce($nonce)` e compartilha `cspNonce` com as views.
  - Emite `Content-Security-Policy` (ou `-Report-Only`) em produção; em `off`, nenhum header de CSP.
  - Diretivas: `default-src 'self'`; `script-src 'self' 'nonce-{nonce}' https://www.googletagmanager.com https://challenges.cloudflare.com`; `style-src 'self' 'unsafe-inline'` (necessário para `@fonts` e o `<style>` do tema); `img-src 'self' data: https://www.google-analytics.com https://*.google-analytics.com`; `font-src 'self' data:`; `connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://challenges.cloudflare.com`; `frame-src https://challenges.cloudflare.com`; `frame-ancestors 'none'`; `base-uri 'self'`; `form-action 'self'`; `object-src 'none'`; `upgrade-insecure-requests` quando HTTPS.
  - `Strict-Transport-Security` só em requisição segura; `X-Content-Type-Options: nosniff`; `Referrer-Policy: strict-origin-when-cross-origin`; `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`; `X-Frame-Options: DENY`.
- `app.blade.php`: nonce no script inline do tema; `@vite` herda o nonce do `Vite`; o `<style>` e o `@fonts` ficam cobertos por `style-src 'unsafe-inline'`; o `<script data-page type="application/json">` do Inertia é bloco de dados, não executável.
- GA: a injeção em `resources/js/lib/analytics.ts` é dinâmica e externa; os hosts já estão na política. Mercado Pago é redirect de topo, sem iframe.
- Turnstile entra em `script-src`, `connect-src` e `frame-src`.

#### Proxies, HTTPS e cookies

- `bootstrap/app.php`: `$middleware->trustProxies(at: config('security.trusted_proxies'), headers: Request::HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_PROTO | HEADER_X_FORWARDED_HOST | HEADER_X_FORWARDED_PORT)`.
- `AppServiceProvider`: `URL::forceScheme('https')` quando produção/`security.force_https`, para canonical, OG, sitemap e robots nunca saírem em `http://`.
- `config/session.php`: `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`; manter `http_only` e `same_site=lax` (Strict quebraria o retorno do Mercado Pago).
- `.env.example`: `SESSION_SECURE_COOKIE=false`, `TRUSTED_PROXIES=`, `CSP_MODE=off` (local), `HSTS_MAX_AGE=31536000`, `TURNSTILE_SITE_KEY=`, `TURNSTILE_SECRET_KEY=`; nenhum valor real.
- RUNBOOK: checklist de produção (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES=127.0.0.1` no nginx local, `CSP_MODE=enforce`) e como voltar para `report-only` se a CSP quebrar algo.

#### Turnstile no cadastro

- `config/services.php`: `turnstile.site_key`/`secret_key`.
- `app/Rules/ValidTurnstile.php`: `Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ...)`, compara `success`, usa `remoteip`; sem segredo configurado a regra passa e o widget não aparece (desativado explicitamente, com aviso no RUNBOOK); serviço indisponível falha fechado com erro de validação genérico.
- `app/Actions/Fortify/CreateNewUser.php`: adicionar `turnstile` à validação (obrigatório apenas quando o segredo existe).
- `resources/js/components/TurnstileWidget.vue`: render explícito com `sitekey`, callback que grava o token no form, expiração/erro; `resources/js/pages/auth/Register.vue` inclui o componente e envia `turnstile`.
- i18n: chave de erro em `auth.php` nos cinco idiomas. `phpunit.xml` sem chamadas reais (`Http::fake`).

#### Testes

- `tests/Feature/SecurityHeadersTest.php`: CSP enforce com nonce no script do tema; `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options`, `frame-ancestors`; HSTS só em HTTPS; `report-only`/`off`; nonce diferente por resposta.
- `tests/Feature/TrustProxiesTest.php`: IP e esquema respeitam proxy confiável; proxy não confiável é ignorado.
- `tests/Feature/Auth/TurnstileTest.php`: ausente/inválido/válido; desativado sem chave; `Http::fake` da siteverify.
- `tests/Feature/SessionConfigTest.php` (ou asserção de config): cookie `secure` default em produção.

### 4. SEO final

- `app/Support/Seo.php`: `NOINDEX` passa a cobrir `profile`, `security`, `appearance`, `two-factor` e `api-tokens` (os nomes reais das rotas de settings e o desafio 2FA); teste para cada um.
- Novo `app/Support/StructuredData.php`:
  - Home: `Organization` (nome, URL, logo) + `WebSite` (nome, URL, `inLanguage`).
  - `tools.show`: `SoftwareApplication` (nome, descrição, `applicationCategory: DeveloperApplication`, `operatingSystem: Any`, `isAccessibleForFree: true`, URL, `inLanguage`) + `BreadcrumbList` (Início → ferramenta).
  - Emitir em `app.blade.php` apenas quando `!$noindex`, em `<script type="application/ld+json" nonce>`; JSON com `JSON_HEX_TAG|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`.
- `app.blade.php`: `twitter:title`, `twitter:description`, `twitter:image` e `twitter:image:alt`; `$noindex` também quando `$page['component'] === 'Error'`.
- 404: `resources/js/pages/Error.vue` (componente padrão do Inertia), `PublicLayout` no `resolveLayout`, textos em novo namespace `errors` (`config/locales.php` + `lang/*/errors.php`, cinco idiomas) e CTA para a home; a página sai `noindex` pela regra do Blade.
- Canonical/OG/sitemap passam a usar HTTPS e host corretos com TrustProxies + `forceScheme` (bloco 3).
- Não adicionar `lastmod` ao sitemap (não há data confiável por página) nem FAQ/Offer; registrar a decisão no ADR-053.
- Limpar `resources/js/layouts/PublicLayout.vue` (comentário "Docs entra na F7" e "não há página de preços") e o rótulo `app.docs` se não houver rota.
- Testes: `SeoMetaTest` (settings/2FA noindex, Twitter), novo `StructuredDataTest` (JSON válido, tipos por página, ausência em noindex), novo `ErrorPageTest` (status 404, componente `Error`, noindex), `SitemapTest`/`RobotsTest` sem regressão.

### 5. Admin residual

- `Admin\OutputTargetController@index`/`@update`: lista slug, label, language, dialetos, plano exigido e `enabled`; PATCH alterna somente `enabled`. Rotas em `routes/admin.php` com o middleware de admin existente.
- `Admin\AiCacheController@index`/`@destroyExpired`: lista metadados (`prompt_hash` truncado, alvo, expiração, criação, expirado) com filtro; botão "Remover expiradas" chama `AiCacheRepository::deleteExpired()`; **nunca** exibe prompt, schema ou resposta em cache.
- Páginas `resources/js/pages/admin/OutputTargets.vue` e `AiCache.vue`; item no `AdminLayout`; tipos em `types/admin.ts`; i18n `admin.*` nos cinco idiomas.
- Testes `AdminOutputTargetsTest` e `AdminAiCacheTest` (autorização, listagem, toggle, poda, ausência de conteúdo sensível); atualizar `AdminPanelTest` e `admin-panel.test.ts` (nova seção).

### 6. Coluna legada `usage_ledger.ai_request_id`

- Remover `ai_request_id` de `app/Models/UsageLedger.php` (fillable/@property), `app/Support/Ai/AiLedger.php` e `database/factories/UsageLedgerFactory.php`.
- **Não** criar o DROP nesta entrega: um worker antigo entre `migrate` e `queue:restart` ainda inseriria a coluna e quebraria o lançamento do ledger. A migration `drop_ai_request_id_from_usage_ledger_table` fica documentada para o deploy seguinte, quando nenhum código em produção referenciar a coluna.
- Manter a chave de log `ai_request_id` (ULID) e explicar no RUNBOOK/glossário que ela não é a coluna.
- SPEC/ADR: marcar a coluna como não escrita e o DROP como pendência de um deploy; teste garante que o ledger grava sem a coluna.

### 7. Documentação final

- `docs/guides/cron.md` (Entregável 1).
- `docs/RUNBOOK.md`: incluir `accounts:purge-deleted` e `history:prune` na tabela do agendador; padronizar `webhooks:prune` para `30 3 * * *` (a linha semeada) e o timezone; nota de que `last_run_at` não é atualizado; checklist de produção/hardening; Turnstile; TrustProxies; CSP e rollback; operação da API (criação/revogação de token, rate limits).
- `docs/SPEC.md`: seções 5/6 (histórico entregue com opt-in/retenção/exclusão), 8 (CSP/headers/Turnstile/TrustProxies entregues), 9 (F7 concluída), 10 (API pública e docs), 11 se necessário; remover "API pública futura" e a frase desatualizada de que `history_visible` nasce `false`.
- `docs/API.md` + `docs/openapi.yaml`.
- `docs/ARQUITETURA.md`: camada de API v1, middleware de segurança, comandos de histórico.
- `docs/glossario.md`: PAT, ability, rate limit, opt-in, retenção, CSP, nonce, HSTS, Turnstile, JSON-LD, breadcrumb.
- `CHANGELOG.md`: entrada da F7.
- ADRs: 050 (API pública v1), 051 (histórico opt-in/retenção/exclusão), 052 (headers/CSP/Turnstile/proxies), 053 (JSON-LD e 404; decisão de não usar `lastmod`/FAQ).
- `docs/acceptance/FASE-7.md`: critérios verificáveis, mapa decisão→código/teste, evidências dos gates, pendência herdada do smoke real da MP (fora do escopo).
- `CLAUDE.md`: corrigir a linha do cron para `deploy-instaled.sh`.
- Artefatos da sessão (plansfacts, surveyfacts, cópia do plano e relatório) nos caminhos do bloco 0.

### 8. Verificação

| Gate | Critério |
|---|---|
| `composer test` | Pest + Pint + PHPStan 7 verdes, incluindo os testes novos |
| `npm run test` | Vitest verde (History, ApiTokens, admin, Register/Turnstile) |
| `npm run types:check` | `vue-tsc` limpo com Wayfinder regenerado |
| `npm run lint:check` / `format:check` | 0 erros / Prettier ok |
| `npm run build` / `build:ssr` | ambos ok, SSR sem `window` no setup |
| `migrate:fresh --seed --force` | em SQLite dedicado `storage/smoke_f7.sqlite`, nunca no banco `devtools`; todas as migrations + seeds |
| `route:list` | rotas `api/v1/*`, `settings/api-tokens`, `dashboard/history` (PATCH/DELETE), `admin/output-targets`, `admin/ai-cache` |
| `schedule:list` | `history:prune` presente; as rotinas antigas intactas |
| Dry-runs | `history:prune --dry-run`, `ai:prune-cache --dry-run`, `payments:expire --dry-run`, `webhooks:prune --dry-run` |
| Smoke local | `php artisan serve` + `curl -I` conferindo CSP/HSTS/headers; token real criado pela tela, POST/GET na API; Turnstile desativado sem chaves; 404 e JSON-LD no HTML |
| Segurança | nenhum schema/prompt em log; `git status` sem segredos; nenhum commit/push |

### 9. Ordem de deploy e riscos

1. Migrations aditivas primeiro (`users.history_enabled` com backfill, `ai_requests.api_token_id`, `personal_access_tokens`, seed de `history:prune`); nenhuma destrutiva.
2. Sanctum muda o `composer.json`: o deploy detecta o hash e roda `composer install` com fallback para `composer update` (comportamento já documentado no RUNBOOK).
3. CSP pode bloquear script/estilo não previsto: validar com build de produção e manter `CSP_MODE=report-only` como rollback operacional; default `enforce`.
4. Turnstile depende de serviço externo: sem chaves fica desativado (documentado); com chaves, indisponibilidade falha fechado.
5. Exclusão de histórico só apaga terminais; jobs em voo terminam e podem ser apagados depois. O ledger permanece para cota/contabilidade.
6. API: token revogado, cota esgotada, rate limit e conta suspensa são os casos de borda testados; nenhum schema em log.
7. Coluna legada: DROP adiado para o deploy seguinte; não incluir migration destrutiva agora.
8. F6: o smoke real da Mercado Pago continua pendente e não bloqueia a F7.

### 10. Critério de conclusão

- Guia `docs/guides/cron.md` publicado e RUNBOOK/CLAUDE.md alinhados.
- F7 entregue com histórico, API v1, hardening, SEO, admin, remoção de escritas da coluna legada e documentação.
- Todos os gates verdes; `docs/acceptance/FASE-7.md` e relatório final preenchidos; nenhum segredo exposto; nenhum commit ou push.
- **Parar para revisão do dono.**
