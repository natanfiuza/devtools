# Survey — conta-gotas do Palette Generator exibindo chave i18n

- Data: 2026-11-11
- Branch: `feature/colors-fase-4`
- Tarefa: corrigir o botão do conta-gotas que mostra `tools.shared.color_picker.preview.eyedropper`

## Contexto

Relato do usuário: no Color Palette Generator o botão de conta-gotas exibe a chave crua em vez do texto. O picker compacto é o componente compartilhado `ColorPickerControls.vue`, usado apenas por essa tool; o HTML Color Picker tem um botão próprio.

## Fatos levantados

- [ColorPickerControls.vue:110](../../resources/js/components/colors/ColorPickerControls.vue) chama `t('tools.shared.color_picker.preview.eyedropper')`.
- Nenhum `lang/*/tools.php` has `shared.color_picker.preview` (verificado nos 5 idiomas).
- A chave existe em `tools['html-color-picker'].preview.eyedropper` nos 5 idiomas; é o texto aprovado que pode ser reaproveitado.
- As demais chaves compartilhadas (`area`, `hue`, `alpha`, `channels`, `formats`, `color_input`) existem nos 5 idiomas; só `preview.eyedropper` faltava.
- [palette-generator.test.ts:183](../../resources/js/__tests__/palette-generator.test.ts) injeta a chave nas mensagens falsas — o teste da UI não podia detectar a ausência.
- `i18n-messages.test.ts` só compila chaves existentes; chave faltando em todos os locales passa pela paridade PHP e pela compilação.
- `ColorPickerControls` é importado só por `GeneratorWorkspace.vue` (Palette Generator).

## Decisões

1. Adicionar a chave no bloco compartilhado nos 5 idiomas (não acoplar o componente ao bloco de uma tool).
2. Criar guarda Vitest para literais estáticos `tools.shared.color_picker.*` contra os `lang/` reais; chaves dinâmicas ficam de fora.

## Textos reaproveitados

| Locale | Texto |
| --- | --- |
| pt_BR | Conta-gotas da tela |
| pt_PT | Conta-gotas do ecrã |
| fr | Pipette de l’écran |
| es | Cuentagotas de pantalla |
| en | Screen eyedropper |

## Verificação executada

- Reprodução: removendo a chave de `pt_BR`, a guarda falhou com `pt_BR:preview.eyedropper`.
- Com a chave restaurada: `shared-i18n-keys`, `i18n-messages` e `palette-generator` verdes.
- `npm run test`, `npm run types:check`, `npm run lint:check` (resultados no relatório).
- Manual pendente: abrir a tool nos 5 idiomas em navegador com `EyeDropper`.
