# ADR-171 — Slug aposentado: URLs públicas nunca são reemitidas
Data: 2026-11-19
Status: Aceito

## Contexto

Fechar o ciclo de vida (ADR-164) exige decidir o que acontece com a URL
pública quando o item é arquivado ou excluído. Reutilizar um slug liberado
permitiria que um link compartilhado no passado passasse a mostrar outro
sistema — conteúdo diferente do que o leitor viu. O slug também não pode
mudar quando o item é renomeado, para não quebrar links existentes.

## Decisão

- `LayoutSystemSlug` espelha `PaletteSlug`: `base(name)` usa `Str::slug`, com
  fallback `layout-system`, corte em `colors.slug.max_length` e sufixo
  `-system` para nomes reservados; `unique(base)` tenta `-2` a `-5` e depois um
  sufixo aleatório; `taken(slug)` consulta `layout_systems.slug`,
  `layout_systems.archived_slug` e `layout_system_retired_slugs.slug`, com
  `Schema::hasTable` defensivo.
- O slug nasce na primeira publicação (criação pública ou update que torna
  público) e não muda ao renomear.
- Tornar privado revoga a URL imediatamente; o slug fica reservado e volta a
  valer se o item for publicado de novo.
- Arquivar move `slug` para `archived_slug` (unique), zera `slug` e revoga a
  URL; restaurar público devolve o slug reservado; restaurar privado mantém o
  slug em `archived_slug` até a publicação.
- Excluir grava `slug` e `archived_slug` em `layout_system_retired_slugs`
  com `insertOrIgnore` e remove a linha. `archived_slug` e a tabela de retirados
  são uniques, então um slug arquivado nunca é reutilizado e um slug excluído
  fica aposentado para sempre.
- A página pública checa `isRetired` antes do `firstOrFail`; privada,
  arquivada, inexistente e aposentada têm o mesmo 404.

## Consequências

- O ULID e o slug nunca são reemitidos; criar um sistema com o mesmo nome de
  um excluído gera slug diferente.
- Links antigos de itens arquivados ou excluídos respondem 404 permanente, e o
  item some do sitemap sem liberar a URL.
- A pilha de slugs cresce monotonicamente; a tabela de retirados é pequena
  (id, slug, timestamps) e não precisa de poda.
