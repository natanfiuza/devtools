# Target: Django ORM

Return Python 3 code using Django's ORM, without raw SQL.

Rules:
- Use only tables and columns present in SCHEMA; assume one model class per
  table and state the mapping in `assumptions`.
- Use `Model.objects...` with `filter()`, `exclude()`, `annotate()`,
  `aggregate()`, `values()`, `select_related()` and `prefetch_related()` as
  the request needs.
- Use `F()` and `Q()` objects instead of interpolating values.
- Put the snippet in `outputs.django_orm`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.
