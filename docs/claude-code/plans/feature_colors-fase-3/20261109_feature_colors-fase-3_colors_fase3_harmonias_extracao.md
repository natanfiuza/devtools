# Plano — Colors Fase 3: harmonias e extração de paletas

- Data: 2026-11-09
- Branch: `feature/colors-fase-3`
- Prompt: `docs/colors/PROMPT-Fase3-harmonias_imagens.md`
- Status: aprovado pelo usuário

## Context

A F2 entregou o Color Palette Generator (`/{locale}/tools/colors/palette-generator`) com escala determinística de 11 tons, contraste e exportações CSS/SCSS/JSON/Tailwind v3/v4; está commitada na `feature/colors-fase-2`. A F3 (`docs/colors/PROMPT-Fase3-harmonias_imagens.md`, `SPEC-COLORS.md` §5/§9/§16) acrescenta ao gerador as **harmonias** (complementar, análoga, triádica, tetrádica, monocromática — métodos HSL e OKLCH) e a **extração de cores dominantes de imagem** (arquivo local ou URL HTTPS, 5/7/9/11 cores), tudo no navegador, sem proxy e sem persistência.

**Fora de escopo:** CRUD de paletas, protótipos, social, tokens DTCG/Style Dictionary, presets, favoritos, proxy CORS no backend.

## Decisões fechadas com o usuário

| Tema | Decisão |
|---|---|
| Superfície | Abas de página no `palette-generator`: **Escala** (F2), **Harmonias**, **Extração**. Picker/base compartilhados na coluna esquerda; clicar numa cor define a base. Sem rota/manifest/SEO novos. |
| Harmonias | Auxiliares: chips com HEX/cópia e “usar como base”; **não** entram nos cinco exports da F2 |
| Modelo das harmonias | Base incluída. Complementar 2 (0°, 180°); análoga 3 (−30°, 0°, +30°); triádica 3 (0°, +120°, +240°); tetrádica retangular 4 (0°, +60°, +180°, +240°); monocromática 5 (mesma matiz, L relativa à base). Padrão **OKLCH**, alternável para **HSL** |
| Monocromática | OKLCH: mantém C/H, L = base ± 0.14 e ± 0.28, presa em 0.08–0.99. HSL: mantém H/S, L = base ± 14 e ± 28 pontos, presa em 0–100 |
| Extração | `color-thief@3.5.0` (MIT, 2026-08-02, MMCQ com `colorSpace: 'oklch'`), encapsulada e executada num worker; ADR-099 emendada (median cut/OKLab → MMCQ OKLCH/RGB) |
| Transparência | Ignorar pixels com alpha < 0,5; nunca compor sobre fundo |
| Poucas cores | Se as cores distintas forem menos que o pedido, devolver as existentes, avisar e **não inventar** |
| Ordenação | Peso decrescente + percentual de pixels por cor |
| Preservação | Paleta extraída só em memória da página (modelo pronto para a F4); sem localStorage/sessionStorage |
| Limites | `config/tools.php` → `limits.colors_image` (defaults + `TOOLS_COLORS_IMAGE_*`), entregues por `ToolProps::for('palette-generator')` |

## Parte 1 — Núcleo de harmonias (`resources/js/lib/color/harmony.ts`)

Puro (sem Vue/DOM/i18n), exportado pelo barril `@/lib/color` (ADR-117):

```ts
export type HarmonyKind = 'complementary' | 'analogous' | 'triadic' | 'tetradic' | 'monochromatic';
export type HarmonyMethod = 'oklch' | 'hsl';
export const HARMONY_KINDS: readonly HarmonyKind[];
export const HARMONY_METHODS: readonly HarmonyMethod[];
export const HARMONY_HUE_OFFSETS: Record<Exclude<HarmonyKind,'monochromatic'>, number[]>;
export interface HarmonyColor { rgba: Rgba; hex: string; oklch: Oklch; clipped: boolean; isBase: boolean }
export interface HarmonySet { kind; method; base: Rgba; colors: HarmonyColor[] }
export function buildHarmony(base: Rgba, kind: HarmonyKind, method: HarmonyMethod): HarmonySet;
```

- OKLCH: `rgbToOklch`, gira `h`, mantém `L`/`C`, mapeia com `mapOklchToSrgb` (L/H preservados, `clipped` quando corta).
- HSL: `rgbToHsl`, gira `h`, mantém `s`/`l`; mono varia `l` (clamp 0–100) e reconstrói com `hslToRgb`.
- Monocromática: offsets relativos por método, base no centro; clamp nos extremos; ordem do mais escuro ao mais claro.
- Alpha herdado da base nos dois métodos; `hex` usa `formatHex`/`formatHex8`.
- **Dedupe** por `canonicalKey` preservando a primeira ocorrência (base): cinzas fazem as rotações de matiz colapsarem; no mono, o clamp pode repetir o limite. `HarmonySet` expõe só as cores únicas; a UI avisa quando `colors.length` é menor que o esperado (`achromatic_note`).
- Determinismo: mesma base → mesmas cores.

Teste `resources/js/__tests__/color-harmony.test.ts`: cada tipo/método com ângulos exatos; base presente e marcada; alpha fracionário; clipping no vermelho saturado; cinza → 1 cor + nota; mono clamp/repetição; HSL vs OKLCH diferem; determinismo.

## Parte 2 — Extração de imagem

### 2.1 Limites (PHP)

`config/tools.php`, bloco `limits.colors_image`:

| chave | env | default |
|---|---|---|
| `max_bytes` | `TOOLS_COLORS_IMAGE_MAX_BYTES` | 10 MiB |
| `max_side` | `TOOLS_COLORS_IMAGE_MAX_SIDE` | 4096 |
| `max_pixels` | `TOOLS_COLORS_IMAGE_MAX_PIXELS` | 16 000 000 |
| `analysis_side` | `TOOLS_COLORS_IMAGE_ANALYSIS_SIDE` | 512 |
| `fetch_timeout_ms` | `TOOLS_COLORS_IMAGE_FETCH_TIMEOUT_MS` | 8000 |

- Novo `app/Support/Colors/ColorsToolProps.php` devolve `(object) ['image' => ['maxBytes','maxSide','maxPixels','analysisSide','fetchTimeoutMs']]` (camelCase), registrado em `ToolProps::for('palette-generator')`; `Show.vue` já espalha `toolProps` como props.
- `Tool.vue` declara `image: ImageLimits`; o teste de montagem passa um fixture.
- Pest `tests/Unit/Colors/ColorsToolPropsTest.php` (defaults e `Config::set`) e uma asserção em `ToolPageTest` de que a página do gerador recebe `toolProps.image` e a do picker recebe `{}`.

### 2.2 Módulos do cliente (`resources/js/tools/palette-generator/lib/`)

| Arquivo | Papel |
|---|---|
| `extract.ts` | Tipos `ExtractionRequest/ExtractionResult/ExtractedColor`; wrapper de `color-thief`; pesos; dedupe; poucas cores |
| `extract.worker.ts` | Executa `extractPalette` no worker (`serve` de `@/lib/worker/serve`) |
| `extract.workerHost.ts` | `new Worker(new URL('./extract.worker.ts', import.meta.url), { type: 'module' })`, fallback `null` |
| `extractionClient.ts` | `createWorkerClient` tipado, com `cancel()`/ids; descarta resultado obsoleto |
| `decodeImage.ts` | `createImageBitmap` + validação de bytes/dimensões/pixels + downscale para `analysisSide` + `ImageData` |
| `fetchImage.ts` | URL HTTPS, `fetch` CORS, timeout, content-type, contagem de bytes |

**`extract.ts`** (`color-thief` só aqui — atualizar versão fica localizado):

- Pré-checagem de alpha: se nenhum pixel tem `a >= 0.5`, retorna `error: 'empty_image'` (não delega o fallback opaco da lib).
- Chama `getPalette({ data, width, height }, { colorCount: count, colorSpace: 'oklch', quality: 1, alphaThreshold: 0.5, ignoreWhite: false, minSaturation: 0 })`; `quality: 1` para determinismo.
- **Pesos próprios**: atribui cada pixel opaco à cor mais próxima em OKLab e conta; percentual = peso/total (soma 1). Ordena por peso desc. Não depende da população interna da lib.
- Dedupe por `canonicalKey`; `sufficient = colors.length >= count`; se `uniqueColors <= count`, devolve as cores exatas com peso (a lib já faz esse atalho).
- Resultado: `{ colors: ExtractedColor[]; requested: 5|7|9|11; algorithmVersion: 1; sufficient: boolean }`; `ExtractedColor { rgba; hex; weight; percentage }`.
- Erros tipados: `empty_image`, `insufficient` (não é erro fatal — é aviso).

**Worker**: payload `{ id, buffer: ArrayBuffer, width, height, count }`; `buffer` transferido; resposta `{ ok: true, result } | { ok: false, error }`. O cliente genérico `@/lib/worker/client.ts` ganha um parâmetro opcional de `Transferable[]` em `run()` (mudança aditiva; testes existentes seguem). Sem Worker (jsdom/ambiente antigo), cai na thread principal — o wrapper é o mesmo.

**`decodeImage.ts`**:

- `acceptsImageType(file)`: `image/jpeg|png|webp|gif|avif`; rejeita SVG e demais.
- Bytes antes de decodificar; `createImageBitmap(file)`; depois valida `width/height <= maxSide` e `width*height <= maxPixels`; falha → erro tipado.
- Downscale proporcional para `analysisSide` num canvas (`willReadFrequently: true`), `getImageData`, `bitmap.close()`; nunca upscala.
- Funções puras `fitAnalysisSize()` e `validateDimensions()` testáveis sem canvas.

**`fetchImage.ts`**:

- `new URL(raw)`; só `https:`; rejeita userinfo; erro `invalid_url`/`not_https`.
- `fetch(url, { mode: 'cors', credentials: 'omit', signal })` com `AbortController` + timeout configurado; limpa o timer no `finally`.
- `response.ok`; `content-type` `image/*` (SVG recusado); `Content-Length` quando presente; leitura por `response.body.getReader()` contando bytes e abortando acima de `maxBytes`; devolve `Blob` com o MIME.
- `TypeError` (CORS/rede) → `cors_or_network` com mensagem que explica o CORS e oferece arquivo local; **nunca** proxy/serviço externo. `AbortError` distingue timeout de cancelamento do usuário.
- Só o **host** da URL é guardado no modelo (nunca a URL completa nem a imagem).

**Cancelamento e limpeza**: um `runId` no Tool; nova imagem/URL incrementa; respostas com id antigo são descartadas; `abort()` no fetch e `terminate()` no worker ao desmontar; `bitmap.close()`, `revokeObjectURL` e liberação do canvas no `finally`.

### 2.3 Modelo para a F4

```ts
export interface ExtractedPalette {
  colors: ExtractedColor[];
  requested: 5 | 7 | 9 | 11;
  algorithmVersion: number;
  source: { kind: 'file'; label: string } | { kind: 'url'; host: string };
}
```

Fica em `Tool.vue` (in-memory), sobrevive à troca de abas e ao “usar como base”; recarregar a página descarta. A F4 acrescenta o botão Salvar lendo esse objeto.

## Parte 3 — Interface

`Tool.vue` mantém a grade de duas colunas (`picker` sticky à esquerda) e ganha `Tabs` de página:

| Aba | Conteúdo |
|---|---|
| `scale` | O conteúdo atual da F2 (`EmptyPalette`/stale/`BaseColorRow`/`ToneScale`/`ExportPanel`) |
| `harmonies` | Novo `components/HarmonyPanel.vue` |
| `extraction` | Novo `components/ExtractionPanel.vue` |

- Estado vazio/stale vale para Escala e Harmonias; a Extração funciona independentemente da cor base (a base só é necessária para a escala).
- **`HarmonyPanel`**: `Tabs` de tipo (5) + `Tabs`/segmented de método (OKLCH/HSL); grade de chips `HarmonySwatch` com swatch, HEX, cópia e “usar como base”; selo `clipped`; nota quando acromático/reduzido. Emite `use-base: [rgba]`; Tool faz `state.setColor(rgba); state.finalize()`.
- **`ExtractionPanel`**: props `{ image: ImageLimits; modelValue: ExtractedPalette | null; copy }`, emite `update:modelValue` e `use-base`. Contém:
  - `FileDropzone` (`accept` raster) com dica de bytes e limites; `data-test="extraction-file"`.
  - Campo de URL + botão Importar, dica “HTTPS e CORS” e aviso de que nada é enviado ao servidor.
  - Seletor de quantidade 5/7/9/11 (padrão 11).
  - Status: extraindo com Cancelar; erros em `role="alert"`; erro de CORS com atalho para o arquivo local.
  - Prévia da imagem (object URL revogado ao trocar/desmontar).
  - Resultado: “Cores dominantes · {count}”, chips `ExtractedSwatch` com HEX, percentual, copiar e “usar como base”; aviso quando `sufficient === false`; versão do algoritmo; origem (nome do arquivo ou host).
  - `data-test`: `extraction-url`, `extraction-import`, `extraction-count-{n}`, `extraction-status`, `extraction-error`, `extraction-results`, `extracted-swatch-{i}`, `extracted-copy-{i}`, `extracted-use-{i}`, `extraction-insufficient`, `extraction-version`.
- Feedback de cópia continua no rodapé `role="status"` da página (`useCopyFeedback`).
- Acessibilidade: chips são `button` com rótulo traduzido e estado selecionado; nada depende só de cor; teclado e foco visíveis; layout 1/2/4 colunas responsivo.

## Parte 4 — i18n (5 idiomas)

Namespace `tools.palette-generator.*`, novas folhas idênticas em `lang/{en,es,fr,pt_BR,pt_PT}/tools.php`:

```text
tabs: scale | harmonies | extraction
harmonies: title, description, method_label, method_oklch, method_hsl,
  kind_complementary|analogous|triadic|tetradic|monochromatic,
  base_badge, use_as_base, copy, copied, clipped_badge, achromatic_note, color_label {index}
extraction: title, description, file_label, file_hint {size}, count_label, count_option {count},
  url_label, url_placeholder, url_hint, import_url, importing, cancel, preview_alt,
  results_title {count}, dominant, percentage {value}, use_as_base, copy, copied,
  insufficient {requested,found}, algorithm_version, source_file, source_url,
  errors: invalid_url, not_https, cors, network, timeout, too_large, not_image,
    decode_failed, dimensions_too_large, too_many_pixels, empty_image, unavailable
```

Interpolações nomeadas (`{count}`, `{size}`, `{value}`), sem `{`/`}` literais. Paridade coberta por `TranslationsParityTest` e `i18n-messages.test.ts`.

## Parte 5 — Documentação e ADRs

- `docs/adr/ADR-121-colors-harmonias.md` (Aceito, 2026-11-09): ângulos, métodos, base incluída, offsets da monocromática, alpha, gamut/clipping, dedupe acromático, harmonias auxiliares (não exportadas); alternativas rejeitadas (quadrado, escada fixa, HSL como padrão).
- `docs/adr/ADR-122-colors-extracao-imagem-url.md`: color-thief 3.5.0 (MIT, manutenção, MMCQ, OKLCH/RGB), worker, limites de config, alpha < 0.5 sem composição, nº pedido vs. existente, pesos/percentuais, URL só HTTPS/CORS sem proxy, cancelamento, modelo in-memory para F4.
- `docs/adr/ADR-099-colors-extracao-imagem.md`: seção **Emenda 2026-11-09** (MMCQ com OKLCH/RGB via color-thief, credita a biblioteca e a licença).
- `docs/adr/ADR-108-colors-import-url-imagem.md`: emenda com as regras de HTTPS/CORS/timeout/bytes.
- `docs/colors/palette-generator.md`: harmonias e extração na F3, critérios de aceite; `docs/colors/import-export.md`: seção de imagem implementada; `docs/colors/glossario.md`: harmonia, método, cor dominante, peso, MMCQ, CORS; `docs/colors/README.md`: status e faixa de ADRs até 122.

## Testes

Vitest (novos/atualizados em `resources/js/__tests__/`):

- `color-harmony.test.ts` — ângulos/métodos, base, alpha, clamp, dedupe, clipping, determinismo.
- `palette-extraction.test.ts` — `ImageData` sintético: quatro quadrantes → quatro cores e pesos; imagem uniforme → 1 cor + `sufficient: false`; alpha < 0.5 ignorado; 5/7/9/11; determinismo; dedupe.
- `palette-image-limits.test.ts` — `fitAnalysisSize`, `validateDimensions`, aceitação de MIME.
- `palette-image-url.test.ts` — esquema HTTPS, content-type, Content-Length, contagem por stream, timeout, `TypeError` → CORS/rede; nenhuma requisição além do `fetch` mockado.
- `palette-extraction-worker.test.ts` — protocolo, descarte de resposta antiga, fallback para a thread principal, `terminate` no dispose.
- `palette-generator.test.ts` (estender) — abas, harmonias (método/tipo, clique define base, cópia), extração (mock de decodificação/worker, resultado, erro CORS + arquivo local, persistência entre abas), feedback em `role="status"`.

Pest: `tests/Unit/Colors/ColorsToolPropsTest.php`; asserções de `toolProps` em `ToolPageTest`.

Qualidade/determinismo: fixture de quadrantes recupera as quatro cores com tolerância pequena; duas execuções iguais → deep equal; imagem transparente → erro; imagem minúscula/1 cor → aviso.

## Sequência (paradas para revisão)

0. Baseline (`composer test`, `npm test`, `types:check`), salvar survey/plansfacts e o plano em `docs/claude-code/plans/feature_colors-fase-3/`. **Parar.**
1. Limites: `config/tools.php`, `ColorsToolProps`, `ToolProps`, Pest; prop `image` no Tool e fixture do teste. **Checkpoint.**
2. `lib/color/harmony.ts` + testes. **Checkpoint.**
3. `extract.ts` (+ instalação de `colorthief@3.5.0`) + testes com `ImageData` sintético. **Checkpoint.**
4. `decodeImage.ts`, worker/cliente, cancelamento e limpeza + testes. **Checkpoint.**
5. `fetchImage.ts` (HTTPS/CORS/timeout/bytes) + testes. **Checkpoint.**
6. UI (abas, `HarmonyPanel`, `ExtractionPanel`, chips) + i18n 5 idiomas + testes de montagem. **Checkpoint.**
7. Docs/ADRs (121, 122, emendas), portões finais, conferência manual e relatório. **Checkpoint final.**

## Verificação

```powershell
npm install colorthief@3.5.0        # uma vez, na etapa 3; lockfile entra no commit
npx vitest run resources/js/__tests__/color-harmony.test.ts resources/js/__tests__/palette-extraction.test.ts resources/js/__tests__/palette-image-limits.test.ts resources/js/__tests__/palette-image-url.test.ts resources/js/__tests__/palette-extraction-worker.test.ts resources/js/__tests__/palette-generator.test.ts
php artisan test --filter="ColorsToolPropsTest|ToolPageTest|TranslationsParityTest"
npm run types:check
npm run lint:check
npm test
composer test
npm run build
npx vitest run resources/js/__tests__/i18n-messages.test.ts
```

Manual: abrir a tool nos 5 idiomas; trocar as três abas; conferir os cinco tipos nos dois métodos; clicar num chip e ver a escala mudar; extrair de PNG com transparência, imagem uniforme, imagem grande (limites), JPEG por URL com CORS liberado, URL com CORS bloqueado (mensagem + arquivo local), cancelar durante a extração, trocar de imagem rápido (resultado antigo descartado), tema claro/escuro, teclado e 375/768/1024/1280 px.

## Riscos

- **color-thief**: API/versão podem divergir do README; validar em teste de integração com `ImageData` sintético logo na etapa 3 e isolar o wrapper. A lib não deve entrar no chunk principal: só o worker a importa.
- **Determinismo**: `quality: 1`, pesos recalculados; não confiar na população interna.
- **jsdom sem canvas/ImageData/Worker**: funções puras para limites e decodificação testadas por contrato; componentes recebem o resultado pronto; worker falso nos testes (padrão existente).
- **CORS**: parte das URLs falhará por natureza; a mensagem precisa ser clara e nunca sugerir proxy.
- **Limites**: aplicados antes do trabalho pesado; sem número mágico no cliente.
- **Bundle**: importar a lib apenas no arquivo do worker.
- **i18n**: chaves novas nos 5 idiomas, sem interpolação literal.
- **Exports da F2 intocados**: harmonias e extração não alteram `exporters.ts`.
- **Sem commit/push**: o usuário aprova cada um via gitpr.

## Arquivos principais

Novos: `resources/js/lib/color/harmony.ts`, `resources/js/tools/palette-generator/lib/{extract,extract.worker,extract.workerHost,extractionClient,decodeImage,fetchImage}.ts`, `resources/js/tools/palette-generator/components/{HarmonyPanel,HarmonySwatch,ExtractionPanel,ExtractedSwatch}.vue`, `app/Support/Colors/ColorsToolProps.php`, `docs/adr/ADR-121-*.md`, `docs/adr/ADR-122-*.md` e os testes listados.
Alterados: `resources/js/tools/palette-generator/Tool.vue`, `resources/js/lib/worker/client.ts`, `app/Support/ToolProps.php`, `config/tools.php`, `package.json`/lock, `lang/*/tools.php`, `docs/colors/*`, `docs/adr/ADR-099-*`, `docs/adr/ADR-108-*`, `tests/Feature/Tools/ToolPageTest.php`.
