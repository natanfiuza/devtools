# Survey — Suporte a DML no AI SQL Builder (2026-10-30)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** "Altere para que a tool AI SQL Builder possa receber alem de prompts para gerar consultas (DQL) tipo de prompts de DML para que se possa ter um resposta de um UPDATE ou de um INSERT por exemplo no Eloquent / ou um Laravel Query Builder"
- **Tela / Módulo:** AI SQL Builder (`resources/js/tools/ai-sql-builder/`, `app/Support/Ai/`, `resources/prompts/`)
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 1 rodada com 4 decisões principais mapeadas e aprovadas integralmente pelo usuário.
- **Arquivos-chave lidos:**
  - `app/Support/Ai/SqlGuard.php`
  - `app/Support/Ai/PromptBuilder.php`
  - `app/Jobs/ProcessAiQuery.php`
  - `docs/adr/ADR-041-gate-lexical-de-sql.md`
  - `resources/prompts/system/*.md`
  - `resources/prompts/*.v1.md`
  - `resources/js/tools/ai-sql-builder/types.ts`
  - `resources/js/tools/ai-sql-builder/SchemaForm.vue`
  - `lang/*/ai.php`
  - `tests/Unit/Ai/SqlGuardTest.php`

## 2. Decisões das rodadas

### Rodada 1 — Escopo de DML, Alvos, Gate Lexical e UX
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Operações DML permitidas | Permitir `INSERT`, `UPDATE`, `UPSERT` e `DELETE`. Cláusula `WHERE` obrigatória em `UPDATE` e `DELETE`. Proibição total de DDL (`DROP`, `ALTER`, `CREATE`, `TRUNCATE`) e comandos administrativos (`GRANT`, `REVOKE`, etc.). |
| Q2 | Cobertura de Alvos (Output Targets) | Suporte universal para todos os alvos suportados pelo AI SQL Builder (`sql`, `eloquent`, `laravel_query_builder`, `prisma`, `typeorm`, `drizzle`, `sqlalchemy`, `sqlmodel`, `django_orm`), sem foco exclusivo em um único framework. |
| Q3 | Adaptação do `SqlGuard` (ADR-061) | Aceitar instruções `SELECT`, `WITH`, `INSERT`, `UPDATE` e `DELETE`. Permitir `INTO` quando precedido de `INSERT`. Exigir `WHERE` para `UPDATE` e `DELETE`. Manter proibição de instruções múltiplas, DDL e funções perigosas. |
| Q4 | Interface do Usuário (UI/UX) e i18n | Inferência 100% automática da intenção (DQL ou DML) pela IA sem novos seletores na tela. Ajustar labels e placeholders em i18n nos 5 idiomas (`en`, `pt-BR`, `pt-PT`, `fr`, `es`) para referenciar "consulta ou operação". |

### Defaults assumidos (não contestados)
- O formato do JSON de resposta da API (`AiQueryResult`) permanece idêntico: `{ title, explanation, sql, dialect, assumptions, warnings, tables_used, needs_clarification, clarification, outputs: { ... } }`.
- Para alvos ORM, o campo `outputs.<target>` conterá a instrução DML idiomática do ORM/query builder, e o campo `sql` conterá o SQL correspondente na sintaxe do dialeto selecionado.
- Os testes unitários do `SqlGuard` e testes de fluxo de IA serão expandidos para cobrir `INSERT`, `UPDATE` (com e sem `WHERE`), `DELETE` (com e sem `WHERE`) e rejeição de DDL/lotes.

## 3. Relatório de fatos levantados

### Fato 1: O Gate Lexical `SqlGuard` e a Cláusula `INTO`
No ADR-041 original, a palavra-chave `INTO` foi proibida globalmente para evitar que queries `SELECT ... INTO ...` criassem tabelas ou escrevessem arquivos no servidor. Ao permitir `INSERT`, a sintaxe padrão `INSERT INTO table ...` é essencial. O `SqlGuard` precisa permitir `INTO` exclusivamente no contexto de `INSERT`, mantendo a proibição de `SELECT ... INTO` e `INTO OUTFILE` / `INTO DUMPFILE`.

### Fato 2: Obrigatoriedade de `WHERE` para Evitar Danos Acidentais
Mesmo que o código gerado nunca seja executado pela plataforma, um dos maiores riscos ao desenvolvedor ao solicitar um DML é copiar e executar inadvertidamente um `UPDATE` ou `DELETE` irrestrito (sem `WHERE`). Exigir a cláusula `WHERE` (tanto no prompt do sistema quanto na validação do `SqlGuard`) é uma salvaguarda em profundidade fundamental.

### Fato 3: Multilíngue e Prompts de Sistema
O sistema possui templates de prompt de sistema específicos por idioma em `resources/prompts/system/{locale}.md` (`en`, `es`, `fr`, `pt_BR`, `pt_PT`) e um fallback em PHP no `PromptBuilder.php`. Todos precisam ser alinhados de forma consistente com as novas regras de DQL + DML.
