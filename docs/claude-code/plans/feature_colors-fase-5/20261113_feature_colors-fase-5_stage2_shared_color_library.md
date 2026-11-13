# Plano — Fase 5, Etapa 2: cliente compartilhado `useColorLibrary` e importação local

- Data: 2026-11-13
- Branch: `feature/colors-fase-5`
- Pré-requisito: Etapa 1 (backend de histórico e favoritos) concluída e verde.

## Objetivo

Levar a biblioteca pessoal de cores para as duas tools que a compartilham — o
HTML Color Picker e o Color Palette Generator — com um único cliente Vue:

- anônimo: histórico e favoritos só no `localStorage`, sem nenhuma requisição;
- logado: histórico e favoritos da conta, com escrita otimista, paginação por
  cursor e uma ação explícita para importar o histórico local anônimo;
- a mesma UI (painéis de histórico e favoritos) nas duas tools;
- todas as strings novas nos cinco idiomas.

## Servidor (props)

- `App\Support\Colors\ColorsToolProps`: novo `library()` com `authenticated`,
  `anonymousHistoryLimit`, `anonymousFavoritesLimit`, `historyPerPage`,
  `favoritesPerPage` e `importBatch`, sempre lidos de `config/colors.php`.
- `ColorsToolProps::for()` passa a somar o bloco `library`.
- `App\Support\ToolProps`: `html-color-picker` deixa de receber objeto vazio e
  recebe `library`.
- `PaletteController@edit`: editor de paleta recebe `library` junto de `form`
  e `image`.
- `config/colors.php`: `library.anonymous_history_limit` (20) e
  `library.anonymous_favorites_limit` (50), com overrides por `.env`; os
  limites do anônimo são os únicos números novos — o resto já veio da Etapa 1.
- `DELETE /api/tools/colors/favorites` sem `color` limpa todos os favoritos da
  conta (o contrato antigo exigia uma cor). Pedido `DeleteColorFavoriteRequest`
  novo, `color` opcional.

## Cliente

- `resources/js/types/colors.ts`: `ColorLibraryToolProps` e `library` em
  `PaletteEditorProps` (camelCase, como as demais props de tool).
- `resources/js/lib/colors/api.ts`: `colorsApi<T>()` sobre `/api/tools/colors`,
  CSRF pelo cookie `XSRF-TOKEN`, `Accept: application/json`,
  `X-Requested-With: XMLHttpRequest` e `credentials: 'same-origin'`.
- `resources/js/lib/colors/library.ts`: `useColorLibrary(props, {storage})`.
  - anônimo: leitura/escrita só no storage local (limites do servidor),
    `load()` não faz fetch;
  - logado: `load()` busca histórico e favoritos em paralelo com cursor,
    `loadMore*()` pagina, `addHistory`/`toggleFavorite`/`clear*` são otimistas
    com rollback e `failed` na falha;
  - `importLocalHistory()` envia o histórico local em lotes de `importBatch`,
    limpa o `localStorage` só depois de todos os lotes passarem e recarrega.
- Painéis compartilhados em `resources/js/components/colors/`:
  `HistoryPanel.vue`, `FavoritesPanel.vue` e `ImportLocalHistoryDialog.vue`
  (confirmação explícita com a contagem). Os painéis antigos exclusivos do
  picker foram removidos.
- Picker (`tools/html-color-picker/Tool.vue`): recebe `library`, injeta o
  composable em `onFinalize` (histórico + última cor), restaura a última cor e
  usa a aba "Biblioteca" com os painéis compartilhados.
- Gerador (`tools/palette-generator/GeneratorWorkspace.vue` e `Tool.vue`):
  recebe `library`, registra cada base finalizada no histórico, ganha a aba
  "Biblioteca" (favoritos + histórico, aplicar cor volta para a base) e o botão
  de favoritar a base atual.
- `pages/dashboard/colors/PaletteEditor.vue`: repassa `library` ao workspace e
  aceita `library` como aba inicial.
- i18n: `tools.shared.library.*` e `palette-generator.tabs.library` nos cinco
  idiomas; blocos mortos `html-color-picker.history`/`favorites` removidos.

## Testes

- `resources/js/__tests__/color-library.test.ts` (novo): anônimo sem fetch,
  leitura do storage existente, carga autenticada com cursor, histórico
  otimista com rollback, favorito otimista com rollback, importação em lotes
  limpando o storage só no sucesso e importação falha preservando o local.
- `color-storage.test.ts`: limites configuráveis e cap de histórico 20.
- `palette-generator.test.ts`: aba Biblioteca, favoritar a base e persistência
  no `localStorage`; dicionário i18n completado.
- `palette-editor.test.ts`: factory de props com `library`.
- Pest: `ColorsToolPropsTest` cobre o bloco `library` (valores, config e
  `authenticated`); `ToolPageTest` cobre as props do picker.

## Verificação

1. `php artisan test --filter=Colors`
2. `composer test`
3. `npm run test`
4. `npm run types:check`
5. `npm run lint:check`
6. `npm run build`

Sem commit/push. Relatório em
`docs/claude-code/reports/feature_colors-fase-5/`.

## Fora desta etapa

Paletas favoritas (Etapa 3), presets (Etapa 4), exportação/importação de tokens
(Etapas 5–6), ADRs e documentação final (Etapa 7).
