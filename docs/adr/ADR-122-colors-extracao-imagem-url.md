# ADR-122 - Extração de cores dominantes de imagem local e por URL
Status: Aceito
Data: 2026-11-09
Relacionados: ADR-099 (extração de paleta de imagem, emenda), ADR-108 (importar paleta de URL, emenda), ADR-052 (CSP, emenda), ADR-002 (processamento local), ADR-101 (escala de 11 tons)

## Contexto
A F3 do Color Palette Generator pede extrair as cores dominantes de uma imagem — de arquivo local ou de URL — e usar uma delas como base da escala. A ADR-099 fixou 5/7/9/11 cores, processamento local e nenhum upload; a ADR-108 fixou URL de imagem com validação, CORS e tamanho. Faltava decidir **como** quantizar, como evitar travar a interface, como medir e apresentar o peso de cada cor, como tratar transparência, imagens com poucas cores e URLs bloqueadas por CORS — e onde guardar o resultado.

As restrições do projeto continuam valendo: a tool roda 100% no navegador, nada de requisição ao servidor, nada de proxy ou serviço externo para "resolver" CORS, e todo limite numérico vem de configuração.

## Decisão

### 1. `color-thief@3.5.0`, encapsulado, no worker

- A quantização usa a [color-thief](https://lokeshdhakar.com/projects/color-thief/) 3.5.0 (MIT, sem dependências de runtime), que implementa **MMCQ** (modified median cut quantization) e aceita `colorSpace: 'oklch'`, entregando a paleta já em OKLCH antes de voltar para RGB.
- A biblioteca é chamada **só** em `lib/extract.ts`, pela API pública `getPaletteSync(ImageData, opções)`; nenhum símbolo de `color-thief/internals` é usado. Trocar de versão ou de biblioteca é uma mudança localizada nesse arquivo.
- Opções fixas: `quality: 1` (varre todos os pixels — determinismo), `colorSpace: 'oklch'`, `ignoreWhite: false` (branco é cor dominante legítima), `minSaturation: 0`, `alphaThreshold: 128` (pixels com alpha < 0,5 são ignorados; nada é composto sobre fundo).
- O empacotamento da biblioteca fica fora do bundle principal: `extract.ts` é importado estaticamente só pelo `extract.worker.ts`; o fallback de thread principal a carrega por **import dinâmico**. Tipos e constantes (`EXTRACTION_COUNTS`, `EXTRACTION_ALGORITHM_VERSION`, `ExtractionResult` etc.) vivem em `extractContract.ts`, que a página pode importar sem puxar a quantização.

### 2. Pesos e percentuais próprios, não a população da biblioteca

Depois da paleta, cada pixel **visível** (alpha ≥ 128) é atribuído à cor mais próxima em OKLab e contado. A cor carrega `weight` (pixels atribuídos) e `percentage` (`weight / pixels visíveis`, com duas casas decimais). As cores são ordenadas por peso decrescente; empate desempata pelo HEX. Assim o percentual mostrado é o da imagem analisada, independente de detalhes internos da biblioteca. Cores com peso zero são descartadas.

- Imagem sem nenhum pixel visível → erro `empty_image` (não se delega o fallback opaco da biblioteca).
- Se a imagem tem menos cores distintas que o pedido, o resultado traz as existentes e `sufficient: false`; a interface avisa e **não inventa** cores.
- `EXTRACTION_ALGORITHM_VERSION = 1` acompanha o resultado. Mudar quantizador, atribuição de peso ou tratamento de alpha exige bump.

O resultado (`ExtractionResult`) traz `colors`, `requested`, `algorithmVersion`, `sufficient` e `visiblePixels`; a tela acrescenta a origem (nome do arquivo ou host da URL) em `ExtractedPalette`.

### 3. Decodificação com limites antes do trabalho pesado

- Formatos aceitos: `image/jpeg`, `image/png`, `image/webp`, `image/gif` e `image/avif`. **SVG é recusado** — não é raster e decodificá-lo mudaria a superfície de ataque (scripts, referências externas).
- A ordem é: bytes → tipo → `createImageBitmap` → dimensões → canvas. Os bytes são medidos antes de decodificar; o tipo é conferido antes de decodificar; e só depois de decodificado o bitmap é validado (`max_side`, `max_pixels`) e desenhado num canvas com `willReadFrequently`, reduzido proporcionalmente para `analysis_side` (nunca ampliado). O bitmap é fechado em `finally`.
- Sem `createImageBitmap`, sem contexto 2D ou decodificação inválida → erro tipado (`unavailable`, `decode_failed`), nunca exceção crua.
- Todos os limites vêm de `config/tools.php` → `limits.colors_image`, entregues pelo servidor em `ToolProps` (camelCase) e lidos pelo cliente em `ImageLimits`:

| Chave | Env | Default |
| --- | --- | --- |
| `max_bytes` | `TOOLS_COLORS_IMAGE_MAX_BYTES` | 10 MiB |
| `max_side` | `TOOLS_COLORS_IMAGE_MAX_SIDE` | 4096 |
| `max_pixels` | `TOOLS_COLORS_IMAGE_MAX_PIXELS` | 16 000 000 |
| `analysis_side` | `TOOLS_COLORS_IMAGE_ANALYSIS_SIDE` | 512 |
| `fetch_timeout_ms` | `TOOLS_COLORS_IMAGE_FETCH_TIMEOUT_MS` | 8000 |

Nenhum desses números é escrito no cliente.

### 4. URL: HTTPS, CORS direto, sem proxy

- `new URL(raw)`: só `https:`; URL inválida → `invalid_url`; `http:` e outros esquemas → `not_https`; URL com userinfo é recusada.
- `fetch(url, { mode: 'cors', credentials: 'omit', referrerPolicy: 'no-referrer', signal })` com `AbortController` e timeout de `fetch_timeout_ms`.
- `response.ok` obrigatório; `content-type` precisa ser um dos formatos aceitos (SVG recusado); `Content-Length`, quando presente, é checado antes de ler; o corpo é lido por `stream` contando bytes e cancelado ao passar de `max_bytes`.
- `TypeError` (CORS ou rede) vira `cors`/`network` com mensagem que explica o bloqueio e oferece **arquivo local**. Aborto do timeout vira `timeout`; aborto pedido pela pessoa vira `cancelled`.
- **Nunca** há proxy, serviço de terceiros ou requisição ao servidor da aplicação para contornar CORS. Quando a origem não libera, o caminho suportado é o arquivo local.

### 5. Worker dedicado, cancelamento e resultado obsoleto

- A extração roda em `extract.worker.ts` (protocolo `serve` de `@/lib/worker/serve`), com `spawnExtractionWorker()` devolvendo `null` quando `Worker` não existe; nesse caso a mesma função roda na thread principal.
- O `ImageData` é transferido (`postMessage` com `Transferable`), sem cópia. Por isso **não** se reutiliza o cliente genérico `@/lib/worker/client.ts`: o fallback dele, em caso de erro do worker, executaria de novo um `ArrayBuffer` já destacado. `extractionClient.ts` implementa o cliente mínimo com spawn ansioso, fallback só quando não há worker, `cancel()` (termina e permite novo spawn) e `dispose()`.
- A tela mantém um `runId`; cada nova imagem ou URL incrementa o id, aborta a busca anterior e descarta respostas com id antigo. Cancelar também incrementa o id e descarta a resposta pendente.
- Ao desmontar: aborta o `fetch`, incrementa o `runId`, revoga o object URL da prévia e chama `dispose()` do cliente. O bitmap é fechado no `decodeImage` e o canvas fica fora do DOM.

### 6. Resultado só em memória, pronto para a F4

- A paleta extraída (`ExtractedPalette`) vive em `Tool.vue`, em memória: sobrevive à troca de abas e ao "usar como base" e é descartada ao recarregar. Não há `localStorage`, `sessionStorage`, upload ou persistência nesta fase.
- A origem guarda **apenas** o nome do arquivo ou o host da URL — nunca a URL completa, o blob ou a imagem.
- A F4 acrescenta o botão Salvar lendo esse objeto; nada no modelo foi desenhado para outra persistência.

### 7. CSP: `blob:` em `img-src` e `https:` em `connect-src`

- A prévia da imagem importada usa `URL.createObjectURL` → `blob:`, liberado em `img-src` (ADR-052, emenda).
- A busca direta da imagem por URL exige `connect-src` com `https:` (ADR-052, emenda). Não há host fixo: a origem é qualquer URL HTTPS escolhida pela pessoa, e a política continua sem `unsafe-inline` em `script-src`.

## Consequências

- A extração é local, determinística e mensurável; a mesma imagem e a mesma quantidade produzem o mesmo resultado.
- A interface não trava: o trabalho pesado está no worker, há cancelamento e resultados obsoletos são descartados.
- URLs sem CORS falham de forma explicada, com caminho alternativo claro; a aplicação não finge resolver o que a origem bloqueia.
- Os limites são operacionais, sem deploy: basta mudar config/.env e o servidor entrega novos valores à página.
- `colorthief` fica isolada num único arquivo e no chunk do worker; a licença MIT está registrada no `package.json`/lock.
- O custo é uma dependência de client nova e uma superfície CSP mais ampla (`https:` em `connect-src`), aceita porque a tool precisa buscar imagens arbitrárias por HTTPS.

## Alternativas rejeitadas
- **Extrair no servidor ou por proxy CORS**: violaria ADR-002, criaria SSRF e faria a imagem sair da máquina da pessoa.
- **Median cut/OKLab próprio (ADR-099 original)**: mais código para manter e um resultado pior documentado; a color-thief implementa MMCQ com OKLCH e é MIT.
- **Usar a população interna da biblioteca como peso**: mediria uma etapa interna da quantização, não os pixels da imagem, e variaria com detalhes da versão.
- **Compor pixels transparentes sobre branco**: mostraria cores que não existem na imagem e faria uma imagem totalmente transparente "funcionar".
- **Preencher cores até o total pedido**: inventaria cores; a saída correta é devolver o que existe com `sufficient: false`.
- **Aceitar SVG**: não é raster e ampliaria a superfície de decodificação sem necessidade.
- **Aceitar `http:` ou userinfo em URL**: mistura de conteúdo ativo e credenciais na URL; o caminho é HTTPS puro.
- **Reusar o cliente de worker genérico**: o fallback reexecutaria um buffer já transferido; o cliente dedicado evita a corrupção.
- **Persistir a paleta extraída em `localStorage`**: fora do escopo da F3 e incompatível com o modelo de paletas da F4.

## Emenda 2026-11-11
A F4 persiste a extração como **cores efetivas** (ADR-123): `colors.items` com `{hex}` na ordem de peso e `colors_count = items.length`; `base` é uma das cores; `source` guarda só `{kind:'file',label}` ou `{kind:'url',host}`; `algorithm_version` (1) fica informativo e abrir a paleta **não** re-quantiza a imagem. Os `roles` padrão da extraída derivam da base em OKLCH e são gravados junto (ADR-126). Nenhuma imagem, URL completa, blob ou percentual é persistido; o rascunho anônimo em `sessionStorage` guarda só base, tipo, cores e aba, por 24 h.
