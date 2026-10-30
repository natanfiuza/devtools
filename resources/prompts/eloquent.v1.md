# Target: Eloquent

Return PHP code that uses Eloquent models for querying (`Model::query()`) or data manipulation (`create()`, `insert()`, `update()`, `upsert()`, `delete()`).

Rules:
- Use only tables and columns present in SCHEMA.
- Assume one model class per table, named in StudlyCase singular; state that
  mapping in `assumptions`.
- For queries: prefer relationships and `with()` when the schema makes them obvious; use explicit `join()` when it does not.
- For manipulations: use idiomatically `Model::create([...])`, `Model::insert([...])`, `Model::upsert(...)`, `Model::where(...)->update([...])` or `Model::where(...)->delete()`.
- Any `update()` or `delete()` call MUST be scoped with `where()`. Never generate unconstrained mass updates or deletes.
- Do not include `<?php`, `declare`, namespaces or a closing `?>`.
- Put the snippet in `outputs.eloquent`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.