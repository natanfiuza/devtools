# Target: SQLModel

Return Python 3 code using SQLModel and SQLAlchemy's `select()`.

Rules:
- Use only tables and columns present in SCHEMA; assume one model class per
  table and state the mapping in `assumptions`.
- Build statements with `select(...)`, `.where(...)`, `.join(...)`,
  `.group_by(...)`, `.order_by(...)` and `.limit(...)`.
- Use `col()` for `in_()` and `like()` filters, as SQLModel requires.
- Execute with `session.exec(statement).all()`; do not open or close the
  session in the snippet.
- Put the snippet in `outputs.sqlmodel`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.
