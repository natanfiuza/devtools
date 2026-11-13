# Plano — Fase 5, Etapa 1: backend de histórico e favoritos de cores

- Data: 2026-11-12
- Branch: `feature/colors-fase-5`
- Origem: `docs/colors/PROMPT-Fase5-organizacao_desing_tokens.md` (organização pessoal)

## Contexto

A Fase 5 leva histórico e favoritos do HTML Color Picker para a conta, compartilha favoritos com o Color Palette Generator e adiciona paletas favoritas, presets e design tokens. Esta etapa entrega somente o backend da biblioteca de cores; o anônimo continua no `localStorage` (ADR-118) e a UI/composable ficam para a Etapa 2.

## Decisões

- Duas tabelas novas com ULID: `color_history_entries` e `color_favorites`, ambas com `unique(user_id, color)` e FK cascade.
- `color` é a chave canônica do picker (`#rrggbbaa` minúsculo ou `transparent`), normalizada no FormRequest por `ColorKey`.
- Histórico sem cota funcional; gravar a mesma cor toca `updated_at` (upsert) em vez de duplicar. Listagem por cursor.
- Favoritos idempotentes (`firstOrCreate`/`delete`), compartilhados por definição entre as duas tools.
- Importação do histórico local é explícita: lote limitado por config, deduplicado e idempotente.
- Escritas sob `throttle:colors-library` por conta; limites e páginas em `config/colors.php`.

## Contrato

| Método | Rota | Efeito |
| --- | --- | --- |
| GET | `/api/tools/colors/history` | lista paginada por `updated_at` desc |
| POST | `/api/tools/colors/history` | registra/atualiza uma cor |
| DELETE | `/api/tools/colors/history` | remove `{color}` ou limpa tudo |
| POST | `/api/tools/colors/history/import` | importa `{colors: [...]}` em lote |
| GET | `/api/tools/colors/favorites` | lista paginada por `created_at` desc |
| POST | `/api/tools/colors/favorites` | favorita `{color}` |
| DELETE | `/api/tools/colors/favorites` | desfavorita `{color}` |

Todas exigem `auth`, `verified` e `active`; as escritas usam o limiter `colors-library`. Erros seguem o envelope JSON do projeto (`error.code`, `error.details.errors`).

## Execução

1. Migrations, models (`ColorHistoryEntry`, `ColorFavorite`), relações em `User`.
2. `App\Support\Colors\ColorKey` e os FormRequests de validação/normalização.
3. Actions `RecordColorHistory`, `ImportColorHistory`, `FavoriteColor`, `UnfavoriteColor`.
4. Controllers `Api\Colors\ColorHistoryController` e `ColorFavoriteController`; rotas em `routes/colors.php`.
5. Config `colors.library.*`, `colors.rates.library_per_minute` e limiter no `ColorsServiceProvider`.
6. Testes Pest em `tests/Feature/Colors/ColorLibraryHttpTest.php`.

## Verificação

- `php artisan test --filter=ColorLibrary`
- `php artisan test --filter=Colors`
- `composer test` (Pint, PHPStan, Pest)
- `php artisan route:list --path=api/tools/colors`

## Fora de escopo

Props/composable/UI (Etapa 2), favoritos de paletas (Etapa 3), presets (Etapa 4), tokens (Etapas 5–6), ADRs/docs (Etapa 7).
