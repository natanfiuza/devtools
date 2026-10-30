# Relatório de Execução — Suporte a DML no AI SQL Builder

**Data:** 2026-10-30  
**Branch:** `develop_natan`  
**Tarefa:** `suporte_dml_ai_sql_builder`  
**ADR Associado:** [ADR-061: Suporte a DML no AI SQL Builder e emenda ao gate lexical (SqlGuard)](file:///docs/adr/ADR-061-suporte-a-dml-e-emenda-ao-gate-lexical-sqlguard.md)  
**Survey Facts:** [docs/survey/20261030_suporte_dml_ai_sql_builder_surveyfacts.md](file:///docs/survey/20261030_suporte_dml_ai_sql_builder_surveyfacts.md)  
**Plano de Implementação:** [docs/gemini/plans/develop_natan/20261030_develop_natan_suporte_dml_ai_sql_builder.md](file:///docs/gemini/plans/develop_natan/20261030_develop_natan_suporte_dml_ai_sql_builder.md)

---

## 1. Resumo da Tarefa

A ferramenta **AI SQL Builder** foi atualizada para aceitar prompts de manipulação de dados (**DML** — `INSERT`, `UPDATE`, `UPSERT` e `DELETE`), além das tradicionais consultas de leitura (**DQL** — `SELECT` e `WITH`).

O suporte foi estendido de forma padronizada para **todos os alvos suportados** (`sql`, `eloquent`, `laravel_query_builder`, `prisma`, `typeorm`, `drizzle`, `sqlalchemy`, `sqlmodel`, `django_orm`), com a implementação de salvaguardas adicionais no gate lexical de segurança (`SqlGuard`), exigindo cláusula `WHERE` obrigatória para qualquer operação de `UPDATE` ou `DELETE`.

---

## 2. Alterações Realizadas

### 2.1 Backend e Gate Lexical (`SqlGuard`)
- **[app/Support/Ai/SqlGuard.php](file:///app/Support/Ai/SqlGuard.php)**:
  - Verificação de início de instrução atualizada para `^(SELECT|WITH|INSERT|UPDATE|DELETE)\b`.
  - Remoção de `INSERT`, `UPDATE` e `DELETE` da lista `FORBIDDEN_KEYWORDS`.
  - Exigência de cláusula `WHERE` obrigatória para instruções `UPDATE` e `DELETE`, lançando `UnsafeSqlException::because('missing_where_clause')` caso ausente.
  - Exigência da cláusula `SET` para `UPDATE` e bloqueio de `SET` isolado fora de `UPDATE`.
  - Manutenção do bloqueio de `FOR UPDATE` em consultas de leitura (`for_update_clause`).
  - Liberação do uso de `INTO` exclusivamente quando associado a `INSERT INTO`, mantendo o bloqueio em `SELECT ... INTO` e arquivos (`INTO OUTFILE` / `INTO DUMPFILE`).
- **[tests/Unit/Ai/SqlGuardTest.php](file:///tests/Unit/Ai/SqlGuardTest.php)**:
  - Adição de testes unitários para `INSERT`, `UPDATE ... WHERE`, `DELETE ... WHERE`.
  - Validação de rejeição de `UPDATE` e `DELETE` sem `WHERE`.
  - Manutenção dos testes de bloqueio de DDL (`DROP`, `ALTER`, `CREATE`, `TRUNCATE`), lotes com ponto e vírgula e funções perigosas do sistema.

### 2.2 Prompts do Sistema e Fallback
- **[app/Support/Ai/PromptBuilder.php](file:///app/Support/Ai/PromptBuilder.php)**:
  - Atualização do template fallback para orientar o modelo na geração de DQL e DML com obrigatoriedade de `WHERE` e proibição irrestrita de DDL.
- **[resources/prompts/system/](file:///resources/prompts/system/) (`en.md`, `es.md`, `fr.md`, `pt_BR.md`, `pt_PT.md`)**:
  - Alinhamento de todos os idiomas para atuar em consultas e manipulação de dados, definindo as regras rígidas (*HARD RULES*) de forma equivalente e consistente.

### 2.3 Prompts Específicos por Alvo (`resources/prompts/`)
- **`sql.v1.md`**: Permissão explícita de `SELECT`, `INSERT`, `UPDATE` e `DELETE` (com `WHERE`).
- **`eloquent.v1.md`**: Instruções para métodos de escrita (`create()`, `insert()`, `update()`, `upsert()`, `delete()`) com `where()` obrigatório.
- **`laravel_query_builder.v1.md`**: Instruções para `DB::table(...)` com `insert()`, `update()`, `updateOrInsert()`, `delete()` com bindings e filtros.
- **`prisma.v1.md`**: Adição de `create`, `update`, `upsert`, `delete`, `deleteMany` com `where`.
- **`typeorm.v1.md`**: Adição de mutações via query builder (`insert()`, `update()`, `delete()`) e repository com `where`.
- **`drizzle.v1.md`**: Adição de `db.insert()`, `db.update().set().where()` e `db.delete().where()`.
- **`sqlalchemy.v1.md`**: Adição de `insert()`, `update().where()`, `delete().where()`.
- **`sqlmodel.v1.md`**: Adição de manipulações com `session.add()` ou `update()`/`delete()` com filtros.
- **`django_orm.v1.md`**: Adição de `objects.create()`, `.filter().update()` e `.filter().delete()`.

### 2.4 Internacionalização (i18n)
- **`lang/{en,es,fr,pt_BR,pt_PT}/ai.php`**:
  - Atualização de títulos, descrições e placeholders (`form.title`, `form.schema_placeholder`, `form.description`, `form.description_placeholder`, `warnings.review`) para refletir "consulta ou operação", contemplando os 5 idiomas suportados.

---

## 3. Validação e Qualidade

Todos os comandos de verificação foram executados e aprovados integralmente:
1. **Pint**: `passed` (código formatado e nos padrões do projeto).
2. **PHPStan**: `passed` (0 erros estáticos).
3. **Pest**: `passed` (757 testes e 16.752 asserções bem-sucedidas, incluindo `SqlGuardTest`).
4. **Vitest**: `passed` (65 suítes de teste de frontend e 823 testes executados com sucesso).
5. **Types Check**: `passed` (`wayfinder:generate` e `vue-tsc --noEmit` sem divergências).
