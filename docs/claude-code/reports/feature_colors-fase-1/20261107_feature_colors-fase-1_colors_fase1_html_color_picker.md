# Relatório — Colors Fase 1: grupo no catálogo + HTML Color Picker

- Data: 2026-11-07
- Branch: `feature/colors-fase-1`
- Plano: `docs/claude-code/plans/feature_colors-fase-1/20261107_feature_colors-fase-1_colors_fase1_plan.md`
- Survey: `docs/survey/20261107_colors_fase1_surveyfacts.md`

## Escopo entregue

Fase 1 do track Colors conforme `docs/colors/PROMPT-INICIAL-CLAUDE-CODE.md`:

- grupo Colors no catálogo, com URL aninhada canônica `/{locale}/tools/colors/html-color-picker`;
- HTML Color Picker completo, 100% no navegador, em cinco idiomas e tema dark-first;
- núcleo de cor próprio em TypeScript, reutilizável pela Fase 2;
- documentação de Colors reconciliada e 25 ADRs renumerados para 091–115.

Fora de escopo confirmado: Palette Generator, backend/banco, persistência de conta, social, moderação, OG dinâmico de paleta e landing `/tools/colors`.

## Documentação (Parte 0)

- 25 ADRs movidos de `docs/colors/adr/` para `docs/adr/` com o sufixo `colors` (`ADR-091-colors-espacos-de-cor.md` … `ADR-115-colors-export-paleta-imagem-layouts.md`) e referências cruzadas atualizadas.
- Novos ADRs: 116 (grupo de tools na URL), 117 (núcleo de cor e gamut), 118 (persistência local do picker), 119 (arquivamento de paleta).
- Correções registradas como “Emenda 2026-11-07” em vez de apagar a decisão original: escala 50–950 (5/7/9/11 só na extração de imagem), OG estática via `seo:og-image` em `public/og/<slug>.png`, histórico local 50 na F1, Free 25 paletas e pago sem limite funcional, arquivamento ≠ exclusão, exportação W3C DTCG, plano F1–F8, `ai_cache` singular, curtida anônima de primeira parte, threads de 2 níveis, URLs com `{locale}` e prompts F2–F8 nos novos números.
- `docs/colors/README.md` com índice e tabela de renumeração; glossário de Colors atualizado e `docs/glossario.md` ganhou “Grupo de tool”.

## Catálogo, rota e SEO (Parte 1)

- Manifest `resources/js/tools/html-color-picker/manifest.json`: `status: available`, `category: colors`, `group: colors`, ícone `palette`, `order: 100`.
- `ToolManifest.group`, `ToolGroupId` e `ToolCategoryId` com `colors`; entrada em `categories.ts` e `icons.ts`.
- Rota nomeada `tools.show.grouped` (`tools/{group}/{slug}`) em `routes/web.php`, entre as rotas fixas e `tools/{slug}`.
- `App\Support\Tools::findForUrl(slug, group)`, `groups()` e `routeFor()`; `ToolController` 404 para URL plano, grupo errado, tool plana pelo caminho agrupado e `/tools/colors`.
- `Seo`, `Sitemap` e `StructuredData` reconhecem a rota agrupada; canonical, hreflang, OG e JSON-LD continuam corretos.
- `registry.ts::toolPath(tool, localeSlug)` devolve `/tools/<group>/<slug>` quando há grupo; home, cards e command palette herdam sem mudança.
- OG estática gerada com `php artisan seo:og-image`: apenas `public/og/html-color-picker.png` é novo; `public/og-default.png` foi regenerado e restaurado por não ter mudado.

## Núcleo de cor (`resources/js/lib/color/`)

Sem Vue, DOM, i18n ou dependência nova:

| Arquivo | Conteúdo |
|---|---|
| `types.ts` | `Rgb`, `Rgba`, `Hsl`, `Hsv`, `Oklab`, `Oklch`, `ParsedColor`, `ColorFormat`, `ContrastVerdict` |
| `convert.ts` | sRGB⇄linear, RGB⇄HSL/HSV, RGB⇄OKLab (matrizes de Ottosson), OKLab⇄OKLCH |
| `named.ts` | 148 nomes CSS + `transparent`; nome mais próximo por distância em OKLab |
| `parse.ts` | HEX 3/4/6/8, `rgb()/rgba()`, `hsl()/hsla()` (vírgula e barra), `oklch()`, `oklab()`, nomes; erro tipado `empty/syntax/range/unknown_name` |
| `format.ts` | 9 formatos, arredondamento só na exibição, `canonicalKey` |
| `gamut.ts` | detecção e mapeamento para sRGB por busca binária de croma (24 iterações), L/H preservados |
| `contrast.ts` | composição source-over no fundo, luminância relativa, razão WCAG e classificação no valor cru |
| `mix.ts` | mistura em OKLab e 10 tons + 10 tintas com alpha preservado |

Decisões de comportamento:

- estado autoritativo é `Rgba`; OKLCH/OKLab fora do gamut é reduzido por croma e o valor digitado fica só como metadado de aviso (`sourceValue`);
- EPSILON de gamut `1e-3`, para que valores exibidos com 4 casas (ex.: `oklch(62.8% 0.2577 29.23)`) não disparem aviso falso;
- classificação AA/AAA usa o valor cru (4,4999 falha; 4,5 passa), enquanto a razão exibida tem 2 casas;
- chave canônica é `#rrggbbaa` ou `transparent` (alpha 0), então `#ff0000` e `#ff0000ff` são a mesma entrada.

## Persistência local (`lib/`)

- `storage.ts`: chaves versionadas `colors.picker.history.v1`, `colors.picker.favorites.v1` e `colors.picker.last.v1`; caps 50/50; dedupe pela chave canônica; validação de shape na leitura; `try/catch` em todo acesso e `browserStorage()` seguro no SSR.
- `useColorState.ts`: porta única de escrita, matiz âncora (cinza não perde a matiz), rascunho de texto separado da cor válida e `finalize()` apenas em pointerup/Enter/blur — o histórico não recebe cada quadro do arraste.
- `applyHex.ts`: HEX do seletor nativo preserva o alpha atual; conta-gotas e imagem aplicam cor opaca.
- `eyedropper.ts`: wrapper tipado de `EyeDropper`, ausência/cancelamento/falha → `null`.
- `imageSampler.ts`: matemática pura (encaixe, ponteiro→pixel, teclado) sem tocar canvas no import.

## Interface

- `Tool.vue`: grade `lg:grid-cols-[minmax(0,1fr)_360px]`, picker à esquerda, formatos abaixo, abas Contraste / Tons / Biblioteca à direita; feedback de cópia em `role="status"` com `aria-live="polite"`.
- Componentes: `ColorArea` (2D, ponteiro capturado, teclado ±1/±10 e ARIA), `HueSlider`, `AlphaSlider`, `ColorPreview` (swatch, input nativo, EyeDropper e aviso de gamut), `ColorInputField`, `FormatsList`, `ContrastPanel`, `ShadesTintsPanel`, `HistoryPanel`, `FavoritesPanel`, `SwatchButton`, `ImageSampler`.
- Contraste: fundo explícito branco/preto/hex, alpha composto antes da conta, rótulos `Texto preto”/“Texto branco (X:1 👍/👎)` com selos AA/AAA normal e grande e rationale em `sr-only`.
- Fallback do conta-gotas: quando `EyeDropper` não existe, o mesmo botão abre um Dialog com imagem local, lupa 8×, ponteiro e navegação por teclado (Enter aplica).
- Nenhum canvas é tocado no mount; nada do picker faz requisição ao servidor.

## i18n

- Bloco `tools.html-color-picker.*` completo nos cinco idiomas (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) e `tools.categories.colors` já existente.
- `TranslationsParityTest` e `i18n-messages.test.ts` verdes.
- Observação: a SPEC pede o texto literal “Black text (X:1 👍/👎)”. Em `en` ele aparece literalmente; nos demais idiomas a frase é traduzida (“Texto preto”, “Texte noir”, “Texto negro”), mantendo o formato, a razão e o emoji, porque a regra do projeto proíbe texto visível hardcoded. O emoji 👍/👎 continua sendo o sinal binário e os selos AA/AAA trazem o detalhe.

## Testes

Novos (Vitest, `resources/js/__tests__/`):

- `color-named`, `color-convert`, `color-parse`, `color-format`, `color-gamut`, `color-contrast`, `color-mix` — núcleo;
- `color-storage` — canonicalização, dedupe, caps 50, JSON corrompido, cota, SSR e última cor;
- `color-eyedropper` — ausente, stub válido, `AbortError`, falha genérica;
- `color-image-sampler` — encaixe sem ampliar, ponteiro→pixel, limites e teclado;
- `html-color-picker` — estado (texto inválido preserva a última cor, matiz âncora, normalização, gamut), `applyHex`, área 2D por teclado e montagem completa da Tool com “input nativo não reseta alpha”.

Ajustados: `home.test.ts` passa a comparar o href com `toolPath(tool, 'pt-br')` por causa da URL aninhada.

## Verificação executada

| Comando | Resultado |
|---|---|
| `npx vitest run resources/js/__tests__/color-*.test.ts resources/js/__tests__/html-color-picker.test.ts` | 109 testes, 11 arquivos, todos verdes |
| `php artisan test --filter='ToolsRegistryTest\|ToolPageTest\|SitemapTest\|SeoMetaTest\|StructuredDataTest\|TranslationsParityTest'` | 54 testes, 14.562 asserções, verdes |
| `npm run types:check` | Wayfinder regerado + `vue-tsc` sem erros |
| `composer test` (Pint + PHPStan + Pest) | verde: 1.471 testes, 25.095 asserções |
| `npm test` | 1.093/1.094 verdes; falha só o timeout de 5 s de `i18n-messages.test.ts` sob carga paralela |
| `npx vitest run resources/js/__tests__/i18n-messages.test.ts` (isolado) | verde |
| `npm run lint:check` | 0 erros (avisos pré-existentes de `vue/html-indent` em outros arquivos) |
| `npm run format:check` | **falha pré-existente**: 175 arquivos fora do Prettier, incluindo `Show.vue` e `json-viewer/Tool.vue`, por divergência entre `.prettierrc` e o estilo commitado; os arquivos novos seguem o estilo do repo e passam no ESLint |

## Pendências / revisão manual

- Abrir `/{locale}/tools/colors/html-color-picker` nos cinco idiomas e conferir textos, foco e tema claro/escuro.
- Testar o EyeDropper nativo no Chromium e o fallback por imagem no Firefox.
- Recarregar a página e confirmar histórico, favoritos e última cor.
- Confirmar visualmente `public/og/html-color-picker.png`.
- Decidir se o gate `format:check` será consertado em tarefa separada (reformatar 175 arquivos) ou se o `.prettierrc` volta a casar com o estilo atual.

## Próximo passo

Fase 2 (Palette Generator) fica para um novo ciclo; o núcleo `@/lib/color` já expõe conversões, gamut, contraste e mistura para reuso.

Nenhum commit ou push foi feito — o usuário aprova cada commit e PR pelo gitpr.
