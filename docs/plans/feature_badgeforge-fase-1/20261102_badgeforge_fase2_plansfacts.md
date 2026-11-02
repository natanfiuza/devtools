# Plano — BadgeForge: Fase 2 (PNG, download, docs e fontes)

- **Branch:** `feature/badgeforge-fase-1`
- **Data:** 2026-11-02
- **Plano técnico completo:**
  `docs/claude-code/plans/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase2.md`
- **Survey:** `docs/survey/20261102_badgeforge_fase2_surveyfacts.md`

## Escopo

1. Fontes Fira Code, Libre Franklin e Space Grotesk empacotadas com métricas
   derivadas dos próprios TTF.
2. Parâmetro `?font=` no servidor e no espelho TypeScript.
3. Endpoint `.png` via Imagick (1×, cap 600 px), cache em disco e
   `?download=1`.
4. UI: seletor de fonte, seletor de formato, botão Baixar e preview inline.
5. Documentação: ADR-077, API/OpenAPI, SPEC, glossário, aceite, guia de
   renderização e CLAUDE.md.

## Decisões da sessão de grill

- **Formato**: seletor SVG/PNG na UI; URL termina em `.svg` ou `.png` e o
  botão Baixar acrescenta `?download=1`. O preview nunca faz requisição.
- **PNG**: rasteriza o SVG na largura natural; só reduz proporcionalmente
  acima de 600 px; saída `png32` com fundo transparente.
- **Cache**: conteúdo endereçado por SHA-256 do SVG em
  `storage/app/data/tools/badgeforge/static/`, escrita atômica, corte por
  `badgeforge:prune` (mtime + `--dry-run`).
- **Download**: vale para os dois formatos; nome `label-message.ext`
  sanitizado para ASCII e repetido em `filename*` UTF-8.
- **Sem Imagick**: 503 `image_unavailable`; nada do conteúdo do badge em log.
- **Fontes**: `?font=fira-code|libre-franklin|space-grotesk`; valor
  desconhecido cai no default do estilo. Métricas geradas por
  `badgeforge:font-metrics`, com o JSON versionado e um guard de drift nos
  testes.
- **Preview inline**: `<img>` dá lugar a SVG inline para que a fonte web se
  aplique; o conteúdo é gerado e escapado pelo renderer.

## Execução

1. **E1** — copiar as 6 TTF + OFL para `resources/badgeforge/fonts/`,
   config de fontes, `TrueTypeFont`, comando `badgeforge:font-metrics`,
   `family_bold_percent` em `FontMetrics`/`metrics.ts` e testes.
2. **E2** — `BadgeFontCatalog`, campos de fonte no `BadgeSpec`/`BadgeRequest`,
   `fonts` no toolProps, espelho TS, três fixtures novas e testes.
3. **E3** — config de PNG, `PngRenderer`, `PngCache`, controller com dispatch
   por extensão e download, rota `svg|png`, `badgeforge:prune` e testes.
4. **E4** — `@font-face`, selects de fonte e formato, botão Baixar, preview
   inline, i18n nos 5 idiomas e testes.
5. **E5** — ADR-077, API/OpenAPI, SPEC, glossário, aceite, guia de deploy,
   CLAUDE.md.

Gates (`composer test`, `npm run test`, `npm run types:check`,
`npm run lint:check`) ao fim de cada etapa; sem commit/push.
