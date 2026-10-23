# Plano de Implementacao — Correcao de ToolProps em Base64 Image

**Data:** 2026-10-23  
**Branch:** `develop_natan`  
**Identificador:** `correcao_base64_image_toolprops`  
**Referencia Survey:** `docs/survey/20261023_base64_image_toolprops_surveyfacts.md`  
**Referencia ADR:** `docs/adr/ADR-043-props-por-tool-na-pagina-generica.md`

---

## 1. Objetivo

Corrigir a recepcao de propriedades (`toolProps`) na ferramenta `base64-image` para que a aba Codificador (Imagem -> Base64) e a aba Historico renderizem normalmente sem erros de `undefined`, alinhando a implementacao ao ADR-043 e adicionando tolerancia a falhas e testes no Vitest.

---

## 2. Diagnostico e Causa Raiz

1. `resources/js/pages/tools/Show.vue` aplica `v-bind="toolProps"`, desempacotando as chaves do objeto retornado por `Base64ImageToolProps::for($user)` como propriedades individuais no componente da tool:
   - `isAuthenticated: boolean`
   - `isPaidPlan: boolean`
   - `maxUploadBytes: number`
   - `downloadThresholdBytes: number`
   - `retentionHours: number`
2. `resources/js/tools/base64-image/Tool.vue` declarava `defineProps<{ toolProps: Base64ImageToolProps }>()`, esperando receber um objeto aninhado com o nome `toolProps`. Como o Vue desempacotou as propriedades na raiz, `props.toolProps` resultou em `undefined`.
3. `Tool.vue` passava `:tool-props="props.toolProps"` (avaliado como `undefined`) para `EncoderPanel.vue` e `HistoryPanel.vue`.
4. Nos componentes filhos, chamadas como `toolProps.isPaidPlan` e `toolProps.isAuthenticated` causavam excecoes nao tratadas (`TypeError: Cannot read properties of undefined`), impedindo o ciclo de montagem e renderizacao do Vue.

---

## 3. Etapas de Execucao

### Etapa 1: Ajuste em `resources/js/tools/base64-image/Tool.vue`
- Adicionar `defineOptions({ inheritAttrs: false })` para evitar vazamento dos limites de `Show.vue` no DOM (em conformidade com ADR-043).
- Tipar as propriedades diretamente com `Base64ImageToolProps` usando `withDefaults`:
  ```ts
  defineOptions({ inheritAttrs: false });

  const props = withDefaults(defineProps<Base64ImageToolProps>(), {
      isAuthenticated: false,
      isPaidPlan: false,
      maxUploadBytes: 2 * 1024 * 1024,
      downloadThresholdBytes: 2 * 1024 * 1024,
      retentionHours: 24,
  });
  ```
- Repassar o objeto de props reativo `props` para os componentes filhos:
  - `<EncoderPanel :tool-props="props" @conversion-done="onConversionDone" />`
  - `<HistoryPanel ref="historyRef" :tool-props="props" />`

### Etapa 2: Programacao defensiva em `EncoderPanel.vue` e `HistoryPanel.vue`
- Em `EncoderPanel.vue`:
  - Usar `withDefaults` na definicao de props com valores seguros caso `toolProps` venha ausente.
  - Assegurar que `formattedMaxLimit` e os condicionais do template utilizem encadeamento seguro / fallbacks.
- Em `HistoryPanel.vue`:
  - Usar `withDefaults` na definicao de props com valores padrao (`isAuthenticated: false`, etc.).

### Etapa 3: Cobertura de Testes Automatizados no Vitest
- Criar teste em `resources/js/__tests__/base64-image-component.test.ts` utilizando `@vue/test-utils` e montando `Tool.vue`:
  - Cenário 1: Visitante (usuario anonimo, plano Free, limites padrao de 2MB) -> valida que o dropzone renderiza, badge exibe 2 MB, badge Pro nao aparece, aba do decodificador e historico estao acessiveis.
  - Cenário 2: Usuario autenticado com plano pago (25MB, Pro) -> valida exibicao do badge de plano Pro e limite formatado de 25 MB.
  - Cenário 3: Tolerancia a props ausentes ou parciais -> valida que o componente nao lanca excecao em tempo de renderizacao.

### Etapa 4: Validacao e Verificacao
- Executar `npm run types:check` (`vue-tsc --noEmit`).
- Executar `npm run test` (Vitest).
- Executar `composer test` (Pest + Pint + PHPStan).
