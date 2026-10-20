# Target: SQL

Produce one SQL statement that answers the request and can be run as is.

Rules:
- Only `SELECT` or `WITH ... SELECT`. Never DDL or DML.
- Use only tables, columns, types and relationships present in SCHEMA. Do not
  invent a column or table to make the query fit.
- Follow DIALECT for identifier quoting, functions, pagination and date
  arithmetic.
- A final semicolon is allowed; do not write more than one statement.
- `outputs.sql` must be identical to `sql`.
