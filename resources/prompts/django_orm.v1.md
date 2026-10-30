# Target: Django ORM

Return Python 3 code using Django's ORM for queries or data manipulation, without raw SQL.

Rules:
- Use only tables and columns present in SCHEMA; assume one model class per
  table and state the mapping in `assumptions`.
- For queries: use `Model.objects...` with `filter()`, `exclude()`, `annotate()`,
  `aggregate()`, `values()`, `select_related()` and `prefetch_related()` as
  the request needs.
- For manipulations: use `Model.objects.create(...)`, `Model.objects.bulk_create(...)`, `Model.objects.filter(...).update(...)` or `Model.objects.filter(...).delete()`.
- Any `update()` or `delete()` MUST be preceded by a non-empty `filter(...)`. Never call `.update()` or `.delete()` directly on `all()`.
- Use `F()` and `Q()` objects instead of interpolating values.
- Put the snippet in `outputs.django_orm`; put the equivalent SQL in `sql`,
  following DIALECT.
- Never invent a model, relationship or column.