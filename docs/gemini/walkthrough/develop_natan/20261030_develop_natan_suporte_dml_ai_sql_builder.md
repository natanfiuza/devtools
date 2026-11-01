# Walkthrough — Suporte a DML no AI SQL Builder

**Data:** 2026-10-30  
**Branch:** `develop_natan`  
**Tarefa:** `suporte_dml_ai_sql_builder`  

A ferramenta **AI SQL Builder** foi atualizada com sucesso para receber e processar tanto consultas de leitura (**DQL**) quanto manipulações de dados (**DML** — `INSERT`, `UPDATE`, `UPSERT` e `DELETE`), cobrindo todos os alvos suportados (Eloquent, Laravel Query Builder, SQL puro, Prisma, TypeORM, Drizzle, SQLAlchemy, SQLModel e Django ORM).

## Principais Alterações

### 1. Gate Lexical de Segurança (`SqlGuard`)
- Flexibilizado para aceitar instruções iniciadas por `SELECT`, `WITH`, `INSERT`, `UPDATE` e `DELETE`.
- `INSERT`, `UPDATE` e `DELETE` foram removidos da lista negra de palavras-chave.
- Cláusula `WHERE` tornou-se estritamente obrigatória para `UPDATE` e `DELETE` (evitando perda inadvertida de dados).
- `INTO` liberado para sintaxe `INSERT INTO`, permanecendo bloqueado para `SELECT ... INTO` e comandos de exportação para arquivo.
- Bloqueio contínuo e rigoroso contra comandos DDL (`CREATE`, `ALTER`, `DROP`, `TRUNCATE`), comandos administrativos e funções do sistema.

### 2. Prompts de Sistema e Alvos
- Atualização do template fallback no `PromptBuilder` e nos templates dos 5 idiomas (`resources/prompts/system/{en,es,fr,pt_BR,pt_PT}.md`).
- Atualização dos 9 alvos em `resources/prompts/*.v1.md`, fornecendo orientações idiomáticas tanto para seleção quanto para inserção, atualização e exclusão com filtros.

### 3. Internacionalização (i18n)
- Labels, placeholders e mensagens de aviso atualizados em `lang/{en,es,fr,pt_BR,pt_PT}/ai.php` para referenciar "consulta ou operação".

---

## Resultados dos Testes Automatizados

- **Pint**: `Passed`
- **PHPStan**: `Passed` (0 erros)
- **Pest**: `757 tests passed, 16.752 assertions`
- **Vitest**: `65 test files passed, 823 tests passed`
- **TypeScript**: `vue-tsc --noEmit passed`

---

## Documentação e Registros

- **ADR-061**: [ADR-061: Suporte a DML no AI SQL Builder e emenda ao gate lexical (SqlGuard)](file:///docs/adr/ADR-061-suporte-a-dml-e-emenda-ao-gate-lexical-sqlguard.md)
- **Survey Facts**: [docs/survey/20261030_suporte_dml_ai_sql_builder_surveyfacts.md](file:///docs/survey/20261030_suporte_dml_ai_sql_builder_surveyfacts.md)
- **Plano de Implementação**: [docs/gemini/plans/develop_natan/20261030_develop_natan_suporte_dml_ai_sql_builder.md](file:///docs/gemini/plans/develop_natan/20261030_develop_natan_suporte_dml_ai_sql_builder.md)
- **Relatório de Execução**: [docs/gemini/reports/develop_natan/20261030_develop_natan_suporte_dml_ai_sql_builder.md](file:///docs/gemini/reports/develop_natan/20261030_develop_natan_suporte_dml_ai_sql_builder.md)
