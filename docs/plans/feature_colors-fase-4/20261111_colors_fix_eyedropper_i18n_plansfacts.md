# Plano — fix da chave i18n do conta-gotas (Colors)

- Data: 2026-11-11
- Branch: `feature/colors-fase-4`
- Origem: `/grill-with-docs` — “Em Color Palette Generator o botão de conta-gotas está mostrando `tools.shared.color_picker.preview.eyedropper` no lugar do texto”

## Entendimento compartilhado

O picker compartilhado (`ColorPickerControls.vue`) usa `tools.shared.color_picker.preview.eyedropper`, mas os 5 `lang/*/tools.php` não têm `shared.color_picker.preview`; o texto existe apenas em `tools['html-color-picker'].preview.eyedropper`. O vue-i18n devolve a chave crua e a UI mostra isso.

## Decisões

1. **Onde corrigir** (Q1 — recomendado aceito): adicionar `preview.eyedropper` em `tools.shared.color_picker` nos 5 idiomas, reaproveitando as traduções já aprovadas no HTML Picker. O componente compartilhado não depende do bloco de uma tool.
2. **Guarda** (Q2 — recomendado aceito): Vitest focado que varre os literais estáticos `tools.shared.color_picker.*` dos componentes compartilhados e exige presença nos 5 idiomas. Chaves dinâmicas (`formats.${format}`) ficam fora.

## Execução

1. `lang/{pt_BR,pt_PT,fr,es,en}/tools.php`: bloco `preview.eyedropper` sob `shared.color_picker`.
2. `resources/js/__tests__/support/langMessages.ts`: helper `exportMessages()` extraído de `i18n-messages.test.ts`.
3. `resources/js/__tests__/shared-i18n-keys.test.ts`: nova guarda.
4. Verificação: reprodução (teste falha sem a chave), suítes, tipos, lint e UI nos 5 idiomas.

## Fora de escopo

- Refatorar o mock de `palette-generator.test.ts` (continua isolado; a guarda olha as mensagens reais).
- Varrer todos os literais `t('...')` do front (Q2 opção C, rejeitada nesta rodada).
