# Relatório — Contador de Requisições da API e Página Pública de Documentação da API v1

- **Data:** 2026-10-25
- **Branch:** `develop_natan`
- **Tarefa:** `contador_e_documentacao_api`
- **Plano:** `docs/gemini/plans/develop_natan/20261025_develop_natan_contador_e_documentacao_api.md`
- **Levantamento (Survey):** `docs/survey/20261025_contador_e_documentacao_api_surveyfacts.md`
- **ADR:** `docs/adr/ADR-057-contador-e-documentacao-publica-da-api.md`

---

## 1. Resumo Executivo

Foram implementados:
1. **Contador de requisições da API (dupla métrica por token e acumulado vitalício da conta):**
   - Contabilização de todas as requisições HTTP autenticadas à superfície `/api/v1/*` (`GET` e `POST`) via middleware `TrackApiTokenUsage` (`personal_access_tokens.requests_count` e `users.api_requests_count`).
   - Contabilização das consultas SQL geradas via API (`POST /api/v1/sql-queries`, incluindo *cache hits* imediatos e excluindo deduplicação em voo) em `StartAiQuery` (`personal_access_tokens.sql_queries_count` e `users.api_sql_queries_count`).
   - Preservação dos totais acumulados da conta (`users`) mesmo após revogação de tokens ou expurgo de 90 dias do histórico (`history:prune`).
   - Exibição dos 3 cards de resumo (`Requisições HTTP na API`, `Consultas SQL via API`, `Tokens ativos`) e dos contadores por token na aba **Tokens de API** (`/settings/api-tokens`), além do resumo no detalhe do usuário no Painel Admin (`/admin/users/{user}`).
2. **Página Pública de Documentação da API (`/{locale}/api-docs`) e Especificação OpenAPI (`/openapi.yaml`):**
   - Página pública e indexável (`ApiDocs.vue` + `ApiDocsController`) nos 5 idiomas (`pt-BR`, `pt-PT`, `en`, `es`, `fr`), usando `PublicLayout`, integrada ao `Sitemap::PAGES`, `Seo::pageMeta()` e `hreflang`.
   - Guia de autenticação Bearer (`ai:query`), explicação do fluxo assíncrono (`200` cache hit vs `202` fila + polling em `poll_url`), referência dos 5 endpoints da API v1, tabela dinâmica de alvos de saída habilitados (`output_targets`), limites de taxa configurados e tabela de códigos de erro.
   - Exemplos de código interativos e copiáveis em 5 abas (`cURL`, `JavaScript / TS`, `PHP`, `Python`, `n8n / HTTP`) utilizando a URL base real da aplicação.
   - Rota `GET /openapi.yaml` (`OpenApiController`) servindo `docs/openapi.yaml`.
3. **Links de Acesso:**
   - Banner/card de destaque no topo da aba **Tokens de API** (`/settings/api-tokens`, `data-test="api-tokens-docs-link"`) apontando para `/{locale}/api-docs`.
   - Link discreto no rodapé público (`PublicLayout.vue`, `data-test="footer-api-docs"`).

---

## 2. Arquivos Criados e Modificados

### Banco de Dados, Modelos, Middleware e Ações
- `database/migrations/2026_10_25_000001_add_api_request_counters_to_tokens_and_users.php` *(novo)* — Migration aditiva para `personal_access_tokens` (`requests_count`, `sql_queries_count`) e `users` (`api_requests_count`, `api_sql_queries_count`), com backfill inicial a partir de `ai_requests`.
- `app/Models/User.php` — Casts e defaults para `api_requests_count` e `api_sql_queries_count`.
- `app/Http/Middleware/TrackApiTokenUsage.php` *(novo)* — Incrementa `requests_count` no token Sanctum e `api_requests_count` no usuário autenticado.
- `app/Actions/Ai/StartAiQuery.php` — Incrementa `sql_queries_count` no token e `api_sql_queries_count` no usuário quando `$apiTokenId !== null` (em *cache hits* e solicitações novas, sem duplicar em *in-flight dedupe*).
- `app/Actions/Account/ExportUserData.php` — Inclui `api_requests_count` e `api_sql_queries_count` na exportação JSON da conta.
- `app/Actions/Account/AnonymizeUser.php` — Zera `api_requests_count` e `api_sql_queries_count` na anonimização da conta.

### Controladores, Rotas, SEO e Configuração
- `app/Http/Controllers/Settings/ApiTokenController.php` — Envia `requestsCount` e `sqlQueriesCount` por token e `stats` (`totalRequests`, `totalSqlQueries`, `activeTokens`).
- `app/Http/Controllers/Admin/UserController.php` — Envia `apiRequestsCount`, `apiSqlQueriesCount` e `apiTokensCount` em `quota`.
- `app/Http/Controllers/ApiDocsController.php` *(novo)* — Renderiza `ApiDocs` com `baseUrl`, `openApiUrl`, `tokensSettingsUrl`, `rateLimits`, `paginationPerPage` e `targets` habilitados.
- `app/Http/Controllers/OpenApiController.php` *(novo)* — Serve `docs/openapi.yaml` em `GET /openapi.yaml`.
- `routes/api.php` — Registra `TrackApiTokenUsage::class` no grupo `v1`.
- `routes/web.php` — Registra `GET /{locale}/api-docs` (`api-docs`) e `GET /openapi.yaml` (`openapi`).
- `app/Support/Sitemap.php` — Inclui `'api-docs'` em `Sitemap::PAGES`.
- `app/Support/Seo.php` — Inclui `'api-docs'` em `Seo::pageMeta()`.
- `config/locales.php` — Expõe o namespace `'api_docs'` para o frontend.

### Traduções (5 idiomas: `pt_BR`, `pt_PT`, `en`, `es`, `fr`)
- `lang/{pt_BR,pt_PT,en,es,fr}/api_docs.php` *(novos)*
- `lang/{pt_BR,pt_PT,en,es,fr}/settings.php`
- `lang/{pt_BR,pt_PT,en,es,fr}/admin.php`
- `lang/{pt_BR,pt_PT,en,es,fr}/app.php`

### Frontend (Vue 3 + TypeScript)
- `resources/js/pages/ApiDocs.vue` *(novo)* — Página pública de documentação da API v1.
- `resources/js/pages/settings/ApiTokens.vue` — Banner com link para a documentação da API, 3 cards de contadores de resumo e contadores individuais em cada token.
- `resources/js/pages/admin/UserDetail.vue` — Exibição de uso da API no card de consumo.
- `resources/js/layouts/PublicLayout.vue` — Link para a documentação da API no rodapé público.
- `resources/js/lib/inertiaApp.ts` — Registra `ApiDocs` no `PublicLayout`.
- `resources/js/composables/useRoutes.ts` — Adiciona `apiDocs` em `useRoutes()`.
- `resources/js/types/settings.ts` e `resources/js/types/admin.ts` — Tipagem estrita das novas propriedades.

### Testes e Documentação
- `tests/Feature/ApiDocsPageTest.php` *(novo)*
- `tests/Feature/Settings/ApiTokenSettingsTest.php`
- `tests/Feature/Api/V1/SqlQueryApiTest.php`
- `tests/Feature/SitemapTest.php`
- `tests/Feature/SeoMetaTest.php`
- `resources/js/__tests__/api-tokens-settings.test.ts`
- `docs/adr/ADR-057-contador-e-documentacao-publica-da-api.md` *(novo)*
- `docs/glossario.md`, `docs/SPEC.md`, `docs/ARQUITETURA.md`, `docs/API.md`, `docs/RUNBOOK.md`, `CHANGELOG.md`

---

## 3. Verificação e Gates Executados

| Gate | Comando | Resultado |
|---|---|---|
| Backend (Pint + PHPStan + Pest 4) | `composer test` | **PASS** — Pint ok, PHPStan 0 erros, Pest **696 testes / 15.657 asserções** |
| Frontend (Vitest 5) | `npm run test` | **PASS** — **61 arquivos / 799 testes** |
| Typecheck (Wayfinder + `vue-tsc --noEmit`) | `npm run types:check` | **PASS** — 0 erros |

Nenhum commit ou push foi realizado, conforme as regras do projeto (`GEMINI.md`).
