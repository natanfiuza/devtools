# BadgeForge — Fase 8: OpenAPI 3.1, API pública de gestão e hardening

Plano aprovado em 2026-11-05. Origem do grill:
`docs/survey/20261105_badgeforge_fase8_surveyfacts.md`.

## Contexto

`docs/SPEC-BADGEFORGE.md` §16 define a F8 em uma linha. Hoje a OpenAPI é um
markdown parcial (1.7.0) sem `components` e sem rota de serving; a API JSON de
gestão prometida em `docs/badgeforge/API.md:328-343` nunca existiu; e a
superfície de imagem não tem throttle, CSP de SVG, contrato uniforme de erro,
health com checks nem CI. A F8 entrega as três frentes sem quebrar cliente
existente.

## Decisões fechadas no grill

1. YAML 3.1.0 autoral em `docs/badgeforge/openapi.yaml` é a fonte única, com
   `components`, `servers`, `tags` e `securitySchemes`; `OPENAPI.md` vira
   ponteiro semântico.
2. Servida em `GET /openapi/badges.yaml` (`application/yaml`,
   `Cache-Control: public, max-age=86400`, sem auth).
3. Cobertura: 4 rotas `/api/badges/**`, `/b/{username}/{badgeAsset}.{ext}` e
   `/api/v1/badgeforge/*`; Inertia fora.
4. `info.version: 1.8.0` + política aditiva (remoção só em major).
5. Teste de contrato bidirecional rota↔spec, placeholders reais, `$ref` sem
   dangling, YAML parseável.
6. API pública de gestão em `/api/v1/badgeforge/*`: GET providers/styles
   anônimos; logos e badges do dono; POST cria badge pessoal via
   `CreateBadgeAsset`; sem PATCH nem rotação de credencial.
7. Abilities `badgeforge:read`/`badgeforge:write`; tokens existentes seguem só
   com `ai:query`; UI de tokens com checkboxes e padrão `ai:query`.
8. Envelope `data`/`meta`/`links` + `ApiError`; `/b/**` passa a usar
   `ApiError`.
9. `api-read` (120/min) nos GET, novo `api-write` (30/min) em POST/DELETE,
   chave por token (IP no anônimo); página 20/máx. 100.
10. Hardening: cap de imagens (~6000/min por IP), CSP de SVG, 503/404
    uniforme, 429 do management para não-Inertia, `/up` com checks, GitHub
    Actions com audits, regressões de header/404/429/503 e log sem PII.
11. Docs pública ganha seção curta (5 idiomas) da API JSON.
12. ADR-088 (API de gestão), ADR-089 (hardening) e emenda na ADR-071.

## Contrato

| Método | Path | Auth | Throttle |
|---|---|---|---|
| GET | `/api/v1/badgeforge/providers` | anônimo | `api-read` |
| GET | `/api/v1/badgeforge/styles` | anônimo | `api-read` |
| GET | `/api/v1/badgeforge/logos` | `badgeforge:read` | `api-read` |
| POST | `/api/v1/badgeforge/logos` | `badgeforge:write` | `api-write` |
| DELETE | `/api/v1/badgeforge/logos/{ulid}` | `badgeforge:write` | `api-write` |
| GET | `/api/v1/badgeforge/badges` | `badgeforge:read` | `api-read` |
| POST | `/api/v1/badgeforge/badges` | `badgeforge:write` | `api-write` |
| DELETE | `/api/v1/badgeforge/badges/{ulid}` | `badgeforge:write` | `api-write` |

Regras herdadas das actions: plano pago para badge pessoal, username, quota de
logos, PAT cifrado write-only em item privado, parser sanitizado de SVG. Dono
errado → 404.

## Arquivos

**Spec/serving**: `docs/badgeforge/openapi.yaml` (novo),
`docs/badgeforge/OPENAPI.md`, `app/Http/Controllers/OpenApiController.php`
(método `badges()`), `routes/web.php`.

**API**: `routes/api.php`, `app/Http/Controllers/Api/V1/BadgeForge/*`,
`app/Http/Resources/Api/*`, reutilizando `StoreCustomLogoRequest`,
`StoreBadgeAssetRequest`, `StoreCustomLogo`, `CustomLogoSvgParser`,
`CreateBadgeAsset` e as actions de exclusão existentes.

**Auth/UI**: `ApiTokenController`, `StoreApiTokenRequest`,
`resources/js/pages/settings/ApiTokens.vue`, tipos, i18n.

**Provedores/config**: `BadgeForgeServiceProvider`,
`ApiServiceProvider`, `AppServiceProvider`, `config/badgeforge.php`,
`config/api.php`.

**Erros/resiliência**: `bootstrap/app.php`, `ApiError`,
`PersonalBadgeController`, `EndpointBadgeController`,
`CustomProviderBadgeController`, `SecurityHeaders`.

**Docs pública**: `BadgeForgeDocs.vue`, `BadgeForgeDocsController`, `lang/*`.

**CI**: `.github/workflows/ci.yml` (novo).

## Etapas

| Etapa | Entrega |
|---|---|
| E1 | Spec 3.1 das imagens (5 rotas + `/b/`), serving em `/openapi/badges.yaml`, `OPENAPI.md` ponteiro, emenda ADR-071, teste de contrato |
| E2 | GET providers/styles/logos/badges + grupo `badgeforge:read` + resources + spec |
| E3 | POST logos (multipart), POST badges, DELETEs + `badgeforge:write` + `api-write` + spec |
| E4 | Abilities na UI de tokens (5 idiomas) + testes de isolamento |
| E5 | `ApiError` em `b/*`; 503 infra / 404 recurso nos três controllers |
| E6 | Limiter `badgeforge-images` + CSP de SVG |
| E7 | 429 do management para não-Inertia + `/up` com checks |
| E8 | Docs pública, ADR-088/089, SPEC §§14–17, API.md, glossário, CLAUDE.md, RUNBOOK.md |
| E9 | Workflow de CI (gates + audits) |
| E10 | Aceite, gates completos, smoke real, relatório e parada para revisão |

## Testes

Pest: `OpenApiContractTest`, `ManagementApi/{CatalogApiTest,BadgeListApiTest,LogoWriteApiTest,BadgeWriteApiTest,AbilityIsolationTest}`,
`Settings/ApiTokenSettingsTest`, `ServingHardeningTest`, `ImageRateLimitTest`,
`ManagementThrottleTest`, `Operations/HealthCheckTest`; atualizações em
`PersonalBadgeServingTest`, `SecurityHeadersTest`, `ApiTokenSettingsTest`.
Vitest: `api-tokens-settings.test.ts`, `docs-badgeforge.test.ts`.

## Gates e smoke

`composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`
(0 erros), `npm run build`, `composer ci:check`,
`route:list --path=api/v1/badgeforge`; smoke real com YAML 1.8.0, CSP/304,
404 JSON em `/b/`, isolamento de abilities, ETag do catálogo, multipart de
logo, POST/GET/DELETE de badge, 429 e `/up`.

## Riscos

- Regressão de ability: grupos novos exigem `badgeforge:*`, nunca `ai:query`.
- Drift da spec: teste de paridade na mesma etapa; `symfony/yaml` explícito em
  `require-dev`.
- `/b/` precisa mudar `render` e o guard de `respond`.
- 503 restrito a falha de DB/cache, nunca `catch (Throwable)`.
- 429 do management por método (CSV é GET).
- CI sem lockfile versionado e sem `--max-warnings 0`.
- Placeholders da spec adotam os nomes reais dos parâmetros.

## Fora de escopo

Rotas Inertia, PATCH/rotação via API, endpoint/custom via API, viewer
embutido, meta-schema, analytics JSON, página de status.

## Regra final

Artefatos em `docs/acceptance/BADGEFORGE-FASE-8.md`, relatório e plano em
`docs/claude-code/{reports,plans}/feature_badgeforge-fase-1/`, `plansfacts` e
`surveyfacts`; gates completos e parada para revisão. **Nenhum commit/push.**
