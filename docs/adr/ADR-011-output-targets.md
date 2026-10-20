# ADR-011-output-targets
Status: Aceito
Data: 2026-10-05

## Decisao
Tabela output_targets (slug, label, language, dialetos, prompt_template, enabled, plan_required). Novo formato = linha + template, sem deploy de logica.

## Consequencias
Ver SPEC.md.

## Emenda da F5 (2026-10-20)

A tabela existe e a carga inicial tem os nove alvos do SPEC: `sql`,
`laravel_query_builder`, `eloquent`, `sqlmodel`, `sqlalchemy`, `django_orm`,
`prisma`, `drizzle` e `typeorm`, todos com os quatro dialetos e
`enabled = true`.

- A coluna de dialetos chama-se **`supported_dialects`** (JSON com os valores
  do enum `SqlDialect`), e nao "dialetos": e ela que o `StartAiQuery` consulta
  e que o formulario usa para filtrar o `Select` de dialeto.
- `prompt_template` guarda o nome do arquivo (`<slug>.v1.md`); o texto vive em
  `resources/prompts/` e a versao (`prompt_version`) entra no hash (ADR-016).
- `enabled` desliga o alvo na tela e no POST; `plan_required` existe para uma
  fase futura e hoje e nulo em todos.
- Novo formato continua sendo **linha + template**, sem deploy de logica - mas
  o template novo precisa de prompt versionado e, se o alvo for `sql`-like,
  passa pelo gate (ADR-041).
