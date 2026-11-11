# ADR-109 - Busca server-side
Status: Aceito

## Decisão
Minhas Paletas usa busca server-side com paginação.

## Consequências
Escala melhor; exige API paginada.

## Emenda 2026-11-11
A implementação (ADR-104, emenda) usa `whereLike` agrupado em `name`, `description` e `whereHas('tags')`, filtros de `status` (`active|archived`) e `tag` (ULID), e sort com whitelist (`updated_at` padrão, `created_at`, `name`, `colors_count`, `duplications_count`) e `direction asc|desc`. `colors_count` e `duplications_count` são colunas denormalizadas e indexadas justamente para não ordenar JSON; a paginação usa `colors.palettes.per_page` com `withQueryString()`. A tela usa debounce de 300 ms com `preserveState`/`preserveScroll`/`replace`.
