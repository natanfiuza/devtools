# Plano de execução — Colors F1.1 (canais) + Fase 2 (Palette Generator)

- Data: 2026-11-09
- Branch: `feature/colors-fase-1`
- Plano detalhado: `docs/claude-code/plans/feature_colors-fase-1/20261109_feature_colors-fase-1_colors_fase2_palette_generator.md`

## Objetivo

1. Fechar a lacuna da F1: sliders RGB/RGBA e HSL/HSLA no HTML Color Picker.
2. Entregar o Color Palette Generator da F2 em `/{locale}/tools/colors/palette-generator`: escala fixa de 11 tons em OKLCH, contraste por tom, valores convertidos da base, cópia e exportação CSS/SCSS/JSON/Tailwind v3/v4, 100% no navegador.

## Decisões

- Escada fixa: L 0.97, 0.94, 0.88, 0.81, 0.74, 0.66, 0.58, 0.50, 0.42, 0.34, 0.22 para 50…950.
- Fatores de croma: 0.18, 0.30, 0.50, 0.72, 0.88, 1.00, 0.92, 0.78, 0.62, 0.46, 0.30.
- Croma relativo à base, normalizado pelo tom mais próximo (`factor[t]/factor[nearest]`); acromático zera croma.
- Alpha preservado nos 11 tons; contraste compõe por opção de texto.
- Base nunca alterada; selo no tom mais próximo; `baseIsTone500` por chave canônica.
- JSON autodescritivo: `name`, `algorithmVersion`, `colors`.
- Estado inicial `#6366f1`; limpar o campo → estado vazio com “restaurar exemplo”.
- Reuso: extrair primitivas do picker para `resources/js/components/colors/` e `useColorState` para `resources/js/composables/`; i18n novo `tools.shared.color_picker.*` nos 5 idiomas.
- Nenhuma persistência na F2.

## Ordem de execução (paradas para revisão)

0. Salvar survey/plansfacts/plano; corrigir `docs/colors/README.md`; baseline.
1. Extração compartilhada (componentes, `useColorState`, `style`/`applyHex`, i18n, imports e testes). Checkpoint A.
2. F1.1: setters RGB/HSL, `ChannelSliders`, `ColorPickerControls`, integração no picker e testes. Checkpoint A.
3. `lib/color/scale.ts` + testes.
4. `lib/exporters.ts` + testes. Checkpoint B.
5. Manifest/ícone/listas de registro/i18n/`ToolPageTest`/`SitemapTest`/OG.
6. UI do gerador + teste de montagem. Checkpoint C.
7. Docs (ADR-120 e docs de Colors), portões finais, checklist manual e relatório. Checkpoint D.

## Portões

`npx vitest run` dos testes novos/afetados; `php artisan test` filtrado; `npm run types:check`; `npm run lint:check`; `npm test`; `composer test`; `npm run build`; `php artisan seo:og-image`; `i18n-messages.test.ts` isolado.

Nunca commitar nem fazer push — o usuário aprova cada commit/PR via gitpr.
