# BadgeForge — Fase 8 (OpenAPI 3.1, API de gestão e hardening)

- Data: 2026-11-05
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase8.md`
- Levantamento/plano da skill: `docs/survey/20261105_badgeforge_fase8_surveyfacts.md`,
  `docs/plans/feature_badgeforge-fase-1/20261105_badgeforge_fase8_plansfacts.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-8.md`
- ADRs: `docs/adr/ADR-088-badgeforge-api-de-gestao.md`,
  `docs/adr/ADR-089-badgeforge-hardening.md` (emenda na
  `docs/adr/ADR-071-badgeforge-documentacao-publica.md`)

## Objetivo

A F8 no `docs/SPEC-BADGEFORGE.md` §16 era uma linha — "OpenAPI +
documentação + hardening" — sem critério, ADR ou artefato. Na prática faltavam
três coisas:

1. **contrato legível por máquina** — o `OPENAPI.md` era um markdown com YAML
   parcial 1.7.0, sem `components`, `servers`, `security` ou `operationId`, e
   não era servido;
2. **API pública de gestão** — prometida em `API.md`, nunca existiu; a gestão
   era só web/Inertia;
3. **hardening mensurável** — imagens sem throttle, SVG sem CSP, falha de banco
   virando 500/404 vazio, `/b/` fora do envelope `ApiError`, 429 do CSV virando
   toast Inertia, `/up` que não checava nada e nenhum CI.

A fase é aditiva: nenhum cliente HTTP existente quebra, exceto o corpo de erro
do `/b/` (que passa a ser `ApiError`) e headers restritivos novos.

Decisões fechadas no grill (27 perguntas): YAML 3.1 autoral e servido em
`/openapi/badges.yaml`; versão 1.8.0 com política aditiva; API de gestão em
`/api/v1/badgeforge/*` com catálogo anônimo cacheável; duas abilities novas
ortogonais ao `ai:query`; envelope e paginação existentes; cap configurável de
imagens por IP; CSP no SVG; 503 + `Retry-After` para infra e 404 para recurso;
429 JSON no management; `/up` com probes; GitHub Actions.

## Resumo executivo

- **Spec**: `docs/badgeforge/openapi.yaml` agora é OpenAPI **3.1.0** com
  `components` (schemas/parameters/responses/headers/examples), `servers`,
  `tags`, `securitySchemes`, `info.version` **1.8.0** e política de
  compatibilidade. Cobre as cinco superfícies de imagem e os oito endpoints de
  gestão. Servido em `GET /openapi/badges.yaml` com
  `Cache-Control: public, max-age=86400`; `OPENAPI.md` virou a explicação
  semântica.
- **API de gestão**: catálogo anônimo (`providers`, `styles`) com ETag/304;
  logos e badges do dono com `badgeforge:read`; POST de logo (multipart) e de
  badge pessoal, DELETE de ambos com `badgeforge:write`; paginação 20/máx. 100;
  dono errado → 404; credencial nunca ecoada.
- **Abilities**: `badgeforge:read`/`badgeforge:write` ortogonais a `ai:query`;
  token novo continua só com `ai:query` por padrão; a tela de tokens ganhou
  checkboxes nos cinco idiomas.
- **Hardening**: cap `badgeforge-images` (6000/min por IP, env) em static,
  provider e `/b/`; CSP fechada em todo SVG; 503 `badge_unavailable` +
  `Retry-After: 60` + `no-store` para `QueryException` em `api/badges/*` e
  `b/*`; 404 uniforme; 429 JSON no `badgeforge-management` para não-Inertia;
  `/up` com banco/cache/storage; workflow de CI.
- **Docs**: seção da API JSON na página pública (5 idiomas), ADR-088/089,
  emenda ADR-071, SPEC §§14–17, `API.md`, `OPENAPI.md`, glossário,
  `CLAUDE.md`, `RUNBOOK.md`.
- Gates: Pest **1.413 / 23.490**, Vitest **983 / 76**, `types:check` limpo,
  `lint:check` 0 erros, `build` exit 0, `composer audit` e
  `npm audit --audit-level=high` limpos.

## Decisão (ADR-088 — API de gestão)

- Oito endpoints sob `/api/v1/badgeforge/*`, dentro de `api/v1` mas **fora** do
  grupo que exige `ai:query`. Dois grupos: catálogo anônimo e rotas do dono.
- O catálogo reusa `ProviderCatalog::forDocs()`/`StyleCatalog::ids()`, é
  público e cacheável (300 s, ETag/304) porque já é público nas docs.
- Logos e badges reusam as actions da web (`StoreCustomLogo`,
  `CreateBadgeAsset`, exclusões); a API só troca `back()` por 201/envelope.
  Regras herdadas: plano pago, username, quota de logos, PAT write-only,
  parser sanitizado.
- O que nunca sai: paths do SVG do logo, credencial, URL de origem de endpoint
  ou provider customizado.
- Limites próprios: `api-read` 120/min e `api-write` 30/min, chaveados por
  token (catálogo por IP).
- Fora de escopo: PATCH, rotação de credencial, criação de endpoint/provider
  customizado e analytics por API.

## Decisão (ADR-089 — hardening)

- Um único cap por IP nas rotas de imagem sem dono (`badgeforge-images`), com
  endpoint/custom mantendo o limite por dono — evita que o cap generoso
  encubra o abuso por conta.
- CSP fechada aplicada só a `image/svg+xml`, independente de `CSP_MODE`, porque
  o SVG é servido em `<img>` e nunca deve executar script.
- 503 só para falha de infraestrutura (`QueryException`) e só em
  `api/badges/*`/`b/*`; qualquer outro erro segue o caminho normal. Recurso
  inexistente continua 404.
- 429 do management é decidido pelo método (CSV é GET), não por
  `expectsJson()`, para não quebrar o toast Inertia dos formulários.
- `/up` substitui a rota do framework por ter o mesmo método+URI; a string
  `health: '/up'` continua garantindo a exceção no modo manutenção.
- CI sem lockfile versionado (ADR-014): `composer install`/`npm install`,
  nunca `composer ci`/`npm ci`; `format:check` excluído por dívida
  pré-existente documentada, sem `continue-on-error`.

## Etapas executadas

| Etapa | Entrega |
|---|---|
| E1 | YAML 3.1.0, serving `/openapi/badges.yaml`, `OPENAPI.md` ponteiro, emenda ADR-071, `OpenApiContractTest` |
| E2 | GET providers/styles/logos/badges + grupos `badgeforge:read` + resources + spec |
| E3 | POST logo (multipart), POST badge, DELETEs + `badgeforge:write` + `api-write` + spec |
| E4 | Abilities na tela de tokens (5 idiomas) + testes de isolamento |
| E5 | Envelope `ApiError` em `b/*`; 503 infra / 404 recurso |
| E6 | Limiter `badgeforge-images` + CSP de SVG |
| E7 | 429 do management para não-Inertia + `/up` com DB/cache/storage |
| E8 | Seção da API nas docs (5 idiomas), ADR-088/089, SPEC, `API.md`, glossário, `CLAUDE.md`, `RUNBOOK.md` |
| E9 | `.github/workflows/ci.yml` com audits e gates |
| E10 | Aceite, gates completos, smoke real e este relatório |

## Arquivos

**Contrato**: `docs/badgeforge/openapi.yaml` (novo),
`docs/badgeforge/OPENAPI.md` (reescrito como ponteiro),
`app/Http/Controllers/OpenApiController.php` (método `badges()`),
`routes/web.php` (`openapi.badges`), `composer.json` (`symfony/yaml` em
`require-dev`).

**API**: `routes/api.php`,
`app/Http/Controllers/Api/V1/BadgeForge/{CatalogController,BadgeLogoController,BadgeAssetController}.php`,
`app/Http/Resources/{ApiBadgeLogoResource,ApiBadgeAssetResource}.php`,
`app/Actions/BadgeForge/ParseCustomLogoUpload.php`.

**Auth**: `app/Http/Controllers/Settings/ApiTokenController.php`,
`app/Http/Requests/StoreApiTokenRequest.php`,
`resources/js/pages/settings/ApiTokens.vue`,
`resources/js/types/settings.ts`, `lang/*/settings.php`.

**Erros/resiliência**: `bootstrap/app.php`, `app/Support/ApiError.php`,
`app/Http/Controllers/BadgeForge/PersonalBadgeController.php`,
`app/Http/Controllers/BadgeForge/CustomLogoController.php`,
`app/Http/Middleware/SecurityHeaders.php`,
`app/Providers/{ApiServiceProvider,BadgeForgeServiceProvider}.php`,
`config/{api,badgeforge}.php`, `routes/public.php`.

**Health**: `app/Http/Controllers/HealthController.php`,
`app/Support/Health/HealthProbe.php`.

**Docs/frontend**: `app/Http/Controllers/BadgeForgeDocsController.php`,
`resources/js/pages/BadgeForgeDocs.vue`, `lang/*/docs.php`,
`docs/SPEC-BADGEFORGE.md`, `docs/badgeforge/API.md`, `docs/glossario.md`,
`CLAUDE.md`, `docs/RUNBOOK.md`.

**CI**: `.github/workflows/ci.yml` (novo).

## Testes

Novos (dez arquivos):

- `tests/Feature/BadgeForge/OpenApiContractTest.php` — parse do YAML, versão,
  `$ref` sem dangling, paridade rota↔spec nos dois sentidos, placeholders,
  headers do serving.
- `tests/Feature/BadgeForge/ManagementApi/CatalogApiTest.php` — anônimo,
  ETag/304, forma, sem segredos.
- `tests/Feature/BadgeForge/ManagementApi/BadgeListApiTest.php` — paginação,
  dono, 401/403.
- `tests/Feature/BadgeForge/ManagementApi/LogoWriteApiTest.php` — 201/422/404,
  SVG cru nunca persistido nem devolvido.
- `tests/Feature/BadgeForge/ManagementApi/BadgeWriteApiTest.php` — 201,
  credencial não ecoada, Free 422.
- `tests/Feature/BadgeForge/ManagementApi/AbilityIsolationTest.php` — `ai:query`
  → 403 na API de gestão e vice-versa.
- `tests/Feature/BadgeForge/ServingHardeningTest.php` — matriz 404/503,
  headers, `/b/` JSON.
- `tests/Feature/BadgeForge/ImageRateLimitTest.php` — cap por IP, 429 com
  `Retry-After`.
- `tests/Feature/BadgeForge/ManagementThrottleTest.php` — Inertia vs CSV/JSON.
- `tests/Feature/Operations/HealthCheckTest.php` — saudável 200, falha 503.

Atualizados onde o comportamento mudou de propósito:
`PersonalBadgeServingTest`, `SecurityHeadersTest`, `ApiTokenSettingsTest`,
`BadgeForgeDocsPageTest`, `docs-badgeforge.test.ts`,
`api-tokens-settings.test.ts`.

## Gates (2026-11-05)

| Gate | Resultado |
|---|---|
| `composer lint:check` (Pint) | ok |
| `composer types:check` (PHPStan 7) | 0 erros |
| `php artisan test` (Pest) | 1.413 testes / 23.490 asserções |
| `npm run test` (Vitest) | 983 testes / 76 arquivos |
| `npm run types:check` | ok (Wayfinder regenerado) |
| `npm run lint:check` (ESLint) | 0 erros; 1.182 avisos pré-existentes |
| `npm run build` | exit 0 |
| `composer audit` | sem avisos |
| `npm audit --audit-level=high` | limpo (14 moderados fora do limiar) |
| `composer ci:check` | falha **só** no `format:check` (162 arquivos com drift anterior à fase, ex. `Dashboard.vue`) |

Durante o gate de audits apareceram 11 avisos do Symfony (http-foundation,
http-kernel, mime, routing, serializer, yaml) e 1 alto do `undici`: foram
corrigidos por update de patch (`symfony/*` → 7.4.23) e `npm audit fix`, em
vez de documentados como exceção.

## Smoke (rede real, `php artisan serve`)

Ambiente descartável: SQLite temporário migrado com seed de planos, usuário
pago (`pro-monthly`) e dois tokens, cap de imagens reduzido a 8/min para
observar o 429. Tudo apagado ao final (banco, tokens, arquivos, diretório
temporário).

- `GET /openapi/badges.yaml` → **200** `application/yaml; charset=UTF-8`,
  `Cache-Control: max-age=86400, public`, `openapi: 3.1.0`, `version: 1.8.0`.
- SVG estática → **200**, `ETag`, `Cache-Control: max-age=86400, public`,
  `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`,
  `X-RateLimit-*`; com `If-None-Match` → **304**.
- `/b/{username}/{ulid}.svg` inexistente → **404** `application/json` com
  `{"error":{"code":"not_found",...}}` (sem página HTML).
- Autorização: sem token **401**; `ai:query` → **403** em
  `/api/v1/badgeforge/logos`; `badgeforge:read` → **200** em `/logos` e **403**
  em `/api/v1/me`; `ai:query` → **200** em `/api/v1/me`.
- Catálogo anônimo → **200** com 9 providers, `ETag`, 304 condicional e
  `X-RateLimit-Limit: 120`; `/styles` → 5 ids.
- Logo: `POST multipart` → **201** (id/slug/viewBox/pathCount/sizeBytes,
  sem paths); `GET` lista; `DELETE` → `{id,deleted:true}`; lista vazia.
- Badge: `POST` → **201** com `url`/`pngUrl`/`credentialSet:false`;
  `GET ?per_page=1` → `meta`/`links`; Free → **422**
  `Personal badges are available on paid plans.`; `DELETE` → 200; repetir →
  **404**.
- Badge pessoal → **200** com CSP e `max-age=3600`.
- Burst de imagens → termina em **429** com `X-RateLimit-Limit: 8`,
  `X-RateLimit-Remaining: 0`, `Retry-After: 51`.
- Servidor com banco quebrado: `/up` → **503**
  `{"status":"down","checks":{"database":false,"cache":true,"storage":true}}`;
  `/b/`, endpoint e custom → **503** `badge_unavailable` com
  `Retry-After: 60` e `Cache-Control: no-store, private`.
- `/{locale}/docs/badgeforge` → **200** com `jsonApiBaseUrl`, `openApiUrl` e
  `tokensSettingsUrl` nas props.

## Notas e desvios

- **`composer ci:check` não é gate único**: o `format:check` já falhava antes
  da fase em 162 arquivos (config do repo usa 4 espaços, muitos arquivos usam
  2). Reformatar tudo dentro da F8 esconderia a mudança; o workflow roda os
  outros passos individualmente e documenta a exceção no cabeçalho. Quando a
  dívida for paga, basta adicionar o passo.
- **Prettier durante a implementação**: rodar `--write` em
  `BadgeForgeDocs.vue`/`docs-badgeforge.test.ts` reformatava arquivos
  inteiros; as alterações foram revertidas e reaplicadas mínimas, no estilo
  original de cada arquivo.
- **`OPENAPI.md`**: a versão 1.7.0 resumida deu lugar a um ponteiro semântico;
  a fonte da verdade é o YAML 1.8.0.
- **`symfony/yaml`**: já vinha transitivo, agora é `require-dev` explícito
  porque o teste de contrato depende dele.
- **`/up`**: o check de storage faz round-trip de PNG no diretório de cache,
  então segue verde mesmo com o banco fora — mostrado no 503 do smoke.
- **Storage residual do smoke**: a badge criada foi servida e o diretório do
  usuário descartável ficou vazio após o DELETE; a pasta vazia foi removida.
  Nenhum arquivo versionado foi tocado pelo smoke.

## Fora de escopo / próximos passos

- Pagar a dívida de formatação e então incluir `format:check` no CI.
- PATCH/rotação de credencial, criação de endpoint/provider customizado e API
  JSON de analytics permanecem fora da fase.
- Rodar o workflow no GitHub após o push (o usuário commita e abre o PR pelo
  gitpr) para validar o ambiente Linux/Imagick.

## Git

Nenhum commit nem push executado. `git status` revisado: 43 arquivos
modificados e os novos arquivos da fase, sem tocar nos artefatos do gitpr.
