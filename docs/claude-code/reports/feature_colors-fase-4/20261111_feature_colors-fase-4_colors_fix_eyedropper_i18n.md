# Relatório — conta-gotas do Palette Generator exibindo a chave i18n

- Data: 2026-11-11
- Branch: `feature/colors-fase-4`
- Tarefa: corrigir o botão do conta-gotas que exibia `tools.shared.color_picker.preview.eyedropper` no lugar do texto

## Sintoma

No Color Palette Generator, o botão do conta-gotas mostrava a chave crua. O botão usa o componente compartilhado `ColorPickerControls.vue`, que chama `t('tools.shared.color_picker.preview.eyedropper')` ([ColorPickerControls.vue:110](../../../resources/js/components/colors/ColorPickerControls.vue)).

## Causa

Nenhum dos 5 `lang/*/tools.php` tinha `shared.color_picker.preview`. O texto existia apenas em `tools['html-color-picker'].preview.eyedropper`, que pertence à página do HTML Picker. Como o vue-i18n devolve a própria chave quando ela não existe, a UI renderizou a chave.

Por que as guardas não pegaram:

- o teste de UI do gerador ([palette-generator.test.ts:183](../../../resources/js/__tests__/palette-generator.test.ts)) usa mensagens falsas com a chave injetada;
- `i18n-messages.test.ts` só compila chaves existentes — uma chave ausente em **todos** os locales passa pela paridade PHP e pela compilação;
- `ColorPickerControls` é importado apenas por `GeneratorWorkspace.vue`, então nenhuma outra tela expôs o problema.

## Mudanças

### Traduções — `lang/{pt_BR,pt_PT,fr,es,en}/tools.php`

Bloco `preview.eyedropper` adicionado sob `shared.color_picker` (entre `alpha` e `channels`), reaproveitando os textos já aprovados no HTML Picker:

| Locale | Texto |
| --- | --- |
| `pt_BR` | Conta-gotas da tela |
| `pt_PT` | Conta-gotas do ecrã |
| `fr` | Pipette de l’écran |
| `es` | Cuentagotas de pantalla |
| `en` | Screen eyedropper |

Nenhum texto novo foi inventado e nenhum componente foi acoplado ao bloco de uma tool.

### Helper de testes — `resources/js/__tests__/support/langMessages.ts` (novo)

`exportMessages()` extraído do script PHP de `i18n-messages.test.ts`: lê `config/locales.php` e `lang/{locale}/{namespace}.php` direto, sem bootstrap do Laravel, como o `HandleInertiaRequests` faz. `i18n-messages.test.ts` passou a importá-lo — a leitura do PHP existe em um lugar só.

### Guarda — `resources/js/__tests__/shared-i18n-keys.test.ts` (novo)

- lê os `.vue` de `resources/js/components/colors/` como texto via `import.meta.glob(..., { query: '?raw', eager: true })`;
- extrai os literais estáticos `tools.shared.color_picker.*` (chaves dinâmicas como `formats.${format}` ficam fora por regex);
- exige valor string não vazio nos 5 locales e falha listando `locale:chave`;
- assere `keys.length > 0` para não passar silenciosamente se o glob quebrar.

## Verificação

| Verificação | Resultado |
| --- | --- |
| Reprodução: remover `preview` de `pt_BR` | Guarda falhou com `expected [ 'pt_BR:preview.eyedropper' ] to deeply equal []` — o bug é reproduzido pelo teste |
| Chave restaurada | Verde |
| `php -l` nos 5 `lang/*/tools.php` | Sem erros |
| `npx vitest run shared-i18n-keys i18n-messages palette-generator` | 3 arquivos / 26 testes verdes |
| `npm run test` (suíte completa) | **1363 testes passando** |
| `npm run types:check` | Sem erros de TypeScript |
| `npm run lint:check` | **0 erros** (1190 warnings de `vue/html-indent` pré-existentes em arquivos não alterados) |
| `npx eslint` nos 3 arquivos de teste alterados | Sem avisos |

### Manual (pendente)

- Abrir `/{locale}/tools/colors/palette-generator` nos 5 idiomas em navegador com `EyeDropper` (ex.: Chrome) e confirmar “Conta-gotas da tela” / “Conta-gotas do ecrã” / “Pipette de l’écran” / “Cuentagotas de pantalla” / “Screen eyedropper”;
- Confirmar que o HTML Color Picker continua inalterado.

## Arquivos

- `lang/pt_BR/tools.php`, `lang/pt_PT/tools.php`, `lang/fr/tools.php`, `lang/es/tools.php`, `lang/en/tools.php` — bloco `preview.eyedropper`
- `resources/js/__tests__/support/langMessages.ts` — novo helper
- `resources/js/__tests__/i18n-messages.test.ts` — passa a usar o helper
- `resources/js/__tests__/shared-i18n-keys.test.ts` — nova guarda
- `docs/plans/feature_colors-fase-4/20261111_colors_fix_eyedropper_i18n_plansfacts.md`
- `docs/survey/20261111_colors_fix_eyedropper_i18n_surveyfacts.md`

## Sem commit

Nenhum commit ou push foi feito, conforme a regra do projeto — o usuário commita via gitpr.
