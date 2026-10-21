# Plano de Implementação — Listagem de Histórico no Painel do Usuário

- **Data**: 2026-10-20
- **Branch**: `develop_natan`
- **Arquivo**: `docs/gemini/plans/develop_natan/20261020_develop_natan_historico_painel_usuario.md`

## 1. Visão Geral
Atualmente, a tela `/dashboard/history` exibe apenas uma mensagem estática de estado vazio criada na fase inicial do projeto. Como a ferramenta **AI SQL Builder** já está em produção gerando consultas (`ai_requests`) e consumos no livro-razão (`usage_ledger`), este plano implementa a busca e listagem paginada do histórico de consultas com detalhes de consumo, dialeto, status e modal para visualização do resultado gerado.

## 2. Modificações Planejadas

### 2.1 Backend
1. **`app/Http/Controllers/Account/HistoryController.php`**:
   - Consultar `AiRequest` pertencentes ao usuário logado (`$request->user()->aiRequests()`), ordenados decrescente por `created_at`.
   - Carregar relacionamento com `outputTarget` (ou dados do dialeto/alvo).
   - Paginar (ex: 20 registros por página).
   - Mapear os campos no formato seguro para Inertia:
     - `id`: ULID
     - `status`: string de status (`queued`, `processing`, `done`, `failed`)
     - `dialect`: nome/label do dialeto SQL
     - `target`: nome do alvo ou dialeto
     - `quotaUnit`: `tokens` ou `uses`
     - `tokensUsed`: `tokens_in + tokens_out` ou `reserved_tokens`
     - `cacheHit`: booleano indicando se veio de cache
     - `createdAt`: data formatada via `Format::datetime` ou `Format::date`
     - `result`: resumo/sql gerado extraído de `response_json` (quando status for `done`)
     - `errorCode`: código de erro amigável se falhou
   - Enviar prop `history` (paginator) e manter compatibilidade com estado vazio caso não haja itens.

2. **`app/Actions/Ai/StartAiQuery.php`**:
   - Definir `'history_visible' => true` na criação de requisições do usuário para assegurar visibilidade futura.

### 2.2 Traduções (i18n) em 5 Idiomas
- Atualizar `lang/{pt_BR,pt_PT,en,es,fr}/account.php` na seção `history`:
  - Cabeçalhos de tabela: Data/Hora, Ferramenta / Alvo, Dialeto, Consumo / Cota, Status, Ações.
  - Textos de status: Sucesso, Falha, Em processamento, Na fila.
  - Textos do modal: Detalhes da Consulta, SQL Gerado, Explicação, Fechar, Copiar SQL.
  - Paginação: Página X de Y, Total de registros, Anterior, Próxima.

### 2.3 Tipagens TypeScript
- Atualizar `resources/js/types/index.d.ts`:
  - Adicionar interfaces `AccountHistoryItem` e `AccountHistoryProps`.

### 2.4 Frontend (`resources/js/pages/dashboard/History.vue`)
- Receber `props: AccountHistoryProps` com `history: Paginator<AccountHistoryItem>`.
- Exibir a tabela com o histórico de consultas quando houver registros (`history.data.length > 0`).
- Exibir o card de estado vazio caso não haja registros.
- Exibir paginação idêntica à de `Billing.vue`.
- Adicionar modal com `Dialog` para exibir os detalhes da consulta (SQL gerado com destaque/cópia, status e consumo).

### 2.5 Testes Automatizados
- **Backend (Pest)**:
  - Adicionar testes em `tests/Feature/AccountPanelTest.php` cobrindo a listagem paginada, isolamento entre usuários e envio dos dados da consulta.
- **Frontend (Vitest)**:
  - Atualizar `resources/js/__tests__/account-panel.test.ts` testando a renderização da tabela de histórico, paginação e exibição do modal de detalhes.

## 3. Verificações Finais
- Executar `composer test`.
- Executar `npm run test`.
- Executar `npm run types:check`.
- Gerar relatório de encerramento em `docs/gemini/reports/develop_natan/20261020_develop_natan_historico_painel_usuario.md`.
