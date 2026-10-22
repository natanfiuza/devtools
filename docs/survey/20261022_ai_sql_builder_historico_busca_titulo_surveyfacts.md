# Survey — AI SQL Builder: Histórico de Consultas com Busca e Título por IA (2026-10-22)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Em AI SQL Builder deve ter um historico das ultimas consultas realizadas, onde o usuario pode fazer uma busca por consultas anteriores, nesta lista a busca deve ser feita no conteudo do schema e no prompt do que a consulta deve fazer. E ao fazer uma consulta peca para a IA gerar um titulo para a consulta exemplo se o schema for de pedido e clientes por exemplos com a saida TypeORM o titulo da consulta gerado deve ser algo como "Busca por Pedidos de Clientes no formato TypeORM". Verifique se Premissas Avisos e Tabelas usadas estao sendo salvos com o registro da consulta.
- **Tela / Modulo:** `resources/js/tools/ai-sql-builder/`, `app/Http/Controllers/Api/AiQueryController.php`, `app/Support/Ai/`, `app/Models/AiRequest.php`, `resources/js/pages/dashboard/History.vue`
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 2 rodadas completas, 6 decisoes de design fechadas.
- **Arquivos-chave lidos:**
  - `app/Models/AiRequest.php`
  - `app/Jobs/ProcessAiQuery.php`
  - `app/Support/Ai/ContractValidator.php`
  - `app/Support/Ai/PromptBuilder.php`
  - `app/Support/Ai/AiResult.php`
  - `app/Actions/Ai/SettleAiQuery.php`
  - `app/Actions/Ai/StartAiQuery.php`
  - `app/Http/Controllers/Api/AiQueryController.php`
  - `app/Http/Controllers/Account/HistoryController.php`
  - `resources/js/tools/ai-sql-builder/Tool.vue`
  - `resources/js/tools/ai-sql-builder/SchemaForm.vue`
  - `resources/js/tools/ai-sql-builder/ResultPanel.vue`
  - `resources/js/tools/ai-sql-builder/types.ts`

## 2. Decisoes das rodadas

### Rodada 1 — Arquitetura de Armazenamento e Contrato IA
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Localizacao do historico | **Opcao A**: Integrado diretamente no AI SQL Builder para usuarios autenticados (com busca e selecao de itens), complementando o historico geral em `dashboard/History`. |
| Q2 | Persistencia de Schema e Prompt | **Opcao A**: Armazenar `schema` e `prompt` (`description`) no registro de `ai_requests` quando `history_visible = true`, permitindo a busca por conteudo. |
| Q3 | Geracao e Armazenamento do Titulo da Consulta pela IA | **Opcao A**: Incluir `"title": "string"` no contrato JSON retornado pela IA (`PromptBuilder`, `ContractValidator`, `AiResult`) com orientacao precisa de formato e persistir a coluna `title` na tabela `ai_requests`. |

### Rodada 2 — Interatividade e Experiencia de Busca
| # | Decisao | Resolucao |
|---|---|---|
| Q4 | Acao ao selecionar item do historico | **Opcao A**: Restaurar a consulta completa no AI SQL Builder: preenche o formulario (schema, descricao, dialeto, alvo de saida) e o painel de resultados (codigo, premissas, avisos e tabelas). |
| Q5 | Mecanismo de busca textual | **Opcao A**: Endpoint de historico com parametro de busca `q`, filtrando no backend por `schema`, `prompt` (descricao) ou `title`, com debounce no frontend. |
| Q6 | Disposicao visual do historico | **Opcao A**: Secao estruturada e colapsavel no AI SQL Builder ("Consultas Anteriores") abaixo da grade de trabalho, contendo campo de busca, badges e listagem com data relativa. |

### Defaults assumidos (nao contestados)
- Visitantes (nao autenticados) recebem aviso indicando que o historico de consultas e exclusivo para contas conectadas.
- Premissas (`assumptions`), avisos (`warnings`) e tabelas usadas (`tables_used`) ja estao comprovadamente presentes em `response_json` do modelo e serao mantidos e exibidos ao restaurar a consulta anterior.
- Convencao de i18n estrita mantida para todos os novos textos em 5 idiomas (`en`, `pt-BR`, `pt-PT`, `fr`, `es`).

## 3. Relatorio de fatos levantados

### Fato 1: Persistencia atual e colunas necessarias
Atualmente, `ai_requests.canonical_payload` era anulado em `SettleAiQuery` e `FailAiQuery` para evitar retencao excessiva de dados em requisicoes sem historico. Para permitir a busca textual por schema e prompt, adicionaremos as colunas `title` (string 255), `prompt` (text) e `schema` (longText) na tabela `ai_requests`, preenchidas na criacao / conclusao da consulta quando `history_visible = true`.

### Fato 2: Contrato da IA (`PromptBuilder` e `ContractValidator`)
O `PromptBuilder` define as regras duras e o formato JSON esperado pelo LLM. Adicionaremos o campo `"title"` instruindo a IA a gerar um titulo conciso e representativo seguindo o formato: `"Busca por <Objetivo do Schema/Regra> no formato <Target/ORM>"`.
O `ContractValidator` validara a presenca e tamanho maximo de `title` (ex: ate 255 caracteres).
O `AiResult` incluira `public ?string $title`.

### Fato 3: Restauracao no Frontend
O componente `Tool.vue` do `ai-sql-builder` sincronizara os estados do `SchemaForm.vue` (passando novos valores ou emitindo evento `restore`) e do `ResultPanel.vue` (passando o resultado restaurado e status `'done'`).
