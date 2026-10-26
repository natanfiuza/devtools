# Survey — Contador de Requisições e Documentação da API (2026-10-25)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** "Crie um contador de requisições da API / Crie tambem uma página com a documentação de como usar a API para integrar com outras ferramentas / Esta pagina de documentacao deve ter um link na aba Tokens de API"
- **Tela / Módulo:**
  - Tokens de API: `/settings/api-tokens` (`resources/js/pages/settings/ApiTokens.vue`, `app/Http/Controllers/Settings/ApiTokenController.php`)
  - Documentação Pública da API: `/{locale}/api-docs` (`resources/js/pages/ApiDocs.vue`, `app/Http/Controllers/ApiDocsController.php`) e `/openapi.yaml` (`app/Http/Controllers/OpenApiController.php`)
  - Superfície da API v1: `/api/v1/*` (`routes/api.php`, `app/Http/Middleware/TrackApiTokenUsage.php`, `app/Actions/Ai/StartAiQuery.php`)
  - Detalhe de Usuário no Admin: `/admin/users/{user}` (`app/Http/Controllers/Admin/UserController.php`, `resources/js/pages/admin/UserDetail.vue`)
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 2 rodadas, 7 decisões (Q1 a Q7), inspeção autônoma de rotas, controllers, models, migrations, ADRs e testes.
- **Arquivos-chave lidos:** `docs/SPEC.md`, `docs/glossario.md`, `docs/ARQUITETURA.md`, `docs/API.md`, `docs/openapi.yaml`, `docs/RUNBOOK.md`, `docs/design/DESIGN.md`, `routes/api.php`, `routes/settings.php`, `routes/web.php`, `bootstrap/app.php`, `app/Http/Controllers/Settings/ApiTokenController.php`, `resources/js/pages/settings/ApiTokens.vue`, `app/Http/Controllers/Api/V1/SqlQueryController.php`, `app/Actions/Ai/StartAiQuery.php`, `app/Http/Controllers/Admin/UserController.php`, `resources/js/pages/admin/UserDetail.vue`, `app/Support/Seo.php`, `app/Support/Sitemap.php`.

## 2. Decisões das rodadas

### Rodada 1 — Escopo do Contador, Exibição, Acesso e Conteúdo da Documentação
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Escopo do contador de requisições da API | **Opções 1 e 3 combinadas**: Contabilizar tanto o total de requisições HTTP autenticadas à `/api/v1/*` (`GET` e `POST`) quanto o total de consultas SQL geradas via API (`POST /api/v1/sql-queries`), por token e no acumulado da conta. |
| Q2 | Onde exibir o contador de requisições | **Recomendado**: Na aba **Tokens de API** (`/settings/api-tokens`), exibindo cards de resumo no topo (total de requisições HTTP da conta, total de consultas geradas via API e tokens ativos) e o contador individual em cada token listado; além de exibir o resumo de uso de API no detalhe do usuário no Painel Admin (`/admin/users/{user}`). |
| Q3 | Localização, acesso e SEO da página de documentação da API | **Opção 2**: Página pública e indexável sob `/{locale}/api-docs`, usando `PublicLayout`, com SEO completo, `hreflang` nos 5 idiomas e presença no `sitemap.xml`, acessível com ou sem login, e linkada de forma destacada na aba `Tokens de API`. |
| Q4 | Conteúdo e interatividade da página de documentação da API | **Recomendado**: Guia completo nos 5 idiomas (`pt-BR`, `pt-PT`, `en`, `es`, `fr`) com autenticação Bearer, fluxo assíncrono (`200`/`202` + polling em `poll_url`), referência dos 5 endpoints (`/me`, `/output-targets`, `POST /sql-queries`, `GET /sql-queries`, `GET /sql-queries/{ulid}`), tabela de parâmetros/erros/rate limits, snippets copiáveis em abas (`cURL`, `JavaScript / TypeScript`, `PHP`, `Python`, `n8n / HTTP`) usando a URL base real da aplicação e botão para baixar/abrir a especificação `openapi.yaml`. |

### Rodada 2 — Persistência em Revogação, Rotas e Modelagem de Domínio
| # | Decisão | Resolução |
|---|---|---|
| Q5 | Persistência do contador ao revogar tokens e momento do incremento | **Recomendado**: Adicionar colunas `requests_count` e `sql_queries_count` em `personal_access_tokens` (por token) e `api_requests_count` e `api_sql_queries_count` em `users` (acumuladores vitalícios da conta que não regridem ao revogar tokens nem após o `history:prune` de 90 dias). Incrementar `requests_count` e `api_requests_count` via middleware `TrackApiTokenUsage` no grupo `/api/v1`, e incrementar `sql_queries_count` e `api_sql_queries_count` em `StartAiQuery` quando `$apiTokenId !== null` (incluindo cache hits, exceto dedupe em voo), com backfill inicial na migration. |
| Q6 | Slug da URL pública, rota do `openapi.yaml` e links no site | **Recomendado**: Rota `GET /{locale}/api-docs` (nome `api-docs`), rota `GET /openapi.yaml` (nome `openapi`, servindo `docs/openapi.yaml`), botão/link destacado em `/settings/api-tokens` apontando para `/{locale}/api-docs` e link discreto no rodapé público (`PublicLayout.vue`). |
| Q7 | Modelagem de Domínio (ADR e Glossário) | **Recomendado**: Criar `ADR-057-contador-e-documentacao-publica-da-api.md` (emendando a `ADR-050` quanto à página pública de documentação e definindo os contadores de API), adicionar "Contador de requisições da API" ao `docs/glossario.md` e sincronizar `docs/SPEC.md`, `docs/ARQUITETURA.md`, `docs/API.md` e `docs/RUNBOOK.md`. |

### Defaults assumidos (não contestados)
- Todas as migrations serão estritamente aditivas (`default(0)`), respeitando o deploy sem downtime.
- Na anonimização da conta (`AnonymizeUser`), os tokens continuam sendo revogados e os contadores da conta são zerados ou mantidos de forma anônima; na exportação de dados (`ExportUserData`), os metadados de uso da API entram no JSON exportado.
- Nenhuma credencial, token em claro, schema ou prompt entra em log.

## 3. Relatório de fatos levantados

### Rastreamento atual de tokens Sanctum e `ai_requests`
- A tabela `personal_access_tokens` guarda `last_used_at`, mas não possui contador de requisições HTTP nem de consultas geradas.
- A tabela `ai_requests` possui `api_token_id` (`nullOnDelete` quando o token é revogado) e sofre poda diária de linhas terminais após 90 dias (`history:prune`). Portanto, contar `ai_requests` dinamicamente via `COUNT(*)` perderia histórico após 90 dias ou perderia o vínculo do token após revogação. Colunas acumuladoras dedicadas em `personal_access_tokens` e `users` resolvem ambos os problemas com leitura O(1).

### Estrutura de rotas públicas e SEO (`App\Support\Seo` e `App\Support\Sitemap`)
- Todas as páginas públicas indexáveis vivem no grupo `Route::prefix('{locale}')` em `routes/web.php`, são listadas em `Sitemap::PAGES` (`app/Support/Sitemap.php`), possuem título e descrição resolvidos no servidor em `Seo::pageMeta()` (`app/Support/Seo.php`) e rota exposta em `useRoutes.ts` (`resources/js/composables/useRoutes.ts`).
- `PublicLayout.vue` é aplicado automaticamente pelo resolver de layout do Inertia para páginas fora de `dashboard/*`, `settings/*`, `admin/*` e `auth/*` (ou explicitamente via `defineOptions({ layout: PublicLayout })`).
