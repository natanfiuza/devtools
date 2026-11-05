# Survey — BadgeForge Fase 8 (OpenAPI 3.1, API pública de gestão e hardening)

Data: 2026-11-05
Branch: `feature/badgeforge-fase-1`
Origem: `/grill-with-docs` — "Pode seguir para a proxima fase 8"

## Contexto

A F8 aparece em `docs/SPEC-BADGEFORGE.md` §16 como uma única linha
(`F8: OpenAPI + documentação + hardening.`), sem critério de aceite, ADR ou
aceite anteriores. O levantamento encontrou três dívidas concretas:

- `docs/badgeforge/OPENAPI.md` é um markdown com um YAML parcial (1.7.0), sem
  `components`, `servers`, `security` ou `operationId`, e não é servido por
  rota nenhuma.
- `docs/badgeforge/API.md:328-343` promete uma API JSON de gestão
  (`/api/badgeforge/providers|styles|logos|badges`) que nunca foi implementada.
- Rotas de imagem sem throttle (`static`, `{provider}`, `/b/`), SVG sem CSP,
  falha de banco virando 500 (endpoint/custom) ou 404 vazio (`/b/`), `/b/`
  fora do envelope `ApiError`, 429 do management como toast mesmo no CSV, CI
  inexistente e `/up` sem checks.

## Fatos levantados no repositório

- **Sanctum**: `config/sanctum.php:22` `stateful: []` — API é servidor-a-
  servidor com Bearer token; sem cookie/CSRF. Expiração nula.
- **`/api/v1`**: `routes/api.php:24-27` exige
  `auth:sanctum, active, verified, abilities:ai:query, TrackApiTokenUsage`.
  `ApiTokenController::ABILITY = 'ai:query'` é o único escopo existente; a UI
  `/settings/api-tokens` só pede nome e emite `['ai:query']`.
- **Envelope**: `app/Support/ApiError.php` produz
  `{"error":{"code","message","details"}}` para `api/*`
  (`bootstrap/app.php:112-118`); `SqlQueryController` pagina com
  `data`/`meta`/`links` e `per_page` 20 (máx. 100).
- **Imagens**: `BadgeImageResponder` define `Content-Type`, `Cache-Control`,
  `ETag`, `Content-Disposition`, 304 e 503 sem Imagick. `SecurityHeaders` já
  aplica nosniff/X-Frame-Options/CSP/HSTS globalmente, mas não CSP de SVG.
- **`/b/{username}/{badgeAsset}.{ext}`** vive em `routes/public.php:14-18`,
  fora dos grupos `api`/`web`; `PersonalBadgeController` converte
  `QueryException` em 404 vazio.
- **Actions reutilizáveis**: `CreateBadgeAsset` (plano pago, username,
  provider, PAT cifrado), `StoreCustomLogo` + `CustomLogoSvgParser` (SVG
  sanitizado), catálogo em `BadgeForgeToolProps::for()` e
  `ProviderCatalog::forDocs()`.
- **Specs existentes**: `docs/openapi.yaml` (API do site) servido em
  `GET /openapi.yaml` por `app/Http/Controllers/OpenApiController.php`
  (`application/yaml`).
- **CI**: não existe workflow algum; deploy por cron (`deploy/deploy.sh`).
- **Testes**: 1.371 Pest / 981 Vitest ao fim da F7; `npm run lint:check` com
  0 erros e ~1.182 avisos pré-existentes.

## Rodadas e decisões

| # | Decisão |
|---|---|
| Q1 | `docs/badgeforge/openapi.yaml` autoral (3.1.0) é a fonte única; `OPENAPI.md` vira ponteiro semântico |
| Q2 | Spec servida publicamente + link na docs pública; sem viewer embutido |
| Q3 | Cobertura restrita a superfície de máquina (sem Inertia) |
| Q4 | Hardening nos quatro eixos: headers/cache, dependências/CI, testes de segurança, resiliência/logs |
| Q5 | **Implementar** a API JSON de gestão prevista no `API.md` (não remover nem adiar) |
| Q6 | `/b/{username}/{badgeAsset}.{ext}` entra na spec |
| Q7 | Cap generoso e configurável (~6000/min por IP) em static, provider e `/b/` |
| Q8 | `info.version: 1.8.0` + política aditiva |
| Q9 | Cobertura final: imagens + `/b/` + `/api/v1/badgeforge/*` |
| Q10 | Prefixo `/api/v1/badgeforge/*` (não `/api/badgeforge`) |
| Q11 | GET providers/styles anônimos e cacheáveis; logos/badges com token |
| Q12 | POST badges cria badge pessoal (`static|provider`); endpoint/custom ficam na web |
| Q13 | GitHub Actions com gates + `composer audit` + `npm audit --audit-level=high` |
| Q14 | Teste de contrato por paridade bidirecional rota↔spec |
| Q15 | 503 + `Retry-After`/`no-store` para falha de infra; 404 para recurso inexistente |
| Q16 | CSP `default-src 'none'; style-src 'unsafe-inline'` nas respostas SVG |
| Q17 | Abilities novas `badgeforge:read` e `badgeforge:write`; `ai:query` intocado |
| Q18 | Credencial na API com as mesmas regras da web (privado + provider compatível) |
| Q19 | Upload de logo em `multipart/form-data` |
| Q20 | `api-read` nos GET e novo `api-write` (30/min) em POST/DELETE; página 20/máx. 100 |
| Q21 | Spec servida em `GET /openapi/badges.yaml` |
| Q22 | Docs pública ganha seção curta (5 idiomas) + link da spec |
| Q23 | `/up` ganha checks de DB, cache e storage de PNG |
| Q24 | 429 do management: toast para Inertia, `429`+`Retry-After` para o resto |
| Q25 | `/b/**` passa a usar o envelope `ApiError` |
| Q26 | API de badges com POST/GET/DELETE (sem PATCH nem rotação de credencial) |
| Q27 | Tela de tokens: só `ai:query` marcado por padrão |

## Riscos registrados

- Token `ai:query` não pode ganhar escrita em badges por herança de grupo.
- Paridade rota↔spec precisa rodar na mesma etapa de cada rota.
- `/b/` exige mudança no `render` **e** no guard de `respond`, senão o 404
  JSON é reescrito pela página Inertia.
- 503 só para `QueryException`/falha de cache em `api/badges/*` e `b/*`.
- 429 do management detectado pelo método (CSV é GET), não por `expectsJson()`.
- CI sem lockfile versionado: `composer install`/`npm install`, sem
  `npm ci` e sem `--max-warnings 0`.

## Fora de escopo

Rotas Inertia na spec, PATCH/rotação de credencial via API, criação de endpoint
ou provider customizado via API, viewer embutido, meta-schema OpenAPI, API JSON
de analytics, página de status.
