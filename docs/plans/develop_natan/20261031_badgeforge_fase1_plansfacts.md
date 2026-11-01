# Plansfacts — BadgeForge Fase 1 (2026-10-31)

> Plano de execução consolidado da sessão de grill. O plano completo aprovado está em
> `docs/claude-code/plans/develop_natan/20261031_develop_natan_badgeforge_fase1.md`.

## Escopo

Núcleo de badges estáticas em SVG: endpoint público `GET /api/badges/static/{label}/{message}.svg`,
cinco estilos, cores CSS, logos embutidos, preview 100% no navegador, snippets (URL, Markdown, HTML, reST),
cache HTTP com ETag/Cache-Control, tool no catálogo e i18n em en/pt-BR/pt-PT/fr/es.

## Decisões fechadas

1. Preview por espelho TypeScript do renderer PHP, com fixtures-ouro byte-a-byte.
2. ADRs 041–050 renumerados para 062–071; novos ADRs 072–076.
3. Geometria autoral visualmente compatível com Shields.
4. Decodificação `_`/`__`, `-` literal; `/` não representável na F1.
5. Rota no grupo `api`, fora do `v1`, sem throttle.
6. Entrada inválida nunca gera 500; cosméticos caem no padrão; vazio → 404.
7. 24 logos autorais com `<path>`, registrados em `config/badgeforge.php`.
8. `logoPosition` left/right; `link` em `<a>` com `noopener noreferrer`.
9. Cores CSS completas + aliases de badge.
10. `cacheSeconds` 86400 padrão, clamp 300–86400.
11. Categoria `badges` (“Imagens”) com ícone `image`.
12. Texto truncado em 100 caracteres com `…`.
13. Catálogo e limites no cliente via `toolProps`.
14. Glossário fundido em `docs/glossario.md`.

## Arquitetura

- `app/Support/BadgeForge/`: `BadgeSpec`, `BadgeRequest`, `Color`, `Style`, `FontMetrics`,
  `LogoCatalog`, `Svg`, `StaticBadgeRenderer`, `BadgeForgeToolProps`.
- `app/Http/Controllers/BadgeForge/StaticBadgeController.php`.
- `config/badgeforge.php` como fonte de limites, defaults e registro de logos.
- `resources/badgeforge/{font-metrics.json,styles.json,logos/*.svg}` como ativos compartilhados PHP/TS.
- `resources/js/tools/badgeforge/`: manifest, `types.ts`, `lib/{color,metrics,render,url,snippets}.ts`,
  `builder.ts`, `logos.ts`, `Tool.vue`.
- Fixtures: `tests/Fixtures/badgeforge/{cases.json,expected/*.svg,tool-props.json}`.

## Etapas

- **E0** — gravar surveyfacts, plansfacts e plano.
- **E1** — núcleo PHP, ativos, fixtures e testes Unit; `composer test`.
- **E2** — rota, controller, ETag/304 e testes Feature; `composer test`.
- **E3** — manifest, categoria, toolProps, i18n, UI TS e Vitest; `npm run test` + `composer test`.
- **E4** — ADRs, SPEC/API/OPENAPI/glossário/DESIGN/aceite, relatório e suíte completa.

Cada etapa para para revisão. Sem commit e sem push (gitpr do usuário).

## Testes

Pest: cores, métricas, normalização do request, renderer contra fixtures, toolProps, endpoint e cache.
Vitest: builder/URL/snippets, renderer espelho, componente e registro da tool.

## Riscos

Métricas aproximadas e glifos não latinos; disciplina byte-a-byte; CSP global em resposta de imagem;
texto do badge em log de acesso do nginx; marcas dos logos; `/` não representável; OG padrão.
