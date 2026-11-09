# Relatório — Colors Fase 2: Color Palette Generator

- Data: 2026-11-09
- Branch: `feature/colors-fase-2`
- Plano: `docs/claude-code/plans/feature_colors-fase-2/20261109_feature_colors-fase-2_colors_fase2_palette_generator.md`
- Plano da sessão (grill): `docs/plans/feature_colors-fase-2/20261109_palette_generator_plansfacts.md`
- Survey: `docs/survey/20261109_palette_generator_surveyfacts.md`
- ADR nova: `docs/adr/ADR-120-colors-algoritmo-escala.md`

## Contexto

A F2 entrega o Color Palette Generator em `/{locale}/tools/colors/palette-generator`: uma cor base vira uma escala determinística de 11 tons (`50–950`) em OKLCH, com contraste por tom, identificação da base, valores convertidos, cópia por tom/escala e exportação em CSS, SCSS, JSON, Tailwind v3 e Tailwind v4. Tudo roda no navegador; nada é persistido e nenhuma requisição é feita ao servidor.

Antes de começar a F2, a lacuna da F1 (sliders RGB/RGBA e HSL/HSLA) foi fechada e a superfície do picker foi extraída para reuso — registrado no relatório `20261109_feature_colors-fase-1_colors_fase1_lacuna_canais.md`.

## O que foi feito

### 1. Núcleo da escala (`resources/js/lib/color/scale.ts`)

- `PALETTE_TONES`, `PALETTE_ALGORITHM_VERSION = 1`, `nearestToneFor()` e `buildPaletteScale()`; exportado pelo barril `@/lib/color` (ADR-117).
- Escada de luminosidade e fatores de croma literais, conforme a ADR-120; croma relativo à base e normalizado pelo tom mais próximo; acromático zera croma e matiz; alpha herdado nos 11 tons.
- Base nunca alterada; `baseIsTone500` por chave canônica; `clippedTones` lista o que o gamut mapping reduziu (preservando L e H).
- Contraste por polaridade de texto (`contrastReport(tone, WHITE).black` e `contrastReport(tone, BLACK).white`), coerente com alpha.
- Puro: sem Vue, DOM, i18n, datas ou aleatoriedade; floats preservados até a formatação.

### 2. Exportadores (`resources/js/tools/palette-generator/lib/exporters.ts`)

- `sanitizePaletteName()` (NFD sem diacríticos, minúsculas, `-`, corte em 32, fallback `primary`).
- `buildPaletteExport()` / `buildAllExports()` para os cinco formatos, sempre na ordem `PALETTE_TONES`, terminando com quebra de linha.
- HEX quando `a === 1`; `rgba()` (até 3 casas) em CSS/SCSS/Tailwind v3 e `#rrggbbaa` em JSON/Tailwind v4.
- Arquivos e MIMEs: `{slug}.css`, `{slug}.scss`, `{slug}.json`, `tailwind.{slug}.config.js`, `{slug}.tailwind.css`.

### 3. Registro, i18n e SEO

- `manifest.json` (`slug: palette-generator`, categoria/grupo `colors`, ícone `swatch-book`, ordem 110) e `SwatchBook` no mapa de ícones.
- Listas de registro atualizadas: `tools-registry.test.ts` (MVP) e `ToolsRegistryTest.php` (`mvpToolSlugs`, lista de `available`, `findForUrl` agrupado/plano, `routeFor`).
- `ToolPageTest` cobre 200 na URL agrupada e 404 na plana/outro grupo; `SitemapTest` inclui a URL canônica; `SeoMetaTest` cobre a tool por ser `available`.
- Bloco `tools.palette-generator.*` completo nos cinco idiomas (`en`, `es`, `fr`, `pt_BR`, `pt_PT`), com interpolações nomeadas; paridade verde.
- `php artisan seo:og-image`: criado `public/og/palette-generator.png`; `public/og-default.png` mudou porque lista os nomes das tools; nenhum outro PNG alterado (conferido com `git status`).

### 4. Interface

Arquivos em `resources/js/tools/palette-generator/`:

| Arquivo | Papel |
| --- | --- |
| `Tool.vue` | estado de cor (`#6366f1` inicial), escala derivada, estados vazio/inválido, feedback em `role="status"` |
| `components/BaseColorRow.vue` | swatch, HEX/HEX8, cópia, formatos convertidos, selo e nota do tom mais próximo, nota de alpha |
| `components/ToneScale.vue` | grade dos 11 tons, copiar escala, versão do algoritmo, resumo de cortes |
| `components/ToneCard.vue` | tom, HEX, selo de mais próximo/corte, contraste preto/branco com razão e AA/AAA (texto para leitor de tela) |
| `components/ExportPanel.vue` | nome + slug sanitizado, 5 abas, copiar e baixar |
| `components/EmptyPalette.vue` | estado vazio com “restaurar exemplo” |

- Reuso: `components/colors/ColorPickerControls.vue` e `FormatsList.vue` da superfície compartilhada; nenhuma duplicação de picker.
- Estados: limpar → vazio com restauração; rascunho inválido mantém a escala anterior e mostra `state.stale`; cópia confirmada por 2500 ms no rodapé.
- Sem `localStorage` e sem chamadas ao servidor.

### 5. Documentação

- `docs/adr/ADR-120-colors-algoritmo-escala.md` (Aceito): tabela literal, normalização, acromático, alpha, gamut, base intacta, versão; consequências e alternativas rejeitadas.
- `docs/colors/palette-generator.md`: estado da F2, entrada, regras da escala e 8 critérios de aceite.
- `docs/colors/export-formats.md`: shapes da F2, regra HEX/HEX8/`rgba()`, tabela de arquivos/MIME; DTCG e Style Dictionary permanecem fora (F5).
- `docs/colors/README.md`: Fases 1 e 2 implementadas; faixa de ADRs até 120; ADR-120 no índice.
- `docs/colors/glossario.md`: escala determinística, tom mais próximo, fator de croma relativo, base intacta, tom cortado, JSON autodescritivo.
- `docs/colors/html-color-picker.md` já registrava os sliders de canais (F1.1); sem alteração.

## Testes

| Suíte | Resultado |
| --- | --- |
| `color-scale.test.ts` | determinismo, ordem, L decrescente, base intacta, nearest 600 para `#6366f1`, branco/preto, cinza acromático, alpha 0.5, clipping do vermelho, ponto fixo, contraste |
| `palette-exporters.test.ts` | 18 testes: sanitização, shapes exatos, ordem, alpha/HEX8, nomes/MIME, determinismo |
| `palette-generator.test.ts` | 8 testes de montagem: 11 cartões, base e nota do 600, versão, contraste, cópia por tom/escala, vazio/restaurar, inválido mantém escala, abas + copiar/baixar, slug, alpha no seletor nativo |
| Suítes Colors + registro | 82/82 |

Um defeito real foi encontrado pelos testes durante a Parte 3: o Tailwind v4 estava exportando alpha como `rgba()` em vez de HEX8. O formatador de valores por tom foi corrigido — a especificação ficou como planejado.

## Portões finais

| Portão | Resultado |
| --- | --- |
| `npx vitest run` (suítes novas/afetadas) | 82/82 |
| `npx vitest run i18n-messages.test.ts` (isolado) | 1/1 |
| `php artisan test` filtrado (Tools/SEO/traduções) | 54 / 14.945 asserções |
| `npm test` | 91 arquivos, 1147 testes |
| `composer test` | Pint ok, PHPStan 0; Pest 1471 / 25.478 asserções (1 depreciação de vendor) |
| `npm run types:check` | sem erros |
| `npm run lint:check` | 0 erros (1182 avisos pré-existentes) |
| `npm run build` | ok |
| `php artisan seo:og-image` | só `palette-generator.png` novo e `og-default.png` alterado |

## Pendências

1. **Conferência manual no navegador** (não executada nesta sessão): abrir `/{locale}/tools/colors/palette-generator` nos 5 idiomas; conferir 11 tons, base ≠ 500 intacta, alpha 0.5 nos 11 tons, contrastes, cópia por tom/escala, as 5 exportações e o download, vazio/restaurar, texto inválido, tema claro/escuro, teclado e larguras 375/768/1024/1280 px; conferir a OG e o 404 da URL plana.
2. `format:check` continua vermelho pré-existente (~175 arquivos); não foi reformatado.
3. **Nada foi commitado ou enviado** — o usuário aprova cada commit/PR pelo gitpr.
