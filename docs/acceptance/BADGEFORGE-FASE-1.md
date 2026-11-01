# Criterios de aceite — BadgeForge FASE 1 (nucleo SVG estatico)

Gerado em 2026-10-31.
Fonte: `docs/SPEC-BADGEFORGE.md` secao 16 + `docs/claude-code/plans/develop_natan/20261031_develop_natan_badgeforge_fase1.md`
+ ADRs 062–076.

## Escopo da fase

Nucleo de badges estaticas em SVG geradas e servidas pelo Laravel: endpoint
publico `GET /api/badges/static/{label}/{message}.svg`, cinco estilos, parser de
cores completo, 24 logos embutidos, `logoSize`/`logoPosition`/`link`/`cacheSeconds`,
ETag/304 e `Cache-Control`, builder 100% no navegador com preview e quatro
snippets, tool registrada no catalogo (home, busca, sitemap, OG) e i18n nos cinco
idiomas.

Fora da fase: PNG/Imagick, providers, endpoint JSON, logos personalizados,
badges pessoais, analytics, exportacao, `?refresh=` e `?download=`.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | `GET /api/badges/static/build/passing.svg` responde 200 com `image/svg+xml; charset=utf-8` e um SVG valido | `StaticBadgeEndpointTest` |
| 2 | O SVG tem `role="img"`, `<title>` e `aria-label="label: message"`; nao ha `<script`, `on*=` nem `javascript:` | `StaticBadgeRendererTest` + `badgeforge-render.test.ts` |
| 3 | Os cinco estilos (`flat`, `flat-square`, `plastic`, `for-the-badge`, `social`) renderizam com a geometria de `styles.json`; `social` fixa as cores | fixtures + `badgeforge.test.ts` |
| 4 | Cores: nomes CSS, apelidos de badge, hex 3/4/6/8, `rgb()`, `rgba()`, `hsl()`, `hsla()` e `transparent`; invalida cai no padrao | `ColorTest` + `badgeforge.test.ts` |
| 5 | `logo` desconhecido e ignorado; `logoSize` sofre clamp 8–20; `logoPosition` aceita `left`/`right` | `BadgeRequestTest` + endpoint |
| 6 | `link` so aceita `http`/`https` e sai como `<a target="_blank" rel="noopener noreferrer">` | renderer + fixture |
| 7 | Texto acima de 100 caracteres trunca com `…` por code points; segmento vazio responde 404 | `BadgeRequestTest` + endpoint |
| 8 | `_` vira espaco, `__` vira `_` e `/` viaja como `%252F` (label `ci%252Fcd` sai `ci/cd` no `<title>`) | `BadgeRequestTest` + endpoint + `badgeforge.test.ts` |
| 9 | `message=1.0.0` e `98%` casam; `.png` e URL sem extensao respondem 404 | `StaticBadgeEndpointTest` |
| 10 | `Cache-Control: public, max-age=<ttl>`; `cacheSeconds` tem clamp 300–86400 e invalido cai no padrao | `StaticBadgeEndpointTest` |
| 11 | `ETag` e sha256 estavel; `If-None-Match` igual responde 304 sem corpo e sem `Content-Type` | `StaticBadgeEndpointTest` |
| 12 | Nenhum log com label/message (`Log::spy()`) | `StaticBadgeEndpointTest` |
| 13 | O espelho TypeScript produz o mesmo SVG **byte a byte** que o PHP nas 21 fixtures | Pest `StaticBadgeRendererTest` + `badgeforge-render.test.ts` |
| 14 | A pagina `/{locale}/tools/badgeforge` entrega `toolProps` identico a `tests/Fixtures/badgeforge/tool-props.json` nos 5 idiomas | `BadgeForgePageTest` + `BadgeForgeToolPropsTest` |
| 15 | A tool aparece no catalogo com categoria `badges`, icone `image`, card na home, busca, sitemap e OG proprio | `ToolsRegistryTest`, `SitemapTest`, `SeoMetaTest`, `home.test.ts`, `tools-registry.test.ts` |
| 16 | i18n completo e com paridade de chaves nos 5 idiomas | `TranslationsParityTest` |
| 17 | A pagina monta preview e snippets sem nenhuma requisicao ao servidor | `badgeforge-component.test.ts` |
| 18 | Gate: `composer test` (pint + phpstan + Pest) e `npm run test` verdes | os comandos |
| 19 | Nenhum token global de provider em `config`/`.env.example`/`app`; nenhum commit feito pelo assistente | `git status` + `grep` |

## Resultado em 2026-10-31

- `composer test`: pint ok, phpstan 0 erros, Pest **820 testes / 17.497
  assercoes**.
- `npm run test`: Vitest verde (68 arquivos).
- `npm run types:check`: limpo.
- `php artisan route:list --path=badges`: somente
  `GET api/badges/static/{label}/{message}.{ext}` (nome `badges.static`, sem
  middleware de autenticacao).

## Notas

- O preview e o servidor usam o mesmo `styles.json`, `font-metrics.json`,
  `colors.json` e as mesmas fixtures; qualquer mudanca no renderer exige mudar
  PHP, TS e fixtures juntos.
- O texto do badge aparece na URL e, por natureza, no access log do proxy; a
  regra de nao registrar conteudo e do aplicativo.
