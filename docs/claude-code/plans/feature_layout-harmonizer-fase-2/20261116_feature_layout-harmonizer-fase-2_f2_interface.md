# Plano — Layout Harmonizer F2 (interface do quadro e registro)

Data: 2026-11-16
Branch: `feature/layout-harmonizer-fase-2`
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase2-interface_quadro_cores.md`
Pré-requisito: F1 concluída (motor puro em `resources/js/tools/layout-harmonizer/engine/`).

## Contexto

A F1 entregou o motor puro (33 tokens, 4 estratégias, 2 temas, alpha, gamut,
contraste, fixtures e 10 ADRs), com todas as gates verdes. A F2 registra a
ferramenta no grupo Colors e constrói a interface do quadro semântico sobre esse
motor. O prompt empacotado da F2 exclui persistência, exportação, edição manual e
protótipos novos.

## Decisões fechadas no grill (2026-11-16)

| Tema | Decisão |
|---|---|
| Escopo | Alinhado ao prompt F2: sem rascunho/localStorage, sem promoção dos protótipos, sem props de backend. ADR-153 e ADR-162 entram na F2; ADR-161 vai para a F4 |
| Entrada | Picker completo reutilizado; alpha exige fundo explícito (campo aparece só com alpha < 1); ferramenta abre vazia, sem seed padrão |
| Estratégia inicial | Análoga (+30°); nada é gerado antes de uma seed |
| Diagnóstico | AA por token (razão + selo) e resumo dos pares que falham; sem AAA nem sugestões (F3) |
| Cópia | HEX (ação principal) e OKLCH (secundária) por token; anônimo copia |
| Temas | Três modos segmentados Claro/Escuro/Comparar, padrão Comparar; grid no desktop, empilhado no mobile; estado local, nunca toca no tema do site |
| Célula | Chave crua + finalidade traduzida + HEX + OKLCH + proveniência + AA |
| i18n | Só `tools.layout-harmonizer.*` nos 5 idiomas; namespace `layout_harmonizer` nasce na F4 |
| Recursos futuros | Sem botões/CTA de salvar, exportar, protótipos ou login |

## Escopo

**Entra:** manifest/registro, ícone `layout-panel-left`, página em
`/{locale}/tools/colors/layout-harmonizer`, picker reutilizado, seletor de
estratégia, quadro dos 33 tokens em 5 famílias, modos claro/escuro/comparar,
proveniência, AA + resumo, prévia de feedback, cópia HEX/OKLCH, estados
vazio/inválido/obsoleto, responsividade, acessibilidade, i18n em 5 idiomas,
testes, ADRs 153/162, OG image e docs.

**Não entra:** rascunho (`localStorage`), salvar/quota, exportação MD/CSS/ZIP,
edição manual, bloqueios, sugestões AA/AAA, secondary manual, protótipos/PNG,
viewer público, login CTA, backend de props, dependências novas, migration.

## Arquitetura

```
Tool.vue
└─ LayoutWorkspace.vue            dono do estado e do layout de duas colunas
   ├─ SeedPicker.vue              ColorPickerControls + campo de fundo (alpha)
   ├─ StrategySelect.vue          quatro estratégias
   ├─ SeedSummary.vue             original/efetiva/fundo/estratégia/acromática
   ├─ ViewModeSwitch.vue          Claro | Escuro | Comparar (aria-pressed)
   ├─ ThemeComparison.vue         grid 1 coluna → 2 no desktop
   │  └─ ThemeBoard.vue ×2
   ├─ ThemeBoard.vue              cabeçalho + grupos + prévia + resumo
   │  ├─ TokenGroup.vue ×5
   │  │  └─ TokenCell.vue
   │  ├─ FeedbackPreview.vue
   │  └─ ContrastSummary.vue
   └─ regiões role="status" (aviso do quadro e confirmação de cópia)
```

## Estado — `composables/useLayoutSystem.ts`

```ts
type LayoutNotice = LayoutEngineErrorCode | 'invalid-seed' | null;
```

- `useColorState({ initial })` + `setFromText('')`: campo abre vazio;
  `hasSeed = text.trim() !== ''` é o único gate do motor.
- `watch` imediato sobre `[hasSeed, rgba, error, strategy, backgroundText]`:
  1. sem seed → `result = null`, `notice = null`;
  2. texto inválido → mantém `result`, `notice = 'invalid-seed'`;
  3. alpha < 1 sem fundo válido → mantém `result`, `notice = 'seed-alpha-without-background'`;
  4. senão → `generateLayoutSystem`; sucesso troca `result` e limpa aviso;
     `LayoutEngineError` preserva `result` e vira `notice`.
- `result` em `shallowRef`; `isStale = notice !== null && result !== null`.
- Fundo: `parseColor` do texto, só `{r,g,b}`, sem default branco.
- Sem storage, sem rascunho, sem `useAppearance`.

## Quadro e diagnóstico

Famílias na ordem do motor: **brand 11**, **surface 4**, **feedback 4**,
**content 10**, **interaction 4**. `lib/boardView.ts` (puro) agrupa tokens por
família preservando ordem, indexa os 16 `checks` por `foreground` e formata a
razão via `Intl.NumberFormat`, sem reclassificar pelo arredondado.

## i18n — `tools.layout-harmonizer.*` (5 arquivos `tools.php`)

`name`, `description`, `keywords`, `intro`, `seed.*`, `strategy.*`, `view.*`,
`families.*`, `tokens.<33 ids>`, `provenance.*`, `contrast.*`, `copy.*`,
`clipped.*`, `states.*`, `feedback_preview.*`, `a11y.*` — ~95 folhas por idioma.

## Arquivos

**Criar:** `resources/js/tools/layout-harmonizer/{manifest.json,Tool.vue,LayoutWorkspace.vue}`,
`composables/useLayoutSystem.ts`, `lib/boardView.ts`,
`components/{SeedPicker,StrategySelect,ViewModeSwitch,SeedSummary,ThemeBoard,TokenGroup,TokenCell,ThemeComparison,FeedbackPreview,ContrastSummary}.vue`;
5 testes `layout-harmonizer-{board-view,system,token-cell,workspace,i18n}.test.ts`;
`public/og/layout-harmonizer.png` (via `seo:og-image`);
`docs/adr/ADR-153-colors-lh-entradas.md` e `docs/adr/ADR-162-colors-lh-copiar-cor.md`.

**Modificar:** `resources/js/tools/icons.ts`, `resources/js/components/colors/ColorPickerControls.vue`
(props opcionais `empty`/`emptyLabel`), `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`,
`resources/js/__tests__/tools-registry.test.ts`, `tests/Feature/Tools/ToolsRegistryTest.php`,
`tests/Feature/Tools/ToolPageTest.php`, `tests/Feature/SitemapTest.php`,
`public/og-default.png` (lista de tools), `ADR-MAPEAMENTO.md`,
`docs/colors/README.md`, `docs/SPEC.md`.

Sem tocar em rotas, `registry.ts`, `components.ts`, `ToolProps.php`, engine F1 ou
protótipos do palette-generator.

## Testes

- **boardView:** grupos 11/4/4/10/4, 33 únicos na ordem do motor; 16 checks, 3 em
  on-primary; 23 tokens sem par; formatação 4,83 e locale `pt_BR`.
- **useLayoutSystem:** abre vazio/sem gerar; seed válida gera 2 temas; texto
  inválido mantém a mesma referência de `result` + aviso + stale; alpha sem fundo
  idem; fundo válido regenera; troca de estratégia regenera; limpar volta ao
  vazio; nenhum acesso a storage.
- **TokenCell:** id/finalidade/HEX/OKLCH; proveniência; croma reduzido; 1 e 3
  selos AA; payloads; swatch oculto.
- **Workspace:** estado vazio; 66 células no Comparar; contagem por modo; seed
  inválida preserva células e mostra aviso; campo de fundo só com alpha; tema do
  site intocado; status de cópia.
- **i18n:** 33 finalidades e chaves dinâmicas não vazias nos 5 idiomas; nome fixo.

## Riscos e mitigação

1. Picker compartilhado: mudança aditiva com default igual; suíte completa cobre.
2. Alpha: fundo inválido/ausente mantém o último quadro; nunca assume branco.
3. Falhas AA pré-existentes: diagnosticadas e listadas, nunca reclassificadas.
4. Paridade i18n: cinco arquivos no mesmo passo; ids iterados do catálogo.
5. 66 células: `shallowRef`, índice de checks pré-computado, sem watcher por célula.
6. Listas fixas em testes e OG image: atualizadas juntas.
7. Sem defaults silenciosos: seed vazia, estratégia análoga visível, fundo explícito.

## Verificação e parada

Gates: `npm run test -- layout-harmonizer`, `npm run test`, `npm run types:check`,
`npm run lint:check`, `npm run format:check`,
`php artisan test tests/Feature/Tools tests/Feature/SitemapTest.php tests/Feature/TranslationsParityTest.php`
e `composer test`. Relatório com resultados reais e **parada para revisão**, sem
commit/push e sem iniciar a F3.
