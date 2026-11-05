# Critérios de aceite — BadgeForge FASE 8 (OpenAPI 3.1, API de gestão e hardening)

Gerado em 2026-11-05.
Fonte: `docs/SPEC-BADGEFORGE.md` seção 16, plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase8.md`),
ADR-088, ADR-089 e a emenda da ADR-071.

## Escopo da fase

- **Contrato**: `docs/badgeforge/openapi.yaml` passa a ser OpenAPI **3.1.0**
  autoral, com `components` reais, `servers` de `APP_URL`, `tags` e
  `securitySchemes`, `info.version` **1.8.0** e política de compatibilidade
  aditiva. Cobre `/api/badges/static`, `/api/badges/{provider}`,
  `/api/badges/endpoint`, `/api/badges/custom`, `/b/{username}/{badgeAsset}` e
  `/api/v1/badgeforge/*`. Servido em `GET /openapi/badges.yaml`
  (`application/yaml`, `Cache-Control: public, max-age=86400`), ao lado do
  `/openapi.yaml` antigo. `OPENAPI.md` vira a explicação semântica (cache, SSRF,
  i18n) apontando para o YAML.
- **API pública de gestão** em `/api/v1/badgeforge/*`: catálogo anônimo
  (providers/styles, ETag/304), logos e badges do dono (`badgeforge:read`),
  escrita de logo (multipart) e badge pessoal, exclusões (`badgeforge:write`),
  paginação `per_page` 20/máx. 100, envelope `data`/`meta`/`links`, dono errado
  → 404. As regras continuam nas actions: plano pago para badge pessoal,
  username, quota de logos, PAT cifrado write-only, parser de SVG sanitizado.
- **Hardening**: cap de imagens por IP (`badgeforge-images`, 6000/min por
  padrão, env-configurável) em static, provider e `/b/`; CSP fechada em todo
  SVG; 404 uniforme para recurso inexistente e 503
  (`badge_unavailable` + `Retry-After: 60` + `no-store`) para falha de
  infraestrutura nos três controllers com imagem, incluindo `/b/`; 429 JSON
  (com headers de throttle) no `badgeforge-management` para requisições não
  Inertia; `/up` com probes de banco, cache e storage de PNG; workflow de CI.
- Docs pública (`/{locale}/docs/badgeforge`) ganha a seção da API JSON nos cinco
  idiomas; ADR-088/ADR-089, emenda da ADR-071, SPEC §§14–17, `API.md`,
  `OPENAPI.md`, glossário, `CLAUDE.md` e `RUNBOOK.md` refletem a entrega.

Fora da fase: rotas Inertia na spec, PATCH/rotação de credencial via API,
criação de endpoint ou provider customizado via API, viewer embutido,
validação por meta-schema, API JSON de analytics e página de status.

## Critérios

| # | Critério | Como verificar |
| --- | --- | --- |
| 1 | `docs/badgeforge/openapi.yaml` é OpenAPI 3.1.0, `info.version` 1.8.0, com `components`, `servers`, `tags`, `securitySchemes` e política de compatibilidade documentada | `OpenApiContractTest`; inspeção do YAML |
| 2 | Todo path+method documentado resolve em `route:list`, toda rota pública de badge/API está documentada, placeholders batem com os nomes reais e todo `$ref` resolve | `OpenApiContractTest` |
| 3 | `GET /openapi/badges.yaml` responde 200 `application/yaml` com `Cache-Control: public, max-age=86400`, sem auth; `GET /openapi.yaml` continua igual | `OpenApiContractTest`; smoke real |
| 4 | `GET /api/v1/badgeforge/providers` e `/styles` são anônimos, cacheados (300 s) e condicionais (ETag/304); o catálogo não expõe segredos | `ManagementApi/CatalogApiTest`; smoke real |
| 5 | `GET logos`/`badges` exigem `badgeforge:read`, listam só o dono, paginam (`per_page` 20/máx. 100) e devolvem `meta`/`links`; recurso de outro dono → 404 `not_found` | `ManagementApi/BadgeListApiTest`, `LogoWriteApiTest`; smoke real |
| 6 | `POST logos` (multipart) responde 201 só com metadados (id/slug/viewBox/pathCount/sizeBytes/createdAt), nunca os paths; SVG cru não é persistido nem devolvido | `ManagementApi/LogoWriteApiTest`; smoke real |
| 7 | `POST badges` responde 201 com a URL pública (`.svg`/`.png`), sem ecoar credencial; Free → 422 `validation_error`; `DELETE` responde `{data:{id,deleted:true}}` e a segunda exclusão → 404 | `ManagementApi/BadgeWriteApiTest`; smoke real |
| 8 | `badgeforge:read`/`badgeforge:write` são ortogonais a `ai:query`: token `ai:query` → 403 na API de gestão; token `badgeforge:read` → 200 nos GET e 403 em `/api/v1/me` | `ManagementApi/AbilityIsolationTest`; smoke real |
| 9 | Tokens novos continuam com `ai:query` marcado por padrão; a tela `/settings/api-tokens` mostra os três escopos nos cinco idiomas | `Settings/ApiTokenSettingsTest`, `api-tokens-settings.test.ts`, `TranslationsParityTest` |
| 10 | `api-read` (120/min) nos GET (inclusive catálogo anônimo) e `api-write` (30/min) em POST/DELETE, com `X-RateLimit-*` e `Retry-After` | `CatalogApiTest`, `BadgeWriteApiTest`; smoke real |
| 11 | `/b/` usa o envelope `ApiError`: recurso inexistente → 404 JSON `not_found` (nunca a página Inertia) | `ServingHardeningTest`, `PersonalBadgeServingTest`; smoke real |
| 12 | Falha de infraestrutura em `/b/`, endpoint e custom → 503 `badge_unavailable` + `Retry-After: 60` + `Cache-Control: no-store`; nunca 500/stack trace | `ServingHardeningTest`; smoke real com banco quebrado |
| 13 | Cap de imagens por IP em static, provider e `/b/` vem de `badgeforge.serving.rate_limit.per_minute` (`BADGEFORGE_SERVING_LIMIT_PER_MINUTE`); endpoint/custom mantêm limite por dono; estouro → 429 com `Retry-After` | `ImageRateLimitTest`; smoke real |
| 14 | Todo SVG sai com `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`, independente de `CSP_MODE` | `SecurityHeadersTest`, `ServingHardeningTest`; smoke real |
| 15 | `badgeforge-management` responde 429 JSON (com headers) para requisições não Inertia — inclusive o CSV — e mantém o toast Inertia no fluxo da UI | `ManagementThrottleTest` |
| 16 | `/up` verifica banco (`select 1`), cache (round-trip) e storage de PNG (round-trip): 200 `up`/503 `down` com booleans por check e `no-store` | `Operations/HealthCheckTest`; smoke real |
| 17 | Nenhuma rota de badge ou da API registra URL de origem, ULID, template, claim ou PII | `Log::spy()` nos testes de serving e da API |
| 18 | CI (`.github/workflows/ci.yml`) roda audits, Pint/PHPStan/Pest, ESLint, Vue TSC, Vitest e build com `composer install`/`npm install`; `format:check` é exceção documentada, nunca `continue-on-error` | inspeção do workflow; execução local dos passos |
| 19 | Docs pública mostra a seção da API JSON com URL base, escopos, CTA de tokens e link da spec, nos cinco idiomas | `docs-badgeforge.test.ts`, `BadgeForgeDocsPageTest`; smoke real |
| 20 | SPEC §14–§17, ADR-088/ADR-089, emenda ADR-071, `API.md`, `OPENAPI.md`, glossário, `CLAUDE.md` e `RUNBOOK.md` refletem a entrega | inspeção de `docs/` |
| 21 | Gate: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check` e `npm run build` | os comandos |
| 22 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-05

- `composer test`: Pint ok, PHPStan **0 erros** (nível 7), Pest **1.413 testes /
  23.490 asserções**.
- `npm run test`: Vitest **983 testes / 76 arquivos**.
- `npm run types:check`: limpo (Wayfinder regenerado antes do `vue-tsc`).
- `npm run lint:check`: **0 erros**; 1.182 avisos de indentação pré-existentes
  (o mesmo número da F7).
- `npm run build`: concluído (exit 0; aviso de timings do rolldown já conhecido).
- `composer ci:check`: falha **só** no `npm run format:check` — 162 arquivos
  versionados já não passavam no Prettier antes desta fase (por exemplo
  `resources/js/pages/Dashboard.vue`, intocado). Reformatar tudo dentro desta
  entrega esconderia a mudança; a exclusão está comentada no workflow e o
  restante do `composer ci:check` roda passo a passo.
- Audits: `composer audit` sem avisos (os 11 avisos do symfony foram corrigidos
  subindo para 7.4.23); `npm audit --audit-level=high` limpo após
  `npm audit fix` (restam 14 moderados, fora do limiar do gate).
- Rotas: `route:list --path=api/v1/badgeforge` mostra os oito endpoints com
  `CheckAbilities:badgeforge:read|write` e `ThrottleRequests:api-read|write`;
  catálogo só com `api-read`.
- Smoke com rede real (`php artisan serve`, banco SQLite descartável com plano
  `pro-monthly`, tokens descartáveis; tudo apagado ao final):
  - `GET /openapi/badges.yaml` → **200** `application/yaml; charset=UTF-8`,
    `Cache-Control: max-age=86400, public`, `openapi: 3.1.0` e
    `version: 1.8.0`.
  - SVG estática → **200** `image/svg+xml` com `ETag`,
    `Cache-Control: max-age=86400, public`,
    `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'` e
    `X-RateLimit-*`; `If-None-Match` → **304**.
  - `/b/{username}/{ulid}.svg` desconhecida → **404** `application/json`
    `{"error":{"code":"not_found",...}}`, sem página HTML de erro.
  - Autorização: sem token **401**; token `ai:query` → **403** em
    `/api/v1/badgeforge/logos`; token `badgeforge:read` → **200** em `/logos` e
    **403** em `/api/v1/me`; token `ai:query` → **200** em `/api/v1/me`.
  - Catálogo anônimo → **200** com 9 providers, `ETag` e 304 condicional;
    `/styles` → 5 ids; limite `api-read` 120/min.
  - Logo: `POST multipart` → **201** com id/slug/viewBox/pathCount/sizeBytes;
    `GET` → item na lista; `DELETE` → `{id,deleted:true}`; lista volta vazia.
  - Badge: `POST` → **201** com `url`/`pngUrl`, `credentialSet:false`;
    `GET ?per_page=1` → `meta`/`links`; Free → **422**
    `{"errors":{"badge":["Personal badges are available on paid plans."]}}`;
    `DELETE` → 200; repetir → **404**.
  - Badge pessoal servida → **200** com CSP e `max-age=3600`.
  - Burst de imagens com cap reduzido a 8/min para o smoke: sequência termina em
    **429** com `X-RateLimit-Limit: 8`, `X-RateLimit-Remaining: 0` e
    `Retry-After: 51`.
  - Servidor com banco quebrado: `/up` → **503**
    `{"status":"down","checks":{"database":false,"cache":true,"storage":true}}`;
    `/b/`, `/api/badges/endpoint/...` e `/api/badges/custom/...` → **503**
    `badge_unavailable` com `Retry-After: 60` e `Cache-Control: no-store,
    private`.
  - `/{locale}/docs/badgeforge` → **200** com `jsonApiBaseUrl`,
    `openApiUrl` e `tokensSettingsUrl` nas props.
- `git status` revisado; nenhum commit/push executado.

## Notas e desvios do plano

- **`format:check` fora do gate de CI**: decisão explícita. Reformatação de 162
  arquivos fica para uma tarefa própria; o workflow lista os passos restantes
  do `composer ci:check` e documenta a exceção no cabeçalho, sem
  `continue-on-error` capaz de mascarar regressões novas.
- **Advisories corrigidos, não documentados**: os 11 avisos do Symfony foram
  resolvidos com update de patch (7.4.19/7.4.20 → 7.4.23); o `undici` de alta
  severidade saiu no `npm audit fix`. Nenhuma exceção de segurança precisou ser
  registrada.
- **Cap do smoke**: o cap de 6000/min é alto demais para observar 429 em teste
  manual; o smoke subiu o servidor com `BADGEFORGE_SERVING_LIMIT_PER_MINUTE=8`
  só para exercitar o caminho. Produção continua no default configurável.
- **Banco do smoke**: SQLite descartável com migrations + seed de planos, para
  não tocar no MySQL de desenvolvimento. Usuários, tokens, arquivos e o próprio
  diretório temporário foram removidos ao final.
- **`/up` e storage**: o check de storage faz round-trip de PNG no diretório de
  cache; por isso ele é verdadeiro mesmo com o banco fora, como mostrado no 503
  do smoke.
- **`OPENAPI.md`**: passou de "YAML resumido 1.7.0" para explicação semântica
  apontando o YAML 3.1; a fonte da verdade é um arquivo só.
- **Contagem de testes**: 1.371 → 1.413 Pest (42 testes novos em dez arquivos
  novos) e 981 → 983 Vitest (um teste novo de docs mais asserções na tela de
  tokens), sem remoção de cobertura.
