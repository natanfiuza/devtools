# Plano — Layout Harmonizer F3 (edição, bloqueios e contraste)

Data: 2026-11-18
Branch: `feature/layout-harmonizer-fase-3`
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase3-edicao_bloqueios_contraste.md`
Pré-requisito: F1 (motor puro) e F2 (tool registrada e quadro semântico) concluídas.

## Contexto

A F2 entregou o quadro somente leitura. A F3 fecha o controle manual — editar e
bloquear tokens por tema, secundária manual, meta AA/AAA para sugestões, aceite
explícito e snapshot do estado efetivo — sem persistir nada. O motor F1 já
aceitava `secondary?: Rgb|null`, mas não overrides; os checks de contraste são
calculados dentro de `buildBoard`, antes de `evaluateContrast`.

## Decisões fechadas no grill (2026-11-16/18)

| Tema | Decisão |
|---|---|
| Manuais × regeneração | Seed/estratégia mudando **descarta manuais não bloqueados**, com aviso (pós-hoc, pois a seed é digitada ao vivo); bloqueios prevalecem |
| Secondary manual | Cor de entrada cujas variantes são derivadas por tema (input `secondary` já existe); sobrevive a seed e estratégia até o botão explícito de voltar ao automático |
| Sugestão | Move **só o foreground** (tokens `on-*`), menor delta de L OKLCH, matiz/croma preservados, gamut mapeado; sem solução → aviso de inviável, sem sugestão |
| Restaurar | Escopos token, tema e sistema; confirmação só quando há mudanças manuais a perder |
| Edição | Popover compacto (novo wrapper shadcn sobre reka Popover) com swatch + `ColorInputField` + lock + restaurar + aplicar/cancelar |
| Sugestões na UI | Seção **abaixo dos quadros**, com seletor AA/AAA, pares que falham, cor proposta, razão projetada e “aceitar neste tema”/“aceitar nos dois temas”; células mantêm só selos AA |
| Cascata | Editar token-base **não** recalcula derivados |
| Constantes | Passo L 0,005, máx. 200 passos, épsilon 0,001 (razão ≥ meta + épsilon) |
| AAA | Meta só para sugestões; nunca altera cores |
| Snapshot | Função pura agora, sem UI; versões + estado efetivo congelado |
| ADRs | Copiar ADR-LH-007 → ADR-151; criar ADR-167 (estado efetivo + ajustes + parâmetros); emenda datada no ADR-158 |
| i18n | Só `tools.layout-harmonizer.*` nas 5 línguas |

## Escopo

**Entra:** overrides e bloqueios por token/tema, Secondary manual, meta AA/AAA,
sugestões por token, aceite por tema/ambos com prévia, avisos de inviabilidade,
restore token/tema/sistema, editor popover, lock na célula, snapshot puro,
i18n, testes, ADR-151/167, emenda 158, PARAMETROS e docs.

**Não entra:** salvar/quota/rascunho, exportação MD/CSS/ZIP, protótipos, viewer,
backend, dependência nova, migration, commit/push.

## Motor — extensão aditiva

- `engine/types.ts`: `LayoutOverrides = Partial<Record<LayoutTheme,
  Partial<Record<LayoutTokenId, Rgb>>>>` e `overrides?: LayoutOverrides` em
  `GenerateLayoutInput`.
- `engine/generate.ts`: levar `overrides` ao `BoardInput` e aplicá-las **dentro
  de `buildBoard`, imediatamente antes de `evaluateContrast`**, com
  `exactColor`: sRGB exato, `source: 'manual'`, `clipped: false`, `theme`/
  `family` preservados, sem reconstruir `on-*`. Ids/temas desconhecidos são
  ignorados. Ausente/vazio ⇒ resultado byte-idêntico ao atual (fixtures
  intactas, `LAYOUT_ALGORITHM_VERSION` não muda).
- Precedência: override por token > Secondary manual (input) > derivação
  automática.

## Módulos puros novos (`resources/js/tools/layout-harmonizer/lib/`)

**`editState.ts`** — estado fora do `result`:

- `LayoutOverrides`, `LayoutLocks`, `OverrideOrigin = 'edit' | 'accept' |
  'lock'`, `LayoutEditState`.
- `signatureOf(effectiveSeed, strategy, manualSecondary)` via `canonicalKey`;
  o fundo já está na seed efetiva.
- `dropUnlocked(state)` — chamado **antes** de gerar, só em geração
  bem-sucedida; `lastSignature` atualizado só então.
- `toggleLock` — ligar captura o valor efetivo como override `lock`; desligar
  remove **apenas** overrides de origem `lock`.
- `setOverride` (sem lock), `acceptSuggestion` (override + mantém lock),
  `restoreToken` (remove override, lock e aceites do token), `restoreTheme`,
  `restoreAll` (não mexe na Secondary manual, que tem reset próprio).
- `effectiveOverrides(result, state)` para o motor.

**`suggestions.ts`** — `LAYOUT_SUGGESTIONS_VERSION = 1`;
`SUGGESTION_STEP = 0.005`, `SUGGESTION_MAX_STEPS = 200`,
`SUGGESTION_EPSILON = 0.001`; metas AA 4,5 e AAA 7.

- Uma sugestão **por token foreground × tema**, não por par: reúne todos os
  checks do token (ex.: `on-primary` contra primary/hover/active).
- Busca o menor k em ambos os sentidos (`L0 ± k·0.005`), preservando h/c,
  `mapOklchToSrgb`; razão projetada medida no **sRGB mapeado**; exige meta em
  **todos** os fundos do token.
- Desempate determinístico: maior razão mínima, depois direção mais clara.
- Sem candidato até 200 → `proposal: null` (inviável). Ao contrário do previsto
  no plano, **AA também pode ser inviável** para tokens multi-fundo; o caso foi
  verificado e coberto por testes.

**`snapshot.ts`** — `buildSnapshot(result, editState, target)`: seed
original/efetiva/fundo, estratégia, origem da secundária, tokens/checks
efetivos congelados (cópia profunda), overrides/locks/ajustes aceitos,
diagnósticos (checks que reprovam AA), `schemaVersion`, `algorithmVersion`,
`LAYOUT_SUGGESTIONS_VERSION`.

## Composable — `composables/useLayoutSystem.ts`

- Novos refs: `overrides`, `locks`, `secondaryText`, `target` (`'aa'|'aaa'`),
  `acceptedAdjustments`, `editNotice`, `lastSignature`; derivados
  `suggestions`, `snapshot`, `secondaryInvalid`, `editable`.
- Ordem no watch/regeneração: validar seed (mantém invalid-seed/alpha/estratégia
  e não toca em edições) → `resolveSeed` → assinatura → se mudou,
  `dropUnlocked` e aviso → gerar **uma vez** com `secondary` e
  `effectiveOverrides`.
- `target` fica **fora** do watch de geração; trocar meta não altera `result`.
- Limpar a seed zera resultado, overrides, locks, ajustes aceitos,
  `lastSignature` e avisos.
- `editNotice` é separado de `notice`: nunca deixa `isStale` verdadeiro.
- Ações explícitas (`applyColor`, `toggleTokenLock`, `acceptAdjustment`,
  `acceptAdjustmentInBothThemes`, `restoreTokenEdit`, `restoreThemeEdits`,
  `restoreAllEdits`, `resetSecondary`) chamam a mesma `regenerate()` — sem
  segundo watch.

## UI

- **Wrapper** `resources/js/components/ui/popover/` (reka
  `PopoverRoot/Trigger/Content/Portal`). Sem dependência nova.
- `TokenEditorPopover.vue`: `useColorState` local, swatch + `ColorInputField`,
  aviso de alpha ignorado, lock, restaurar, aplicar/cancelar; montado só
  enquanto aberto.
- `components/colors/ColorInputField.vue`: props opcionais `id`/`testId` com
  default atual (retrocompatível).
- `TokenCell.vue`: botão de editar no rail e lock no cabeçalho (`aria-pressed`),
  selos AA, indicador discreto de sugestão.
- `SuggestionsPanel.vue`: seletor AA/AAA, linhas por token/tema com fundos que
  falham, proposta, razão atual→projetada, aceite por tema e nos dois temas
  (quando o mesmo token tem proposta nos dois), `inviable` sem ação.
- `SecondaryField.vue` na coluna da seed, com “voltar ao automático”;
  `SeedSummary` mostra origem `manual`.
- `RestoreControls.vue`: tema e sistema, com `Dialog` de confirmação; a célula
  restaurar o token. Restaurar não muda `lastSignature`.

## i18n — `tools.layout-harmonizer.*` (5 arquivos)

Grupos: `edit.*`, `lock.*`, `restore.*`, `secondary.*`, `suggestions.*`,
`edit_notice.discarded`. Sem `@`/`|` crus.

## Arquivos

**Criados:** `lib/{editState,suggestions,snapshot}.ts`;
`components/{TokenEditorPopover,SuggestionsPanel,SecondaryField,RestoreControls}.vue`;
`components/ui/popover/{Popover,PopoverTrigger,PopoverContent}.vue` + `index.ts`;
testes `layout-harmonizer-{overrides,edits,suggestions,snapshot,token-editor,suggestions-panel}.test.ts`;
`docs/adr/ADR-151-colors-lh-bloqueios.md`,
`docs/adr/ADR-167-colors-lh-estado-efetivo-e-ajustes.md`.

**Modificados:** `engine/{types,generate}.ts`; `composables/useLayoutSystem.ts`;
`LayoutWorkspace.vue`; `components/{TokenCell,TokenGroup,ThemeBoard,ThemeComparison,SeedSummary}.vue`;
`components/colors/ColorInputField.vue`; `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`;
testes `layout-harmonizer-{system,workspace}.test.ts`; `docs/adr/ADR-158-colors-lh-contraste.md`
(emenda datada); `ADR-MAPEAMENTO.md`; `PARAMETROS.md`; `docs/colors/README.md`;
`docs/SPEC.md` §5 e §9; `docs/glossario.md`.

**Sem tocar:** rotas, `registry.ts`, `components.ts`, `ToolProps.php`, fixtures
da F1.

## Testes

- **Motor:** override aplicado (valor, source `manual`, `clipped` falso, tema),
  checks recalculados, sem cascata, independência por tema, ausente/vazio
  idêntico ao atual, ids desconhecidos ignorados, precedência.
- **editState:** bloqueado sobrevive a seed/estratégia; manual não bloqueado cai
  com aviso; unlock remove só origem `lock`; restore token/tema/sistema; aceite
  mantém lock; assinatura usa seed efetiva.
- **Suggestions:** menor ΔL, todos os fundos atendidos, desempate, AAA e AA
  inviáveis em fundo multi-faixa, h/c preservados, razão do sRGB mapeado, sem
  sugestão quando passa, meta pura.
- **Snapshot:** versões, cópia profunda independente, overrides/locks/aceites
  congelados, diagnósticos só AA.
- **Composable:** lock claro não bloqueia escuro; manuais caem com
  `editNotice` sem `isStale`; entrada/estratégia inválida preserva `result` e
  edições; secundária persiste e reseta; trocar meta não regenera; nenhum
  acesso a storage.
- **UI:** editor aplicar/cancelar e inválido; ids únicos; lock com
  `aria-pressed`; aceite em um tema não altera o outro; “ambos” aplica a
  proposta de cada tema e ignora inviável; restore; células só com AA.
- **i18n:** novas chaves nos 5 idiomas, sem `@`/`|`.

Comandos: `npm run test -- layout-harmonizer`, `npm run test`,
`npm run types:check`, `npm run lint:check`, `npm run format:check`;
`php artisan test tests/Feature/Tools tests/Feature/SitemapTest.php tests/Feature/TranslationsParityTest.php`;
`composer test`.

## Riscos e mitigação

1. **Hook no motor:** overrides antes de `evaluateContrast`; ausência
   byte-idêntica; fixtures intactas.
2. **Ordem da assinatura:** resolver seed efetiva antes de gerar, nunca gerar
   duas vezes; só descarta em sucesso.
3. **Aviso de descarte:** canal separado (`editNotice`).
4. **Lock ambíguo:** origem por override; unlock não apaga manual/aceito;
   restore apaga ambos.
5. **Popover/reka:** testes via `document.body` com limpeza, sem setup global.
6. **Ids duplicados do `ColorInputField`:** `id`/`testId` opcionais e seletor
   escopado no teste.
7. **Sugestões conflitantes:** agrupadas por token/tema exigindo todos os
   fundos.
8. **Performance:** um `computed` único; `result`/`target` como chaves.
9. **Sem defaults silenciosos:** meta AA visível, aviso de descarte, inviável
   explícito, alpha ignorado declarado.

## Verificação manual

Nos 5 idiomas: editar e bloquear em um tema sem afetar o outro; trocar seed com
manual não bloqueado (aviso) e bloqueado (preservado); Secondary manual + voltar
ao automático; meta AAA → sugestões e caso inviável; aceitar em um tema e nos
dois; restaurar token/tema/sistema; teclado/foco no popover e diálogos; conferir
que o tema do site não muda e que nada é salvo.

## Parada

Salvar plano, survey e plansfacts; escrever o relatório com gates reais;
**parar para revisão**, sem commit/push e sem iniciar a F4.
