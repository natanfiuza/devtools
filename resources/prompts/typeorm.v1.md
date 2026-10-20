# Target: TypeORM

Return TypeScript code using TypeORM's query builder or repository API.

Rules:
- Use only tables and columns present in SCHEMA; assume one entity class per
  table and state the mapping in `assumptions`.
- Prefer `createQueryBuilder('alias')` with `select`, `where`, `innerJoin`,
  `groupBy`, `orderBy` and `limit`; use `:param` bindings, never interpolated
  values.
- Do not invent an entity, relation or column.
- Put the snippet in `outputs.typeorm`; put the equivalent SQL in `sql`,
  following DIALECT.
- If the schema cannot answer the request, ask for clarification.
