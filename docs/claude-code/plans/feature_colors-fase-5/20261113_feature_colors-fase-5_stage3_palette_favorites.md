# Plano — Fase 5, Etapa 3: favoritos de paletas públicas e aba Favoritas

- Data: 2026-11-13
- Branch: `feature/colors-fase-5`
- Pré-requisito: Etapas 1 e 2 concluídas e verdes.

## Objetivo

Permitir que a conta favorite paletas públicas de outras contas, sem copiá-las,
e ter uma aba "Favoritas" em Minhas Paletas:

- favorito é só o vínculo com a origem; o retrato exibido é sempre o atual da
  paleta pública;
- favoritar a própria paleta é recusado (403) — ela já está em Minhas Paletas;
- paleta privada, arquivada ou inexistente responde 404, como a página pública;
- a listagem esconde favoritos de paletas que deixaram de ser públicas, sem
  apagar o vínculo (se voltar a ser pública, ele reaparece);
- escrita idempotente (favoritar/desfavoritar duas vezes não muda nada) e sob
  `throttle:colors-library`.

## Dados

- Migration `2026_11_13_000001_create_color_palette_favorites_table.php` (só
  criação de tabela): `ulid` PK, `user_id` e `color_palette_id` com FK em
  cascata, `timestamps`, unique `[user_id, color_palette_id]` e índice
  `[user_id, created_at]`.
- `App\Models\ColorPaletteFavorite` (`HasUlids`, `#[Fillable]`, `scopeForUser`,
  relações `user()` e `palette()`), mais `User::favoritePalettes()`.

## Contrato HTTP (JSON)

- `POST /api/tools/colors/palettes/{colorPalette}/favorite` → `{favorited:true}`
- `DELETE /api/tools/colors/palettes/{colorPalette}/favorite` → `{favorited:false}`
- Actions `FavoritePalette` (`firstOrCreate` com retry em corrida) e
  `UnfavoritePalette` (delete idempotente).
- `PaletteFavoriteController`: 401 sem conta, 404 para paleta não pública/ativa/
  sem slug, 403 (`AccessDeniedHttpException`, mantendo `error.code=forbidden` no
  envelope) para a própria paleta.
- `PaletteController@index`: `?tab=favorites` devolve `tab` e o paginador
  `favorites` (ordenado por `created_at` desc, desempate `id`), com
  `$presenter->favoriteRow()` (linha + `favorited_at`); o padrão continua
  `mine` com `favorites: null`.
- `PublicPaletteController@show`: novas props `favorited` e `canFavorite`.
- `PalettePresenter::favoriteRow()`.

## Cliente

- `types/colors.ts`: `PaletteTab`, `FavoritePaletteRow`,
  `PalettePaginator<T>` genérico e `favorites`/`tab` em `PalettesIndexProps`;
  `favorited`/`canFavorite` em `PublicPaletteProps`.
- `components/colors/FavoritePaletteCard.vue`: prévia, nome com link para a
  página pública (locale da sessão), tags, uso e data do favorito; remover via
  `colorsApi` DELETE com estado de carregamento e erro inline.
- `pages/dashboard/colors/PalettesIndex.vue`: abas "Minhas paletas"/"Favoritas"
  com `tab` na query string (só o servidor filtra), filtros escondidos na aba de
  favoritos, lista e vazio próprios, paginação compartilhada e recarga parcial
  (`only: ['favorites']`) ao remover.
- `pages/colors/PublicPalette.vue`: estrela quando `canFavorite`, escrita
  otimista POST/DELETE com rollback e aviso inline na falha.
- i18n `colors.favorites.*` e `colors.public.favorite*` em en, pt-BR, pt-PT,
  fr e es.

## Testes

- Novo `tests/Feature/Colors/PaletteFavoriteHttpTest.php`: visitante 401,
  idempotência sem cópia, 403 na própria paleta, 404 privada/arquivada/
  desconhecida, aba Favoritas (visibilidade, ordem, `favorited_at`, paleta que
  volta a ser pública), padrão `mine`, isolamento entre contas, props da página
  pública e throttle.
- `palette-list.test.ts`: props novas, dicionário `favorites`, abas, filtros
  escondidos, lista e vazio de favoritas.
- `palette-public.test.ts`: props novas, estrela só com `canFavorite`, POST e
  DELETE otimistas e rollback com aviso.

## Verificação

1. `php artisan test --filter=PaletteFavorite`
2. `php artisan test --filter=Colors`
3. `composer test`
4. `npm run test`
5. `npm run types:check`
6. `npm run lint:check`
7. `npm run build`

## Fora desta etapa

- Presets do gerador (Etapa 4), exportação e importação de design tokens
  (Etapas 5–6), ADRs/docs finais (Etapa 7).
