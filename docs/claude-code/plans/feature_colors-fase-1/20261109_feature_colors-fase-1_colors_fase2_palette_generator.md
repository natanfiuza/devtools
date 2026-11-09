# Colors — Fase 2: Color Palette Generator (fechando a lacuna da F1)

- Data: 2026-11-09
- Branch: `feature/colors-fase-1`
- Survey: `docs/survey/20261109_palette_generator_surveyfacts.md`

## Context

A Fase 1 foi verificada: gates verdes (111 testes Vitest; 54 testes Pest com 14.562 asserções) e picker, núcleo `@/lib/color`, catálogo e SEO implementados. Há uma lacuna funcional: `docs/colors/html-color-picker.md:13` exige sliders RGB/RGBA e HSL/HSLA, mas o picker só tem área 2D, matiz e alpha. O grill decidiu corrigir isso antes da F2.

A F2 (`docs/colors/PROMPT-Fase2-gerador_paletas.md`; `SPEC-COLORS.md` §5 e §16) entrega o Palette Generator em `/{locale}/tools/colors/palette-generator`: escala determinística de 11 tons (50–950), modelo explícito de L/C/H/alpha/gamut, contraste por tom, identificação da base e valores convertidos, cópia por cor/escala e exportação CSS, SCSS, JSON, Tailwind v3/v4, versionada por algoritmo. Tudo no navegador; sem persistência, harmonia, importação de imagem/URL, DTCG/Style Dictionary ou protótipo.

Branch: continuar em `feature/colors-fase-1` (a F1 não foi commitada; F1 + F2 no mesmo PR). Sem commit/push.

Fora de escopo: persistência/conta, harmonias, tons variáveis (só extração de imagem), importação, W3C DTCG, Style Dictionary, protótipos, OG dinâmica, social/moderação, Figma API.

## Decisões fechadas

| Tema | Decisão |
| --- | --- |
| Escada | 11 tons fixos em OKLCH, L e fator de croma literais |
| Croma | relativo à base, normalizado no tom mais próximo |
| Alpha | preservado nos 11 tons; contraste compõe por opção de texto |
| Base ≠ 500 | base nunca alterada; selo no tom mais próximo + nota neutra |
| JSON | autodescritivo (`name`, `algorithmVersion`, `colors`) |
| Estado inicial | `#6366f1`; limpar → estado vazio com “restaurar exemplo” |
| Reuso | extrair superfície para `components/colors` + i18n `tools.shared.color_picker.*` |
| Persistência | nenhuma |

## Parte 0 — Reuso compartilhado

Mover (atualizando imports/testes):

| De | Para |
| --- | --- |
| `tools/html-color-picker/components/ColorArea.vue` | `resources/js/components/colors/ColorArea.vue` |
| `HueSlider.vue`, `AlphaSlider.vue`, `ColorInputField.vue` | `resources/js/components/colors/` |
| `SwatchButton.vue`, `FormatsList.vue` | `resources/js/components/colors/` |
| `lib/style.ts`, `lib/applyHex.ts` | `resources/js/components/colors/lib/` |
| `lib/useColorState.ts` | `resources/js/composables/useColorState.ts` |
| — | novo `components/colors/ChannelSliders.vue` |
| — | novo `components/colors/ColorPickerControls.vue` |

- `hueBackground()` vai de `useColorState.ts` para `components/colors/lib/style.ts`.
- `ColorPickerControls.vue`: prop `state: ColorState`; swatch + HEX8 + `input[type=color]` (alpha preservado via `withCurrentAlpha`), `ColorInputField`, `ColorArea`, `HueSlider`, `AlphaSlider`, `ChannelSliders`; sem EyeDropper/formatos; passa `null` no lugar do erro `empty`.
- Ficam no picker: `Tool.vue`, `ColorPreview.vue`, `ContrastPanel`, `ShadesTintsPanel`, `HistoryPanel`, `FavoritesPanel`, `ImageSampler`, `storage.ts`, `eyedropper.ts`, `imageSampler.ts`.
- i18n: novo `tools.shared.color_picker.*` nos 5 idiomas; remover de `tools.html-color-picker` as folhas `area.*`, `hue.label`, `alpha.label`, `color_input.*`, `preview.native`, `formats.*`. O picker mantém nome/descrição/intro/keywords, `preview.alpha|eyedropper|eyedropper_image|gamut`, `image.*`, `contrast.*`, `shades`, `tints`, `history`, `favorites`, `tabs`.
- Atualizar imports em `html-color-picker/Tool.vue`, `ContrastPanel`, `ShadesTintsPanel`, `HistoryPanel`, `FavoritesPanel` e `html-color-picker.test.ts`.

Critério: `npm run types:check` verde, testes do picker/color verdes, `grep` sem paths/chaves antigos.

## Parte 1 — Fechar a F1: sliders de canais

Em `resources/js/composables/useColorState.ts`:

```ts
export type RgbChannel = 'r' | 'g' | 'b';
export type HslChannel = 'h' | 's' | 'l';

hsl: ComputedRef<Hsl>;
setRgbChannel(channel: RgbChannel, value: number): void;
setHslChannel(channel: HslChannel, value: number): void;
```

- `hsl` = `rgbToHsl(rgba)` com `h` do matiz âncora.
- `setRgbChannel`: clamp 0..255, alpha intacto, `adoptHue`, `clearGamut`, `syncText`.
- `setHslChannel('h')`: grava a âncora e reconstrói com `hslToRgb` usando `s`/`l` atuais; não chama `adoptHue`.
- `setHslChannel('s'|'l')`: clamp 0..100, usa `hsl.value`, mantém alpha; não toca a âncora.

`ChannelSliders.vue`: props `{ rgba, hsl }`; emits `set-rgb`, `set-hsl`, `finalize`; `Tabs` RGB/HSL com 3 linhas `label + range + number`; `finalize` em `change`/`blur`/`Enter`; alpha continua no `AlphaSlider`; `data-test` `channels-tabs`, `channel-tab-*`, `channel-slider-*`, `channel-value-*`; inserir depois de `AlphaSlider`.

## Parte 2 — Algoritmo da escala

Novo `resources/js/lib/color/scale.ts`, reexportado por `lib/color/index.ts`. Puro, determinístico, sem Vue/DOM/i18n/random/data/locale.

```ts
export const PALETTE_TONES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const;
export type PaletteTone = (typeof PALETTE_TONES)[number];
export const PALETTE_ALGORITHM_VERSION = 1;

export interface PaletteToneResult {
    tone: PaletteTone;
    rgba: Rgba;
    oklch: Oklch;
    hex: string;
    clipped: boolean;
    contrast: { black: ContrastVerdict; white: ContrastVerdict };
}

export interface PaletteScale {
    base: Rgba;
    baseOklch: Oklch;
    tones: PaletteToneResult[];
    nearestTone: PaletteTone;
    baseIsTone500: boolean;
    clippedTones: PaletteTone[];
    algorithmVersion: number;
}

export function nearestToneFor(lightness: number): PaletteTone;
export function buildPaletteScale(base: Rgba): PaletteScale;
```

| Tom | L | fator de C |
| ---: | ---: | ---: |
| 50 | 0.97 | 0.18 |
| 100 | 0.94 | 0.30 |
| 200 | 0.88 | 0.50 |
| 300 | 0.81 | 0.72 |
| 400 | 0.74 | 0.88 |
| 500 | 0.66 | 1.00 |
| 600 | 0.58 | 0.92 |
| 700 | 0.50 | 0.78 |
| 800 | 0.42 | 0.62 |
| 900 | 0.34 | 0.46 |
| 950 | 0.22 | 0.30 |

```
baseOklch = rgbToOklch(base)
nearest   = nearestToneFor(baseOklch.l)   // menor |L - L_base|; empate -> tom mais claro
c(t)      = baseOklch.c * fator[t] / fator[nearest]
mapped(t) = mapOklchToSrgb({ l: L[t], c: c(t), h: baseOklch.h })
rgba(t)   = { ...mapped(t).rgb, a: base.a }
```

- Acromático (`c < 1e-4`): `c = 0`, `h = 0`.
- Alpha herdado; nada é composto na geração.
- Contraste: preto = `contrastReport(tone, WHITE).black`; branco = `contrastReport(tone, BLACK).white`.
- `baseIsTone500 = canonicalKey(base) === canonicalKey(tones[500].rgba)`; base nunca entra nos exports.
- Arredondamento só em `format.ts`/exporters.

## Parte 3 — Exportadores

`resources/js/tools/palette-generator/lib/exporters.ts`:

```ts
export type PaletteExportFormat = 'css' | 'scss' | 'json' | 'tailwind-v3' | 'tailwind-v4';
export interface PaletteExportFile { format; filename; mime; contents }
export function sanitizePaletteName(raw: string): string;
export function buildPaletteExport(scale, format, rawName): PaletteExportFile;
export function buildAllExports(scale, rawName): PaletteExportFile[];
```

- Sanitização: trim, minúsculas, NFD + remoção de `\p{M}`, `[^a-z0-9]+` → `-`, colapsa/apara, corta em 32, fallback `primary`.
- Alpha: `a === 1` → `#rrggbb`; `a < 1` → CSS/SCSS/TW3 `rgba(r, g, b, a)` e JSON/TW4 `#rrggbbaa`.
- CSS `{name}.css`; SCSS `{name}.scss`; JSON `{name}.json` autodescritivo; Tailwind v3 `tailwind.{name}.config.js`; Tailwind v4 `{name}.tailwind.css`.
- Ordem `PALETTE_TONES`, quebra de linha final, download por `downloadText` de `@/lib/download`.

## Parte 4 — Tool, manifest, i18n e SEO

Arquivos: `resources/js/tools/palette-generator/{manifest.json,Tool.vue,components/{BaseColorRow,ToneScale,ToneCard,ExportPanel,EmptyPalette}.vue,lib/exporters.ts}`.

- Manifest: `available`, `category: colors`, `group: colors`, `icon: swatch-book`, `order: 110`, keywords. `SwatchBook` existe em `@lucide/vue`; adicionar a `tools/icons.ts`.
- `Tool.vue`: `DEFAULT_BASE = #6366f1`; `useColorState` sem persistência; `scale` computado; `isEmpty`; layout `lg:grid-cols-[360px_minmax(0,1fr)]` com `aside` sticky (`ColorPickerControls`), coluna direita com `EmptyPalette` ou `BaseColorRow + ToneScale + ExportPanel`; feedback `role=status`.
- `BaseColorRow`: swatch, HEX/HEX8, copiar, `FormatsList` (valores convertidos), selo base, nota do tom mais próximo, aviso “base não é 500”, nota de alpha; base não é um dos 11 cartões.
- `ToneScale`: título/descrição, copiar escala (lista `tone: hex`), grade `grid-cols-2 sm:grid-cols-3 lg:grid-cols-4`, versão do algoritmo e resumo de clipping.
- `ToneCard`: tom, swatch, selo “mais próximo”, clipping, HEX/HEX8, contraste preto/branco com razão `toFixed(2)`, `👍/👎`, selos AA/AAA, copiar.
- `ExportPanel`: nome (padrão `primary`), sanitização visível, 5 abas, `<pre tabindex="0">`, copiar/baixar por formato.
- Estados: vazio/restaurar; inválido mantém escala anterior + `state.stale`; cópia no rodapé.
- i18n `tools.palette-generator.*` nos 5 idiomas (base, scale, export, empty, state) com interpolações nomeadas.
- SEO: `php artisan seo:og-image`; versionar só `public/og/palette-generator.png`.
- Listas de registro: `tools-registry.test.ts`, `ToolsRegistryTest.php`, `ToolPageTest`, `SitemapTest`.

## Testes

- `color-channels.test.ts`, `color-scale.test.ts`, `palette-exporters.test.ts`, `palette-generator.test.ts`.
- Atualizar `html-color-picker.test.ts` (imports, fixture `tools.shared.color_picker.*`, canais).
- `home.test.ts`/`command-palette.test.ts` dinâmicos: rodar sem edição esperada.

## Documentação

ADR-120 (`docs/adr/ADR-120-colors-algoritmo-escala.md`), `palette-generator.md`, `html-color-picker.md`, `export-formats.md`, `README.md`, glossários; relatório em `docs/claude-code/reports/feature_colors-fase-1/20261109_feature_colors-fase-1_colors_fase2_palette_generator.md`.

## Verificação

```powershell
npx vitest run resources/js/__tests__/color-channels.test.ts resources/js/__tests__/color-scale.test.ts resources/js/__tests__/palette-exporters.test.ts resources/js/__tests__/palette-generator.test.ts resources/js/__tests__/html-color-picker.test.ts
php artisan test --filter="ToolsRegistryTest|ToolPageTest|SitemapTest|SeoMetaTest|StructuredDataTest|TranslationsParityTest"
npm run types:check
npm run lint:check
npm test
composer test
npm run build
php artisan seo:og-image
npx vitest run resources/js/__tests__/i18n-messages.test.ts
```

Manual: 5 idiomas; 11 tons; base ≠ 500 intacta; alpha 0.5; contraste; cópia; 5 exports/download; vazio/restaurar; inválido; tema claro/escuro; teclado; 375/768/1024/1280; OG; 404 na URL plana.

## Riscos

Mover componentes/i18n (types + grep + paridade); chave i18n ausente (teste de texto); âncora HSL nos extremos; clipping esperado no croma normalizado; tabela literal na ADR; grade sem 11 colunas; OG regrava PNGs (restaurar idênticos); `format:check` pré-existente vermelho; timeout do `i18n-messages` isolado; sem commit/push.

## Sequência (paradas)

0. Docs iniciais + README + baseline.
1. Extração compartilhada. Checkpoint A.
2. F1.1 canais. Checkpoint A.
3. `scale.ts` + testes.
4. Exportadores + testes. Checkpoint B.
5. Registro/i18n/SEO/OG.
6. UI + testes. Checkpoint C.
7. Docs finais, portões, manual, relatório. Checkpoint D.
