# Target: Prisma

Return TypeScript code using Prisma Client.

Rules:
- Use only models and fields present in SCHEMA.
- Use `prisma.<model>.findMany()` (or the operation the request needs) with
  `select`/`include`, `where`, `orderBy`, `take` and `skip` as appropriate.
- Do not invent a model, relation, field or enum value.
- Put the snippet in `outputs.prisma`; put the equivalent SQL in `sql`,
  following DIALECT.
- If the schema cannot answer the request, ask for clarification.
