# Plano de execução — Layout Harmonizer F2 (interface do quadro e registro)

Data: 2026-11-16
Branch: `feature/layout-harmonizer-fase-2`
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase2-interface_quadro_cores.md`
Plano completo: `docs/claude-code/plans/feature_layout-harmonizer-fase-2/20261116_feature_layout-harmonizer-fase-2_f2_interface.md`
Survey: `docs/survey/20261116_layout_harmonizer_f2_surveyfacts.md`

## Objetivo

Registrar o Layout Harmonizer no grupo Colors e construir a **interface do quadro
semântico** sobre o motor puro da F1, sem persistência, exportação, edição manual
ou protótipos novos. Ao fim: gates reais, relatório e parada para revisão.

## Decisões que dirigem o plano (grill de 2026-11-16)

| Tema | Decisão |
| --- | --- |
| Escopo | Alinhado ao prompt F2: sem rascunho/`localStorage`, sem promoção dos protótipos, sem props de backend |
| Entrada | Picker completo reutilizado; alpha exige fundo explícito; tool abre **vazia**, sem seed padrão |
| Estratégia inicial | Análoga (`analogous`); nada é gerado antes de uma seed |
| Diagnóstico | AA por token + resumo dos pares que falham; sem AAA nem sugestões |
| Cópia | HEX (principal) e OKLCH (secundária) por token; anônimo copia |
| Temas | Três modos segmentados Claro/Escuro/Comparar, padrão Comparar; estado local, nunca toca no tema do site |
| Célula | Chave crua + finalidade traduzida + HEX + OKLCH + proveniência + AA |
| i18n | Só `tools.layout-harmonizer.*` nos 5 idiomas; namespace novo fica para a F4 |
| Recursos futuros | Sem botão/CTA de salvar, exportar, protótipos ou login |
| ADRs | 153 (entradas) e 162 (copiar cor) entram na F2; 161 (rascunho) vai para a F4 |

## Arquitetura entregue

```
Tool.vue
└─ LayoutWorkspace.vue            estado + duas colunas
   ├─ SeedPicker.vue              ColorPickerControls + campo de fundo
   ├─ StrategySelect.vue          4 estratégias
   ├─ SeedSummary.vue             original/efetiva/fundo/estratégia
   ├─ ViewModeSwitch.vue          Claro | Escuro | Comparar
   ├─ ThemeComparison.vue → ThemeBoard.vue ×2
   ├─ ThemeBoard.vue → TokenGroup.vue ×5 → TokenCell.vue
   │                   FeedbackPreview.vue + ContrastSummary.vue
   └─ regiões role="status" (aviso do quadro e confirmação de cópia)
```

Estado em `composables/useLayoutSystem.ts` (`shallowRef` para o resultado,
`watch` imediato, último estado válido preservado, aviso `invalid-seed` /
`seed-alpha-without-background` / códigos do motor, `isStale` marcando o quadro
como reflexo da última entrada válida). Visão pura em `lib/boardView.ts` (grupos
por família, índice de 16 checks por `foreground`, razão formatada por `Intl`).

## Etapas executadas

| Etapa | Conteúdo | Resultado |
| --- | --- | --- |
| 1 | Baseline da F1 (suíte do motor) | Verde antes de tocar no registro |
| 2 | Registro (`manifest.json`, `Tool.vue`, ícone `layout-panel-left`) | `/{locale}/tools/colors/layout-harmonizer` |
| 3 | `lib/boardView.ts` + `useLayoutSystem.ts` | Estado e visão puras, sem storage |
| 4 | Componentes do quadro e do workspace | 33 tokens × 2 temas, 3 modos |
| 5 | Picker compartilhado: props `empty`/`emptyLabel` | Estado vazio sem swatch branco implícito |
| 6 | i18n nos 5 idiomas (~95 folhas) | Paridade e compilação verdes |
| 7 | Listas fixas de registro (JS/PHP), sitemap e página agrupada | Sem divergência entre código e testes |
| 8 | Testes novos (boardView, sistema, célula, workspace, i18n) | 5 arquivos, suíte completa verde |
| 9 | ADR-153/162, `ADR-MAPEAMENTO.md`, README e SPEC | Docs alinhadas à F2 |
| 10 | Gates completas + relatório | Parada para revisão, sem commit/push |

## Entregáveis

1. Código em `resources/js/tools/layout-harmonizer/` (tool, workspace, 10 componentes, composable, lib).
2. i18n em `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` (bloco `layout-harmonizer`).
3. `docs/adr/ADR-153-colors-lh-entradas.md` e `docs/adr/ADR-162-colors-lh-copiar-cor.md`.
4. Atualizações: `ADR-MAPEAMENTO.md`, `docs/colors/README.md`, `docs/SPEC.md` (§5 e §9).
5. Relatório, plano e este plansfacts.

## O que NÃO foi feito (por decisão de escopo)

- Nenhuma persistência, rascunho, cota, salvamento, exportação MD/CSS/ZIP.
- Nenhuma edição manual, bloqueio, sugestão AA/AAA ou secondary manual.
- Nenhum protótipo novo, viewer público, login CTA ou prop de backend.
- Nenhuma rota, migration, dependência ou `composer`/`npm install`.
- Nenhum commit ou push.
