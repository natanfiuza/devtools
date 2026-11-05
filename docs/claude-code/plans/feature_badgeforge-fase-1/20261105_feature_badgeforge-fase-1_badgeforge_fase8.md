# BadgeForge — Fase 8: OpenAPI 3.1, API pública de gestão e hardening

## Contexto

F1–F7 estão entregues. A F8 no `docs/SPEC-BADGEFORGE.md` §16 é uma linha —
`F8: OpenAPI + documentação + hardening.` — sem critério de aceite, sem ADR e
sem artefato. Na prática, hoje:

- `docs/badgeforge/OPENAPI.md` é markdown envolvendo um YAML parcial (1.7.0)
  com 5 paths e nenhum `components`, `servers`, `security` ou `operationId`;
  não é consumível por ferramenta nenhuma e não é servido.
- A "API pública de gestão" prometida em `docs/badgeforge/API.md:328-343`
  (`/api/badgeforge/providers|styles|logos|badges`) **nunca existiu** — a
  gestão é web/Inertia.
- `/api/badges/{provider}`, `/api/badges/static` e `/b/{username}/{ulid}.{ext}`
  não têm throttle; as respostas SVG não têm CSP; falha de banco em
  endpoint/custom vira 500 e em `/b/` vira 404 vazio; `/b/` está fora do
  envelope `ApiError`; `badgeforge-management` responde 429 como toast Inertia
  mesmo para o CSV de analytics; não existe CI; `/up` não checa nada.

A F8 fecha essas três frentes: contrato legível por máquina, a API de gestão
prometida e hardening mensurável. **Nada quebra para cliente existente**: a
superfície HTTP atual continua igual, salvo headers restritivos novos e o corpo
de erro do `/b/`.

## Decisões fechadas no grill

1. **YAML 3.1.0 autoral** em `docs/badgeforge/openapi.yaml` é a fonte da
   verdade, com `components` reais (schemas, parameters, responses, headers,
   examples), `servers` vindo de `APP_URL`, `tags` e `securitySchemes`.
   `OPENAPI.md` passa a explicar semântica (cache, SSRF, i18n) apontando para
   o YAML.
2. Servida em **`GET /openapi/badges.yaml`** (`application/yaml`,
   `Cache-Control: public, max-age=86400`, sem auth), ao lado do
   `/openapi.yaml` existente.
3. **Cobertura**: `/api/badges/static`, `/api/badges/{provider}`,
   `/api/badges/endpoint`, `/api/badges/custom`, `/b/{username}/{badgeAsset}`
   e a nova `/api/v1/badgeforge/*`. Inertia/HTML fora da spec.
4. **`info.version: 1.8.0`** + seção "Política de compatibilidade": mudanças
   aditivas; remoção só em major. (A versão descreve o contrato, não o
   arquivo.)
5. **Teste de contrato** bidirecional: todo path+method documentado resolve em
   `route:list`, toda rota pública de badge/API está documentada, placeholders
   iguais aos nomes reais dos parâmetros, `$ref` sem dangling, YAML parseável.
6. **API pública de gestão** em `/api/v1/badgeforge/*` — GET providers/styles
   anônimos e cacheáveis; logos e badges do dono; POST cria badge pessoal
   (`static|provider`) via `CreateBadgeAsset`; sem PATCH nem rotação de
   credencial.
7. **Abilities novas** `badgeforge:read` (GET logos/badges) e
   `badgeforge:write` (POST/DELETE). Tokens existentes continuam só com
   `ai:query`; a tela `/settings/api-tokens` ganha checkboxes com apenas
   `ai:query` marcado por padrão.
8. **Envelope** existente: sucesso `{"data":...}` (+`meta`/`links` em lista,
   `per_page` 20/máx. 100), erro `ApiError` com códigos estáveis. `b/*` passa
   a usar o mesmo envelope de erro.
9. **Limites**: `api-read` (120/min) nos GET autenticados e no catálogo
   (chave por token ou IP), novo `api-write` (30/min) em POST/DELETE, chave
   por token.
10. **Hardening**: cap configurável por IP (~6000/min) em static, provider e
    `/b/`; CSP restritiva só em SVG; 503 + `Retry-After: 60` +
    `Cache-Control: no-store` para falha de infra, 404 para recurso
    inexistente, uniforme nos três controllers com imagem; 429 + `Retry-After`
    no `badgeforge-management` quando não é Inertia; `/up` com checks de DB,
    cache e storage de PNG; GitHub Actions com gates e audits; regressões de
    headers, matriz 404/429/503 e log sem URL/ULID/template/PII.
11. **Docs pública** `/{locale}/docs/badgeforge` ganha seção curta (5 idiomas)
    sobre a API JSON: token, abilities e link da spec.
12. **ADRs**: ADR-088 (API pública de gestão), ADR-089 (hardening) e emenda na
    ADR-071 (spec autoral e servida, substituindo "gerada a partir das
    definições").

## Contrato

### Endpoints novos

| Método | Path | Auth | Throttle | Retorno |
|---|---|---|---|---|
| GET | `/api/v1/badgeforge/providers` | anônimo | `api-read` (IP) | catálogo (`ProviderCatalog::forDocs()`) |
| GET | `/api/v1/badgeforge/styles` | anônimo | `api-read` (IP) | `StyleCatalog::ids()` |
| GET | `/api/v1/badgeforge/logos` | `badgeforge:read` | `api-read` | logos do dono (metadados, sem paths) |
| POST | `/api/v1/badgeforge/logos` | `badgeforge:write` | `api-write` | logo criado (`multipart/form-data`) |
| DELETE | `/api/v1/badgeforge/logos/{ulid}` | `badgeforge:write` | `api-write` | `{data:{id,deleted:true}}` |
| GET | `/api/v1/badgeforge/badges` | `badgeforge:read` | `api-read` | badges pessoais do dono, paginado |
| POST | `/api/v1/badgeforge/badges` | `badgeforge:write` | `api-write` | badge pessoal + URL pública |
| DELETE | `/api/v1/badgeforge/badges/{ulid}` | `badgeforge:write` | `api-write` | `{data:{id,deleted:true}}` |

Regras herdadas das actions, não reimplementadas: plano pago para badge
pessoal, username obrigatório, quota de logos, PAT cifrado write-only só em
item privado de provider que suporta privado, parser sanitizado de SVG.
Dono errado → 404 (`not_found`).

### Headers

- Imagens continuam com `Content-Type`, `Cache-Control`, `ETag`,
  `Content-Disposition` e 304; SVG ganha
  `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`.
- Rotas throttleadas ganham `X-RateLimit-*`/`Retry-After` do próprio Laravel.
- 503 de infra leva `Retry-After: 60` + `Cache-Control: no-store`.
- `/openapi/badges.yaml` leva `Cache-Control: public, max-age=86400`.

## Arquivos

**Spec/serving**: `docs/badgeforge/openapi.yaml` (novo),
`docs/badgeforge/OPENAPI.md` (vira ponteiro),
`app/Http/Controllers/OpenApiController.php` (método `badges()` reaproveitando
o helper atual), `routes/web.php` (rota `openapi.badges`).

**API de gestão**: `routes/api.php` (dois grupos sob `v1/badgeforge`),
`app/Http/Controllers/Api/V1/BadgeForge/{CatalogController,BadgeLogoController,BadgeAssetController}.php`,
`app/Http/Resources/Api/{ApiBadgeLogoResource,ApiBadgeAssetResource}.php`.
Reutiliza `StoreCustomLogoRequest`, `StoreBadgeAssetRequest`, `StoreCustomLogo`,
`CustomLogoSvgParser`, `CreateBadgeAsset` e as actions de exclusão já chamadas
por `PersonalBadgeManagementController`/`CustomLogoController`.

**Auth**: `app/Http/Controllers/Settings/ApiTokenController.php` (constantes
das abilities), `app/Http/Requests/StoreApiTokenRequest.php` (array de
escopos), `resources/js/pages/settings/ApiTokens.vue`, tipos em
`resources/js/types/`.

**Provedores/config**: `app/Providers/BadgeForgeServiceProvider.php`
(limiter de imagens, 429 do management), `app/Providers/ApiServiceProvider.php`
(`api-write`), `app/Providers/AppServiceProvider.php` (listener
`DiagnosingHealth`), `config/badgeforge.php` (`serving.rate_limit`),
`config/api.php` (`write` por minuto).

**Erros/resiliência**: `bootstrap/app.php` (envelope para `b/*` e mapeamento
de falha de infra), `app/Support/ApiError.php` (`badge_unavailable`),
`app/Http/Controllers/BadgeForge/{PersonalBadgeController,EndpointBadgeController,CustomProviderBadgeController}.php`,
`app/Http/Middleware/SecurityHeaders.php` (CSP de SVG).

**Frontend/i18n**: `resources/js/pages/BadgeForgeDocs.vue`,
`app/Http/Controllers/BadgeForgeDocsController.php` (props
`jsonApiBaseUrl`/`openApiUrl`/`tokensSettingsUrl`),
`lang/{en,es,fr,pt_BR,pt_PT}/{settings,tools}.php`.

**CI**: `.github/workflows/ci.yml` (novo).

## Etapas

| Etapa | Entrega |
|---|---|
| E1 | Spec 3.1 das imagens (5 rotas + `/b/`), `components`/`servers`/`tags`, serving em `/openapi/badges.yaml`, `OPENAPI.md` ponteiro, emenda ADR-071, teste de contrato cobrindo só o que existe |
| E2 | GET providers/styles/logos/badges + grupos `badgeforge:read` + resources + spec atualizada no mesmo commit |
| E3 | POST logos (multipart), POST badges, DELETEs + `badgeforge:write` + `api-write` + spec |
| E4 | Abilities na UI de tokens (5 idiomas) + testes de isolamento entre `ai:query` e `badgeforge:*` |
| E5 | `ApiError` em `b/*`; 503 infra / 404 recurso nos três controllers; atualiza testes existentes de serving |
| E6 | Limiter `badgeforge-images` (static, provider, `/b/`) + CSP de SVG; atualiza `SecurityHeadersTest` |
| E7 | 429 do management para não-Inertia + `/up` com checks de DB/cache/storage |
| E8 | Docs pública da API JSON (5 idiomas), ADR-088/089, SPEC §§14–17, `API.md`, glossário, `CLAUDE.md`, `RUNBOOK.md` |
| E9 | Workflow de CI (gates + `composer audit` + `npm audit --audit-level=high`) |
| E10 | Aceite, gates completos, smoke real, relatório, artefatos da skill e parada para revisão |

Ordem obrigatória: E1 antes de E2/E3 (spec e teste de paridade primeiro);
E2 antes de E3 (recursos de leitura reusados na escrita); E5 antes de E6 (429
de imagem usa o envelope); E4 antes de E8 (docs linkam a tela de tokens);
E9 por último (pina os comandos finais).

## Testes

Pest: `OpenApiContractTest` (paridade, `$ref`, placeholders, versão, headers do
YAML), `ManagementApi/CatalogApiTest` (anônimo, ETag/304, forma, sem segredos),
`ManagementApi/BadgeListApiTest` (paginação, dono, 401/403),
`ManagementApi/LogoWriteApiTest` (201/422/404, SVG cru nunca persistido nem
devolvido), `ManagementApi/BadgeWriteApiTest` (201, credencial não ecoada, Free
422), `ManagementApi/AbilityIsolationTest` (token `ai:query` → 403 na API e
vice-versa), `Settings/ApiTokenSettingsTest` (padrão só `ai:query`),
`ServingHardeningTest` (matriz 404/503, headers, `/b/` JSON),
`ImageRateLimitTest`, `ManagementThrottleTest` (Inertia vs CSV),
`Operations/HealthCheckTest` (saudável 200, falha 503). Atualizar
`PersonalBadgeServingTest`, `SecurityHeadersTest` e `ApiTokenSettingsTest` onde
o comportamento muda de propósito. Log hygiene: `Log::spy()` em todas as rotas
de badge e da API, sem URL/ULID/template/PII.

Vitest: `api-tokens-settings.test.ts`, atualização em
`docs-badgeforge.test.ts`.

## Gates e smoke

`composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`
(0 erros; os ~1182 avisos pré-existentes não podem virar falha de gate),
`npm run build`, `composer ci:check`, `php artisan route:list --path=api/v1/badgeforge`.

Smoke com rede real (`php artisan serve`): YAML 200 com `version: 1.8.0`; SVG
com CSP e 304 por `If-None-Match`; 404 de `/b/` em JSON; token `ai:query` → 403
e token `badgeforge:read` → 200 na API e 403 em `/api/v1/me`; ETag no catálogo;
multipart de logo → 201 e DELETE; POST badge → 201, GET paginado, DELETE;
burst → 429 com `Retry-After`; `/up` 200 e 503 quando DB cai.

## Riscos

- **Regressão de ability**: os grupos novos exigem `badgeforge:*`, nunca
  `ai:query`; `ApiTokenController::ABILITY` permanece.
- **Drift da spec**: teste bidirecional na mesma etapa da rota; `symfony/yaml`
  entra explicitamente em `require-dev`.
- **`/b/` e página HTML de erro**: estender tanto o `render` quanto o guard de
  `respond`, senão o 404 JSON é reescrito pela página Inertia.
- **503 amplo demais**: mapear apenas `QueryException`/falha de cache em
  `api/badges/*` e `b/*`; nunca `catch (Throwable)`.
- **Inertia 429**: detectar pelo método (CSV é GET), não por `expectsJson()`,
  para não quebrar os testes de toast.
- **CI**: sem lockfile versionado, usar `composer install`/`npm install`, nunca
  `npm ci`; sem `--max-warnings 0`; advisory não corrigível vira exceção
  documentada, nunca `continue-on-error` no gate inteiro.
- **Placeholders**: a spec adota os nomes reais (`badgeAsset`, `badgeEndpoint`,
  `customProvider`); é documentação, não contrato HTTP.
- **Throttle de imagem**: default alto e configurável; GitHub Camo e proxies
  compartilhados documentados no RUNBOOK.

## Fora de escopo

Rotas Inertia na spec, PATCH/rotação de credencial via API, criação de endpoint
ou provider customizado via API, viewer embutido, validação por meta-schema,
API JSON de analytics, página de status.

## Regra final

Ao terminar: `docs/acceptance/BADGEFORGE-FASE-8.md`, relatório em
`docs/claude-code/reports/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase8.md`,
plano em `docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase8.md`,
`docs/plans/feature_badgeforge-fase-1/20261105_badgeforge_fase8_plansfacts.md` e
`docs/survey/20261105_badgeforge_fase8_surveyfacts.md`; rodar todos os gates e
parar para revisão. **Nenhum commit nem push.**
