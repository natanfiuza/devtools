# Target: Drizzle ORM

Return TypeScript code using Drizzle ORM for queries or data manipulation.

Rules:
- Use only tables and columns present in SCHEMA; assume the schema is imported
  from the project and state the import path in `assumptions`.
- For queries: use `db.select({...}).from(table)` with `.where()`, `.innerJoin()`,
  `.groupBy()`, `.orderBy()` and `.limit()`.
- For manipulations: use `db.insert(table).values(...)`, `db.update(table).set(...).where(...)` or `db.delete(table).where(...)`.
- Any `update()` or `delete()` MUST include an explicit `.where()` clause.
- Use the operators the dialect needs (`eq`, `and`, `gte`, `inArray`, `desc`,
  ...); do not interpolate values into SQL strings.
- Put the snippet in `outputs.drizzle`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a table, column or relation.