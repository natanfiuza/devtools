# Plano de Implementacao — Prompts de Sistema por Idioma no AI SQL Builder

- **Data:** 2026-10-23
- **Branch:** `develop_natan`
- **Identificador:** `ai_sql_builder_i18n_prompts`
- **Survey de referencia:** `docs/survey/20261023_ai_sql_builder_i18n_prompts_surveyfacts.md`

---

## 1. Contexto e Objetivo

No AI SQL Builder, ao enviar uma consulta, o idioma selecionado pelo usuario na interface deve determinar o idioma em que os campos textuais retornados pela IA — em particular `explanation` ("How this query was built" / "Como a consulta foi construída"), `assumptions` ("Assumptions" / "Premissas") e `warnings` ("Warnings" / "Avisos") — sao redigidos.

Para alcancar esse comportamento com determinismo, alta fidelidade do LLM e total integridade de cache:
1. O frontend envia explicitamente o `locale` ativo na requisicao.
2. O backend valida o `locale` e carrega um prompt de sistema dedicado para o idioma (`resources/prompts/system/{locale}.md`).
3. O `CanonicalPayload` e o `prompt_hash` passam a incorporar o `locale`, garantindo isolamento estrito de cache entre idiomas diferentes.
4. Uma migration retrocompativel adiciona a coluna `locale` na tabela `ai_requests`.
5. Um novo ADR (ADR-056) e o glossario formalizam a decisao.

---

## 2. Mudancas Arquiteturais e Componentes

### 2.1. Banco de Dados e Migrations
- **Arquivo:** `database/migrations/YYYY_MM_DD_HHMMSS_add_locale_to_ai_requests_table.php`
- Adiciona coluna `locale` (`string('locale', 10)->nullable()->after('status')`).
- Retrocompativel (sem locks bloqueantes, aceita nulo).
- Atualizacao do Model `app/Models/AiRequest.php`: adicionar `locale` ao `#[Fillable]` e as docblocks `@property`.

### 2.2. Prompts de Sistema por Idioma
- Criacao do diretorio `resources/prompts/system/`:
  - `en.md`: Prompt de sistema em ingles com regras duras e exigencia de `title`, `explanation`, `assumptions`, `warnings` e `clarification` em ingles.
  - `pt_BR.md`: Prompt de sistema em portugues do Brasil com regras e diretrizes expressas em pt-BR.
  - `pt_PT.md`: Prompt de sistema em portugues de Portugal com regras e diretrizes em pt-PT.
  - `es.md`: Prompt de sistema em espanhol com regras e diretrizes em espanhol.
  - `fr.md`: Prompt de sistema em frances com regras e diretrizes em frances.
- O formato do JSON (`JSON SHAPE`) mantem as chaves em ingles para preservar 100% da compatibilidade do contrato com `ContractValidator.php`.

### 2.3. Montagem de Prompt (`PromptBuilder`)
- **Arquivo:** `app/Support/Ai/PromptBuilder.php`
- Injeta o template de sistema correspondente ao `$payload->locale` (com fallback seguro para `en.md`).
- Combina o system prompt com as instrucoes tecnicas do alvo (`$prompt->text`, ex: `sql.v1.md`, `eloquent.v1.md`).

### 2.4. Identidade Canônica e Isolamento de Cache (`Canonicalizer`, `CanonicalPayload`, `PayloadCodec`)
- **Arquivo:** `app/Support/Ai/CanonicalPayload.php`
  - Adiciona campo `public string $locale` no construtor.
  - Inclui `'locale' => $this->locale` em `toArray()` (ordenado por `ksort`).
  - Em `fromArray()`, decodifica `$locale = $data['locale'] ?? Locales::fallback()`, garantindo que payloads antigos ou em voo durante o deploy sejam processados sem falhas.
- **Arquivo:** `app/Support/Ai/Canonicalizer.php`
  - Recebe `string $locale` e valida se e um dos suportados em `Locales::isSupported()`.
- O hash MD5 resultante isola o cache (`AiCache` e deduplicacao in-flight) automaticamente por idioma.

### 2.5. Validacao e Acoes HTTP (`StoreAiQueryRequest`, Controllers e `StartAiQuery`)
- **Arquivo:** `app/Http/Requests/StoreAiQueryRequest.php`
  - Adiciona regra `'locale' => ['sometimes', 'nullable', 'string', Rule::in(Locales::codes())]`.
- **Arquivo:** `app/Http/Controllers/Api/AiQueryController.php` (Web API)
  - Resolve `$locale = $data['locale'] ?? Locales::preferred($request)`.
  - Passa `$locale` para `StartAiQuery`.
- **Arquivo:** `app/Http/Controllers/Api/V1/SqlQueryController.php` (API Publica v1)
  - Resolve `$locale = $data['locale'] ?? Locales::preferred($request)`.
  - Passa `$locale` para `StartAiQuery`.
- **Arquivo:** `app/Actions/Ai/StartAiQuery.php`
  - Recebe `string $locale`.
  - Passa `$locale` para `$this->canonicalizer->canonicalize(...)`.
  - Grava `'locale' => $locale` ao criar o `AiRequest`.

### 2.6. Frontend (`resources/js/tools/ai-sql-builder/`)
- **Arquivo:** `resources/js/tools/ai-sql-builder/types.ts`
  - Atualiza interface `AiQueryInput` para incluir `locale?: string`.
- **Arquivo:** `resources/js/tools/ai-sql-builder/SchemaForm.vue`
  - Obtem o locale ativo de `useI18n()` (ou recebe por prop).
  - Emite `locale` no evento `@submit`.
- **Arquivo:** `resources/js/tools/ai-sql-builder/Tool.vue`
  - Repassa o input contendo `locale` para `submit(input)`.

### 2.7. Documentacao e ADR
- **Arquivo:** `docs/adr/ADR-056-i18n-prompts-e-isolamento-de-cache-ai-sql-builder.md`
  - Registra a motivacao, opcoes avaliadas, inclusao de `locale` no `prompt_hash` e estrategia de templates de sistema.
- **Arquivo:** `docs/glossario.md`
  - Atualiza o termo `Prompt hash` para constar explicitamente `idioma (locale)`.

---

## 3. Plano de Testes e Validacao

### 3.1. Testes Backend (Pest)
- Testes unitarios de `CanonicalPayload` e `Canonicalizer`:
  - Verificar que dois payloads com mesmo schema e descricao, mas `locale` diferente (`pt_BR` vs `en`), geram hashes distintos.
  - Verificar que `fromArray()` trata retrocompatibilidade quando `locale` esta ausente.
- Testes unitarios de `PromptBuilder`:
  - Verificar que o prompt gerado para `pt_BR` contem instrucoes em portugues exigindo `Como a consulta foi construída`.
  - Verificar que para `en` contem `How this query was built`.
  - Testar fallback para `en` caso um locale desconhecido seja fornecido.
- Testes de integracao de `StoreAiQueryRequest` e `AiQueryController`:
  - Enviar POST com `locale: 'pt_BR'` e validar persistencia do locale em `ai_requests`.
  - Enviar POST sem `locale` e validar que o fallback `Locales::preferred()` e acionado.
- Testes do job `ProcessAiQuery`:
  - Executar o job com payload em `pt_BR` e validar que `PromptBuilder` recebe o locale correto.

### 3.2. Testes Frontend (Vitest)
- Testes de `SchemaForm.spec.ts`:
  - Verificar que o evento `submit` inclui o `locale` corrente.
- Testes de `api.spec.ts` / `useAiQuery.spec.ts`:
  - Garantir que `postQuery` transmite o campo `locale` no corpo JSON.

### 3.3. Quality Gates
1. `composer test` (Pint, PHPStan, Pest).
2. `npm run test` (Vitest).
3. `npm run types:check` (vue-tsc e rotas).

---

## 4. Ordem Sequencial de Execucao

1. **Passo 1:** Criar a migration `add_locale_to_ai_requests_table` e atualizar `AiRequest.php`.
2. **Passo 2:** Atualizar `CanonicalPayload`, `Canonicalizer` e seus testes unitarios.
3. **Passo 3:** Criar os templates de prompt de sistema em `resources/prompts/system/{en,pt_BR,pt_PT,fr,es}.md`.
4. **Passo 4:** Atualizar `PromptBuilder` para carregar o template de sistema correspondente e cobrir com testes.
5. **Passo 5:** Atualizar `StoreAiQueryRequest`, `StartAiQuery`, `AiQueryController` e `SqlQueryController`.
6. **Passo 6:** Atualizar os tipos e componentes do frontend (`types.ts`, `SchemaForm.vue`, `Tool.vue`).
7. **Passo 7:** Criar `ADR-056` e atualizar `docs/glossario.md`.
8. **Passo 8:** Executar suite completa de validacao (`composer test`, `npm run test`, `npm run types:check`).
