# Plano — Colors Fase 7

- Data: 2026-11-15
- Branch: `feature/colors-fase-7`
- Task: comentários, moderação IA, denúncias e notificações
- Survey: `docs/survey/20261115_colors_fase7_surveyfacts.md`

## Objetivo

Entregar comentários em paletas públicas com threads de 2 níveis, filtro local
e moderação DeepSeek assíncrona com cache em `ai_cache`, denúncias, e
notificações completas (sino, e-mail, preferências, silenciar por paleta), tudo
atrás de `COLORS_COMMENTS_ENABLED` (default false) e sem commit/push.

## Decisões fechadas

1. `pending` visível com selo “em análise” para todos; nunca rotulado aprovado.
2. Rejeição: novo some; edição volta ao `approved_body`; sem aprovada, some.
3. Exclusão do pai: tombstone com respostas; sem respostas, some; admin mantém.
4. Threads de 2 níveis; responder a resposta = 422.
5. `ai_cache.expires_at` nullable; NULL = permanente; poda ignora NULL.
6. Filtro local síncrono/bloqueante, 422 genérico, nada gravado.
7. Falha definitiva da IA => `flagged`, visível como “em análise”.
8. E-mail default: comentário/resposta ligados; curtida/follow no app.
9. Silenciar por paleta: dono; corta curtidas, comentários e respostas em app +
   e-mail; follows não.
10. Resposta notifica autor do pai + dono, dedup, sem auto-notificação.
11. Denúncia única por par; pending/resolved repetida = 422; dismissed reabre.
12. Admin aprova/rejeita/exclui com auditoria sem texto.
13. Anonimização mantém texto, autor “conta removida”; purge anula `user_id`.
14. Flag default false; off => thread some e endpoints 404.
15. Listas por idioma em `resources/colors/moderation/`, fallback inglês.
16. Motivo de denúncia enum fixo.
17. Sino no cabeçalho + página `/dashboard/notifications` (lista+preferências).
18. Escrita logada/verified/active; leitura pública com flag.

## Arquitetura

### Migrações

1. `prepare_ai_cache_for_moderation`: `purpose` string(32) default `ai` +
   índice; `expires_at` nullable; `down` preenche NULL com now() e reverte.
2. `color_palette_comments`: ULID, paleta cascade, `parent_id` self-FK
   nullOnDelete, `user_id` nullable nullOnDelete, `body`, `approved_body`
   nullable, `status`, `locale`, `revision`, `moderation_reason`,
   `moderated_at`/`notified_at`/`edited_at`, softDeletes, índices por paleta,
   pai, status e usuário.
3. `color_comment_reports`: ULID, comentário cascade, denunciante/resolvedor
   nullable nullOnDelete, motivo, status, datas, unique(comment,user).
4. `color_notification_preferences`: PK user, 4 booleanos de e-mail.
5. `color_palette_mutes`: par único paleta+usuário, FKs cascade.

### Modelos e enums

`ColorPaletteComment` (ULID, SoftDeletes, escopos, `displayBody`,
`canBeRepliedTo`), `ColorCommentReport`, `ColorNotificationPreference`,
`ColorPaletteMute`; enums de status/motivo; factories com estados; relações no
`User`. `AiCache` nullable + `purpose`; repositório do SQL filtra purpose `ai`;
`ai:prune-cache` ganha `--purpose` e `--all`.

### Filtro local

`resources/colors/moderation/{en,pt_BR,pt_PT,fr,es}.txt`, `WordLists` com cache
em memória e fallback inglês, `WordFilter` com NFKC/minúsculas/zero-width/
pontuação por token e n-gramas, sem logar conteúdo.

### Moderação IA

Prompt `colors_comment_moderation.v1.md` (comentário = dado não confiável,
JSON estrito approved/rejected + categoria). `ModerationPayload`
(purpose `colors.comment_moderation`, conteúdo, locale, modelo, versões, MD5),
`ModerationContractValidator`, `ModerationCacheRepository` sobre `ai_cache`
(permanente por padrão, TTL opcional, comparação de payload completo),
`ModerateColorComment` (IDs + revisão, after commit, unique, provider direto,
sem cota), `ApplyModerationResult` com UPDATE condicional por revisão/status.

### Comentários

Actions create/update/delete, policy do autor, presenter sem PII nem campos
internos, raízes paginadas + respostas com limite, tombstone, middleware
`colors.comments` => 404, throttles `colors-comments`/`colors-reports`, rotas
em `api/tools/colors`, leitura via props Inertia. Flag off não consulta nem
renderiza.

### Denúncias

`ReportColorComment` com unique-catch e reabertura; admin resolve/dismiss com
`RecordsAdminActions`; rejeitar/excluir resolve pendentes; sem notificação.

### Notificações

4 toggles de e-mail; `database` sempre; `PaletteLiked`/`UserFollowed` ganham
`mail` + mute no envio; novas `PaletteCommentApproved` e
`CommentReplyApproved` (autor do pai + dono, dedup, gate `notified_at`); payload
sem corpo; e-mail de marca + `lang/*/mail.php`; sino no header; página
`/dashboard/notifications`; mute do dono na página pública.

### Admin

`/admin/comments` (filtros + aprovar/rejeitar/excluir) e
`/admin/comment-reports` (resolve/dismiss), auditoria sem texto.

### Config, privacidade e docs

Config/env para flag, limite 255, paginação, replies, modelo, prompt, policy,
caminho das listas, TTL, retries, throttles. `AnonymizeUser` apaga preferência
e mutes. ADRs 139–143, emendas 110/113/137, SPEC v1.4, docs colors, RUNBOOK,
glossário e índice de ADRs.

## Etapas

0. Survey + este plano.
1. Schema/modelos/factories/cache. Gate: `CommentsSchemaTest`,
   `CommentModerationCacheTest`.
2. Filtro local. Gate: `WordFilterTest`.
3. Moderação. Gate: `CommentModerationTest`, cache, validator.
4. Comentários + UI + flag. Gate: HTTP/visibilidade/flag + Vitest da thread.
5. Denúncias. Gate: `CommentReportTest`.
6. Notificações. Gate: notificações/preferências + Vitest sino/página.
7. Admin. Gate: `AdminCommentsTest`.
8. i18n/docs/ADRs. Gate: `npm run test`.
9. `composer test`, `npm run test`, `types:check`, `lint:check`, `format:check`,
   `build`, `build:ssr`; relatório final. Sem commit/push.

## Riscos

- Cache permanente com texto: TTL + expurgo documentados.
- `after_commit=false`: usar `ShouldQueueAfterCommit`/`->afterCommit()`.
- Corrida de revisão: só UPDATE condicional aplica resultado.
- Timeout do job < `retry_after` 150; retries finitos.
- Flag desligada por padrão; ligar exige decisão explícita.
- Falso positivo do filtro: matching por token e lista versionada.
- `ai_cache` compartilhada: separação por `purpose` + payload completo.
