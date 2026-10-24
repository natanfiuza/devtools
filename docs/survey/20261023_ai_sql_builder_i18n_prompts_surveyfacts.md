# Survey — Prompts de Sistema por Idioma no AI SQL Builder (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "Em Ai Sql Builder ao enviar uma consulta verifique qual o idioma e prepare um prompt para cada idioma para que os textos "How this query was built", "Assumptions" e "Warnings" venham no idioma selecionado."
- **Tela / Modulo:** Ferramenta AI SQL Builder (`/tools/ai-sql-builder` e `api/ai/queries` / `api/v1/sql-queries`).
  - Frontend: `resources/js/tools/ai-sql-builder/` (`Tool.vue`, `SchemaForm.vue`, `ResultPanel.vue`, `useAiQuery.ts`, `api.ts`, `types.ts`).
  - Backend: `app/Http/Controllers/Api/AiQueryController.php`, `app/Http/Controllers/Api/V1/SqlQueryController.php`, `app/Actions/Ai/StartAiQuery.php`, `app/Jobs/ProcessAiQuery.php`.
  - Prompts e Suporte: `app/Support/Ai/PromptBuilder.php`, `app/Support/Ai/Canonicalizer.php`, `app/Support/Ai/CanonicalPayload.php`, `resources/prompts/`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 2 rodadas de perguntas, 6 decisoes tecnicas com trade-offs explorados, inspecao autonoma do codigo.
- **Arquivos-chave lidos:**
  - `app/Support/Ai/PromptBuilder.php`
  - `app/Support/Ai/Canonicalizer.php`
  - `app/Support/Ai/CanonicalPayload.php`
  - `app/Support/Ai/PayloadCodec.php`
  - `app/Actions/Ai/StartAiQuery.php`
  - `app/Jobs/ProcessAiQuery.php`
  - `app/Support/Ai/ContractValidator.php`
  - `app/Http/Requests/StoreAiQueryRequest.php`
  - `app/Http/Controllers/Api/AiQueryController.php`
  - `app/Http/Controllers/Api/V1/SqlQueryController.php`
  - `app/Models/AiRequest.php`
  - `app/Models/AiCache.php`
  - `app/Support/Locales.php`
  - `config/locales.php`
  - `resources/js/tools/ai-sql-builder/ResultPanel.vue`
  - `resources/js/tools/ai-sql-builder/formatDownload.ts`
  - `lang/*/ai.php`

## 2. Decisoes das rodadas

### Rodada 1 — Origem do idioma, organizacao dos prompts e isolamento de cache
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Origem e validacao do idioma na submissao da consulta | **Opcao A**: O frontend envia explicitamente o `locale` ativo no payload do POST (`StoreAiQueryRequest`). O backend valida o campo contra os idiomas suportados em `config/locales.php`, com fallback para `Locales::preferred($request)`. |
| Q2 | Estrategia de estruturacao dos prompts por idioma | **Opcao A**: Templates de sistema dedicados por idioma (`en`, `pt_BR`, `pt_PT`, `fr`, `es`), com instrucoes formuladas no idioma alvo exigindo que `explanation`, `assumptions` e `warnings` venham no idioma selecionado. |
| Q3 | Isolamento de cache (`prompt_hash`) por idioma | **Opcao A**: O `locale` integra o `CanonicalPayload` e o calculo do `prompt_hash`, garantindo que consultas em idiomas diferentes gerem caches independentes. |
| Q4 | Persistencia do idioma em `ai_requests` | **Opcao A**: Adicionar coluna `locale` (varchar 10, nula) via migration retrocompativel na tabela `ai_requests`. |

### Rodada 2 — Armazenamento fisico e tratamento regional
| # | Decisao | Resolucao |
|---|---|---|
| Q5 | Armazenamento fisico dos prompts de sistema | **Opcao A**: Arquivos Markdown dedicados sob `resources/prompts/system/{locale}.md`, carregados pelo `PromptBuilder` com fallback para `en`. |
| Q6 | Tratamento regional (`pt_BR` vs `pt_PT`) | **Opcao A**: Prompts dedicados para os 5 idiomas suportados pela plataforma (`en`, `pt_BR`, `pt_PT`, `fr`, `es`), respeitando as particularidades lexicais de cada um. |

### Defaults assumidos (nao contestados)
- Retrocompatibilidade total em `CanonicalPayload::fromArray()`: caso uma linha sem `locale` seja lida (como registros de cache antigos ou em voo durante deploy), adota `Locales::fallback()` (`en`) sem lancar excecao.
- Os templates de alvo (`resources/prompts/*.v1.md`, ex: `sql.v1.md`, `eloquent.v1.md`) permanecem focados nas regras tecnicas do formato/ORM e sao combinados com o prompt de sistema do idioma selecionado no `PromptBuilder`.
- As labels de interface em `ResultPanel.vue` ja sao traduzidas via `vue-i18n` (`t('ai.result.explanation')`, `t('ai.result.assumptions')`, `t('ai.result.warnings')`), portanto o foco do prompt de sistema e garantir que o *conteudo gerado* pelo LLM venha redigido no idioma correspondente.

## 3. Relatorio de fatos levantados

### Isolamento de Cache e Identidade Canônica
- No estado atual, o `CanonicalPayload` continha: `schema_source`, `dialect`, `target`, `schema`, `description`, `model`, `prompt_version`. O hash MD5 do JSON gerado define o `prompt_hash`.
- Se duas consultas identicas fossem feitas em idiomas distintos sem o `locale` no payload canonico, ocorreria colapso de cache: o segundo usuario receberia a explicacao gerada no idioma do primeiro. A inclusao de `locale` resolve essa precondicao.

### Contrato de Resposta e Estrutura JSON
- O contrato retornado pela IA (`ContractValidator.php`) valida os campos: `title`, `explanation`, `sql`, `dialect`, `assumptions`, `warnings`, `tables_used`, `needs_clarification`, `clarification`, `outputs`.
- As instrucoes de sistema em `resources/prompts/system/{locale}.md` devem manter o formato estrito JSON e as mesmas chaves de contrato (em ingles), mas exigir imperativamente que os valores de `title`, `explanation`, `assumptions`, `warnings` e `clarification` sejam redigidos no idioma selecionado.

### API Publica (v1) e Web API
- Ambas as rotas (`/api/ai/queries` na web e `/api/v1/sql-queries` na API v1) utilizam `StoreAiQueryRequest` e chamam `StartAiQuery`.
- Ao tornar o campo `locale` opcional com validacao `Rule::in(Locales::codes())` e fallback para `Locales::preferred($request)`, clientes da API v1 continuam funcionando normalmente mesmo sem enviar o campo.
