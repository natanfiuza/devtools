# Plano de execução — Colors Fase 3 (harmonias e extração de imagem)

- Data: 2026-11-09
- Branch: `feature/colors-fase-3` (criada a partir da F2 commitada e enviada)
- Plano detalhado: `docs/claude-code/plans/feature_colors-fase-3/20261109_feature_colors-fase-3_colors_fase3_harmonias_extracao.md`

## Objetivo

Entregar no Color Palette Generator (`/{locale}/tools/colors/palette-generator`):

1. **Harmonias** complementar, análoga, triádica, tetrádica e monocromática, em HSL e OKLCH, identificadas na tela, com cópia e “usar como base”.
2. **Extração de 5/7/9/11 cores dominantes** de imagem local ou URL HTTPS (padrão 11), mostrando quantidade, percentual e origem, separada da escala de 11 tons.
3. Paleta extraída preservada **somente em memória** para a F4.
4. Tudo 100% no navegador, sem upload/proxy/persistência, com limites configuráveis, cancelamento e descarte de resultado obsoleto.

## Decisões

- Abas de página no gerador: Escala (F2), Harmonias, Extração; picker/base compartilhados; clicar numa cor define a base.
- Harmonias auxiliares, fora dos cinco exports da F2. Base incluída; complementar 2, análoga 3, triádica 3, tetrádica 4 (retangular 0/60/180/240), monocromática 5; OKLCH padrão e HSL alternativo.
- Monocromática relativa à base: OKLCH ±0,14/±0,28 de L (0,08–0,99); HSL ±14/±28 pontos (0–100).
- Extração com `color-thief@3.5.0` (MIT, MMCQ OKLCH/RGB), encapsulada e executada em worker; ADR-099 emendada.
- Alpha < 0,5 ignorado, sem composição; poucas cores devolvem o existente com aviso; ordenação por peso com percentual.
- Limites em `config/tools.php` → `limits.colors_image` + `ColorsToolProps`; URL só HTTPS com `fetch` CORS, timeout e contagem de bytes; nunca proxy.
- `ExtractedPalette` in-memory no `Tool.vue`; recarregar descarta.
- Nenhum commit/push: o usuário aprova via gitpr.

## Ordem de execução (paradas para revisão)

0. Survey/plansfacts/plano salvos; baseline (`composer test`, `npm test`, `types:check`). **Parar.**
1. Limites: `config/tools.php`, `ColorsToolProps`, `ToolProps`, Pest; prop `image` no Tool e fixture do teste. Checkpoint.
2. `lib/color/harmony.ts` + testes. Checkpoint.
3. `extract.ts` + `colorthief@3.5.0` + testes com `ImageData` sintético. Checkpoint.
4. `decodeImage.ts`, worker/cliente, cancelamento e limpeza + testes. Checkpoint.
5. `fetchImage.ts` (HTTPS/CORS/timeout/bytes) + testes. Checkpoint.
6. UI (abas, `HarmonyPanel`, `ExtractionPanel`, chips) + i18n 5 idiomas + testes de montagem. Checkpoint.
7. Docs/ADRs 121–122 e emendas, portões finais, conferência manual e relatório. Checkpoint final.

## Portões

`npx vitest run` dos testes novos/afetados; `php artisan test` filtrado; `npm run types:check`; `npm run lint:check`; `npm test`; `composer test`; `npm run build`; `npx vitest run i18n-messages.test.ts`.

Conferência manual: 5 idiomas, três abas, cinco tipos nos dois métodos, clique em chip, PNG com transparência, imagem uniforme/grande, URL com e sem CORS, cancelamento, troca rápida de imagem, temas, teclado e 375/768/1024/1280 px.
