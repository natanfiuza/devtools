# ADR-174 — Rota pública, viewer e SEO do sistema de layout
Data: 2026-11-19
Status: Aceito

## Contexto

A ADR-154 fixou que o sistema público é compartilhável e reutiliza o viewer do
Colors, mas a fase precisava decidir a rota, o que o viewer mostra e como a
página entra em SEO e sitemap sem virar um segundo perfil público de paleta.

## Decisão

- **Rota**: `{locale}/colors/s/{slug}` (`colors.systems.show`), fora do grupo
  `dashboard/colors`. O slug só existe para item público e ativo (ADR-171).
- **Viewer**: `PublicLayoutSystem.vue` usa `ThemeComparison`/`ThemeBoard` com
  `editable=false` — cópia de HEX/OKLCH funciona; não há handler de edição,
  bloqueio, restauração, sugestões ou exportação. Mostra autor, tags, datas,
  estratégia, seed, versões e 66 tokens.
- **Conteúdo**: só o presenter público — nome, descrição, tags, autor, versões,
  `state.snapshot`, crédito de origem permitido e `duplications_count`. Nunca
  `source_palette_id`, conta, origem privada ou paths internos.
- **404 uniforme**: privado, arquivado, retirado (slug em
  `layout_system_retired_slugs`) e inexistente respondem o mesmo 404.
- **SEO**: `Seo::pageMeta` trata `colors.systems.show` com nome/descrição e
  fallback `colors.meta.system_description` (com estratégia e 66 tokens), com
  `Schema::hasTable('layout_systems')` defensivo. `Seo::toolSlug()`/`image()`
  reconhecem a rota como a tool `layout-harmonizer` e usam a OG estática
  `public/og/layout-harmonizer.png` — sem OG dinâmica por sistema. Canonical e
  hreflang são iguais em todos os locales.
- **Sitemap**: `Sitemap::layoutSystems()` usa `Schema::hasTable`,
  `publiclyVisible()`, limite `colors.layout_systems.sitemap_limit` (500) e
  entra em `urls()` junto das paletas.
- **Compartilhamento**: não há curtidas, comentários, favoritos, seguir, perfil
  público próprio nem API JSON para sistema de layout nesta fase.

## Consequências

- O sistema público é uma vitrine estática do snapshot, não um objeto social;
  as superfícies sociais continuam exclusivas de paleta.
- A OG é única por ferramenta, o que evita gerar imagem por requisição.
- A página pública some do sitemap ao arquivar e responde 404 sem vazar
  existência, sem precisar de poda de cache.
