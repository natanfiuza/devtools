# Survey — Correcao de ToolProps em Base64 Image (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Ocorreu um erro em Base64 Image Encoder / Decoder, a aba Codificador (Imagem -> Base64) nao esta exibindo nada, e no console exibe `TypeError: Cannot read properties of undefined (reading 'isPaidPlan')` e `TypeError: Cannot read properties of undefined (reading 'isAuthenticated')`.
- **Tela / Modulo:** Ferramenta Base64 Image Encoder / Decoder (`/{locale}/tools/base64-image`), componentes `resources/js/tools/base64-image/Tool.vue`, `EncoderPanel.vue`, `HistoryPanel.vue` e casca generica `resources/js/pages/tools/Show.vue`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** Inspecao profunda de codigo de `Show.vue`, `Base64ImageToolProps.php`, `ToolProps.php`, `ADR-043`, `Tool.vue`, `EncoderPanel.vue` e `HistoryPanel.vue`. Rodada unica com 3 decisoes arquiteturais e de testes alinhadas.
- **Arquivos-chave lidos:**
  - `resources/js/pages/tools/Show.vue`
  - `app/Http/Controllers/ToolController.php`
  - `app/Support/ToolProps.php`
  - `app/Support/Base64Image/Base64ImageToolProps.php`
  - `resources/js/tools/base64-image/Tool.vue`
  - `resources/js/tools/base64-image/EncoderPanel.vue`
  - `resources/js/tools/base64-image/HistoryPanel.vue`
  - `resources/js/tools/base64-image/types.ts`
  - `docs/adr/ADR-043-props-por-tool-na-pagina-generica.md`
  - `tests/Feature/Tools/Base64ImageTest.php`

## 2. Decisoes das rodadas

### Rodada 1 — Alinhamento Arquitetural, Tolerancia a Falhas e Testes
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Alinhamento do contrato de props em `Tool.vue` com ADR-043 | **Opcao A (Recomendada):** Declarar `defineOptions({ inheritAttrs: false })` e desestruturar as props diretamente com `defineProps<Base64ImageToolProps>()` com valores padrao (`withDefaults`), repassando o objeto `props` para `:tool-props="props"` nos componentes filhos (`EncoderPanel` e `HistoryPanel`). |
| Q2 | Tolerancia a falhas e programacao defensiva nos subcomponentes | **Opcao A (Recomendada):** Implementar guardas defensivas e valores padrao com `withDefaults` em `EncoderPanel.vue` e `HistoryPanel.vue`, prevenindo quebras em caso de atraso na hidratacao ou props nulas. |
| Q3 | Estrategia de testes para prevencao de regressao | **Opcao A (Recomendada):** Criar teste de renderizacao de componente no Vitest (`resources/js/__tests__/base64-image-component.test.ts` via `@vue/test-utils`) montando `Tool.vue` com as props desempacotadas de `Show.vue`, garantindo cobertura para visitante e plano pago. |

### Defaults assumidos (nao contestados)
- Nenhum ADR novo e necessario, pois o comportamento esperado ja esta normatizado pelo ADR-043.
- Nao ha novos termos para o glossario (`docs/glossario.md`).
- A logica de backend (`Base64ImageToolProps.php` e testes Pest) ja enviava a estrutura correta; a correcao e estritamente no frontend Vue.

## 3. Relatorio de fatos levantados

### Causa Raiz do Erro no Vue
- `Show.vue` executa `<component :is="toolComponent" v-bind="toolProps" />`. No Vue, `v-bind="objeto"` desempacota cada chave do objeto como uma prop individual de primeiro nivel (`isAuthenticated`, `isPaidPlan`, `maxUploadBytes`, `downloadThresholdBytes`, `retentionHours`), alem dos quatro limites de `config/tools.php`.
- O componente `base64-image/Tool.vue` declarava `defineProps<{ toolProps: Base64ImageToolProps }>()`, aguardando uma unica prop chamada `toolProps`.
- Consequentemente, `props.toolProps` ficava `undefined`. Ao repassar `:tool-props="props.toolProps"`, os componentes filhos `EncoderPanel.vue` e `HistoryPanel.vue` recebiam `undefined`.
- No `EncoderPanel.vue`, a computada `formattedMaxLimit` e o template `<Badge v-if="toolProps.isPaidPlan">` tentavam acessar propriedades de `undefined`, interrompendo o ciclo de vida do componente e deixando a aba do Codificador vazia.

### Conformidade com ADR-043 e AI SQL Builder
- O ADR-043 explicita que a pagina generica desempacota o pacote `toolProps` e a ferramenta deve declarar as props que consome, usando `inheritAttrs: false` para evitar que os limites das tools de navegador vazem para atributos HTML no DOM.
- O componente `ai-sql-builder/Tool.vue` ja adota `defineOptions({ inheritAttrs: false })` e `defineProps<AiToolProps>()`. A ferramenta `base64-image` agora adota a mesma convencao.
