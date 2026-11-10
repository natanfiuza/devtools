# Relatório — Colors Fase 3: harmonias e extração de cores de imagem

- Data: 2026-11-09
- Branch: `feature/colors-fase-3`
- Plano: `docs/claude-code/plans/feature_colors-fase-3/20261109_feature_colors-fase-3_colors_fase3_harmonias_extracao.md`
- Plano da sessão (grill): `docs/plans/feature_colors-fase-3/20261109_colors_fase3_plansfacts.md`
- Survey: `docs/survey/20261109_colors_fase3_surveyfacts.md`
- ADRs novas: `docs/adr/ADR-121-colors-harmonias.md`, `docs/adr/ADR-122-colors-extracao-imagem-url.md`
- Emendas: ADR-052 (CSP), ADR-099 (extração), ADR-108 (URL de imagem)

## Contexto

A F2 entregou o Color Palette Generator com a escala determinística de 11 tons. A F3 acrescenta duas superfícies auxiliares na mesma página, em abas:

1. **Harmonias** — complementar, análoga, triádica, tetrádica e monocromática, em OKLCH (padrão) e HSL, com a base incluída. Clicar numa cor a define como base; a escala da F2 é recalculada. As harmonias **não** entram nos cinco exports.
2. **Extração** — cores dominantes de uma imagem local ou de URL HTTPS, com 5/7/9/11 cores (padrão 11), worker dedicado, cancelamento, limites configuráveis e resultado só em memória (pronto para a F4).

Tudo continua 100% no navegador: nenhum upload, nenhuma requisição ao servidor da aplicação e nenhum proxy para CORS.

## O que foi feito

### 1. Limites vindos do servidor

- `config/tools.php` ganhou `limits.colors_image` (`max_bytes` 10 MiB, `max_side` 4096, `max_pixels` 16 000 000, `analysis_side` 512, `fetch_timeout_ms` 8000) com env `TOOLS_COLORS_IMAGE_*` e comentário citando a ADR-122.
- `app/Support/Colors/ColorsToolProps.php` entrega os valores em camelCase e está registrado em `ToolProps::for('palette-generator')`; a página do picker continua com `toolProps == []`.
- `.env.example` documenta as cinco variáveis (não estava no plano; necessário para não esconder configuração operacional).
- `resources/js/tools/palette-generator/lib/imageLimits.ts` descreve o shape no cliente; nenhum número mágico no front.

### 2. Núcleo de harmonias (`resources/js/lib/color/harmony.ts`)

- `buildHarmony(base, kind, method)`, `HARMONY_KINDS`, `HARMONY_METHODS` e `HARMONY_HUE_OFFSETS`; exportado pelo barril `@/lib/color`.
- Ângulos: complementar `0/180`, análoga `-30/0/+30`, triádica `0/120/240`, tetrádica retangular `0/60/180/240`.
- Monocromática: OKLCH ±0.14/±0.28 presos em 0.08–0.99; HSL ±14/±28 presos em 0–100; ordem do escuro ao claro.
- OKLCH gira só a matiz e usa `mapOklchToSrgb` (clip por croma, preservando L/H); HSL gira só a matiz.
- Alpha herdado; `dedupe` pela chave canônica; `isBase` por chave canônica; `expected` permite a UI avisar quando o conjunto encolhe (cinza/clamp).
- Puro: sem Vue, DOM ou i18n; determinístico.

### 3. Extração de imagem

| Arquivo | Papel |
| --- | --- |
| `lib/extractContract.ts` | tipos e constantes (`EXTRACTION_COUNTS`, `EXTRACTION_ALGORITHM_VERSION = 1`, request/result/outcome), sem a biblioteca |
| `lib/extract.ts` | wrapper da color-thief 3.5.0 (MMCQ/OKLCH), alpha ≥ 0,5, pesos em OKLab, dedupe, `sufficient`, versão |
| `lib/extract.worker.ts` | `serve()` do protocolo de worker |
| `lib/extract.workerHost.ts` | `new Worker(new URL(...), { type: 'module' })`, `null` sem `Worker` |
| `lib/extractionClient.ts` | cliente dedicado: spawn ansioso, transferência do buffer, descarte de resposta obsoleta, `cancel()`, `dispose()`, fallback por import dinâmico |
| `lib/decodeImage.ts` | bytes → MIME → `createImageBitmap` → dimensões → canvas 512 (`willReadFrequently`), `close()` em `finally` |
| `lib/fetchImage.ts` | só HTTPS/sem userinfo, `fetch` CORS direto, timeout, content-type raster, corte por stream, erros tipados |
| `lib/imageErrors.ts` | códigos tipados de erro |
| `lib/extractedPalette.ts` | modelo em memória com origem (nome do arquivo ou host) |

Regras implementadas:

- Formatos: JPEG, PNG, WebP, GIF, AVIF; SVG recusado.
- Pixels com alpha < 128 (0–255) são ignorados; imagem toda transparente → `empty_image`.
- Peso = pixels visíveis atribuídos à cor mais próxima em OKLab; percentual com 2 casas; ordenação por peso decrescente e HEX no empate.
- Menos cores distintas que o pedido → devolve as existentes com `sufficient: false` e aviso.
- URL: `invalid_url`, `not_https`, `cors`, `network`, `timeout`, `too_large`, `not_image`, `decode_failed`, `dimensions_too_large`, `too_many_pixels`, `empty_image`, `unavailable`, `cancelled`.
- Cancelar interrompe o worker e volta ao ocioso; trocar de imagem aborta o fetch e descarta a resposta antiga (`runId`); desmontar aborta, revoga a prévia e faz `dispose()`.

### 4. Interface

- `Tool.vue` ganhou as abas de página `scale`, `harmonies` e `extraction` (Reka/shadcn `Tabs`), com o picker na coluna esquerda. Estado vazio/stale vale para Escala e Harmonias; a Extração funciona sem cor base.
- `components/HarmonyPanel.vue`: tipo (5) e método (OKLCH/HSL) com `aria-pressed`, nota de acromático/redução e grade de chips.
- `components/HarmonySwatch.vue`: swatch, HEX, selo de base, selo de "fora do sRGB", copiar e "usar como base" com rótulos traduzidos.
- `components/ExtractionPanel.vue`: `FileDropzone`, URL + Importar, quantidade 5/7/9/11, status com cancelar, erro em `role="alert"` (CORS com atalho para arquivo local), prévia, origem, versão do algoritmo, aviso de poucas cores e chips.
- `components/ExtractedSwatch.vue`: swatch, HEX, percentual e ações.
- Paleta extraída mora em `Tool.vue` (`ref`), sobrevive à troca de abas e ao "usar como base" e some ao recarregar.

### 5. i18n

- Bloco `tools.palette-generator.*` ampliado nos cinco idiomas: `tabs`, `harmonies` (incluindo `kind_label`/`method_label`) e `extraction` (status, origens, versão, percentual, `use_file` e os 13 erros).
- Manifesto (`description`, `keywords`, `intro`) atualizado para mencionar harmonias e extração.
- Interpolações nomeadas (`{index}`, `{count}`, `{size}`, `{value}`, `{requested}`, `{found}`, `{version}`, `{name}`, `{host}`), sem chaves fotogramas.

### 6. Segurança e infraestrutura

- `SecurityHeaders` (ADR-052): `img-src` ganhou `blob:` (prévia da imagem) e `connect-src` ganhou `https:` (busca direta por URL). Teste atualizado (11 testes, 46 asserções).
- `colorthief@3.5.0` (MIT) instalado; `extractContract.ts` separa tipos/constantes para a página não puxar a biblioteca. No build, a quantização aparece só em `extract-*.js` (import dinâmico do fallback) e `extract.worker-*.js`, nunca no chunk da página.

### 7. Documentação

- `docs/adr/ADR-121-colors-harmonias.md` e `docs/adr/ADR-122-colors-extracao-imagem-url.md` (Aceito, 2026-11-09), com consequências e alternativas rejeitadas.
- Emendas: ADR-052 (CSP), ADR-099 (MMCQ/color-thief em vez de median cut/OKLab próprio), ADR-108 (regras de HTTPS/CORS/timeout/bytes).
- `docs/colors/palette-generator.md`: estado da F3, abas, harmonias, extração e 9 critérios de aceite.
- `docs/colors/import-export.md`: seção de imagem implementada (incluindo o que fica para a F4).
- `docs/colors/glossario.md`: harmonia, método, cor dominante, peso, percentual, MMCQ e CORS; median cut atualizado.
- `docs/colors/README.md`: fases 1–3, faixa de ADRs até 122 e tabela com 121/122.

## Desvios do plano (e por quê)

1. **Cliente de worker dedicado em vez de estender `@/lib/worker/client.ts`.** O cliente genérico reexecuta o payload na thread principal quando o worker morre; aqui o `ArrayBuffer` já foi transferido, e a reexecução falharia. `extractionClient.ts` resolve a fila com `null` e a próxima extração usa pixels novos.
2. **`extractContract.ts` + import dinâmico no fallback.** O plano pedia a biblioteca só no chunk do worker. Como o fallback de thread principal usa `extractPalette`, a importação estática puxava a color-thief para o chunk da página. Separar o contrato e usar `import('./extract')` no fallback manteve a página limpa (verificado no build).
3. **`alphaThreshold: 128` (escala 0–255), não `0.5`.** A opção da color-thief é 0–255; 128 preserva a regra de "alpha < 0,5 ignorado".
4. **CSP alterada (ADR-052 emendada).** Necessário para `blob:` na prévia e `https:` na busca por URL; sem isso a feature não funciona sob `enforce`. Registrado como emenda, não como reescrita.
5. **`.env.example` atualizado.** Não estava no plano, mas os limites novos são operacionais e precisam estar documentados fora do código.

## Testes

| Suíte | Resultado |
| --- | --- |
| `color-harmony.test.ts` | 17 testes: ângulos/métodos, base, alpha, dedupe acromático, clamp, clipping, determinismo |
| `palette-extraction.test.ts` | 17 testes: quadrantes e pesos, ordenação, percentuais, alpha 127/128, transparente, 5/7/9/11, uniforme, branco, determinismo, contrato |
| `palette-image-limits.test.ts` | 24 testes: `fitAnalysisSize`, dimensões, MIME, decodificação, canvas, `close()` |
| `palette-image-url.test.ts` | 16 testes: esquema, opções de fetch, content-type, Content-Length, stream, timeout, cancelamento, CORS |
| `palette-extraction-worker.test.ts` | 8 testes: transferência, descarte de resposta antiga, fallback, cancelar/respawn, dispose |
| `palette-generator.test.ts` | 17 testes de montagem (8 da F2 + 9 da F3): abas, harmonias tipo/método/uso, acromático, vazio/stale, extração de arquivo, quantidade, CORS, erro de decodificação, cancelar, persistência entre abas |
| Pest | `ColorsToolPropsTest`, `ToolPageTest`, `SecurityHeadersTest`, `TranslationsParityTest` verdes |
| `i18n-messages.test.ts` | 1 teste verde (paridade e interpolações) |

## Portões finais

| Portão | Resultado |
| --- | --- |
| `npm run types:check` | limpo |
| `npm run lint:check` | 0 erros (avisos restantes são pré-existentes em arquivos de outras tools; nenhum nos arquivos da F3) |
| `npm test` | 96 arquivos, **1235 testes verdes** |
| `composer test` | Pint ok, PHPStan ok, **1474 testes / 25 800 asserções** |
| `npm run build` | sucesso; color-thief só nos chunks de worker/fallback |
| `php artisan test --filter="TranslationsParityTest"` | 5 testes, 13 058 asserções |
| `npx vitest run resources/js/__tests__/i18n-messages.test.ts` | verde |

## Conferência manual

Não executada neste ambiente (sem navegador). Fica para a revisão do usuário:

- abrir a tool nos 5 idiomas e trocar as três abas;
- conferir os cinco tipos nos dois métodos e clicar num chip para mudar a base;
- extrair de PNG com transparência, imagem uniforme e imagem grande (limites);
- JPEG por URL com CORS liberado e URL bloqueada (mensagem + arquivo local);
- cancelar durante a extração e trocar de imagem rapidamente;
- tema claro/escuro, teclado e larguras 375/768/1024/1280 px.

Os comportamentos correspondentes estão cobertos por testes automatizados (montagem, unidade e worker).

## Pendências

- **Commit e PR são do usuário**, pelo gitpr (regra do CLAUDE.md). Nada foi commitado nem enviado.
- F4: botão Salvar lendo `ExtractedPalette`, persistência de paletas e protótipos (fora do escopo desta fase).
