# Plano de execução — Colors Fase 8 (auditoria final e relatório de aceite)

Data: 2026-11-15
Branch: `feature/colors-fase-8`
Plano completo: `docs/claude-code/plans/feature_colors-fase-8/20261115_feature_colors-fase-8_auditoria_final.md`

## Objetivo

Auditar o track Colors F1–F7, corrigir só bloqueantes e altos, provar o
funcionamento no navegador e entregar aceite, ADR-144 e relatório. Sem commit,
push ou deploy.

## Etapas executadas

| Etapa | Conteúdo | Gate |
| --- | --- | --- |
| 0 | Linha de base: branch, `git status`, `composer ci:check` e `npm run build` | números registrados |
| 1 | `ExportUserData` com bloco `colors` (`palettes`, `tags`, `history`, `favorites`, `presets`, `palette_favorites`, `likes`, `following`, `comments` com `withTrashed`, `reports_filed`, preferências e mutes) | `AccountPanelTest`, `PurgeDeletedUsersTest` |
| 2 | `AnonymizeUser` apaga biblioteca, presets, favoritos, curtidas da conta, follows de saída e tags; `user_id`/`resolved_by` viram `NULL`; nenhuma paleta excluída | `Settings/ProfileUpdateTest`, `PurgeDeletedUsersTest`, `AccountPanelTest` |
| 3 | `data-test` em `CommentReports.vue` + `admin-comment-reports.test.ts` | Vitest da fila de denúncias |
| 4 | Docs: `docs/colors/*`, SPEC, README, ARQUITETURA, CHANGELOG, RUNBOOK §11, `.env.example` | `TranslationsParityTest` + inspeção |
| 5 | `npm run format` no repositório inteiro | `format:check`, `lint:check` |
| 6 | Gate estático completo | `composer ci:check`, `npm run build`, `build:ssr` |
| 7 | Checklist manual Playwright em SQLite descartável (14 itens) | evidências em `.../evidence/` |
| 8 | Entregáveis: aceite, ADR-144, relatório, survey/plan | — |
| 9 | Gate final e parada | `composer ci:check`, `npm run build`, `git status --short` |

## Correções fora do plano

As duas únicas correções que apareceram na execução além das etapas, ambas
altas e dentro do escopo "só bloqueante/alto":

1. `ExtractionPanel.vue`: trocar o `formatBytes` local (que chamava
   `Intl.NumberFormat('pt_BR')` e lançava `RangeError`) pelo helper de
   `resources/js/lib/bytes.ts`.
2. `lang/*/notifications.php`: trocar `:actor`/`:palette` por
   `{actor}`/`{palette}` no bloco `kinds` e adicionar guarda em
   `resources/js/__tests__/i18n-messages.test.ts`.

Nenhuma mudança estrutural fora do plano foi necessária; não houve parada
obrigatória.

## Confirmação de fechamento (2026-11-15, pós-execução)

- A fase foi aceita **como está**, com P1–P6 deferidas como backlog.
- `COLORS_COMMENTS_ENABLED` permanece `false` em produção; ligar fica para uma
  decisão operacional futura.
- Nenhuma alteração de código decorreu desta confirmação; os registros foram
  feitos no aceite e neste survey/plano.

## Decisões operacionais

- Banco: SQLite descartável em `%TEMP%` com semeadura local; `.env` nunca
  editado; guarda `DB::connection()->getDatabaseName()` conferida.
- Flag: `COLORS_COMMENTS_ENABLED=true` só no processo de auditoria.
- SSR: passada dedicada com `build:ssr` + `npm run ssr`; `public/hot` removido.
- Teardown: worker, serve e SSR parados; SQLite, seed, PNG de OG e
  `.playwright-mcp/` apagados; `optimize:clear`.
