# Relatório — Layout Harmonizer F3 (edição, bloqueios e contraste)

Data: 2026-11-18
Branch: `feature/layout-harmonizer-fase-3`
Fases anteriores: F1 (motor puro) e F2 (interface do quadro), em 2026-11-16
Autorização: grill de 2026-11-16/18 ("Nova branch fase 3 criada, pode iniciar a
fase 3"), com decisões em
`docs/survey/20261118_layout_harmonizer_f3_surveyfacts.md`, plano em
`docs/claude-code/plans/feature_layout-harmonizer-fase-3/` e plano de execução
em `docs/plans/feature_layout-harmonizer-fase-3/`.

## 1. Escopo entregue

A F3 fechou o controle manual do Layout Harmonizer, ainda **sem persistir
nada**:

- **edição por token e tema** em popover, com aplicar/cancelar, validação e
  aviso de que o alpha é ignorado (tokens do quadro são opacos);
- **bloqueio por token/tema** (`aria-pressed`), que sobrevive à troca de seed e
  de estratégia e não é sobrescrito pelo aceite de uma sugestão (ADR-151);
- **descarte de manuais não bloqueados** quando seed/estratégia/secundária
  mudam, com aviso próprio que não marca o quadro como obsoleto;
- **secundária manual** com origem `manual` no resumo e botão explícito de
  voltar ao automático; texto inválido preserva o último quadro válido;
- **restaurar** por token (na célula), por tema e por sistema (junto ao
  quadro), com diálogo de confirmação; restaurar o sistema não toca na
  secundária manual;
- **meta AA/AAA** selecionável no painel de sugestões, que **nunca altera
  cores** nem regenera o quadro;
- **sugestões por token de frente × tema**: menor delta de L OKLCH, matiz e
  croma preservados, todos os fundos atendidos, razões atual→projetada;
- **aceite explícito** no tema da linha ou nos dois temas (cada tema recebe a
  própria proposta; tema que já passa ou é inviável é ignorado); nada se
  aplica automaticamente;
- **aviso de combinação inviável** (`proposal: null`), sem ação de aceite;
- **snapshot puro do estado efetivo** com versões, seed original/efetiva,
  overrides, bloqueios, aceites e diagnósticos AA (ADR-167).

## 2. Arquivos

### Criados — código (`resources/js/tools/layout-harmonizer/`)

| Arquivo | Conteúdo |
|---|---|
| `lib/editState.ts` | overrides com origem (`edit`/`accept`/`lock`), locks, assinatura, descarte, restore e contagem |
| `lib/suggestions.ts` | proposta de menor passo de L, todos os fundos, metas AA/AAA, inviável |
| `lib/snapshot.ts` | snapshot congelado do estado efetivo, cópia profunda, versões |
| `components/TokenEditorPopover.vue` | editor: swatch, `ColorInputField`, lock, restaurar, aplicar/cancelar |
| `components/SuggestionsPanel.vue` | meta, linhas, razões, aceite por tema/ambos, inviável |
| `components/SecondaryField.vue` | secundária manual + voltar ao automático |
| `components/RestoreControls.vue` | restaurar tema/sistema com `Dialog` |
| `components/ui/popover/` | wrapper shadcn sobre reka Popover (`Popover`, `PopoverTrigger`, `PopoverContent`, `index.ts`) |

### Criados — testes

- `layout-harmonizer-overrides.test.ts` (motor)
- `layout-harmonizer-edits.test.ts` (`editState`)
- `layout-harmonizer-suggestions.test.ts`
- `layout-harmonizer-snapshot.test.ts`
- `layout-harmonizer-token-editor.test.ts`
- `layout-harmonizer-suggestions-panel.test.ts`

### Criados — docs

- `docs/adr/ADR-151-colors-lh-bloqueios.md`
- `docs/adr/ADR-167-colors-lh-estado-efetivo-e-ajustes.md`
- `docs/plans/feature_layout-harmonizer-fase-3/20261118_f3_edicao_plansfacts.md`
- `docs/survey/20261118_layout_harmonizer_f3_surveyfacts.md`
- `docs/claude-code/plans/feature_layout-harmonizer-fase-3/20261118_feature_layout-harmonizer-fase-3_f3_edicao.md`
- este relatório

### Modificados

| Arquivo | Mudança |
|---|---|
| `engine/types.ts` | `LayoutOverrides` e `overrides?` em `GenerateLayoutInput` |
| `engine/generate.ts` | aplica overrides em `buildBoard` antes de `evaluateContrast`, sem cascata |
| `composables/useLayoutSystem.ts` | overrides/locks/meta/secundária/snapshot, assinatura, `editNotice`, ações explícitas |
| `LayoutWorkspace.vue` | wiring do editor, painel, secundária e restaurar |
| `components/TokenCell.vue` | lock no cabeçalho, editar, restaurar, selo de sugestão |
| `components/{TokenGroup,ThemeBoard,ThemeComparison,SeedSummary}.vue` | repasse de props e origem da secundária |
| `components/colors/ColorInputField.vue` | props opcionais `id`/`testId` (retrocompatível) |
| `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` | grupos `edit`, `lock`, `restore`, `secondary`, `suggestions`, `edit_notice` |
| testes `layout-harmonizer-{system,workspace}.test.ts` | cenários de edição, aceite, restore, meta e secundária |
| `docs/adr/ADR-158-colors-lh-contraste.md` | emenda datada (2026-11-18) |
| `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md` | F3 = 151 + ADR-167 |
| `docs/colors/layout-harmonizer/PARAMETROS.md` | parâmetros e versão das sugestões |
| `docs/colors/README.md` | seção do LH em F3; 13 ADRs em `docs/adr/` + ADR-167 |
| `docs/SPEC.md` | §5 e §9 com a F3 concluída |
| `docs/glossario.md` | termos "ajuste sugerido" e "estado efetivo" |

## 3. Decisões de implementação

### Overrides no motor, sem cascata

O override entra dentro de `buildBoard`, imediatamente antes de
`evaluateContrast`, como sRGB exato (`source: 'manual'`, `clipped: false`):
o contraste é remedido, mas `on-*`, variantes e estados automáticos não são
reconstruídos a partir da cor editada. Sem overrides, o resultado é idêntico ao
da F1 (fixtures e `LAYOUT_ALGORITHM_VERSION` intactos). Ids e temas
desconhecidos são ignorados. Precedência: override > secundária manual >
derivação.

### Assinatura e descarte

A regeneração usa uma assinatura estável (`canonicalKey` da seed efetiva,
estratégia e secundária manual) e só descarta overrides sem bloqueio quando a
assinatura realmente muda e a geração teve sucesso. O aviso de descarte é
separado do aviso de entrada inválida: `isStale` continua falso, porque o quadro
exibido é resultado da última geração bem-sucedida.

### Bloqueio e aceite

Ligar o bloqueio captura o valor efetivo como override de origem `lock`;
desligar remove só esse congelamento. O aceite grava o valor proposto e mantém
o bloqueio; restaurar o token remove override, bloqueio e os aceites
registrados daquele token. Restaurar o sistema não mexe na secundária manual,
que tem reset próprio.

### Sugestões

Uma sugestão por foreground × tema (nunca por par), exigindo a meta em todos os
fundos do token. O passo é 0,005 com teto de 200 em cada direção, preservando
h/c, e a razão é medida no sRGB mapeado com folga de 0,001. O desempate no
mesmo passo é a maior razão mínima e, depois, a direção mais clara. Tudo puro:
`target` fica fora do watch, então trocar AA/AAA não regenera nada.

## 4. Achado: AA não é universalmente alcançável

O plano partia de que "AA é sempre alcançável (preto/branco)". Com tokens
medidos contra base, hover e active, isso não se sustenta quando os três fundos
cruzam a faixa de luminância média: um foreground claro passa na base e falha
nos estados escuros; um foreground escuro faz o inverso. Verificado com seeds
fixas:

- `#3b82f6`, AA: todas as sugestões do quadro saem inviáveis.
- `#777777`, AA: há propostas viáveis no tema escuro (os estados clareiam).
- `#3b82f6`, AAA: só o token de fundo único tem proposta viável (tema claro).
- Nenhuma seed amostrada teve proposta viável para o mesmo token nos dois
  temas; o botão "aceitar nos dois temas" continua implementado e renderizado
  quando as duas linhas têm proposta, mas é raro na prática.

O caso é consequência da regra aprovada de exigir todos os fundos em uma só
proposta, não um defeito; a interface mostra o aviso de inviável sem ação e
sem alterar cores. Está coberto por testes nos dois sentidos (azul inviável,
cinza viável) e registrado no ADR-167, na emenda do ADR-158 e em
`PARAMETROS.md`.

## 5. Testes e gates — resultados reais

| Gate | Resultado |
|---|---|
| `npm run test` | **145 arquivos / 1770 testes**, todos verdes (17 arquivos `layout-harmonizer-*`, 6 novos nesta fase) |
| `npm run types:check` | limpo |
| `npm run format:check` | limpo |
| `npm run lint:check` | **0 erros** (25.400 warnings de estilo pré-existentes no repo) |
| `php artisan test tests/Feature/Tools tests/Feature/SitemapTest.php tests/Feature/TranslationsParityTest.php` | **51 testes / 18.403 asserções**, verde |
| `composer test` | pint ok, phpstan 0 erros, Pest **1737 testes / 31.119 asserções**, verde |

Observação de execução: a primeira rodada da suíte Vitest foi feita em paralelo
com `composer test` e dois arquivos de teste de componente falharam por
temporização; o arquivo visível no log (`layout-harmonizer-workspace.test.ts`)
passou isolado e a suíte completa passou em rodada sequencial (145/1770). Não
houve mudança de código entre as rodadas.

Conferências específicas da fase:

- overrides não mudam o resultado quando ausentes (fixtures da F1 intactas) e
  não geram cascata;
- bloqueio sobrevive a seed/estratégia; manual sem bloqueio cai com
  `editNotice = 'discarded'` e `isStale` falso;
- trocar a meta preserva a identidade do `result`;
- aceite em um tema não altera o outro; "nos dois" aplica cada proposta e
  ignora inviável;
- snapshot não compartilha referências com o quadro e lista só diagnósticos AA;
- popover é consultado via `document.body` com limpeza no `afterEach`;
- nenhum acesso a storage em toda a fase.

## 6. Fora de escopo (mantido)

Persistência, rascunho/`localStorage`, cota, salvar, exportação MD/CSS/ZIP,
protótipos, viewer público, login CTA, props de backend, rotas novas, migration,
dependências novas, commit e push.

## 7. Estado da árvore e próxima fase

`git status` contém apenas os arquivos da F3 (código, testes, i18n, docs,
ADR-151/167 e artefatos de plano/survey) mais dois relatórios `.gitpr` da
sessão da F2 que já estavam na árvore antes desta fase. Nada foi commitado nem
enviado. **Parada para revisão**: a F4 não foi iniciada.
