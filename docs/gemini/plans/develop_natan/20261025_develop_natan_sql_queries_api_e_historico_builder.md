# Plano de Implementação — Correção da API v1 (`/api/v1/sql-queries`) e Gravação de Consultas no AI SQL Builder

- **Data:** 2026-10-25
- **Branch:** `develop_natan`
- **Survey de Referência:** `docs/survey/20261025_sql_queries_api_e_historico_builder_surveyfacts.md`

---

## 1. Visão Geral

Este plano corrige dois problemas reportados pelo usuário:
1. **`GET /api/v1/sql-queries` retornando `"data": []` mesmo com queries criadas no AI SQL Builder**: Remover o filtro restritivo por `api_token_id` na leitura (`SqlQueryController::index` e `SqlQueryController::show`), escopando a leitura pela conta (`user_id`) e enriquecendo o `ApiSqlQueryResource` com os metadados da consulta (`title`, `schema_source`, `dialect`, `schema`, `description`).
2. **AI SQL Builder não gravando/exibindo as consultas geradas**:
   - Mudar o padrão de `users.history_enabled` para `true` (opt-out) via migration retrocompatível, no model `User` e na `UserFactory`, fazendo backfill de `users.history_enabled = true` e `ai_requests.history_visible = true` (e extraindo `title` de `response_json` onde aplicável) para registros já existentes.
   - Corrigir `SettleAiQuery::fromCache()` para também gravar `title` quando `history_visible` for `true`.
   - Corrigir o ciclo de vida no frontend (`Tool.vue`) para recarregar o componente `QueryHistory.vue` quando o `status` transitar para `'done'` (após o polling concluir, e não apenas no término do `POST` inicial).

---

## 2. Etapas de Implementação

### Etapa 1: Migration e Modelo (`users.history_enabled` e backfill de `ai_requests`)
- **Arquivo novo:** `database/migrations/2026_10_25_000001_enable_history_by_default_and_backfill_ai_requests.php`
  - Alterar o default da coluna `users.history_enabled` para `true`.
  - Atualizar registros existentes em `users` para `history_enabled = true`.
  - Atualizar registros existentes em `ai_requests` para `history_visible = true`.
  - Para linhas em `ai_requests` onde `title` é `null` e `response_json` não é `null`, extrair `title` do JSON (quando presente) e preencher `title`.
- **Arquivo:** `app/Models/User.php`
  - Adicionar `'history_enabled' => true` em `$attributes` para que novas instâncias em memória já nasçam com `history_enabled = true` (alinhado ao default do banco).

### Etapa 2: Backend do AI SQL Builder (`SettleAiQuery`)
- **Arquivo:** `app/Actions/Ai/SettleAiQuery.php`
  - No método `fromCache()`, incluir `'title' => $locked->history_visible ? $hit->result->title : null` no `forceFill(...)`, garantindo paridade com o método `__invoke()` e com o cache hit imediato do `StartAiQuery`.

### Etapa 3: API Pública v1 (`SqlQueryController` e `ApiSqlQueryResource`)
- **Arquivo:** `app/Http/Controllers/Api/V1/SqlQueryController.php`
  - Em `index()` e `show()`, filtrar as consultas por `->where('user_id', $user->getKey())` (removendo `->where('api_token_id', $token->getKey())`), permitindo listar e consultar todas as queries da conta (criadas no AI SQL Builder web ou por qualquer token da conta).
- **Arquivo:** `app/Http/Resources/ApiSqlQueryResource.php`
  - Incluir no array de saída:
    - `'title' => $model->title` (já vem do `AiQueryResource`, manter garantido)
    - `'schema_source' => $model->schema_source->value`
    - `'dialect' => $model->dialect->value`
    - `'schema' => $model->schema`
    - `'description' => $model->prompt`

### Etapa 4: Frontend do AI SQL Builder (`Tool.vue`)
- **Arquivo:** `resources/js/tools/ai-sql-builder/Tool.vue`
  - Adicionar um `watch(status, (newStatus) => { if (newStatus === 'done') { queryHistoryRef.value?.reload(); } })` para garantir que tanto respostas imediatas (200 cache hit) quanto respostas assíncronas via polling (202 -> `queued` -> `processing` -> `done`) atualizem imediatamente a lista de "Consultas Anteriores" (`QueryHistory.vue`) assim que ficarem prontas.

### Etapa 5: Testes Automatizados (Pest e Vitest)
- **Arquivo:** `tests/Feature/Api/V1/SqlQueryApiTest.php`
  - Atualizar os testes para validar que `GET /api/v1/sql-queries` e `GET /api/v1/sql-queries/{ulid}` retornam as consultas da conta autenticada (incluindo as criadas na web e por outros tokens da mesma conta) e continuam bloqueando (404) consultas de outra conta.
  - Validar a presença de `title`, `schema_source`, `dialect`, `schema` e `description` no payload de `GET /api/v1/sql-queries`.
- **Arquivo:** `tests/Feature/Ai/AiActionsTest.php` e `tests/Feature/AccountPanelTest.php`
  - Ajustar expectativas onde o default de `history_enabled` da conta recém-criada era testado como `false` (agora `true` por padrão, mantendo o funcionamento do opt-out quando desligado e o `locked = true` na tela `/dashboard/history` para contas Free).
- **Arquivo:** `resources/js/__tests__/ai-sql-builder.test.ts`
  - Garantir cobertura do recarregamento do histórico ao concluir uma consulta.

### Etapa 6: Documentação (`docs/`) e Relatório Final
- Atualizar `docs/API.md`, `docs/openapi.yaml`, `docs/SPEC.md`, `docs/ARQUITETURA.md` e `docs/glossario.md` para refletir:
  - Escopo por conta (`user_id`) na leitura de `/api/v1/sql-queries` e os novos campos do `ApiSqlQueryResource`.
  - `users.history_enabled` ligado por padrão (`true`, opt-out).
- Executar todos os gates: `composer test`, `npm run test` e `npm run types:check`.
- Gerar relatório detalhado em `docs/gemini/reports/develop_natan/20261025_develop_natan_sql_queries_api_e_historico_builder.md`.
