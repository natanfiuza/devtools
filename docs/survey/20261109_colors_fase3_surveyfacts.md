# Survey — Colors Fase 3: harmonias e extração de imagem

- Data: 2026-11-09
- Branch: `feature/colors-fase-3` (criada pelo usuário a partir da F2 commitada e enviada)
- Task: executar `docs/colors/PROMPT-Fase3-harmonias_imagens.md` (verificação da fase anterior, decisões, plano e parada para aprovação)

## 1. Contexto da tarefa

A F2 entregou o Color Palette Generator com escala determinística de 11 tons (ADR-120), contraste, cópia e exportações CSS/SCSS/JSON/Tailwind v3/v4. A F3 acrescenta ao gerador **harmonias** (complementar, análoga, triádica, tetrádica e monocromática, em HSL e OKLCH) e **extração de 5/7/9/11 cores dominantes de imagem** (arquivo local ou URL HTTPS), 100% no navegador, sem proxy, sem upload e sem persistência. A paleta extraída fica em memória para a F4 (salvar).

O prompt exige: verificar a fase anterior, listar o que falta, apresentar arquivos/dependências/riscos/testes e **parar para aprovação** antes de codificar. Fora de escopo: CRUD de paletas, protótipos, social, tokens DTCG/Style Dictionary, presets/favoritos e proxy CORS no backend.

## 2. Verificação da fase anterior (F2)

| Item | Resultado |
| --- | --- |
| Árvore de trabalho | limpa; branch `feature/colors-fase-3` |
| Núcleo de cor | `convert`, `gamut`, `format`, `contrast`, `mix`, `scale`, `types` e barril em `resources/js/lib/color/` |
| Escala | `buildPaletteScale`, `PALETTE_TONES`, `PALETTE_ALGORITHM_VERSION = 1` (ADR-120) |
| Exportações | `exporters.ts` com CSS/SCSS/JSON/Tailwind v3/v4 |
| UI | `Tool.vue` em duas colunas, picker sticky, `ToneScale`, `ExportPanel`, estados vazio/stale |
| Worker | `lib/worker/client.ts` e `serve.ts` genéricos; sem suporte a transferência ainda |
| Extração existente | nenhuma no gerador; o `ImageSampler` do picker decodifica no fio principal com canvas de 1024 px, sem limites de bytes/dimensão e sem URL |
| ToolProps | `ToolProps::for()` mapeia por slug; `Show.vue` espalha `toolProps` como props |

## 3. Rodadas do grill

### Rodada 1 — superfície e modelo

| Pergunta | Decisão |
| --- | --- |
| Onde vivem harmonias e extração | Abas de página no próprio `palette-generator`: Escala, Harmonias, Extração |
| Papel das harmonias | Auxiliares clicáveis (copiar/“usar como base”), sem entrar nos cinco exports da F2 |
| Biblioteca de extração | `color-thief@3.5.0` (MIT, publicada em 2026-08-02, MMCQ com `colorSpace: 'oklch'`) |
| Limites | `config/tools.php` → `limits.colors_image`, entregues por `ToolProps` |

### Rodada 2 — semântica

| Pergunta | Decisão |
| --- | --- |
| Modelo das harmonias | Base incluída; tetrádica retangular (0/60/180/240); OKLCH padrão, HSL alternativo |
| Transparência | Ignorar alpha < 0,5; nunca compor sobre fundo |
| Poucas cores | Devolver as existentes, avisar e não inventar |
| Preservação para a F4 | Somente em memória da página; sem localStorage/sessionStorage |

### Rodada 3 — ajuste fino

| Pergunta | Decisão |
| --- | --- |
| Monocromática | Passos relativos à base: OKLCH ±0,14/±0,28 de L; HSL ±14/±28 pontos de L |
| Pesos | Percentual de pixels por cor, ordenado por peso decrescente |
| URL | Só HTTPS, `fetch` CORS, timeout configurável; CORS bloqueado = explicar e oferecer arquivo local, nunca proxy |

## 4. Fatos levantados no código

### Núcleo de cor

- `rgbToHsl`/`hslToRgb`, `rgbToOklch`/`oklchToRgb`, `normalizeHue`, `clamp` em `convert.ts`.
- `mapOklchToSrgb` preserva L/H, reduz croma e marca `clipped`.
- `formatHex`/`formatHex8` e `canonicalKey` em `format.ts`; alpha normalizado na chave.
- O barril `index.ts` reexporta tudo — novo `harmony.ts` fica disponível por `@/lib/color`.

### Picker e componentes compartilhados

- `ColorPickerControls` recebe `state: ColorState`; `useColorState` é a porta única (`setColor`, `finalize`).
- `SwatchButton` (props `color`, `label`, `selected`, `caption`; emite `select`) e `FormatsList` são reutilizáveis.
- `useCopyFeedback` fornece `message` e `copy(value, success?)`; feedback em `role="status"` já existe no gerador.
- `FileDropzone` emite `file` sem decodificar; regras de arquivo em `lib/file.ts`.

### Infraestrutura de worker

- `createWorkerClient(spawn, run)` aceita um spawner e injeta ids; descarta respostas obsoletas e cai na thread principal sem Worker.
- `serve(handle)` escuta mensagens no worker.
- O Regex Tester já usa `new Worker(new URL('./x.worker.ts', import.meta.url), { type: 'module' })`.
- O cliente genérico ainda não aceita lista de `Transferable`; a extração transfere o `ArrayBuffer` do `ImageData`.

### color-thief 3.5.0

- Licença MIT; sem dependências de runtime; funciona no navegador, em worker e no Node.
- `getPalette(source, { colorCount (2–20), quality, colorSpace: 'oklch'|'rgb', ignoreWhite, alphaThreshold, minSaturation, signal })`.
- MMCQ; devolve cores com população normalizada; amostragem determinística com `quality` fixo.
- **Cuidado**: a lib tenta de novo com `alphaThreshold: 0` quando nenhum pixel sobra — a tool precisa pré-checar alpha por conta própria e devolver `empty_image`.
- O wrapper deve recalcular os pesos, para não depender da população interna.

### Backend

- `config/tools.php` agrupa `limits.*`; Base64 Image e Share Text já têm blocos próprios.
- `app/Support/ToolProps.php` usa `match ($slug)`; `tests/Unit/BadgeForge/BadgeForgeToolPropsTest.php` é o precedente de teste de props.
- `ToolController` passa `maxInputBytes`, `virtualizeFrom`, `regex*` e `toolProps`; a página é genérica (ADR-029/ADR-043).

## 5. Decisões técnicas derivadas

- Harmonia pura em `resources/js/lib/color/harmony.ts`: ângulos, offsets da monocromática, alpha herdado, gamut por `mapOklchToSrgb`, dedupe por `canonicalKey` (cinzas colapsam; clamp do mono repete extremos).
- Extração em módulos próprios da tool: `extract.ts` (só ele importa color-thief), `extract.worker.ts`, `extract.workerHost.ts`, `extractionClient.ts`, `decodeImage.ts`, `fetchImage.ts`.
- Decodificação com `createImageBitmap`, validação de bytes/dimensões/pixels antes do trabalho pesado e downscale para `analysisSide` num canvas; quantização no worker, com fallback na thread principal.
- URL: `https` apenas, `credentials: 'omit'`, timeout por `AbortController`, leitura por stream contando bytes; erros tipados e mensagem de CORS sem proxy.
- `ExtractedPalette` in-memory no `Tool.vue`, sobrevivendo à troca de abas; a F4 acrescenta “Salvar” lendo esse objeto.
- Limites em `config/tools.php` (`TOOLS_COLORS_IMAGE_*`) e `ColorsToolProps`; nenhum número mágico no cliente.

## 6. Pendências fora do escopo da F3

CRUD e limites de paletas salvas, página pública/SEO/OG dinâmica, exportação de imagem da paleta, DTCG/Style Dictionary, protótipos site/mobile, presets/favoritos, social/comentários/moderação, importação de JSON e qualquer proxy de imagem no backend.
