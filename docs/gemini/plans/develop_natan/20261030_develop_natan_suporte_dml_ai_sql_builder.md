# Plano de Implementação — Suporte a DML no AI SQL Builder

**Branch:** `develop_natan`  
**Data:** 2026-10-30  
**Status:** Pronto para Aprovação  
**ADR Relacionado:** [ADR-061: Suporte a DML no AI SQL Builder e emenda ao gate lexical (SqlGuard)](file:///docs/adr/ADR-061-suporte-a-dml-e-emenda-ao-gate-lexical-sqlguard.md)  
**Survey Facts:** [Survey 20261030](file:///docs/survey/20261030_suporte_dml_ai_sql_builder_surveyfacts.md)

---

## 1. Visão Geral e Objetivo

Habilitar a ferramenta **AI SQL Builder** para aceitar e processar requisições de manipulação de dados (**DML** — `INSERT`, `UPDATE`, `UPSERT` e `DELETE`), além das tradicionais consultas de leitura (**DQL** — `SELECT` e `WITH`).

A mudança abrange todos os alvos de saída suportados (`sql`, `eloquent`, `laravel_query_builder`, `prisma`, `typeorm`, `drizzle`, `sqlalchemy`, `sqlmodel`, `django_orm`), atualiza o gate lexical de segurança (`SqlGuard`) com salvaguarda obrigatória de cláusula `WHERE` para atualizações e exclusões, alinha os prompts de sistema e alvos, e atualiza as mensagens e termos de interface nos 5 idiomas da plataforma (`en`, `pt-BR`, `pt-PT`, `fr`, `es`).

---

## 2. Decisões Consolidadas

1. **Operações Permitidas**:
   - `SELECT`, `WITH` (DQL).
   - `INSERT`, `UPDATE`, `UPSERT`, `DELETE` (DML).
   - `WHERE` obrigatório em `UPDATE` e `DELETE`.
   - DDL (`CREATE`, `ALTER`, `DROP`, `TRUNCATE`), comandos de controle/administração (`GRANT`, `REVOKE`, `KILL`, `SHUTDOWN`) e funções perigosas continuam 100% proibidos.
2. **Cobertura Universal de Alvos**:
   - Atualização do prompt do sistema (`system/*.md`) e de todos os 9 alvos existentes em `resources/prompts/`.
3. **Segurança no `SqlGuard`**:
   - Regex de início: `/^(SELECT|WITH|INSERT|UPDATE|DELETE)\b/i`.
   - Remoção de `INSERT`, `UPDATE`, `DELETE` da lista negra.
   - Liberação de `INTO` quando precedido por `INSERT`, bloqueando em `SELECT`.
   - Rejeição imediata de `UPDATE` ou `DELETE` sem `WHERE`.
4. **UX & i18n**:
   - Reconhecimento automático do tipo de operação pela IA a partir do prompt do usuário.
   - Atualização das strings de interface em `lang/{en,es,fr,pt_BR,pt_PT}/ai.php`.

---

## 3. Detalhamento das Alterações por Componente

### 3.1 Backend — Gate Lexical (`SqlGuard.php`) e Testes

- **Arquivo:** `app/Support/Ai/SqlGuard.php`
  - Atualizar `FORBIDDEN_KEYWORDS`: remover `INSERT`, `UPDATE`, `DELETE`.
  - Atualizar verificação de início: aceitar `SELECT`, `WITH`, `INSERT`, `UPDATE`, `DELETE`.
  - Adicionar validação: se a instrução for `UPDATE` ou `DELETE`, exigir `\bWHERE\b` no corpo normalizado. Caso contrário, disparar `UnsafeSqlException::because('missing_where_clause')`.
  - Atualizar checagem de `INTO`: proibir apenas se a instrução não começar com `INSERT` (evitando `SELECT ... INTO`).
  - Manter bloqueios de comentários executáveis MySQL, múltiplos comandos (`;`) e funções perigosas.
- **Arquivo:** `tests/Unit/Ai/SqlGuardTest.php`
  - Adicionar testes aceitando `INSERT INTO`, `UPDATE ... WHERE`, `DELETE ... WHERE`.
  - Adicionar testes recusando `UPDATE` sem `WHERE` e `DELETE` sem `WHERE`.
  - Manter testes recusando `DROP`, `ALTER`, `TRUNCATE`, `CREATE`, `SELECT ... INTO`, etc.

### 3.2 Prompts do Sistema e Fallback

- **Arquivos:** `resources/prompts/system/{en,es,fr,pt_BR,pt_PT}.md` e `app/Support/Ai/PromptBuilder.php`
  - Atualizar a introdução do perfil: engenheiro sênior especializado em gerar código de consulta e manipulação de dados (DQL/DML).
  - Atualizar as regras rígidas (*HARD RULES*):
    - Permitir `SELECT`, `INSERT`, `UPDATE`, `DELETE`.
    - Proibir estritamente `UPDATE` ou `DELETE` sem cláusula `WHERE`.
    - Manter proibição estrita de DDL (`CREATE`, `ALTER`, `DROP`, `TRUNCATE`) e mais de uma instrução SQL.
  - Atualizar as diretrizes de preenchimento de `title` e `explanation` para contemplar tanto consultas quanto operações de escrita.

### 3.3 Prompts Específicos dos Alvos (`resources/prompts/*.v1.md`)

- **`sql.v1.md`**: Alterar a regra que dizia "Only `SELECT` or `WITH ... SELECT`. Never DDL or DML" para permitir `SELECT`, `INSERT`, `UPDATE`, `DELETE` (com `WHERE` obrigatório em `UPDATE` e `DELETE`), mantendo "Never DDL".
- **`eloquent.v1.md`**: Instruir uso de métodos idiomáticos de escrita (`create()`, `insert()`, `update()`, `upsert()`, `delete()`) além de `query()`, com respeito a `fillable` e `WHERE`.
- **`laravel_query_builder.v1.md`**: Instruir uso de `DB::table(...)->insert()`, `update()`, `updateOrInsert()`, `delete()`, com bindings e `where()`.
- **Demais alvos (`prisma.v1.md`, `typeorm.v1.md`, `drizzle.v1.md`, `sqlalchemy.v1.md`, `sqlmodel.v1.md`, `django_orm.v1.md`)**:
  - Incorporar regras e exemplos para operações de escrita (ex: `create`/`update`/`delete` no Prisma, `insert`/`update`/`delete` no TypeORM query builder, Drizzle `db.insert(...)`, SQLAlchemy `insert(Model)`/`update(Model)`, Django `Model.objects.create()` / `.update()` / `.delete()`).

### 3.4 Internacionalização (i18n)

- **Arquivos:** `lang/en/ai.php`, `lang/es/ai.php`, `lang/fr/ai.php`, `lang/pt_BR/ai.php`, `lang/pt_PT/ai.php`
  - Atualizar chaves:
    - `form.title`: "Descreva a consulta ou operação" / "Describe the query or operation"
    - `form.schema_placeholder`: mencionar consulta ou operação
    - `form.description`: "O que a consulta ou operação deve fazer?"
    - `form.description_placeholder`: incluir exemplo com escrita (ex: atualizar status de pedidos)
    - `warnings.review`: "Revise o resultado antes de usar. A consulta ou operação nunca é executada aqui."

---

## 4. Plano de Verificação e Qualidade

### Testes Automatizados
- Executar a suíte de testes do Pest:
  ```powershell
  php artisan test --filter=SqlGuardTest
  vendor/bin/pest
  ```
- Análise estática e checagem de tipos:
  ```powershell
  composer test
  npm run types:check
  ```

### Verificação Manual
- Enviar prompt de consulta: ex. "liste os clientes inativos" -> gera `SELECT` / Eloquent `where()`.
- Enviar prompt de inserção: ex. "insira um novo usuário com nome e email" -> gera `INSERT INTO` / Eloquent `User::create(...)`.
- Enviar prompt de atualização: ex. "atualize o status do pedido para pago onde id = 10" -> gera `UPDATE ... WHERE ...` / Eloquent `Order::where('id', 10)->update(...)`.
- Enviar prompt de exclusão: ex. "delete os logs com mais de 30 dias" -> gera `DELETE FROM ... WHERE ...`.
- Testar salvaguarda de segurança: envio de `UPDATE` ou `DELETE` sem `WHERE` deve ser bloqueado pelo gate ou conter `WHERE` preventivo gerado pela IA.
