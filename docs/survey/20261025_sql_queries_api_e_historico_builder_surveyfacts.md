# Survey — Correção da API v1 (`/api/v1/sql-queries`) e Gravação de Consultas no AI SQL Builder (2026-10-25)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** A rota de api (`api/v1/sql-queries`) não esta retornando nenhum dado no campo `"data"` mesmo com querys criadas no AI query builder. Em AI SQL Builder não esta gravando as consultas geradas.
- **Tela / Modulo:**
  - API Pública v1: `GET /api/v1/sql-queries`, `GET /api/v1/sql-queries/{ulid}` (`app/Http/Controllers/Api/V1/SqlQueryController.php`, `app/Http/Resources/ApiSqlQueryResource.php`).
  - AI SQL Builder (Web & Ações): `/{locale}/tools/ai-sql-builder`, `GET /api/ai/history`, `POST /api/ai/queries` (`app/Actions/Ai/StartAiQuery.php`, `app/Actions/Ai/SettleAiQuery.php`, `app/Http/Controllers/Api/AiQueryController.php`, `resources/js/tools/ai-sql-builder/Tool.vue`, `resources/js/tools/ai-sql-builder/QueryHistory.vue`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 2 rodadas, 6 decisões (Q1 a Q6), inspeção autônoma de controllers, actions, models, migrations, Vue components e suítes de testes.
- **Arquivos-chave lidos:**
  - `app/Http/Controllers/Api/V1/SqlQueryController.php`
  - `app/Http/Resources/ApiSqlQueryResource.php`
  - `app/Http/Resources/AiQueryResource.php`
  - `app/Http/Controllers/Api/AiQueryController.php`
  - `app/Http/Controllers/Account/HistoryController.php`
  - `app/Actions/Ai/StartAiQuery.php`
  - `app/Actions/Ai/SettleAiQuery.php`
  - `app/Models/AiRequest.php`
  - `app/Models/User.php`
  - `resources/js/tools/ai-sql-builder/Tool.vue`
  - `resources/js/tools/ai-sql-builder/QueryHistory.vue`
  - `resources/js/tools/ai-sql-builder/useAiQuery.ts`
  - `database/migrations/2026_10_22_000001_add_history_enabled_to_users_table.php`
  - `tests/Feature/Api/V1/SqlQueryApiTest.php`
  - `tests/Feature/Ai/AiApiTest.php`
  - `tests/Feature/Ai/AiActionsTest.php`
  - `tests/Feature/AccountPanelTest.php`

## 2. Decisoes das rodadas

### Rodada 1 — Escopo da API v1, Política de Gravação e Contrato do Recurso
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Escopo de listagem e leitura em `/api/v1/sql-queries` | **Escopo por Conta (`user_id`) em toda a API v1**: `GET /api/v1/sql-queries` e `GET /api/v1/sql-queries/{ulid}` filtram por `user_id` sem restringir por `api_token_id`, retornando todas as consultas da conta (tanto as geradas no AI SQL Builder web quanto via qualquer token da conta). |
| Q2 | Política de gravação de consultas no AI SQL Builder | **`users.history_enabled` passa a ser `true` por padrão (opt-out)** para todas as contas (Free e Pagas), gravando `history_visible = true`, `title`, `prompt` e `schema` em `StartAiQuery` e `SettleAiQuery`, além de recarregar o `QueryHistory.vue` quando o polling termina em `done`. |
| Q3 | Dados retornados em `ApiSqlQueryResource` | **Incluir campos de entrada/identificação** (`title`, `schema_source`, `dialect`, `schema`, `description`) no `ApiSqlQueryResource` além dos campos já existentes (`id`, `status`, `cache_hit`, `result`, `tokens`, `poll_after_ms`, `quota`, `error_code`, `output_target`, `poll_url`, `created_at`). |

### Rodada 2 — Tela `/dashboard/history`, Dedupe e Backfill
| # | Decisao | Resolucao |
|---|---|---|
| Q4 | Tela `/dashboard/history` e Plano Free | **Opção 1**: Manter `/dashboard/history` exclusiva para planos pagos (`locked = true` no Free), enquanto no AI SQL Builder (`QueryHistory.vue` / `GET /api/ai/history`) e na API v1 (`/api/v1/sql-queries`) as consultas gravadas aparecem normalmente para qualquer conta (Free ou Paga). |
| Q5 | Deduplicação em `StartAiQuery` | **Manter `api_token_id` gravado na criação e no dedupe de `StartAiQuery`**, removendo a restrição de `api_token_id` apenas na leitura (`SqlQueryController::index` e `SqlQueryController::show`). |
| Q6 | Backfill das consultas e contas existentes via Migration | **Criar migration retrocompatível** alterando o default de `users.history_enabled` para `true`, atualizando `users.history_enabled = true` e `ai_requests.history_visible = true` para os registros existentes, e preenchendo `title` a partir de `response_json` onde estiver nulo. |

### Defaults assumidos (nao contestados)
- Nenhuma credencial, schema ou prompt entra em logs.
- A tela `/dashboard/history` permanece restrita a planos pagos (`EnsureHistoryHasPaidPlan` e `locked = true` para Free), preservando a regra comercial de gerenciamento centralizado em `/dashboard/history`.
- Consultas de **outra conta** (`user_id` diferente) continuam respondendo `404` em `GET /api/v1/sql-queries/{ulid}` e não aparecem em `GET /api/v1/sql-queries`.

## 3. Relatorio de fatos levantados

### Fato 1: Causa raiz do `"data": []` em `GET /api/v1/sql-queries`
Em `app/Http/Controllers/Api/V1/SqlQueryController.php` (linhas 48-50 e 119-124), as consultas `index` e `show` filtram por:
```php
AiRequest::query()
    ->where('user_id', $user->getKey())
    ->where('api_token_id', $token->getKey())
```
Como as consultas geradas no navegador (`POST /api/ai/queries`) têm `api_token_id = null`, o filtro `->where('api_token_id', $token->getKey())` exclui todas as consultas criadas na ferramenta web AI SQL Builder (e também as criadas por outros tokens da mesma conta).

### Fato 2: Três causas combinadas para o AI SQL Builder não gravar/exibir as consultas geradas
1. **Default `history_enabled = false` em `users`**: A migration `2026_10_22_000001_add_history_enabled_to_users_table.php` definiu `default(false)`. Em `StartAiQuery.php`, `$historyVisible = (bool) $lockedUser->history_enabled;` fazia todas as consultas nascerem com `history_visible = false`, `title = null`, `prompt = null` e `schema = null`, a menos que o usuário tivesse plano pago e ativasse manualmente em `/dashboard/history`.
2. **Reload prematuro em `Tool.vue` durante execução assíncrona (fila/polling)**: Em `resources/js/tools/ai-sql-builder/Tool.vue` (linhas 141-145), `submit(input).then(() => queryHistoryRef.value?.reload())` dispara logo após o retorno `202 Accepted` do `POST`, quando a consulta ainda está `queued` (sem `title` e sem `result`). Quando o `QueryPoller` em `useAiQuery.ts` recebe `status === 'done'`, o `queryHistoryRef.value?.reload()` não era chamado novamente. Selain disso, `AiQueryController::history()` não filtrava apenas consultas concluídas ou retornava a consulta ainda sem `title`/`result`.
3. **`SettleAiQuery::fromCache()` omitindo o `title`**: Em `app/Actions/Ai/SettleAiQuery.php` (linhas 144-152), quando uma solicitação em fila é liquidada via hit de cache (`fromCache`), o `forceFill` não atribuía `'title' => $locked->history_visible ? $hit->result->title : null`.
