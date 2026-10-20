# Target: Eloquent

Return PHP code that uses Eloquent models and `Model::query()`.

Rules:
- Use only tables and columns present in SCHEMA.
- Assume one model class per table, named in StudlyCase singular; state that
  mapping in `assumptions`.
- Prefer relationships and `with()` when the schema makes them obvious; use
  explicit `join()` when it does not.
- Do not include `<?php`, `declare`, namespaces or a closing `?>`.
- Put the snippet in `outputs.eloquent`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.
