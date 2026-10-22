# Plano de Implementação — Histórico de Consultas com Busca e Título por IA no AI SQL Builder

**Data**: 2026-10-22  
**Branch**: `develop_natan`  
**Referência**: [docs/survey/20261022_ai_sql_builder_historico_busca_titulo_surveyfacts.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/survey/20261022_ai_sql_builder_historico_busca_titulo_surveyfacts.md)

---

## 1. Visão Geral e Objetivos
1. **Contrato da IA com Título**:
   - Ajustar o prompt em `PromptBuilder` para solicitar um título sintetizando o objetivo do schema/regra e o formato de saída (ex: *"Busca por Pedidos de Clientes no formato TypeORM"*).
   - Validar o campo `"title"` em `ContractValidator` e incluí-lo em `AiResult`.
2. **Persistência de Dados**:
   - Migration adicionando colunas `title` (nullable string 255), `prompt` (nullable text) e `schema` (nullable longText) à tabela `ai_requests`.
   - Preencher `prompt` e `schema` em `StartAiQuery` quando `history_visible = true`.
   - Preencher `title` em `SettleAiQuery` a partir do `AiResult`.
3. **Endpoint de Histórico com Busca**:
   - Adicionar rota `GET /api/ai/history` (ou `queries/history`) com suporte a parâmetro de busca `?q=...`.
   - A query filtra por `user_id`, `history_visible = true` e busca textual em `title`, `prompt` ou `schema`.
   - Retorna as últimas consultas com todos os dados necessários para restauração completa.
4. **Interface no AI SQL Builder (`resources/js/tools/ai-sql-builder/`)**:
   - Novo componente `QueryHistory.vue` exibindo as últimas consultas realizadas e campo de busca interativo com debounce.
   - Ao selecionar uma consulta anterior, restaurar os campos no `SchemaForm` e o resultado completo em `ResultPanel` (código gerado, premissas, avisos e tabelas usadas).
   - Atualizar `dashboard/History` para utilizar o `title` real gerado pela IA.
5. **i18n & Testes**:
   - Adicionar todas as chaves nos 5 idiomas (`en`, `pt-BR`, `pt-PT`, `fr`, `es`).
   - Testes unitários e de integração no backend (Pest) e frontend (Vitest).

---

## 2. Etapas Detalhadas de Execução

### Etapa 1: Backend - Contrato e Suporte a Título na IA
- Modificar `app/Support/Ai/PromptBuilder.php`:
  - Instruir a geração do campo `"title"` com as diretrizes e exemplos de nomenclatura solicitados.
  - Atualizar a especificação do JSON SHAPE com `"title": "string"`.
- Modificar `app/Support/Ai/ContractValidator.php`:
  - Validar a presença e tipo string de `title`, garantindo limite de caracteres.
- Modificar `app/Support/Ai/AiResult.php`:
  - Adicionar propriedade `title` ao construtor, `toArray()` e tipos.

### Etapa 2: Banco de Dados e Persistência
- Criar migration `database/migrations/2026_10_22_000004_add_title_prompt_schema_to_ai_requests_table.php`.
- Atualizar `app/Models/AiRequest.php`:
  - Adicionar `$fillable` e tipos para `title`, `prompt`, `schema`.
- Atualizar `app/Actions/Ai/StartAiQuery.php`:
  - Ao persistir a nova solicitação, se `history_visible` for verdadeiro, gravar `$schema` e `$description` (prompt).
- Atualizar `app/Actions/Ai/SettleAiQuery.php`:
  - Gravar `$result->title` na coluna `title` de `ai_requests`.
- Atualizar `app/Http/Controllers/Account/HistoryController.php`:
  - Priorizar o `title` persistido na exibição da lista.

### Etapa 3: Endpoint de Histórico no AI SQL Builder
- Criar método `index` em `app/Http/Controllers/Api/AiQueryController.php` (ou rota dedicada):
  - Autenticado, filtrando por `$user->id` e `history_visible = true`.
  - Filtro opcional `q`: `where(fn ($query) => $query->where('title', 'like', "%{$q}%")->orWhere('prompt', 'like', "%{$q}%")->orWhere('schema', 'like', "%{$q}%"))`.
  - Retornar coleção formatada com `id`, `title`, `dialect`, `outputTarget`, `schemaSource`, `schema`, `prompt`, `result` (com sql, premissas, avisos, tabelas usadas), `createdAt`.

### Etapa 4: Frontend do AI SQL Builder
- Criar `resources/js/tools/ai-sql-builder/QueryHistory.vue`:
  - Input de busca com ícone de lupa.
  - Lista de cards/itens com data relativa, badge de dialeto/alvo e o título gerado pela IA.
  - Estado vazio e estado de busca sem resultados.
  - Evento de seleção emitindo a consulta selecionada.
- Atualizar `resources/js/tools/ai-sql-builder/Tool.vue`:
  - Incorporar `QueryHistory` abaixo ou ao lado do construtor para usuários autenticados.
  - Ao selecionar um item histórico:
    - Preenche o formulário `SchemaForm` com os valores da consulta (`schema`, `description`, `dialect`, `output_target`, `schema_source`).
    - Define `status = 'done'` e entrega o `result` correspondente ao `ResultPanel`.
- Atualizar `resources/js/tools/ai-sql-builder/types.ts` e composables conforme necessário.

### Etapa 5: Traduções (i18n)
- Atualizar `lang/{en,pt-BR,pt-PT,fr,es}/ai.php`:
  - Chaves para título da seção de histórico, placeholder de busca, estados vazios, botões de restauração, etc.

### Etapa 6: Testes e Validação
- Rodar `composer test` (Pest, PHPStan, Pint).
- Rodar `npm run test` (Vitest).
- Rodar `npm run types:check` (Wayfinder + vue-tsc).
