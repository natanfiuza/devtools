# Plano de Implementação — Contador de Requisições da API e Página de Documentação Pública (`/{locale}/api-docs`)

**Data**: 2026-10-25
**Branch**: `develop_natan`
**Survey**: `docs/survey/20261025_contador_e_documentacao_api_surveyfacts.md`

---

## 1. Objetivo

1. **Contador de Requisições da API**:
   - Contabilizar tanto o **total de requisições HTTP autenticadas** na superfície `/api/v1/*` (`GET` e `POST`) quanto o **total de consultas SQL criadas via API** (`POST /api/v1/sql-queries`), por token (`personal_access_tokens`) e no acumulado vitalício da conta (`users`).
   - Exibir os contadores na aba **Tokens de API** (`/settings/api-tokens`) — cards de resumo no topo + contadores individuais por token na lista — e na tela de detalhe do usuário no **Painel Administrativo** (`/admin/users/{user}`).
2. **Página Pública de Documentação da API (`/{locale}/api-docs`)**:
   - Criar uma página pública, indexável e traduzida nos 5 idiomas (`pt-BR`, `pt-PT`, `en`, `es`, `fr`) explicando como integrar o AI SQL Builder via API v1 com outras ferramentas.
   - Expor a especificação OpenAPI em `GET /openapi.yaml` para download/importação em clientes HTTP (Postman, Insomnia, Bruno, etc.).
   - Incluir exemplos copiáveis em abas (`cURL`, `JavaScript / TypeScript`, `PHP`, `Python`, `n8n / HTTP Request`) com a URL base real da aplicação.
   - Adicionar um link/botão de destaque para a página de documentação dentro da aba **Tokens de API** (`/settings/api-tokens`) e um link no rodapé público (`PublicLayout.vue`).

---

## 2. Etapas de Implementação

### Etapa 1 — Banco de Dados e Modelos (Migrations Retrocompatíveis)
- Criar a migration aditiva `database/migrations/2026_10_25_000001_add_api_request_counters_to_tokens_and_users.php`:
  - Em `personal_access_tokens`:
    - `requests_count` (`unsignedBigInteger`, `default(0)`)
    - `sql_queries_count` (`unsignedBigInteger`, `default(0)`)
  - Em `users`:
    - `api_requests_count` (`unsignedBigInteger`, `default(0)`)
    - `api_sql_queries_count` (`unsignedBigInteger`, `default(0)`)
  - **Backfill seguro na migration**: atualizar `sql_queries_count` e `requests_count` nos tokens existentes e `api_sql_queries_count` / `api_requests_count` em `users` a partir das contagens existentes em `ai_requests` onde `api_token_id IS NOT NULL`.
- Atualizar `App\Models\User` (`casts` para `api_requests_count` e `api_sql_queries_count` como `integer`).
- Atualizar `App\Actions\Account\ExportUserData` para incluir as estatísticas de uso da API e contadores dos tokens na exportação de dados da conta.

### Etapa 2 — Rastreamento de Requisições HTTP e Consultas SQL na API v1
- Criar o middleware `App\Http\Middleware\TrackApiTokenUsage`:
  - Executado no grupo `Route::prefix('v1')->middleware(['auth:sanctum', 'active', 'verified', 'abilities:ai:query', TrackApiTokenUsage::class])` em `routes/api.php`.
  - Após a requisição ser autenticada e autorizada pelo token Sanctum, incrementa atomicamente:
    - `PersonalAccessToken::whereKey($token->getKey())->increment('requests_count')`
    - `User::whereKey($user->getKey())->increment('api_requests_count')`
- Atualizar `App\Actions\Ai\StartAiQuery`:
  - Quando `$apiTokenId !== null` e a solicitação **não** for `deduplicated` (ou seja, conta tanto para cache hit quanto para nova solicitação na fila):
    - Incrementar `PersonalAccessToken::whereKey($apiTokenId)->increment('sql_queries_count')`
    - Incrementar `User::whereKey($lockedUser->getKey())->increment('api_sql_queries_count')`

### Etapa 3 — Backend e Frontend do Contador em `/settings/api-tokens` e no Admin
- **`App\Http\Controllers\Settings\ApiTokenController`**:
  - Em `index()`, incluir em cada item de `tokens`:
    - `requestsCount` (`(int) $token->requests_count`)
    - `sqlQueriesCount` (`(int) $token->sql_queries_count`)
  - Enviar prop adicional `stats`:
    - `totalRequests`: `(int) $user->api_requests_count`
    - `totalSqlQueries`: `(int) $user->api_sql_queries_count`
    - `activeTokens`: `$tokens->count()`
- **`resources/js/types/settings.ts` e `resources/js/pages/settings/ApiTokens.vue`**:
  - Atualizar tipos `ApiToken` (`requestsCount`, `sqlQueriesCount`) e `ApiTokensProps` (`stats`).
  - Adicionar no topo de `ApiTokens.vue`:
    - **Banner / Link para a Documentação da API**: card/faixa com ícone `BookOpen`, explicação curta e botão ` outline` levando para `routes.apiDocs.value` (`/{locale}/api-docs`).
    - **Cards de Resumo de Uso da API** (`dl` em grid de 3 colunas seguindo o padrão visual do `DESIGN.md` — título `12px uppercase text-subtle`, valor em `font-mono text-2xl`):
      1. Total de requisições à API (`stats.totalRequests`)
      2. Consultas SQL geradas via API (`stats.totalSqlQueries`)
      3. Tokens ativos (`stats.activeTokens`)
  - Na lista de tokens (`data-test="api-tokens-lista"`), exibir ao lado de "Criado em" e "Último uso" o número de requisições HTTP e consultas daquele token formatados no idioma da página.
- **Painel Admin (`App\Http\Controllers\Admin\UserController` e `resources/js/pages/admin/UserDetail.vue`)**:
  - Incluir os contadores `apiRequestsCount`, `apiSqlQueriesCount` e `apiTokensCount` nos dados de `quota` ou seção de consumo do usuário em `UserDetail.vue`.

### Etapa 4 — Página Pública de Documentação da API (`/{locale}/api-docs`) e `/openapi.yaml`
- **Rotas (`routes/web.php`)**:
  - No grupo `Route::prefix('{locale}')`: `Route::get('api-docs', ApiDocsController::class)->name('api-docs');`
  - Fora do prefixo de idioma (como `sitemap.xml` e `robots.txt`): `Route::get('openapi.yaml', OpenApiController::class)->name('openapi');`
- **Controllers**:
  - `App\Http\Controllers\ApiDocsController`: renderiza `Inertia::render('ApiDocs', [...])` passando:
    - `baseUrl`: `rtrim((string) config('app.url'), '/').'/api/v1'`
    - `openApiUrl`: `route('openapi')`
    - `tokensSettingsUrl`: `route('api-tokens.index')`
    - `rateLimits`: lidos de `config('api.rate_limits')` (`postPerMinute`, `postPerHour`, `readPerMinute`)
    - `paginationPerPage`: `(int) config('api.pagination.per_page', 20)`
    - `dialects`: `['mysql', 'pgsql', 'sqlsrv', 'sqlite']`
    - `schemaSources`: `['ddl', 'json', 'migration', 'models']`
    - `targets`: alvos habilitados (`OutputTarget::query()->enabled()->orderBy('sort')->get(['slug', 'label', 'language', 'supported_dialects'])`)
  - `App\Http\Controllers\OpenApiController`: serve o conteúdo de `base_path('docs/openapi.yaml')` com cabeçalho `Content-Type: application/yaml; charset=UTF-8`.
- **SEO, Sitemap e Navegação**:
  - `App\Support\Sitemap`: adicionar `'api-docs'` em `Sitemap::PAGES`.
  - `App\Support\Seo`: adicionar `'api-docs'` no `match` de `Seo::pageMeta()`.
  - `resources/js/composables/useRoutes.ts`: expor `apiDocs: ComputedRef<string>` usando a rota gerada pelo Wayfinder.
  - `resources/js/layouts/PublicLayout.vue`: adicionar link para `routes.apiDocs.value` no rodapé público.
- **Página `resources/js/pages/ApiDocs.vue`**:
  - Seguir estritamente o `DESIGN.md` (`max-w-[1120px]`, tipografia Inter + JetBrains Mono, cards de borda `1px`, sem sombra).
  - Seções:
    1. **Cabeçalho**: Título, subtítulo, URL base copiável (`https://.../api/v1`), botão para **Gerenciar Tokens de API** (`/settings/api-tokens`) e botão para **Especificação OpenAPI (`openapi.yaml`)**.
    2. **Início Rápido & Autenticação**: Como criar o Personal Access Token na aba `Tokens de API`, enviar `Authorization: Bearer <token>`, cabeçalhos obrigatórios e funcionamento do ciclo assíncrono (`200 OK` em cache hit vs `202 Accepted` na fila + polling em `poll_url`).
    3. **Exemplos de Integração (Abas interativas com botão Copiar)**:
       - `cURL` (criar consulta + consultar `poll_url`)
       - `JavaScript / TypeScript (fetch)` (função assíncrona com loop de polling respeitando `poll_after_ms`)
       - `PHP (Laravel Http)`
       - `Python (requests)`
       - `n8n / HTTP Request` (configuração passo a passo de Header Auth e nós HTTP Request)
    4. **Referência de Endpoints**:
       - `GET /api/v1/me`
       - `GET /api/v1/output-targets`
       - `POST /api/v1/sql-queries` (com tabela de campos: `schema_source`, `dialect`, `output_target`, `schema`, `description`, `locale` e lista dinâmica dos alvos habilitados)
       - `GET /api/v1/sql-queries`
       - `GET /api/v1/sql-queries/{ulid}`
    5. **Limites de Taxa (Rate Limits) e Códigos de Erro**:
       - Tabela dinâmica com os limites de `config/api.php` (`30/min`, `300/h`, `120/min`) e cabeçalho `Retry-After`.
       - Tabela de códigos de erro HTTP (`401`, `402`, `403`, `404`, `422`, `429`, `503`) e envelope `{ error: { code, message, details } }`.

### Etapa 5 — Internacionalização (i18n nos 5 idiomas)
- Adicionar as novas chaves em `lang/{pt_BR,pt_PT,en,es,fr}/`:
  - `settings.php` (`api_tokens.stats.*`, `api_tokens.docs_banner.*`, `api_tokens.requests_count`, `api_tokens.queries_count`)
  - `admin.php` (chaves de uso da API no detalhe da conta)
  - `app.php` (`footer.api_docs`)
  - Novo arquivo `lang/{pt_BR,pt_PT,en,es,fr}/api_docs.php` (e registrar o namespace se necessário no carregamento de traduções do Inertia) com todos os textos da página de documentação da API nos 5 idiomas, mantendo paridade 100% no `TranslationsParityTest`.

### Etapa 6 — Documentação e Testes
- **Documentação**:
  - Criar `docs/adr/ADR-057-contador-e-documentacao-publica-da-api.md` (e atualizar cabeçalho da `ADR-050`).
  - Atualizar `docs/glossario.md`, `docs/SPEC.md`, `docs/ARQUITETURA.md`, `docs/API.md`, `docs/RUNBOOK.md` e `CHANGELOG.md`.
- **Testes**:
  - Backend (Pest):
    - Ampliar `tests/Feature/Api/V1/SqlQueryApiTest.php` e `tests/Feature/Settings/ApiTokenSettingsTest.php` para validar o incremento de `requests_count` (em `GET` e `POST`), `sql_queries_count` (em `POST`, sem contar dedupe), preservação dos acumuladores `api_requests_count` e `api_sql_queries_count` em `users` mesmo após revogar o token, e entrega de `stats` na tela `settings/ApiTokens`.
    - Criar `tests/Feature/ApiDocsPageTest.php` testando a página `/{locale}/api-docs` nos 5 idiomas, metas de SEO, presença no `sitemap.xml` e a rota `/openapi.yaml`.
  - Frontend (Vitest):
    - Ampliar `resources/js/__tests__/api-tokens-settings.test.ts` para verificar a exibição dos contadores (resumo + por token) e o link para a documentação da API.
    - Criar teste unitário para `ApiDocs.vue`.
- **Gates Finais**:
  - Rodar `composer test`, `npm run test` e `npm run types:check`.
  - Gerar relatório final em `docs/gemini/reports/develop_natan/20261025_develop_natan_contador_e_documentacao_api.md`.
