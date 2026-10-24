# Relatorio de Tarefa — Prompts de Sistema por Idioma no AI SQL Builder

- **Data:** 2026-10-23
- **Branch:** `develop_natan`
- **Tarefa:** `ai_sql_builder_i18n_prompts`
- **Status:** Concluido com sucesso
- **ADR criado:** [ADR-056: Internacionalizacao de Prompts e Isolamento de Cache no AI SQL Builder](../../../adr/ADR-056-i18n-prompts-e-isolamento-de-cache-ai-sql-builder.md)
- **Plano de execucao:** [20261023_develop_natan_ai_sql_builder_i18n_prompts.md](../plans/develop_natan/20261023_develop_natan_ai_sql_builder_i18n_prompts.md)
- **Survey facts:** [20261023_ai_sql_builder_i18n_prompts_surveyfacts.md](../../../survey/20261023_ai_sql_builder_i18n_prompts_surveyfacts.md)

---

## 1. Resumo da Tarefa

Implementacao do suporte e envio de idioma no AI SQL Builder, de modo que consultas enviadas pelo usuario considerem o idioma selecionado (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) e instruam o modelo de IA a redigir os campos `explanation` ("Como a consulta foi construída" / "How this query was built"), `assumptions` ("Premissas" / "Assumptions") e `warnings` ("Avisos" / "Warnings") no idioma correto, mantendo o isolamento do cache (`AiCache`) e a compatibilidade total com o validador de contrato JSON.

---

## 2. Modificacoes Realizadas

### 2.1. Banco de Dados e Model
- **Migration:** `database/migrations/2026_10_23_000005_add_locale_to_ai_requests_table.php`
  - Adiciona coluna `locale` (`string(10)->nullable()`) apos `status` com indice.
- **Model `App\Models\AiRequest`:**
  - Adicionado `locale` na PHPDoc `@property string|null $locale` e na lista `#[Fillable]`.
- **Factory `Database\Factories\AiRequestFactory`:**
  - Definido default `'locale' => 'en'`.

### 2.2. Prompts de Sistema Dedicados por Idioma
- Criado o diretorio `resources/prompts/system/` com os templates de sistema correspondentes aos 5 idiomas suportados pela plataforma:
  - `en.md` (Ingles)
  - `pt_BR.md` (Portugues do Brasil)
  - `pt_PT.md` (Portugues de Portugal)
  - `es.md` (Espanhol)
  - `fr.md` (Frances)
- Cada template formula as regras estritas e instrucoes didaticas na lingua-alvo, preservando as chaves JSON em ingles do contrato do sistema (`sql`, `dialect`, `assumptions`, `warnings`, `outputs`, etc.).

### 2.3. Montagem do Prompt (`PromptBuilder`)
- Atualizado `app/Support/Ai/PromptBuilder.php`:
  - O metodo `system()` carrega o template de sistema correspondente ao `$payload->locale` (com fallback seguro para `en.md` e fallback em memoria).
  - Substitui os marcadores `{target_instructions}`, `{target_key}` e `{target_slug}` dinamicamente.

### 2.4. Identidade Canônica e Isolamento de Cache
- **`App\Support\Ai\CanonicalPayload`:**
  - Adicionado o campo `public string $locale = 'en'`.
  - Serializado `'locale' => $this->locale` em `toArray()`, ordenado via `ksort`.
  - Decodificacao em `fromArray()` com fallback para `'en'` caso ausente, assegurando retrocompatibilidade total durante o deploy.
  - Como o `prompt_hash` e o MD5 do payload canonico, consultas em idiomas diferentes geram chaves distintas no `AiCache` e no dedupe in-flight.
- **`App\Support\Ai\Canonicalizer`:**
  - Recebe `$locale = 'en'`, valida e normaliza tanto codigos (`pt_BR`) quanto slugs (`pt-br`).

### 2.5. Fluxo HTTP e API
- **`App\Http\Requests\StoreAiQueryRequest`:**
  - Adicionada regra de validacao para `'locale'`: opcional, aceitando codigos e slugs suportados.
- **`App\Http\Controllers\Api\AiQueryController` (Web API):**
  - Normaliza o locale recebido ou recorre a `Locales::preferred($request)`.
  - Passa `$locale` para a acao `StartAiQuery`.
- **`App\Http\Controllers\Api\V1\SqlQueryController` (API Publica v1):**
  - Normaliza o locale recebido ou recorre a `Locales::preferred($request)`.
  - Passa `$locale` para `StartAiQuery`.
- **`App\Actions\Ai\StartAiQuery`:**
  - Recebe e resolve o `$locale`.
  - Passa `$locale` ao canonicalizar e grava `'locale' => $payload->locale` no registro `AiRequest`.

### 2.6. Frontend
- **`resources/js/tools/ai-sql-builder/types.ts`:**
  - Adicionado `locale?: string` na interface `AiQueryInput`.
- **`resources/js/tools/ai-sql-builder/SchemaForm.vue`:**
  - Emite `locale: locale.value` no evento `@submit`.
- **`resources/js/tools/ai-sql-builder/Tool.vue`:**
  - Submete o payload enriquecido com o locale para a rota da API.

### 2.7. Documentacao e ADR
- Criado `docs/adr/ADR-056-i18n-prompts-e-isolamento-de-cache-ai-sql-builder.md`.
- Atualizado `docs/glossario.md` no termo `Prompt hash`.

---

## 3. Validacao e Quality Gates

- **Vitest (Frontend):**
  - `npm run test`: **60 passed** (60 arquivos de teste, 794 testes aprovados, 0 falhas).
- **TypeScript:**
  - `npm run types:check`: Wayfinder routes geradas com sucesso e `vue-tsc --noEmit` aprovado sem erros.
- **Pint & PHPStan & Pest (Backend):**
  - `composer test`:
    - `pint`: passed (padrao de codigo).
    - `phpstan`: passed (0 erros).
    - `pest`: 684 passed (684 testes, 14.648 assercoes, 0 falhas).
