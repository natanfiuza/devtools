# Relatório — Layout Harmonizer F2 (interface do quadro e registro)

Data: 2026-11-16
Branch: `feature/layout-harmonizer-fase-2`
Fase anterior: F1 (motor puro, 2026-11-16)
Autorização: grill de 2026-11-16 ("Pode iniciar a fase 2"), com as decisões
registradas em `docs/survey/20261116_layout_harmonizer_f2_surveyfacts.md` e o
plano em `docs/claude-code/plans/feature_layout-harmonizer-fase-2/`.

## 1. Escopo entregue

A F2 tornou o Layout Harmonizer **visível e utilizável** no grupo Colors:

- tool registrada em `/{locale}/tools/colors/layout-harmonizer` (rota agrupada
  existente, sem rota nova), com cabeçalho, SEO e i18n fornecidos pela página;
- seed escolhida no Color Picker compartilhado, **sem valor padrão**: a tool abre
  vazia, com swatch neutro tracejado e rótulo de vazio;
- alpha exige **fundo explícito** — o campo aparece só quando a seed tem alpha
  < 1 e nunca assume branco;
- quatro estratégias com descrição, padrão análoga;
- quadro dos 33 tokens nas cinco famílias do motor, com chave crua, finalidade,
  HEX, OKLCH, proveniência e diagnóstico AA por token;
- três modos de visão — Claro, Escuro e Comparar (padrão) — com grid lado a lado
  no desktop e empilhado no mobile; o tema da tool **não** toca no tema do site;
- cópia de HEX (ação principal) e OKLCH (secundária) por token, inclusive para
  anônimo, com confirmação anunciada em `role="status"`;
- estados vazio, inválido e obsoleto; a entrada inválida **preserva o último
  quadro válido** e explica o motivo;
- nenhum botão de funcionalidade não implementada (salvar, exportar, protótipos,
  login).

## 2. Arquivos

### Criados — `resources/js/tools/layout-harmonizer/`

| Arquivo | Conteúdo |
|---|---|
| `manifest.json` | slug, `status: available`, categoria/grupo `colors`, ícone `layout-panel-left`, ordem, keywords |
| `Tool.vue` | corpo da tool: intro + workspace |
| `LayoutWorkspace.vue` | estado, layout de duas colunas, seletor de modo, avisos e feedback de cópia |
| `composables/useLayoutSystem.ts` | seed/estratégia/fundo, geração com último estado válido, avisos tipados, `isStale` |
| `lib/boardView.ts` | grupos por família, índice dos 16 checks por foreground, formatação de razão por locale |
| `components/SeedPicker.vue` | `ColorPickerControls` + campo de fundo (alpha) |
| `components/StrategySelect.vue` | quatro estratégias com descrição |
| `components/SeedSummary.vue` | original, efetiva, fundo, estratégia e nota acromática |
| `components/ViewModeSwitch.vue` | Claro \| Escuro \| Comparar com `aria-pressed` |
| `components/ThemeComparison.vue` | dois quadros lado a lado |
| `components/ThemeBoard.vue` | cabeçalho, grupos, prévia e resumo |
| `components/TokenGroup.vue` | uma família semântica |
| `components/TokenCell.vue` | token, valores, proveniência, AA e cópia |
| `components/FeedbackPreview.vue` | pares de feedback e `on-*` como amostras neutras |
| `components/ContrastSummary.vue` | pares que falham AA (ou "todos aprovados") |

### Criados — testes e assets

- `resources/js/__tests__/layout-harmonizer-board-view.test.ts`
- `resources/js/__tests__/layout-harmonizer-system.test.ts`
- `resources/js/__tests__/layout-harmonizer-token-cell.test.ts`
- `resources/js/__tests__/layout-harmonizer-workspace.test.ts`
- `resources/js/__tests__/layout-harmonizer-i18n.test.ts`
- `public/og/layout-harmonizer.png` (card da tool, gerado por `seo:og-image`)
- `docs/adr/ADR-153-colors-lh-entradas.md`
- `docs/adr/ADR-162-colors-lh-copiar-cor.md`

### Modificados

| Arquivo | Mudança |
|---|---|
| `resources/js/tools/icons.ts` | `layout-panel-left` → `LayoutPanelLeft` (paridade cobrada no PHP) |
| `resources/js/components/colors/ColorPickerControls.vue` | props opcionais `empty`/`emptyLabel`; default preserva o comportamento atual |
| `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` | bloco `layout-harmonizer` (~95 folhas por idioma) |
| `resources/js/__tests__/tools-registry.test.ts` | slug na lista `MVP` e caminho agrupado |
| `tests/Feature/Tools/ToolsRegistryTest.php` | slug nas duas listas fixas e em `findForUrl` |
| `tests/Feature/Tools/ToolPageTest.php` | página agrupada 200 com `toolProps` vazio; URLs plana/grupo errado 404 |
| `tests/Feature/SitemapTest.php` | slug na lista agrupada |
| `public/og-default.png` | lista de tools do card padrão inclui a nova tool |
| `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md` | F2 = 153/162; 161 → F4 |
| `docs/colors/README.md` | seção do LH em F2; 12 ADRs em `docs/adr/` |
| `docs/SPEC.md` | §5 e §9 com a F2 concluída |

## 3. Decisões de implementação

### Estado e "último válido"

`useLayoutSystem` observa `[hasSeed, rgba, error, strategy, backgroundText]` no
mount e a cada mudança. Sem seed, `result = null`. Entrada inválida, alpha sem
fundo ou erro tipado do motor **não** substituem `result`: o aviso muda e
`isStale` sinaliza que o quadro reflete a última entrada válida. Nenhum acesso a
storage, nenhum rascunho — a F2 não persiste nada.

A validade da seed é julgada por `parseColor(text)`, e não pelo `error` do picker,
porque mexer nos sliders não limpa um erro de texto obsoleto.

### Temas independentes do site

O modo da visão é estado de apresentação no workspace; o quadro renderiza as
cores geradas em superfícies próprias. O `<html>` nunca recebe `dark`, e um teste
do workspace cobre isso.

### Quadro sem reclassificação

`boardView` só agrupa, indexa e formata. A classificação AA/AAA vem do motor
(razão crua); a razão exibida é a formatada, e o arredondado nunca decide o
veredito. Falhas pré-existentes (ex.: `on-primary` sobre `primary-hover`) são
diagnosticadas no resumo, não corrigidas nem escondidas.

### Picker compartilhado

A única alteração fora do diretório da tool é aditiva: `empty`/`emptyLabel`
opcionais em `ColorPickerControls`. Sem elas, o comportamento anterior é mantido;
a suíte completa do palette-generator continua verde.

### `title` em reka-ui

`Badge` usa `Primitive` (`inheritAttrs: false`), então `title` no componente não
chegava ao DOM. Os tooltips de AA e de croma reduzido foram movidos para os
elementos envoltórios (`<li>` e `<span>`).

### i18n

Somente `tools.layout-harmonizer.*`, nos cinco idiomas, em um único passo. As 33
finalidades são iteradas do catálogo no teste de i18n, para que um token novo não
passe sem tradução. Nome "Layout Harmonizer" é igual nos cinco idiomas. Nenhum
namespace novo — `layout_harmonizer` nasce na F4, como decidido.

## 4. Achado das gates: OG image da tool

Registrar a tool quebrou `SeoMetaTest` ("falta o PNG de cada tool que funciona"),
porque o teste guarda contra uma tool `available` sem card. O fluxo documentado é
rodar `php artisan seo:og-image`, que desenha uma imagem por tool a partir do
registro. Rodado: gerou `public/og/layout-harmonizer.png` e reescreveu
`public/og-default.png` (a lista de tools mudou); os demais PNGs saíram
byte-idênticos. O teste então passou.

## 5. Gates — resultados reais

| Gate | Resultado |
|---|---|
| `npm run test` | **139 arquivos / 1693 testes**, todos verdes (inclui os 5 do LH) |
| `npm run types:check` | limpo |
| `npm run format:check` | limpo |
| `npm run lint:check` | **0 erros** (24.833 warnings de estilo pré-existentes no repo) |
| `php artisan test tests/Feature/Tools tests/Feature/SitemapTest.php tests/Feature/TranslationsParityTest.php` | verde |
| `composer test` | pint ok, phpstan 0 erros, Pest **1737 testes / 30.924 asserções**, verde |

## 6. Fora de escopo (mantido)

Persistência, rascunho/`localStorage`, cota, salvar, exportação MD/CSS/ZIP,
edição manual ou bloqueios, sugestões AA/AAA, secondary manual, protótipos,
viewer público, login CTA, props de backend, rotas novas, migration, dependências,
commit e push.

## 7. Estado da árvore e próxima fase

`git status` contém apenas arquivos da F2 (tool, testes, i18n, docs, ADRs 153/162,
ícone, picker aditivo, PNGs de OG e os artefatos de plano/survey). Nada foi
commitado nem enviado. **Parada para revisão**: a F3 não foi iniciada.
