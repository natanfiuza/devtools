# Plano — AI SQL Builder: Descricao Didatica, Dicas de Saida e Scroll Vertical

- **Data:** 2026-10-22
- **Branch:** `develop_natan`
- **Arquivo:** `docs/gemini/plans/develop_natan/20261022_develop_natan_ai_sql_builder_descricao_dicas_scroll.md`

## 1. Objetivo
Aprimorar o retorno visual e a exportacao de consultas no AI SQL Builder:
1. Incluir uma breve descricao didatica e resumida do que foi feito na consulta (`explanation`), gerada contextualmente pela IA.
2. Adicionar dicas uteis de boas praticas de framework/tecnologia para o tipo de saida ativo (`tips`), traduzidas nos 5 idiomas suportados (`en`, `pt-BR`, `pt-PT`, `fr`, `es`).
3. Reorganizar a secao de metadados (Descricao, Dica, Premissas, Avisos, Tabelas) em fluxo vertical com barra de rolagem (`overflow-y-auto max-h-64`).
4. Incluir esses novos textos como comentarios no arquivo gerado no download do resultado.

---

## 2. Etapas de Implementacao

### Etapa 1: Backend - Contrato e Prompt da IA
- **`app/Support/Ai/PromptBuilder.php`**:
  - Atualizar o system prompt para incluir o campo `"explanation": "string"` no JSON retornado pela IA.
  - Adicionar orientacao: gerar uma breve explicacao didatica, clara e concisa (em 1 ou 2 paragrafos curtos) no mesmo idioma da solicitacao, explicando as tecnicas/filtros/joins adotados na consulta.
- **`app/Support/Ai/ContractValidator.php`**:
  - Adicionar validacao para `explanation` (opcional para retrocompatibilidade, `?string`, tamanho maximo de 2000 caracteres).
  - Repassar `explanation` para a instanciacao de `AiResult`.
- **`app/Support/Ai/AiResult.php`**:
  - Adicionar `public ?string $explanation = null` ao construtor e a `toArray()`.
  - Atualizar `@phpstan-type ResultArray`.
- **Testes Backend**:
  - Atualizar/adicionar testes unitarios em `ContractValidatorTest` e `PromptBuilderTest`.

### Etapa 2: Traducoes i18n (5 idiomas)
- Adicionar chaves em `lang/{en,pt_BR,pt_PT,es,fr}/ai.php`:
  - `ai.result.explanation`: "Como esta consulta foi construída" / "How this query was built" / etc.
  - `ai.result.tip`: "Dica de boas práticas" / "Best practice tip" / etc.
  - `ai.tips.<target>`: Dicas especificas para cada um dos 9 alvos:
    - `laravel_query_builder`: Explicando a vantagem de preferir `Model::query()` em vez de `DB::table(...)` para manter casts, atributos protegidos, scopes e tipagem no IDE.
    - `eloquent`: Dica sobre relacionamentos e eager loading (`with()`) para prevenir problemas de N+1.
    - `sql`: Dica sobre indexacao das colunas usadas em WHERE/JOIN e analise com `EXPLAIN`.
    - `prisma`: Dica sobre o uso de `select` vs `include` para otimizar payload e colunas buscadas.
    - `drizzle`: Dica sobre query builders relacionais vs modo SQL core do Drizzle.
    - `typeorm`: Dica sobre `select` explicito e uso de QueryBuilder para agregacoes complexas.
    - `sqlalchemy`: Dica sobre sessao e uso de `select(...)` do SQLAlchemy 2.0.
    - `sqlmodel`: Dica sobre interoperabilidade entre modelos Pydantic e queries SQLModel.
    - `django_orm`: Dica sobre `select_related()` e `prefetch_related()` para evitar queries adicionais.

### Etapa 3: Tipagem Frontend e Download
- **`resources/js/tools/ai-sql-builder/types.ts`**:
  - Adicionar `explanation?: string | null` em `AiQueryResult`.
- **`resources/js/tools/ai-sql-builder/formatDownload.ts`**:
  - Aceitar `explanation?: string | null` e `tip?: string | null` em `FormatDownloadOptions`.
  - Adicionar labels para explicacao e dica.
  - Montar no topo dos comentarios o bloco didatico:
    1. Explicacao / Como foi construida
    2. Dica de Boas Praticas
    3. Premissas
    4. Avisos
    5. Tabelas Usadas
  - Respeitar a sintaxe de comentario de cada linguagem (`#` para Python, `/* ... */` para as demais).
- **Testes Vitest**:
  - Atualizar testes de `formatDownload.test.ts`.

### Etapa 4: Frontend - Layout Vertical com Scroll em ResultPanel.vue
- **`resources/js/tools/ai-sql-builder/ResultPanel.vue`**:
  - Obter a dica traduzida do target ativo: `t(`ai.tips.${activeSlug.value}`)` (verificando se a chave existe ou com fallback seguro).
  - Atualizar `downloadOutput()` para enviar `explanation`, `tip` e seus respectivos labels traduzidos para `formatDownloadContent`.
  - Modificar a secao de metadados (`hasMeta` computado agora considerando tambem se ha `explanation` ou `tip`):
    - Substituir o grid `sm:grid-cols-2` por um container de layout vertical:
      - `flex flex-col gap-4 max-h-72 overflow-y-auto rounded-md border p-4 bg-muted/20 text-sm`
    - Elementos ordenados de cima para baixo:
      1. **Breve Descricao / Como foi construida** (`result.explanation`): em destaque com subtitulo e paragrafo explicativo.
      2. **Dica de boas praticas** (`tip` do target atual): card com icone de lampada/dica informativa (ex: `Lightbulb` do Lucide ou badge `Dica`).
      3. **Premissas** (`result.assumptions`): lista com marcadores.
      4. **Avisos** (`result.warnings`): lista com marcadores e estilo de alerta suave se aplicavel.
      5. **Tabelas Usadas** (`result.tables_used`): tags/badges ou texto mono separado por virgula.

### Etapa 5: Validacao e Verificacao de Qualidade
- Executar testes automatizados:
  - `composer test` (Pint, PHPStan, Pest)
  - `npm run test` (Vitest)
  - `npm run types:check` (vue-tsc)
- Gerar o relatorio final em `docs/gemini/reports/develop_natan/{dataatual}_develop_natan_ai_sql_builder_descricao_dicas_scroll.md`.
