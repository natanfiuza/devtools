# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona contadores de uso e documentação pública da API v1
```

---

## 🎯 Resumo

Usuários da API pública v1 não tinham visibilidade do próprio consumo, e o produto não oferecia documentação pública de integração — o que limitava a adoção por times externos. Esta mudança adiciona contadores vitalícios de requisições HTTP e de consultas SQL por token e por conta, expõe esses números na área de configurações e no painel administrativo, e publica uma página de documentação da API (`/{locale}/api-docs`) com exemplos em cURL, JS/TS, PHP, Python e n8n, além da especificação OpenAPI 3.1 servida em `/openapi.yaml`.

## 🛠️ Mudanças Técnicas

- Migration `2026_10_25_000001` adiciona `requests_count` e `sql_queries_count` em `personal_access_tokens` e `api_requests_count`/`api_sql_queries_count` em `users`, com backfill a partir de `ai_requests` agrupado por token e por usuário.
- Novo middleware `TrackApiTokenUsage` no grupo `/api/v1` (após `auth:sanctum`, `active`, `verified`, `abilities:ai:query`) incrementa o contador do token e o acumulado da conta por requisição autenticada válida.
- `StartAiQuery` passa a incrementar o contador de consultas SQL em cache hit e em nova solicitação aceita, sem contar deduplicação em voo (evita dupla contagem).
- `ApiTokenController@index` passa a retornar `requestsCount`/`sqlQueriesCount` por token e bloco `stats` (total de requisições, total de consultas, tokens ativos).
- `Admin\UserController@show` inclui `apiRequestsCount`, `apiSqlQueriesCount` e `apiTokensCount` na cota exibida.
- `AnonymizeUser` zera os novos contadores e `ExportUserData` passa a incluí-los no export (LGPD).
- Novos `ApiDocsController` (Inertia `ApiDocs`) e `OpenApiController` (serve `docs/openapi.yaml` com `application/yaml`); rotas `api-docs` dentro do grupo de locale e `openapi.yaml` fora do prefixo.
- Nova página `ApiDocs.vue` com URL base copiável, fluxo de autenticação assíncrono (200 cache / 202 fila), tabela de endpoints, alvos de saída habilitados, rate limits e tabela de erros.
- Frontend: `ApiTokens.vue` exibe banner de documentação, cards de estatísticas e contadores por token; `UserDetail.vue` exibe contadores administrativos; `PublicLayout.vue` ganha link no rodapé.
- i18n (`pt_BR`, `pt_PT`, `en`, `es`, `fr`) com novo arquivo `api_docs.php`, chaves em `settings.php`, `admin.php` e `app.php`; SEO em `Seo.php`, sitemap e configuração de locales atualizados.
- Testes: contadores por token/conta em `SqlQueryApiTest`, página de docs e OpenAPI em `ApiDocsPageTest`, estatísticas e preservação pós-revogação em `ApiTokenSettingsTest`, além de SEO/Sitemap e teste de componente `api-tokens-settings.test.ts`.

## ⚠️ Impacto/Avisos

- **Banco de dados:** novas colunas em `personal_access_tokens` e `users` com `default 0`. A migration executa backfill iterando grupos de `ai_requests`; em bases grandes isso pode alongar o deploy — recomenda-se janela de manutenção ou execução assíncrona. O `down()` remove as colunas e perde o histórico acumulado.
- **Escrita extra por requisição:** cada chamada autenticada à API v1 gera dois `UPDATE` adicionais (token e usuário). Avaliar impacto em ambientes com alto tráfego.
- **Sem novas variáveis de ambiente:** os limites exibidos usam `config('api.rate_limits.*')` e `config('api.pagination.per_page')` com fallbacks (30/300/120 e 20).
- **Dependência de arquivo:** `/openapi.yaml` depende de `docs/openapi.yaml` existir no deploy (retorna 404 caso ausente).
- **Novas rotas públicas:** `/{locale}/api-docs` e `/openapi.yaml` — verificar cache de CDN/nginx e exposição em produção.
- **Compatibilidade de tipos:** campos novos no frontend são opcionais (`?`), sem quebra para payloads antigos.
- **Privacidade:** export de dados do usuário passa a incluir os contadores; anonimização os zera conforme política LGPD.

close #99

---

[![GitPR](https://img.shields.io/badge/GitPR-1_error_%C2%B7_6_warnings-red)](https://gitpr.natanfiuza.dev.br/)