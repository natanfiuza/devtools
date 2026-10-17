# Plano — OG images quadradas e metas de página no servidor

Data: 2026-10-17
Branch: develop_natan
Task: og_quadrada_metas_no_servidor

## Decisões

1. Título/descrição por página resolvidos no servidor (`App\Support\Seo::pageMeta`),
   fonte única; Blade escreve `<title>`, `meta description`, `og:title` e
   `og:description`. PageHead passa a emitir só o `<title>`.
2. Imagens OG em 1200×1200, conteúdo essencial na faixa central 1200×628.
3. Cards de tool continuam sem idioma (nome + keywords do manifest; ADR-030).
4. Card padrão continua com wordmark + lista de tools.
5. `og:title` = título puro; `<title>` = "Página - devtools".
6. `twitter:card` = `summary_large_image`.
7. Cache-bust `?v=2` configurável em `config/seo.php`.

## Passos

1. `config/seo.php`: 1200×1200 e `image.version => 2`.
2. `App\Support\Seo`: `pageMeta()`, `toolMeta()`, `legalIntro()` (interpolação
   `{app}`), `documentTitle()` e URL versionada em `image()`.
3. `HandleInertiaRequests`: compartilhar `seo.meta`; tipar em `global.d.ts`.
4. `PageHead.vue`: ler `page.props.seo.meta.title`; remover metas e props.
5. Páginas Home, tools/Show, Privacy e Terms: `<PageHead />`.
6. `app.blade.php`: metas server-side e `<title>` fallback dinâmico.
7. `GenerateOgImage`: layout quadrado com `mainBaseline()`/`LINES_OFFSET`;
   wordmark pequeno no topo da faixa central.
8. Regenerar os 8 PNGs (`php artisan seo:og-image`).
9. Testes: `SeoMetaTest` (metas server-side, 5 idiomas, dimensões, `?v=`,
   shared props, noindex) e Vitest (`page-head`, `home`, `tool-page`).
10. Docs: ADR-033, emenda na ADR-030, SPEC, DESIGN, glossário, CHANGELOG.
11. Rodar `composer test`, `npm run test`, `npm run types:check`.
12. Relatório em `docs/claude-code/reports/develop_natan/`.

## Verificação

- `getimagesize` dos 8 PNGs → 1200×1200 `image/png`.
- Smoke local com `INERTIA_SSR_ENABLED=false` e UA de WhatsApp: `<title>JSON
  Viewer - devtools</title>`, `og:title`, `og:description`, `meta description`,
  `og:image` com `?v=2`, width/height 1200.
- Login sem OG/description e `<title>devtools</title>`.
- Validar preview após deploy no Facebook Sharing Debugger/opengraph.xyz;
  WhatsApp pode manter o preview da página em cache por URL.

## Observações

- Query string nunca vai para `is_file()`; o check usa `public_path($relative)`.
- Navegação SPA atualiza só o `<title>`; description/OG permanecem do
  carregamento inicial (correto para crawlers).
- Nenhum commit/push: os PNGs e o código ficam para o usuário commitar via gitpr.
