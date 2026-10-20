# Target: SQLAlchemy

Return Python 3 code using SQLAlchemy 2.0 style (`select()` and mapped
classes).

Rules:
- Use only tables and columns present in SCHEMA; assume one mapped class per
  table and state the mapping in `assumptions`.
- Use `select(Model.column, ...)`, `.where(...)`, `.join(...)`,
  `.group_by(...)`, `.order_by(...)` and `.limit(...)`.
- Execute with `session.execute(statement).all()` or `.scalars()` as the
  selected columns require; do not create the session in the snippet.
- Put the snippet in `outputs.sqlalchemy`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.
