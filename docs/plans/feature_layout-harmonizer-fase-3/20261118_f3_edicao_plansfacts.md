# Plano de execução — Layout Harmonizer F3 (edição, bloqueios e contraste)

Data: 2026-11-18
Branch: `feature/layout-harmonizer-fase-3`
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase3-edicao_bloqueios_contraste.md`
Plano completo: `docs/claude-code/plans/feature_layout-harmonizer-fase-3/20261118_feature_layout-harmonizer-fase-3_f3_edicao.md`
Survey: `docs/survey/20261118_layout_harmonizer_f3_surveyfacts.md`

## Objetivo

Fechar o controle manual do Layout Harmonizer **sem persistir nada**: editar
tokens, bloquear por token/tema, restaurar por token/tema/sistema, secundária
manual com volta explícita ao automático, meta AA/AAA só para sugestões,
sugestões que nunca se aplicam sozinhas, aceite por tema ou nos dois temas,
aviso de combinação inviável e snapshot puro do estado efetivo. Ao fim: gates
reais, relatório e parada para revisão.

## Decisões que dirigem o plano (grill de 2026-11-16/18)

| Tema | Decisão |
| --- | --- |
| Manuais × regeneração | Seed/estratégia mudando **descarta manuais não bloqueados**, com aviso posterior (a seed é digitada ao vivo); bloqueios prevalecem |
| Secondary manual | Cor de entrada cujas variantes são derivadas por tema; sobrevive a seed e estratégia até o botão explícito de voltar ao automático |
| Sugestão | Move **só o foreground** (`on-*`), menor delta de L OKLCH, matiz/croma preservados, gamut mapeado; sem solução → aviso de inviável, sem sugestão |
| Restaurar | Escopos token, tema e sistema; confirmação só quando há mudanças manuais a perder |
| Edição | Popover compacto (wrapper shadcn sobre reka Popover) com swatch + `ColorInputField` + lock + restaurar + aplicar/cancelar |
| Sugestões na UI | Seção **abaixo dos quadros**, com seletor AA/AAA, pares que falham, cor proposta, razão projetada e “aceitar neste tema”/“aceitar nos dois temas”; células mantêm só selos AA |
| Cascata | Editar token-base **não** recalcula derivados |
| Constantes | Passo L 0,005, máx. 200 passos, épsilon 0,001 (razão ≥ meta + épsilon) |
| AAA | Meta só para sugestões; nunca altera cores |
| Snapshot | Função pura agora, sem UI; versões + estado efetivo congelado |
| ADRs | Copiar ADR-LH-007 → ADR-151; criar ADR-167 (estado efetivo + ajustes + parâmetros); emenda datada no ADR-158 |
| i18n | Só `tools.layout-harmonizer.*` nas 5 línguas |

## Arquitetura entregue

```text
LayoutWorkspace.vue
├─ SeedPicker / StrategySelect / SeedSummary
│  └─ SecondaryField.vue             secundária manual + voltar ao automático
├─ ViewModeSwitch → ThemeComparison → ThemeBoard ×2
│  ├─ TokenGroup → TokenCell        lock no cabeçalho, editar no rail
│  │                                └─ TokenEditorPopover.vue (reka Popover)
│  └─ RestoreControls.vue            restaurar tema/sistema + dialog
├─ SuggestionsPanel.vue              meta AA/AAA, propostas, aceite(s), inviável
└─ regiões role="status"
```

Puro, fora de Vue:

- `engine/{types,generate}.ts`: `LayoutOverrides`, aplicados em `buildBoard`
  antes de `evaluateContrast`, `source: 'manual'`, sem cascata;
- `lib/editState.ts`: overrides com origem, locks, assinatura, descarte,
  restauração e contagem;
- `lib/suggestions.ts`: uma sugestão por foreground × tema, passo 0,005, teto
  200, épsilon 0,001, metas 4,5/7, `proposal: null` = inviável;
- `lib/snapshot.ts`: snapshot do estado efetivo com versões, cópia profunda,
  overrides, locks, aceites e diagnósticos AA.

## Etapas executadas

| Etapa | Conteúdo | Resultado |
| --- | --- | --- |
| 1 | Motor: `overrides` em `types`/`generate` + testes | Overrides válidos, sem cascata, ausência idêntica ao atual |
| 2 | `lib/editState.ts` + testes | Lock/unlock, descarte por assinatura, restore por escopo |
| 3 | `lib/suggestions.ts` e `lib/snapshot.ts` + testes | Proposta de menor delta, inviável explícito, snapshot congelado |
| 4 | `useLayoutSystem` com edição, meta e snapshot | Ações explícitas chamam a mesma geração; meta não regenera |
| 5 | Wrapper `components/ui/popover/` e `TokenEditorPopover` | Editor montado só aberto, alpha ignorado declarado |
| 6 | Lock/edição nas células e wiring do workspace | Um tema por vez; célula só com selos AA |
| 7 | `SuggestionsPanel`, `SecondaryField`, `RestoreControls` | Aceite por tema/ambos, inviável sem ação, confirmações |
| 8 | i18n nos 5 idiomas | Paridade e compilação verdes |
| 9 | Testes novos e estendidos | 17 arquivos `layout-harmonizer-*` verdes (6 novos) |
| 10 | ADRs 151/167, emenda 158, `PARAMETROS`, README, SPEC, glossário | Docs alinhadas à F3 |
| 11 | Gates completas + relatório | Parada para revisão, sem commit/push |

## Entregáveis

1. Código em `resources/js/tools/layout-harmonizer/` (motor, 3 libs, composable,
   7 componentes e o wrapper de popover).
2. i18n em `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` (bloco
   `layout-harmonizer`, grupos `edit`, `lock`, `restore`, `secondary`,
   `suggestions` e `edit_notice`).
3. `docs/adr/ADR-151-colors-lh-bloqueios.md` e
   `docs/adr/ADR-167-colors-lh-estado-efetivo-e-ajustes.md`; emenda datada em
   `docs/adr/ADR-158-colors-lh-contraste.md`.
4. Atualizações: `ADR-MAPEAMENTO.md`, `PARAMETROS.md`, `docs/colors/README.md`,
   `docs/SPEC.md` (§5 e §9), `docs/glossario.md`.
5. Relatório, plano e este plansfacts.

## O que NÃO foi feito (por decisão de escopo)

- Nenhuma persistência, rascunho, cota, salvamento ou exportação MD/CSS/ZIP.
- Nenhum protótipo novo, viewer público, login CTA ou prop de backend.
- Nenhuma rota, migration, dependência ou `composer`/`npm install`.
- Nenhum commit ou push.
