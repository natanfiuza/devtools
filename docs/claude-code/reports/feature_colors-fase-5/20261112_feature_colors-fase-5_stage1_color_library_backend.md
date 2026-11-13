# Relatório — Fase 5, Etapa 1: backend de histórico e favoritos de cores

- Data: 2026-11-12
- Branch: `feature/colors-fase-5`
- Tarefa: backend da biblioteca pessoal de cores (histórico e favoritos)

## Entregue

- Tabelas `color_history_entries` e `color_favorites`, com ULID, FK cascade para `users`, `unique(user_id, color)` e índices de listagem (`updated_at`/`created_at`). Migrations apenas criam tabelas novas, sem downtime.
- Models `ColorHistoryEntry` e `ColorFavorite` com `HasUlids`, `#[Fillable]`, relação `user()` e `scopeForUser`; relações `colorHistoryEntries()`/`colorFavorites()` em `User`.
- `App\Support\Colors\ColorKey`: formato canônico `#rrggbbaa` minúsculo ou `transparent`, com normalização de caixa/espaços e validação por regex.
- FormRequests: `StoreColorHistoryRequest`, `ImportColorHistoryRequest`, `DeleteColorHistoryRequest`, `ColorFavoriteRequest`.
- Actions: `RecordColorHistory` (upsert que toca `updated_at` com retry em corrida), `ImportColorHistory` (dedupe no lote + upsert em massa), `FavoriteColor`/`UnfavoriteColor` (idempotentes).
- Endpoints JSON em `/api/tools/colors/*` (7 rotas) sob `auth`, `verified`, `active`; escritas com `throttle:colors-library` por conta.
- Config `colors.library.{history_per_page,favorites_per_page,import_batch}` e `colors.rates.library_per_minute`, com overrides por `.env`; limiter novo no `ColorsServiceProvider`.
- Testes Pest `tests/Feature/Colors/ColorLibraryHttpTest.php`.

## Contrato implementado

- `GET /history` e `GET /favorites`: paginação por cursor, `{data, next_cursor}`, ordenação por `updated_at`/`created_at` desc.
- `POST /history`: normaliza, grava e devolve `{entry}`; repetir a cor não duplica e sobe `updated_at`.
- `DELETE /history`: com `{color}` remove uma cor; sem corpo limpa o histórico.
- `POST /history/import`: `{colors}` validado (1..`import_batch`), deduplicado, idempotente; devolve `{imported, total}`.
- `POST /favorites` e `DELETE /favorites`: `{favorited}` e entrada; segunda chamada não é erro.
- Cores inválidas (`#fff`, `#rrggbb`, nomes, vazio) retornam 422 no envelope de erro do projeto.

## Verificação executada

| Comando | Resultado |
| --- | --- |
| `php artisan test --filter=ColorLibrary` | 14 testes, 95 asserções, todos verdes |
| `php artisan test --filter=Colors` | 76 testes, 507 asserções, todos verdes |
| `composer test` | Pint ok; PHPStan 0 erros; Pest **1548 testes / 27441 asserções** verdes (1 depreciação pré-existente do Pest) |
| `php artisan route:list --path=api/tools/colors` | 7 rotas com nomes e middleware corretos |

Uma correção no caminho: o `phpdoc_align` do Pint pediu ajuste no docblock de `ImportColorHistory`; rodado `vendor/bin/pint` no arquivo e a pipeline ficou verde.

## Pendências e próximos passos

- Etapa 2: props de biblioteca em `ColorsToolProps`/`ToolProps` (incluindo `html-color-picker`), composable `useColorLibrary`, painéis compartilhados, registro do gerador e importação local opt-in.
- Etapas 3–6: paletas favoritas, presets e tokens export/import.
- Etapa 7: ADRs 127–132, SPEC/docs, glossário e matriz manual. Os comentários do código já citam ADR-127/ADR-132, que serão registradas nessa etapa.
- Sem UI nesta etapa; nada foi validado em navegador.
- Nenhum commit ou push foi feito, conforme a regra do projeto.
