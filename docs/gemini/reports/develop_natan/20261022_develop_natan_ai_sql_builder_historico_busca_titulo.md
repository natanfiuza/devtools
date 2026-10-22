# Relatório — AI SQL Builder: Histórico de Consultas com Busca e Título por IA

**Data**: 2026-10-22  
**Branch**: `develop_natan`  
**Tarefa**: `ai_sql_builder_historico_busca_titulo`

---

## 1. Resumo da Tarefa
Implementação de histórico integrado na ferramenta **AI SQL Builder** com suporte a pesquisa textual no conteúdo do schema e do prompt/descrição, e solicitação à IA para geração de título contextualizado para a consulta (ex.: *"Busca por Pedidos de Clientes no formato TypeORM"*). Além disso, foi verificada e garantida a persistência e restauração de premissas (`assumptions`), avisos (`warnings`) e tabelas usadas (`tables_used`).

---

## 2. Alterações Realizadas

### Contrato da IA e Validações
- [`app/Support/Ai/PromptBuilder.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Support/Ai/PromptBuilder.php): Adicionado `"title": "string"` ao JSON SHAPE do system prompt com instrução explícita de gerar um título curto e informativo no formato exemplificado (*"Busca por <Objetivo do Schema/Regra> no formato <Target/ORM>"*) e no mesmo idioma do usuário.
- [`app/Support/Ai/ContractValidator.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Support/Ai/ContractValidator.php): Validação do campo `title` (opcional, máximo 255 caracteres, sanitizado).
- [`app/Support/Ai/AiResult.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Support/Ai/AiResult.php): Propriedade `public ?string $title` adicionada ao construtor, `toArray()` e tipos.

### Persistência e Banco de Dados
- Migration [`database/migrations/2026_10_22_000005_add_title_prompt_schema_to_ai_requests_table.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/database/migrations/2026_10_22_000005_add_title_prompt_schema_to_ai_requests_table.php): Adição das colunas `title` (varchar 255), `prompt` (text) e `schema` (longtext) à tabela `ai_requests`.
- [`app/Models/AiRequest.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Models/AiRequest.php): Adicionados campos ao `$fillable` e docblocks.
- [`app/Actions/Ai/StartAiQuery.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Actions/Ai/StartAiQuery.php): Persistência de `schema` e `prompt` na criação da solicitação quando `history_visible = true`.
- [`app/Actions/Ai/SettleAiQuery.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Actions/Ai/SettleAiQuery.php): Gravação de `title` na liquidação da consulta quando `history_visible = true`.
- [`app/Http/Controllers/Account/HistoryController.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Http/Controllers/Account/HistoryController.php): Histórico geral do dashboard passa a exibir prioritariamente o `title` gerado pela IA.

### Endpoint de Histórico no AI SQL Builder
- [`routes/web.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/routes/web.php) e [`app/Support/Ai/AiToolProps.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Support/Ai/AiToolProps.php): Registrada a rota `GET /api/ai/history` e exposta em `endpoints.history`.
- [`app/Http/Controllers/Api/AiQueryController.php`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Http/Controllers/Api/AiQueryController.php): Método `history(Request $request)` que busca as últimas consultas do usuário logado (`history_visible = true`), filtrando opcionalmente por `q` (termo de busca em `title`, `prompt` ou `schema`).

### Frontend e Interatividade
- [`resources/js/tools/ai-sql-builder/QueryHistory.vue`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/ai-sql-builder/QueryHistory.vue): Novo componente com listagem, busca com debounce, badges e estados vazios/erro.
- [`resources/js/tools/ai-sql-builder/Tool.vue`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/ai-sql-builder/Tool.vue): Incorpora o `QueryHistory` e gerencia a restauração completa da consulta selecionada (preenchimento do schema, descrição, dialeto e alvo no formulário, bem como restauração do resultado gerado no painel).
- [`resources/js/tools/ai-sql-builder/SchemaForm.vue`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/ai-sql-builder/SchemaForm.vue): Método exposto `populate()` para carregar os dados históricos nos campos do formulário.
- [`resources/js/tools/ai-sql-builder/ResultPanel.vue`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/ai-sql-builder/ResultPanel.vue): Exibição do título gerado pela IA no topo do painel de resultados.
- [`resources/js/tools/ai-sql-builder/useAiQuery.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/ai-sql-builder/useAiQuery.ts): Método `restore()` para carregar o resultado histórico no estado da tool.

### Internacionalização (i18n)
- Traduções adicionadas nos 5 idiomas em `lang/{en,es,fr,pt_BR,pt_PT}/ai.php` para todas as novas chaves (`ai.history.*`).

---

## 3. Verificação e Testes
- **Testes Backend (`composer test`)**: 655 testes passando (100%), PHPStan sem erros (nível estrito) e Pint sem desvios de estilo de código.
- **Testes Frontend (`npm run test`)**: 57 arquivos de teste, 771 testes passando (100% Vitest).
- **Tipagem TypeScript (`npm run types:check`)**: Wayfinder gerado e `vue-tsc --noEmit` executado com zero erros de tipo.
