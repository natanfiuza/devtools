# ADR-057: Contador de Requisições da API e Documentação Pública da API v1

- **Data:** 2026-10-25
- **Status:** Aceita
- **Emenda:** ADR-050 (API pública v1 e Personal Access Tokens do Sanctum)

## Contexto

A ADR-050 introduziu a API pública v1 (`/api/v1`) autenticada por Personal Access Tokens do Laravel Sanctum e gerenciada pelo usuário em `/settings/api-tokens`, mas manteve a documentação da API apenas nos arquivos do repositório (`docs/API.md` e `docs/openapi.yaml`), sem uma página pública navegável no site e sem contadores de uso visíveis por token e por conta.

Além disso, calcular o total de consultas geradas via API apenas por `COUNT(*)` em `ai_requests` seria impreciso a longo prazo porque:
1. O comando diário `history:prune` remove solicitações terminais após `AI_HISTORY_RETENTION_DAYS` (90 dias por padrão);
2. A revogação de um token aplica `nullOnDelete` em `ai_requests.api_token_id` e apaga a linha em `personal_access_tokens`;
3. Requisições HTTP de leitura (`GET /api/v1/me`, `GET /api/v1/output-targets`, `GET /api/v1/sql-queries`, `GET /api/v1/sql-queries/{ulid}`) não geram linhas em `ai_requests`.

## Decisão

1. **Dupla Métrica de Uso da API (Por Token e Acumulado Vitalício da Conta):**
   - Em `personal_access_tokens`: adicionar `requests_count` (`unsignedBigInteger`, default `0`) e `sql_queries_count` (`unsignedBigInteger`, default `0`).
   - Em `users`: adicionar `api_requests_count` (`unsignedBigInteger`, default `0`) e `api_sql_queries_count` (`unsignedBigInteger`, default `0`) como acumuladores vitalícios da conta.
   - Assim, revogar um token ou podar o histórico de 90 dias (`history:prune`) nunca reduz o contador total da conta.
   - Na anonimização da conta (`AnonymizeUser`), os contadores em `users` são zerados junto com a revogação de todos os tokens; na exportação de dados (`ExportUserData`), os contadores da conta entram no JSON exportado.

2. **Momento do Incremento:**
   - **Requisições HTTP (`requests_count` e `api_requests_count`)**: incrementadas pelo middleware `TrackApiTokenUsage` no grupo `/api/v1` (após `auth:sanctum`, `ability:ai:query`, `verified` e `not_suspended`), contabilizando todas as chamadas autenticadas (`GET` e `POST`).
   - **Consultas SQL geradas via API (`sql_queries_count` e `api_sql_queries_count`)**: incrementadas dentro de `StartAiQuery` quando `$apiTokenId !== null`, tanto em solicitações novas quanto em *cache hits* imediatos, sem incrementar em deduplicações de solicitações já em voo (`inFlight`).

3. **Exibição dos Contadores:**
   - Em `/settings/api-tokens`: exibir 3 cards de resumo no topo (Total de requisições HTTP na API, Total de consultas SQL via API e Tokens ativos) e exibir `requests_count` e `sql_queries_count` em cada token listado.
   - Em `/admin/users/{user}`: exibir `apiRequestsCount`, `apiSqlQueriesCount` e `apiTokensCount` no card de consumo da conta.

4. **Página Pública de Documentação da API (`/{locale}/api-docs`) e Especificação OpenAPI (`/openapi.yaml`):**
   - Emendar a ADR-050 para expor a documentação da API pública v1 no próprio site em `GET /{locale}/api-docs` (rota nomeada `api-docs`, página `ApiDocs.vue` com `PublicLayout`), traduzida nos 5 idiomas (`pt-BR`, `pt-PT`, `en`, `es`, `fr`), indexável, com `hreflang` e incluída em `Sitemap::PAGES` e `Seo::pageMeta()`.
   - Expor `GET /openapi.yaml` (rota nomeada `openapi`, sem prefixo de idioma) servindo `docs/openapi.yaml`.
   - Adicionar um banner/link de destaque na aba **Tokens de API** (`/settings/api-tokens`) e um link no rodapé público (`PublicLayout.vue`) apontando para `/{locale}/api-docs`.

## Consequências

- **Positivas:** Visibilidade imediata para o usuário e para o administrador sobre o volume de chamadas HTTP e consultas SQL geradas por cada integração; leitura O(1) sem `COUNT(*)` pesado; preservação do histórico acumulado após revogação de tokens ou expurgo de 90 dias; integração facilitada com exemplos prontos (`cURL`, `JavaScript / TS`, `PHP`, `Python`, `n8n`) usando a URL real da aplicação.
- **Negativas / Trade-offs:** Duas instruções `increment()` leves por chamada na API v1, executadas apenas em requisições autenticadas que já carregam o usuário e o PAT do Sanctum.
