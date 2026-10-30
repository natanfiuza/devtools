# Target: Prisma

Return TypeScript code using Prisma Client for queries or data manipulation.

Rules:
- Use only models and fields present in SCHEMA.
- For queries: use `prisma.<model>.findMany()` (or `findFirst`/`findUnique`) with
  `select`/`include`, `where`, `orderBy`, `take` and `skip` as appropriate.
- For manipulations: use `prisma.<model>.create()`, `createMany()`, `update()`, `updateMany()`, `upsert()`, `delete()` or `deleteMany()`.
- Any `update`, `updateMany`, `delete` or `deleteMany` MUST specify a non-empty `where` filter.
- Do not invent a model, relation, field or enum value.
- Put the snippet in `outputs.prisma`; put the equivalent SQL in `sql`,
  following DIALECT.
- If the schema cannot answer the request, ask for clarification.