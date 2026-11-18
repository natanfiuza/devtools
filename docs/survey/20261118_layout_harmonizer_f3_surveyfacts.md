# Survey — Layout Harmonizer F3 (edição, bloqueios e contraste)

Data: 2026-11-18
Branch: `feature/layout-harmonizer-fase-3`
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase3-edicao_bloqueios_contraste.md`
Base: F1 (motor puro) e F2 (tool registrada e quadro semântico) concluídas.

## Contexto da tarefa

A F2 entregou o quadro somente leitura: seed, estratégia e temas geram os 33
tokens e os 16 checks, mas não há como corrigir um valor. O prompt da F3 pede o
controle manual: editar e bloquear tokens por tema, restaurar, voltar a geração
automática, secundária manual com retorno explícito, meta AA/AAA para
sugestões, sugestões que nunca se aplicam sozinhas, aceite no tema escolhido ou
nos dois, manutenção do bloqueio ao aceitar, prévia dos efeitos em dependentes
automáticos, avisos de combinação inviável e snapshot consistente do estado
efetivo. Fora da F3: salvar, quota, rascunho, exportações e protótipos.

Regras em vigor: GIT só pelo usuário (gitpr); toda string visível via i18n (5
idiomas); tools do cliente 100% no navegador; não reabrir decisões aprovadas;
não inventar defaults silenciosos; migrations retrocompatíveis (nenhuma nesta
fase).

## Decisões do grill

| # | Tema | Decisão |
| --- | --- | --- |
| 1 | Manuais × regeneração | Trocar seed/estratégia descarta overrides não bloqueados, com aviso posterior; bloqueios prevalecem |
| 2 | Secundária manual | Cor de entrada do motor, variantes derivadas por tema; sobrevive a seed/estratégia até reset explícito |
| 3 | Sugestão | Só o foreground (`on-*`), menor delta de L OKLCH, h/c preservados; sem solução na meta → inviável |
| 4 | Restaurar | Token, tema e sistema; confirmação só quando há mudanças a perder |
| 5 | Edição | Popover sobre reka Popover com swatch, `ColorInputField`, lock, restaurar, aplicar/cancelar |
| 6 | Painel | Abaixo dos quadros; AA/AAA, proposta, razões e aceite por tema/ambos; células só com AA |
| 7 | Cascata | Editar base não recalcula derivados |
| 8 | Constantes | Passo 0,005, máx. 200 passos, épsilon 0,001 |
| 9 | AAA | Meta só de sugestão; nunca altera cores |
| 10 | Snapshot | Função pura, sem UI, com versões e estado efetivo |
| 11 | ADRs | 151 (bloqueios), 167 (estado efetivo/ajustes), emenda datada no 158 |
| 12 | i18n | Só `tools.layout-harmonizer.*` nos 5 idiomas |

## Fatos levantados (verificados no código)

- O motor já aceitava `secondary?: Rgb|null`; não havia overrides. Os checks
  são calculados dentro de `buildBoard`, antes de `evaluateContrast`, então o
  override entra exatamente nesse ponto: sRGB exato, `source: 'manual'`,
  `clipped: false`, sem reconstruir `on-*`.
- `LAYOUT_CONTRAST_PAIRS` tem 16 pares; `on-primary`/`on-secondary`/`on-accent`
  são medidos contra três fundos (base, hover, active) — o que governa a
  viabilidade de uma proposta única.
- `canonicalKey` (em `lib/color/format.ts`) serve de assinatura estável para a
  seed efetiva e a secundária manual; o fundo já está dentro da seed efetiva.
- O `watch` da F2 observa texto/RGBA da seed, estratégia e fundo. A meta fica
  fora dele: trocar AA/AAA preserva a identidade do `result`.
- `ColorInputField` não aceitava `id`/`testId`; o editor no popover precisa de
  ids próprios para não colidir com o picker da seed. Props opcionais mantêm o
  padrão anterior.
- Popovers do reka renderizam em portal (`document.body`); os testes consultam
  o conteúdo por `document.querySelector` + `DOMWrapper` e limpam o body no
  `afterEach`.
- i18n é folha a folha nos 5 arquivos, com compilação estrita e `@`/`|` crus
  proibidos.

## Achado de produto: AA não é universalmente alcançável

A premissa do plano de que “AA é sempre alcançável (preto/branco)” é falsa para
os tokens `on-*` medidos contra base, hover e active. Quando os três fundos
cruzam a faixa de luminância média, um foreground claro passa na base e falha
em hover/active, e um foreground escuro faz o contrário. Exemplo verificado:

- `#3b82f6`, tema claro: `on-primary` branch/reprovado; nenhuma luminosidade
  atende AA nos três fundos → todas as sugestões AA do quadro saem inviáveis.
- `#777777`, tema escuro: hover/active clareiam, a faixa permite uma proposta
  viável → sugestões AA com `proposal` e razões ≥ 4,5.
- AAA: `#3b82f6` só tem proposta viável no token de fundo único (no tema
  claro); os demais são inviáveis.
- Nenhuma seed amostrada produziu proposta viável para o mesmo token nos dois
  temas. O aceite “nos dois temas” continua implementado e é renderizado
  quando as duas linhas têm proposta; na prática é raro.

Isso é consequência da regra aprovada de exigir todos os fundos numa só
proposta — não um defeito — e está coberto por testes com seeds fixas nos dois
sentidos (azul inviável, cinza viável). O aviso não bloqueia o trabalho nem
altera cores.

## Relatório de testes da fase

- `npm run test` — **145 arquivos / 1770 testes**, todos verdes (inclui os 17
  arquivos `layout-harmonizer-*`, 6 deles novos nesta fase).
- `npm run types:check` — limpo.
- `npm run format:check` — limpo.
- `npm run lint:check` — **0 erros** (25.400 warnings de estilo pré-existentes
  no repo).
- `php artisan test tests/Feature/Tools tests/Feature/SitemapTest.php tests/Feature/TranslationsParityTest.php`
  — 51 testes / 18.403 asserções, verde.
- `composer test` — pint ok, phpstan 0 erros, Pest **1737 testes / 31.119
  asserções**, verde.

## Arquivos-chave

- Motor: `engine/types.ts` (`LayoutOverrides`), `engine/generate.ts` (override
  antes do contraste).
- Estado puro: `lib/editState.ts`, `lib/suggestions.ts`, `lib/snapshot.ts`.
- Estado Vue: `composables/useLayoutSystem.ts` (ações explícitas chamam a
  mesma `regenerate`, sem segundo watch).
- UI: `components/TokenEditorPopover.vue`, `SuggestionsPanel.vue`,
  `SecondaryField.vue`, `RestoreControls.vue`, `TokenCell.vue` e
  `components/ui/popover/`.
- Testes: `layout-harmonizer-{overrides,edits,suggestions,snapshot,token-editor,suggestions-panel,system,workspace}.test.ts`.
