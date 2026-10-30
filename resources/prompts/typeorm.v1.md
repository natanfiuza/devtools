# Target: TypeORM

Return TypeScript code using TypeORM's query builder or repository API for queries or data manipulation.

Rules:
- Use only tables and columns present in SCHEMA; assume one entity class per
  table and state the mapping in `assumptions`.
- For queries: prefer `createQueryBuilder('alias')` with `select`, `where`, `innerJoin`,
  `groupBy`, `orderBy` and `limit`.
- For manipulations: use query builder (`insert().into(Entity).values(...)`, `update(Entity).set(...).where(...)`, `delete().from(Entity).where(...)`) or repository methods (`save()`, `insert()`, `update()`, `delete()`).
- Any update or delete operation MUST include a `where` condition.
- Use `:param` bindings, never interpolated values.
- Do not invent an entity, relation or column.
- Put the snippet in `outputs.typeorm`; put the equivalent SQL in `sql`,
  following DIALECT.
- If the schema cannot answer the request, ask for clarification.