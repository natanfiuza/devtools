# BadgeForge — Fase 2 (PNG, download, docs e fontes do badge)

- Data: 2026-11-02
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase2.md`
- Levantamentos: `docs/survey/20261102_badgeforge_fase2_surveyfacts.md`, `docs/plans/feature_badgeforge-fase-1/20261102_badgeforge_fase2_plansfacts.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-2.md`

## Objetivo

Entregar a F2 do SPEC-BADGEFORGE: PNG via Imagick, cache em disco,
`?download=1`, docs/ADR e, por decisão da sessão de grill, as fontes
empacotadas Fira Code / Libre Franklin / Space Grotesk com `?font=` e preview
em SVG inline.

## Resumo executivo

- O SVG da F1 permanece byte a byte igual (nenhuma fixture existente mudou).
- `?font=` troca a pilha e as métricas para três famílias cujos avanços são
  derivados dos próprios TTF (`badgeforge:font-metrics --check`).
- `.png` responde 200 `image/png`, 1× natural e transparente, com cap
  proporcional de 600 px, ETag/304 e cache content-addressed em
  `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png`.
- `?download=1` vale para SVG e PNG, com nome ASCII sanitizado e `filename*`
  RFC 5987; sem Imagick o PNG responde 503 `image_unavailable`.
- A UI ganhou seletor de fonte, seletor de formato e botão Baixar; o preview é
  SVG inline para a fonte web valer, e os snippets seguem o formato escolhido.
- Gates finais: Pint/PHPStan ok, Pest 850 testes, Vitest 888 testes,
  `types:check` limpo, `lint:check` 0 erros.

## Decisões (ADR-077)

Arquivo: `docs/adr/ADR-077-badgeforge-fonte-do-badge.md`.

1. Fontes empacotadas em `resources/badgeforge/fonts/<slug>/`, catálogo em
   `config/badgeforge.php`; métricas geradas dos TTF (normalização para 1000
   unidades/em; Fira Code tem `unitsPerEm=2000`).
2. `?font=` cosmético: desconhecido/vazio/tipo errado cai na pilha do estilo.
3. Preview em SVG inline (`v-html` de documento gerado e escapado pelo
   renderer), porque fonte da página não vale dentro de `<img>`.
4. PNG 1× natural, `png32` transparente, downscale Lanczos só acima de 600 px.
5. Cache em disco pelo sha256 do SVG, escrita atômica (tmp + rename);
   `badgeforge:prune` remove por mtime (48 h) com `--dry-run`.
6. Sem Imagick → 503 `image_unavailable`; log só da ausência da extensão.
7. Download com `Content-Disposition` sanitizado + RFC 5987.

## O que foi feito por etapa

### E1 — Fontes e métricas

- Copiadas as 6 TTF (Regular + Bold), `OFL.txt` e `README.txt` de
  `docs/fonts/` para `resources/badgeforge/fonts/{fira-code,libre-franklin,space-grotesk}/`.
  `docs/fonts/` continua fora do controle de versão.
- `config/badgeforge.php`: `fonts_path` e `fonts` com
  `{slug => [name, family, stack, regular, bold]}`.
- `App\Support\BadgeForge\TrueTypeFont`: parser `head`/`cmap` 4/`hmtx`.
- `App\Console\Commands\BadgeForgeFontMetrics`: gera `families.<slug>` e
  `family_bold_percent` para os mesmos 192 code points de `sans`; faz merge e
  tem `--check`.
- `resources/badgeforge/font-metrics.json`: três famílias novas, `sans` e
  `helvetica` preservados.
- `FontMetrics` (PHP) e `lib/metrics.ts` (TS): usam
  `family_bold_percent[family] ?? bold_percent`.
- Testes: `TrueTypeFontTest`, `FontMetricsTest`, guarda de drift.

### E2 — `?font=` no servidor e no espelho TS

- Novos `BadgeFont` e `BadgeFontCatalog` (busca case-insensitive; null para
  desconhecido).
- `BadgeSpec` carrega um objeto `BadgeFont` (slug/name/stack); `BadgeRequest`
  resolve o `?font=` e o default é a pilha do estilo.
- `StaticBadgeRenderer` usa `spec->font->stack`/`spec->font->slug`; a saída
  padrão não mudou.
- `BadgeForgeToolProps` expõe `fonts` na ordem da config; `tool-props.json`
  atualizado (igualdade exata preservada).
- Espelho TS: `types.ts` (`BadgeFont`, `props.fonts`), `lib/fonts.ts`
  (`DEFAULT_FONT`, `fontAsset`), `builder.ts`, `lib/render.ts`, `lib/url.ts`
  (`font=<slug>` só quando difere do padrão).
- Fixtures novas: `flat_font_fira_code` (103 px), `flat_font_libre_franklin`
  (91 px), `flat_font_space_grotesk` (93 px); `flat_basic` continua 98 px.
  Nenhum `expected/*.svg` existente mudou.
- Testes: Pest (`BadgeRequestTest`, `BadgeForgeToolPropsTest`,
  `BadgeFontCatalogTest`, `StaticBadgeRendererTest`) e Vitest
  (`badgeforge.test.ts`, `badgeforge-render.test.ts`).

### E3 — PNG, cache em disco e download

- `config/badgeforge.php`: bloco `png` com `max_width=600`,
  `cache_path=app/data/tools/badgeforge/static`, `prune_after_hours=48`.
- `PngRenderer` (não final, mockável): `isSupported()` e `render()` com
  `readImageBlob`, `png32` e resize Lanczos proporcional.
- `PngCache`: leitura por hash, escrita atômica, `directory()`/`path()`
  estáticos usados pela poda.
- `StaticBadgeController`: dispatch `svg|png`, 503 com `ApiError`, ETag do
  corpo servido, `Content-Disposition` quando `download=1`.
- `routes/api.php`: `where('ext', 'svg|png')`.
- `PruneBadgePngs`: `badgeforge:prune {--dry-run}`, inspirado em
  `PruneBase64Images`.
- Testes: `StaticBadgePngTest` (200/formato/dimensões, determinismo, cap 600,
  ETag/304, download SVG e PNG, acento via `filename*`, cache hit com
  `PngRenderer` mockado, 503, HEAD), `PruneBadgePngsTest`; o caso antigo de
  “PNG na F1 dá 404” virou “extensão não suportada” com `.gif`.

### E4 — UI

- `resources/css/app.css`: seis `@font-face` (400/700, `font-display: swap`,
  URLs para `resources/badgeforge/fonts/...`).
- `Tool.vue`: seletor de fonte na seção Avançado, seletor SVG/PNG junto de
  Incorporar, botão Baixar (`?download=1`), snippets com dimensões do PNG
  reduzidas pelo cap e preview em SVG inline.
- `lib/url.ts`: `BuildBadgeUrlOptions` (`format`, `download`).
- i18n nos 5 idiomas: `fields.font`, `fields.format`, `fonts.default`,
  `formats.svg|png`, `actions.download`.
- `tool-props.json` e limites: `png.maxWidth`.
- Testes: `badgeforge-component.test.ts` (fonte muda `font-family` e URL,
  formato muda extensão, Baixar leva `download=1`, preview inline com
  `aria-label`), `badgeforge.test.ts` (URL e preview).
- Observação: o repositório mantém `.vue` com template em 2 espaços (109
  arquivos não passam pelo Prettier). Um `prettier --write` acidental foi
  revertido e as edições reaplicadas no estilo do próprio arquivo; os `.ts`
  de teste ficaram no padrão do Prettier, como os demais TS.

### E5 — Documentação

- `docs/adr/ADR-077-badgeforge-fonte-do-badge.md` (novo).
- `docs/adr/ADR-073-badgeforge-formatos.md`: nota de que o 404 de `.png` era
  da F1.
- `docs/badgeforge/API.md`: `.png`, `font`, `download`, cap 600, cache, 503 e
  nomes de download.
- `docs/badgeforge/OPENAPI.md`: versão 1.2.0, `ext: [svg, png]`, `font`,
  `download`, respostas `image/svg+xml`/`image/png`, 503.
- `docs/SPEC-BADGEFORGE.md`: §3, §5, §6, §7 e §16.
- `docs/glossario.md`: “Fonte do badge”, “Cache estático do PNG” e “Download
  do badge”; “Badge” atualizado.
- `docs/acceptance/BADGEFORGE-FASE-2.md` (novo).
- `docs/guides/badgeforge-render.md` (novo): requisitos, instalação das TTF,
  `fc-cache`, verificação, métricas, poda e diagnóstico.
- `CLAUDE.md`: fase atual F2 (entregue) e ADRs 062–077.

## Gates e testes

| Comando | Resultado |
|---|---|
| `composer test` | Pint ok; PHPStan 0 erros; Pest **850 testes / 17.630 asserções** |
| `npm run test` | Vitest **888 testes / 68 arquivos** |
| `npm run types:check` | limpo |
| `npm run lint:check` | 0 erros (avisos de indentação pré-existentes em `.vue`) |
| `php artisan badgeforge:font-metrics --check` | `font-metrics.json esta em dia com os TTF.` |
| `php artisan route:list --path=badges` | uma rota: `GET|HEAD api/badges/static/{label}/{message}.{ext}` |

Suítes novas/afetadas: `StaticBadgePngTest`, `PruneBadgePngsTest`,
`BadgeFontCatalogTest`, `TrueTypeFontTest`, `FontMetricsTest`,
`BadgeRequestTest`, `BadgeForgeToolPropsTest`, `StaticBadgeRendererTest`,
`StaticBadgeEndpointTest`, `BadgeForgePageTest`,
`badgeforge.test.ts`, `badgeforge-render.test.ts`,
`badgeforge-component.test.ts`.

O aviso `Invalid UTF-8 string passed to pango_layout_set_text()` do librsvg
aparece no Pest; é benigno, não contém conteúdo do badge e está documentado no
guia.

## Smoke local (`php artisan serve --port=8123`)

| Verificação | Resultado |
|---|---|
| `?font=space-grotesk` | 200, 93 px, `font-family="Space Grotesk,DejaVu Sans,sans-serif"` |
| `.png` | 200, `image/png`, 98×20 RGBA |
| badge 100+100 chars | SVG 1.404 px; PNG 600×9 (cap proporcional) |
| `?download=1` SVG | `attachment; filename="build-passing.svg"; filename*=UTF-8''build-passing.svg` |
| caminho acentuado (`vers%C3%A3o/caf%C3%A9`) | `filename="versao-cafe.svg"` + `filename*=UTF-8''vers%C3%A3o-caf%C3%A9.svg` |
| segunda requisição do mesmo PNG | mesmo mtime/tamanho, 3 arquivos antes e depois |
| `/{locale}/tools/badgeforge` | 200, página contém a ação “Baixar” |

Os PNGs gerados no smoke foram apagados do cache ao fim.

## Desvios do plano

- `BadgeSpec->font` é um objeto (`slug`, `name`, `stack`) em vez de dois
  campos soltos; `toolProps.fonts` inclui `stack` para o TS resolver a mesma
  pilha.
- A URL do PNG é `/static/{label}/{message}.png`; a linha `.svg.png` do plano
  era engano (com `{message}.{ext}`, `.svg` entraria no texto).
- O badge longo tem largura natural acima de 600 (1.404 px no smoke), então o
  teste do cap deriva a altura esperada da largura natural do SVG.
- O preview inline usa `v-html` com `eslint-disable` local justificado: o
  documento é gerado e escapado pelo renderer.

## Riscos e pendências operacionais

1. **Deploy**: instalar `php-<versao>-imagick` e as 6 TTF no fontconfig do
   usuário do PHP-FPM, com `fc-cache -f`. Sem isso, o PNG responde 503 (extensão
   ausente) ou sai em DejaVu (fonte ausente). Guia:
   `docs/guides/badgeforge-render.md`.
2. **Agendamento**: cadastrar `badgeforge:prune` em `task_schedules` (sugestão
   diária; exemplo de INSERT no guia). O cache é sem teto por natureza.
3. **Bytes do PNG variam por versão de ImageMagick**: o ETag acompanha; o cache
   é por máquina.
4. **Paridade**: qualquer mudança no renderer exige PHP + TS + fixtures juntos.
5. Nada foi commitado ou publicado; o tree segue para revisão do usuário.

## Arquivos

Novos (principais): `app/Support/BadgeForge/{BadgeFont,BadgeFontCatalog,PngCache,PngRenderer,TrueTypeFont}.php`,
`app/Console/Commands/{BadgeForgeFontMetrics,PruneBadgePngs}.php`,
`resources/js/tools/badgeforge/lib/fonts.ts`,
`tests/Feature/BadgeForge/StaticBadgePngTest.php`,
`tests/Unit/BadgeForge/{BadgeFontCatalogTest,PruneBadgePngsTest,TrueTypeFontTest}.php`,
3 fixtures `expected/flat_font_*.svg`, `resources/badgeforge/fonts/`,
`docs/adr/ADR-077-badgeforge-fonte-do-badge.md`,
`docs/acceptance/BADGEFORGE-FASE-2.md`,
`docs/guides/badgeforge-render.md`.

Modificados (principais): `config/badgeforge.php`, controller, `BadgeRequest`,
`BadgeSpec`, `StaticBadgeRenderer`, `FontMetrics`, `BadgeForgeToolProps`,
`routes/api.php`, `resources/css/app.css`, `Tool.vue` e libs TS do BadgeForge,
5 arquivos `lang/*/tools.php`, fixtures (`cases.json`, `tool-props.json`),
testes citados, `CLAUDE.md`, `docs/SPEC-BADGEFORGE.md`, `docs/glossario.md`,
`docs/badgeforge/{API,OPENAPI}.md`, `docs/adr/ADR-073-...md`.
