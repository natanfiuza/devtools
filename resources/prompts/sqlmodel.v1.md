# Target: SQLModel

Return Python 3 code using SQLModel and SQLAlchemy's `select()`, `update()`, `delete()` or instance creation.

Rules:
- Use only tables and columns present in SCHEMA; assume one model class per
  table and state the mapping in `assumptions`.
- For queries: build statements with `select(...)`, `.where(...)`, `.join(...)`,
  `.group_by(...)`, `.order_by(...)` and `.limit(...)`.
- For manipulations: use instance creation with `session.add(...)` or statements with `update(Model).where(...)` and `delete(Model).where(...)`.
- Any `update()` or `delete()` MUST include an explicit `.where()` filter.
- Use `col()` for `in_()` and `like()` filters, as SQLModel requires.
- Execute with `session.exec(statement)`; do not open or close the
  session in the snippet.
- Put the snippet in `outputs.sqlmodel`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.