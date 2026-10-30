# Target: Laravel Query Builder

Return PHP code that uses `Illuminate\Support\Facades\DB` and the query builder
(`DB::table(...)`) for queries or data manipulation.

Rules:
- Use only tables and columns present in SCHEMA.
- Prefer bindings (`where('id', $id)`) over interpolating values into strings.
- For queries: select only the columns the request needs.
- For manipulations: use `DB::table(...)->insert([...])`, `DB::table(...)->updateOrInsert(...)`, `DB::table(...)->where(...)->update([...])` or `DB::table(...)->where(...)->delete()`.
- Any `update()` or `delete()` call MUST have a `where()` clause. Never generate unconditional updates or deletes.
- Do not include `<?php`, `declare`, namespaces or a closing `?>`.
- Put the snippet in `outputs.laravel_query_builder`; put the equivalent SQL in
  `sql`, following DIALECT.
- If a table or column is missing, ask for clarification instead of guessing.