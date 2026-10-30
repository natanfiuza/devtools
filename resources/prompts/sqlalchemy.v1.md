# Target: SQLAlchemy

Return Python 3 code using SQLAlchemy 2.0 style (`select()`, `insert()`, `update()`, `delete()` and mapped classes).

Rules:
- Use only tables and columns present in SCHEMA; assume one mapped class per
  table and state the mapping in `assumptions`.
- For queries: use `select(Model.column, ...)`, `.where(...)`, `.join(...)`,
  `.group_by(...)`, `.order_by(...)` and `.limit(...)`.
- For manipulations: use `insert(Model).values(...)`, `update(Model).where(...).values(...)` or `delete(Model).where(...)`.
- Any `update()` or `delete()` statement MUST include a `.where()` clause.
- Execute with `session.execute(statement)`; do not create or close the session in the snippet.
- Put the snippet in `outputs.sqlalchemy`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.