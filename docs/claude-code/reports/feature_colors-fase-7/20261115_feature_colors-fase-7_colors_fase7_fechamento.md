# Colors — Fase 7 (fechamento)

Data: 2026-11-15
Branch: `feature/colors-fase-7`
Escopo: comentários em paletas públicas, moderação local + IA, denúncias,
notificações completas (e-mail, sino, preferências, silenciar) e kill switch.

## Resultado

A Fase 7 está implementada em todas as etapas do plano, com gates verdes no
fechamento. Nada foi commitado nem enviado: o commit e o PR ficam com o usuário
pelo gitpr. A flag `COLORS_COMMENTS_ENABLED` continua `false` por padrão, então
a produção só abre a superfície por decisão explícita.

## O que entrou

### Banco e modelos

- Migrações (retrocompatíveis):
  - `2026_11_15_000001_prepare_ai_cache_for_moderation.php` — `purpose` com
    default `ai` e índice; `expires_at` nullable (`NULL` = permanente).
  - `2026_11_15_000002_create_color_palette_comments_table.php`.
  - `2026_11_15_000003_create_color_comment_reports_table.php`.
  - `2026_11_15_000004_create_color_notification_preferences_table.php`.
  - `2026_11_15_000005_create_color_palette_mutes_table.php`.
- Modelos `ColorPaletteComment`, `ColorCommentReport`,
  `ColorNotificationPreference`, `ColorPaletteMute`; enums
  `ColorCommentStatus`, `ColorCommentReportReason`, `ColorCommentReportStatus`;
  factories e relações em `User`/`ColorPalette`.
- `AiCache`/`AiCacheRepository`: `purpose`, `expires_at` nulo aceito e nunca
  podado, `AiCacheFactory::permanent()`. `ai:prune-cache` ganhou `--purpose=` e
  `--all` (que exige `--purpose`).

### Comentários

- `CreateColorComment`, `UpdateColorComment`, `DeleteColorComment`, presenter
  público e controller da API (`POST`/`PATCH`/`DELETE` em `comments/{id}`).
- Thread de 2 níveis validada no servidor; `pending`/`flagged` visíveis com
  selo "em análise"; edição guarda `approved_body` e volta a ele quando a
  revisão é rejeitada; exclusão é soft delete com tombstone quando há respostas.
- Escrita exige login + `verified` + `active`; leitura pública com a flag.
- `EnsureColorCommentsEnabled` (alias `colors.comments`) responde 404 uniforme
  em comentário, denúncia e mute quando a flag está desligada; a página recebe
  `comments = null`.
- Componentes `CommentThread`, `CommentForm`, `CommentItem` e
  `CommentReportDialog`; `PublicPalette.vue` só renderiza com `comments !== null`.

### Moderação

- Filtro local `WordLists`/`WordFilter` com arquivos
  `resources/colors/moderation/{en,pt_BR,pt_PT,fr,es}.txt`, n-gramas de tokens,
  fallback para inglês e resposta 422 `comments_blocked` sem revelar o termo.
- Prompt versionado `colors_comment_moderation.v1`, `ModerationPayload`,
  `ModerationContractValidator` e `ModerationCacheRepository` sobre `ai_cache`
  com `purpose = colors.comment_moderation` e comparação do payload inteiro.
- Job `ModerateColorComment` (`ShouldQueueAfterCommit` + `ShouldBeUnique` por
  `commentId:revision`) chamando `LlmProvider` sem cota da conta.
- `ApplyModerationResult`: UPDATE condicional por `revision` + `status`; falha
  do provedor/contrato/retries vira `flagged`, nunca aprovação; `failed()` do job
  cobre worker morto.

### Denúncias

- `ReportColorComment` com unique `(comment_id, user_id)`, motivo em enum fixo,
  repetição `pending`/`resolved` em 422 e `dismissed` reabrindo a mesma linha.
- `ResolveColorCommentReport`/`ResolveColorCommentReports`; rejeitar ou excluir
  comentário resolve as pendentes automaticamente.
- Admin em `/admin/comment-reports` (`CommentReportController` +
  `CommentReports.vue`), auditado sem o texto.

### Notificações

- `PaletteCommentApproved` e `CommentReplyApproved`; `PaletteLiked` e
  `UserFollowed` passam a ter `mail` condicionado à preferência; `via()` avalia
  silenciar a paleta e conta suspensa/anonimizada no envio.
- `ColorNotificationPreference` (4 toggles de e-mail), `ColorPaletteMute`,
  `MutePaletteButton`, `NotificationBell` em `AppSidebarHeader`,
  `notifications.unread` compartilhado por Inertia (sem polling) e página
  `/dashboard/notifications` com lista, marcar como lidas e preferências.
- E-mails em `lang/*/mail.php` (5 idiomas), no locale da conta, com link para
  `#comment-{id}` e para as preferências; payload nunca carrega o corpo.

### Admin de moderação

- `Admin\CommentModerationController` (`/admin/comments`) com filtros
  `pending|flagged|rejected|approved|all` e busca; `CommentModeration.vue` com
  aprovar, rejeitar (com diálogo de confirmação) e excluir.
- `AdminModerateColorComment` usa UPDATE condicional com incremento de
  `revision` para invalidar jobs em voo e audita `comment.approved`,
  `comment.rejected` e `comment.deleted` via `RecordsAdminActions`, sem o texto.
- Entradas novas no `AdminLayout`: Moderação e Denúncias.

### i18n e docs

- `lang/{en,es,fr,pt_BR,pt_PT}`: `colors.php`, `admin.php`, `account.php` e o
  novo namespace `mail.php` (registrado em `config/locales.php`).
- ADRs novas: `ADR-139` (comentários e threads), `ADR-140` (moderação e cache),
  `ADR-141` (denúncias), `ADR-142` (notificações e-mail/sino/silêncio) e
  `ADR-143` (kill switch).
- Emendas datadas de 2026-11-15 em ADR-106, ADR-110, ADR-111, ADR-113, ADR-114
  e ADR-137, sem reescrever as decisões antigas.
- `docs/colors/SPEC-COLORS.md` em v1.4, `docs/colors/README.md` (status, faixa
  de ADRs até 143 e emendas), `social.md`, `moderacao.md`, `paleta-publica.md` e
  `glossario.md` atualizados; `docs/RUNBOOK.md` ganhou a purga do cache de
  moderação e dois sintomas novos na tabela de diagnóstico.

## Gates (resultado real)

| Script | Resultado |
|---|---|
| `composer test` | **passou** — Pint `passed`, PHPStan `0 errors`, Pest **1737 testes / 30275 asserções** |
| `npm run test` | **passou** — **127 arquivos / 1541 testes** |
| `npm run types:check` | **passou** — Wayfinder + `vue-tsc --noEmit` sem erros |
| `npm run lint:check` | **passou** — 0 erros, 1193 avisos (quase todos pré-existentes, `vue/html-indent` e afins) |
| `npm run format:check` | **falhou** — 267 arquivos com divergência do Prettier, **pré-existente**; nenhum arquivo foi reformatado |
| `npm run build` | **passou** (aviso de chunk > 500 kB, já existente) |
| `npm run build:ssr` | **passou** |

Antes do fechamento, o Pint acusou estilo em `app/Jobs/ModerateColorComment.php`,
`app/Models/ColorPaletteComment.php` e `tests/Feature/Colors/CommentsSchemaTest.php`;
os três foram corrigidos com `vendor/bin/pint` nos próprios arquivos e o
`composer test` passou na sequência.

Na primeira execução de `npm run test` do fechamento, um teste de leitura de
`lang/` (`i18n-messages.test.ts`, que chama `php -r` por `execFileSync`) falhou
uma vez e passou isolado e em duas execuções completas seguintes; foi tratado
como oscilação de spawn de processo sob carga, não como regressão de código.

## Pendências e decisões operacionais

- **Ligar a superfície**: `COLORS_COMMENTS_ENABLED=true` só depois do aceite
  funcional manual (flag ligada em local), como manda a ADR-143.
- **Worker obrigatório**: moderação e notificações são `ShouldQueueAfterCommit`;
  sem worker a thread funciona, mas fica em "em análise".
- **Cache permanente**: a moderação guarda o texto enquanto a linha existir.
  `COLORS_COMMENT_CACHE_TTL_DAYS` define validade; a limpeza é
  `php artisan ai:prune-cache --purpose=colors.comment_moderation --all`.
- **`ExportUserData` não foi ampliado** nesta fase; comentários e denúncias
  seguem o tratamento já dado a paletas e curtidas, e o ponto fica registrado
  para a auditoria da F8.
- **Bloqueio entre contas**, listas de seguidores/seguindo e notificação ao
  denunciante continuam fora de escopo.
- O `format:check` do repositório continua divergente de forma pré-existente;
  corrigir isso é uma tarefa própria, não um efeito colateral desta fase.

## Git

Nenhum commit, push ou deploy foi feito. O usuário commita e abre o PR pelo
gitpr, aprovando cada um.
