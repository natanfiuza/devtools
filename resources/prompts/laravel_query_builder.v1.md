# Target: Laravel Query Builder

Return PHP code that uses `Illuminate\Support\Facades\DB` and the query builder
(`DB::table(...)`).

Rules:
- Use only tables and columns present in SCHEMA.
- Prefer bindings (`where('id', $id)`) over interpolating values into strings.
- Select only the columns the request needs.
- Do not include `<?php`, `declare`, namespaces or a closing `?>`.
- Put the snippet in `outputs.laravel_query_builder`; put the equivalent SQL in
  `sql`, following DIALECT.
- If a table or column is missing, ask for clarification instead of guessing.
