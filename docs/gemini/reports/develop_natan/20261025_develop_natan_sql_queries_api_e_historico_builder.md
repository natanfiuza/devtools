# Relatório de Execução: Correção da API v1 (`/api/v1/sql-queries`) e Gravação do Histórico no AI SQL Builder

- **Data:** 2026-10-25
- **Branch:** `develop_natan`
- **Plano de Referência:** `docs/gemini/plans/develop_natan/20261025_develop_natan_sql_queries_api_e_historico_builder.md`
- **Survey Facts:** `docs/survey/20261025_sql_queries_api_e_historico_builder_surveyfacts.md`

---

## 1. Diagnóstico das Causas Raiz

### 1.1. `/api/v1/sql-queries` retornando `"data": []` mesmo com consultas geradas no AI SQL Builder
- `App\Http\Controllers\Api\V1\SqlQueryController::index` e `show` filtravam as consultas não apenas pela conta (`user_id`), mas também estritamente por `->where('api_token_id', $token->getKey())`.
- As consultas criadas pela interface web do AI SQL Builder (`POST /api/ai/queries`) são gravadas com `api_token_id = null`, ficando invisíveis para qualquer token de API v1 da mesma conta.
- Além disso, `SqlQueryController::index` filtrava por `->where('history_visible', true)`, que estava sendo gravado como `false` por padrão para todas as contas (ver item 1.2).

### 1.2. AI SQL Builder não gravando as consultas geradas no histórico
1. **Default `users.history_enabled = false`:** A migration `2026_10_22_000001_add_history_enabled_to_users_table.php` havia definido `history_enabled` com default `false`. Ao iniciar uma consulta em `StartAiQuery`, `$keepHistory = (bool) $user->history_enabled` avaliava para `false`, gravando `history_visible = false`, `title = null`, `prompt = null` e `schema = null`, além de impedir a persistência do `title` em `SettleAiQuery`. Como `GET /api/ai/history` filtra por `where('history_visible', true)`, nenhuma consulta era retornada.
2. **Falta de `title` em `SettleAiQuery::fromCache()`:** Quando uma consulta batia no cache (`fromCache`), o método atualizava `response_json`, mas não preenchia a coluna `title` mesmo com `history_visible = true`.
3. **Timing de atualização do histórico no frontend (`Tool.vue`):** O componente `resources/js/tools/ai-sql-builder/Tool.vue` chamava `queryHistoryRef.value?.reload()` imediatamente no `.then()` do `POST /api/ai/queries` (`202 Accepted`), quando a consulta ainda estava em `queued` (e `GET /api/ai/history` retorna apenas `status = done`). Quando o polling atingia `status === 'done'`, o histórico não era recarregado.

---

## 2. Alterações Realizadas

### 2.1. Banco de Dados e Modelos
- **[`database/migrations/2026_10_25_000001_enable_history_by_default_and_backfill_ai_requests.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/database/migrations/2026_10_25_000001_enable_history_by_default_and_backfill_ai_requests.php):**
  - Altera o default de `users.history_enabled` para `true`.
  - Atualiza os registros existentes em `users` (`history_enabled = true`) e `ai_requests` (`history_visible = true`).
  - Faz backfill de `ai_requests.title` a partir de `response_json` nas consultas concluídas onde `title` estava nulo.
- **[`app/Models/User.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Models/User.php):**
  - Define `'history_enabled' => true` em `$attributes` para garantir que instâncias em memória também nasçam com o histórico ativo por padrão (modelo opt-out).

### 2.2. Actions, Controllers e Resources (Backend)
- **[`app/Actions/Ai/SettleAiQuery.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Actions/Ai/SettleAiQuery.php):**
  - Em `fromCache()`, persiste `'title' => $locked->history_visible ? $hit->result->title : null`.
- **[`app/Http/Controllers/Api/V1/SqlQueryController.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Http/Controllers/Api/V1/SqlQueryController.php):**
  - Remove o filtro `->where('api_token_id', $token->getKey())` dos métodos `index()` e `show()`, escopando a leitura pela conta dona do token (`user_id` + `history_visible = true`).
  - Mantém o vínculo `api_token_id` na criação e deduplicação em `StartAiQuery`.
- **[`app/Http/Resources/ApiSqlQueryResource.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Http/Resources/ApiSqlQueryResource.php):**
  - Expõe os metadados completos da consulta: `title`, `schema_source`, `dialect`, `schema` e `description` (mapeado de `prompt`).

### 2.3. Frontend (AI SQL Builder)
- **[`resources/js/tools/ai-sql-builder/Tool.vue`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/ai-sql-builder/Tool.vue):**
  - Adiciona `watch(status, (newStatus) => { if (newStatus === 'done') queryHistoryRef.value?.reload(); })` para recarregar o componente `QueryHistory.vue` assim que o polling ou retorno em cache concluir a consulta.

### 2.4. Testes e Documentação
- **[`tests/Feature/Api/V1/SqlQueryApiTest.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/tests/Feature/Api/V1/SqlQueryApiTest.php):**
  - Atualizado para validar listagem e leitura escopadas por conta (incluindo consultas criadas no AI SQL Builder web e por outros tokens da mesma conta), respeito ao opt-out (`history_enabled = false`) e presença dos novos campos em `ApiSqlQueryResource`.
- **[`tests/Feature/Ai/AiActionsTest.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/tests/Feature/Ai/AiActionsTest.php) e [`tests/Feature/AccountPanelTest.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/tests/Feature/AccountPanelTest.php):**
  - Atualizados para refletir `history_enabled = true` como padrão (opt-out) e persistência de `title` em `fromCache()`.
- **Ajustes em testes pré-existentes:**
  - [`tests/Feature/SitemapTest.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/tests/Feature/SitemapTest.php): sincronizado `sitemapGroups()` com `App\Support\Sitemap::PAGES` adicionando `['contact', []]`.
  - [`tests/Feature/CheckoutTest.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/tests/Feature/CheckoutTest.php) e [`app/Providers/PaymentsServiceProvider.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Providers/PaymentsServiceProvider.php): ajustada asserção do `artisan about --json` via `Artisan::output()`.
  - [`resources/js/__tests__/contact.test.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/__tests__/contact.test.ts) e [`resources/js/__tests__/tools-registry.test.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/__tests__/tools-registry.test.ts): tipagem explícita de props em `contact.test.ts` e ajuste de timeout em `tools-registry.test.ts`.
- **Documentação atualizada:**
  - [`docs/API.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/API.md), [`docs/openapi.yaml`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/openapi.yaml), [`docs/SPEC.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/SPEC.md), [`docs/ARQUITETURA.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/ARQUITETURA.md) e [`docs/glossario.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/glossario.md).

---

## 3. Verificação e Gates de Qualidade

Todos os gates obrigatórios do repositório foram executados e aprovados:

1. **`composer test`:**
   - **Pint (`--test`):** `passed`
   - **PHPStan (`phpstan analyse`):** `passed` (0 erros)
   - **Pest (`@php artisan test`):** `passed` — 692 testes aprovados (14.975 asserções).
2. **`npm run test -- --run`:**
   - **Vitest:** `passed` — 61 arquivos de teste aprovados (798 testes).
3. **`npm run types:check`:**
   - **Wayfinder + `vue-tsc --noEmit`:** `passed` (0 erros).
