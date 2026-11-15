# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona comentários, moderação IA e notificações nas paletas
```

---

## 🎯 Resumo

Habilita a camada social das paletas públicas do Colors (Fase 7): comentários em thread de dois níveis, moderação por IA, denúncias, mute e notificações (sino + e-mail). A mudança existe para permitir interação pública sem abrir mão de segurança, previsibilidade de custo e privacidade: todo o fluxo nasce atrás de kill switch, com moderação assíncrona que falha para estado seguro (`flagged`, nunca aprovação), sem logar ou serializar o corpo dos comentários, e com notificações disparadas apenas na aprovação.

A entrega cobre backend (schema, regras de negócio, job de moderação, API e admin), frontend (componentes, composables, páginas de conta e moderação, i18n) e cobertura de testes, além da documentação de decisão (ADRs 106/110/111/113/114/137/139/140/141/142/143, runbook e docs/colors).

## 🛠️ Mudanças Técnicas

- **Banco de dados:** cria `color_palette_comments`, `color_comment_reports`, `color_notification_preferences`, `color_palette_mutes`; adiciona `purpose` (default `ai`, indexado) e torna `expires_at` nullable em `ai_cache`, permitindo linhas permanentes e isolamento por caso de uso.
- **Configuração:** novas envs em `.env.example` e blocos `comments`/`reports`/`notifications` em `config/colors.php` (limite de 255 caracteres, paginação, modelo/prompt/policy de moderação, TTL de cache, tentativas/backoff/timeout do job) e rate limits `comment_per_minute`/`report_per_minute`.
- **Modelos e enums:** `ColorPaletteComment` (soft delete, `revision`, `approved_body`, `displayBody()`, `isVisibleToPublic()`, `canBeRepliedTo()`), `ColorCommentReport`, `ColorNotificationPreference`, `ColorPaletteMute`; enums de status/motivo de comentário e denúncia, novo `AdminActionType` e relações em `User`/`ColorPalette`.
- **Regras de negócio (Actions):** `CreateColorComment`/`UpdateColorComment` aplicam filtro local, exigem pai raiz visível, gravam como `pending`, incrementam `revision` e disparam a moderação após commit; `ApplyModerationResult` faz UPDATE condicional por `revision` + `status = pending` para impedir que resultado atrasado sobrescreva edição; `AdminModerateColorComment`, `ResolveColorCommentReports`, `ReportColorComment` (uma linha por par comentário/denunciante, bloqueio de auto-denúncia e tratamento de corrida por unique constraint), `DeleteColorComment` (tombstone quando há respostas) e `NotifyApprovedComment` (notificação apenas na aprovação, com `notified_at` reivindicado atomicamente e deduplicação de destinatários).
- **Moderação por IA:** job `ModerateColorComment` (`ShouldQueue`/`ShouldBeUnique`/`AfterCommit`) chama `LlmProvider` com modelo por uso, valida contrato estrito (`decision`/`category`), usa cache em `ai_cache` com `purpose = colors.comment_moderation` e converte falhas em `flagged`; suporte com `CommentTextNormalizer`, `WordFilter`/`WordLists` (listas locais por locale com fallback `en`), `ModerationPayload`, `ModerationContractValidator`, `ModerationPromptBuilder` e `ModerationCacheRepository`. Prompt versionado em `resources/prompts` e listas em `resources/colors/moderation`.
- **API e HTTP:** `CommentPresenter` serializa a thread sem expor `user_id`, e-mail, `revision` ou `approved_body`; `PublicPaletteController` só entrega a thread quando habilitada e expõe `muted`/`canMute` ao dono; novos controllers de API (comentário, denúncia, mute idempotente), conta (`NotificationController` com sino, leitura e quatro toggles) e admin (moderação de comentários e denúncias com auditoria via `RecordsAdminActions`, sem corpo do texto).
- **Middleware e ciclo de vida do usuário:** `EnsureColorCommentsEnabled` (alias `colors.comments`) responde 404 uniforme quando desligado; `HandleInertiaRequests` compartilha a contagem de não lidas; `AnonymizeUser` passa a apagar notificações, preferências e mutes.
- **Notificações:** `PaletteLiked`/`UserFollowed` ganham canal `mail` condicionado às preferências; novas `PaletteCommentApproved`/`CommentReplyApproved` respeitam mute, suspensão e anonimização.
- **Frontend:** componentes `CommentForm`, `CommentItem`, `CommentThread` (dois níveis, selo "em análise", tombstone, edição, exclusão e paginação via `comments_page`), `CommentReportDialog` (motivos fechados com mensagem traduzida via `ColorsApiError`), `MutePaletteButton` e `NotificationBell`; composables `useCommentThread` (CRUD otimista e mapeamento de erro HTTP para chave de tradução) e `usePaletteMute` (otimista com rollback); páginas `Notifications`, `CommentModeration` e `CommentReports`; rotas web/admin/API, tipos TS e navegação ampliada.
- **Operação:** `ai:prune-cache` ganha `--purpose` e `--all` (com exigência de `--purpose`), `AiCacheRepository` filtra por `purpose = ai` e ignora linhas permanentes, e `LlmProvider::complete()` aceita modelo opcional.
- **i18n:** traduções completas em pt_BR, pt_PT, en, es e fr para comentários, denúncias, mute, admin, mail e novo `notifications.php`.
- **Testes:** suítes PHP Feature (HTTP de comentários, moderação IA, notificações, denúncias, visibilidade, feature flag, schema, cache e override de modelo) e Unit (`ModerationContractValidator`, `WordFilter`), além de testes Vitest para thread, denúncias, sino, página de notificações, moderação admin e navegação.

## ⚠️ Impacto/Avisos

- **Migrações:** quatro novas tabelas e alteração em `ai_cache` (coluna `purpose` com default `ai` e índice; `expires_at` nullable). Rodar `migrate` antes do deploy; o default preenche linhas existentes, mas o prune antigo deixa de limpar registros não-`ai` sem `--purpose`/`--all`.
- **Envs novas:** obrigatórias para habilitar a feature (limites, rate limits, modelo/prompt/policy de moderação, TTL de cache, tentativas/backoff/timeout do job). Sem elas, o kill switch deve permanecer desligado.
- **Feature flag:** com `colors.comments` desativado, endpoints de comentários retornam 404 uniforme — comportamento esperado e coberto por teste.
- **Filas:** moderação depende de worker ativo; sem processamento, comentários ficam `pending`/invisíveis ao público.
- **Privacidade/auditoria:** logs de falha, presenter e auditoria admin não expõem corpo de comentário nem dados pessoais; revisar antes de alterar esses pontos.
- **Dependências:** nenhum pacote novo; reutiliza `LlmProvider`, filas e mailer existentes.
- **i18n:** cinco locales alterados simultaneamente — validar revisão de texto (pt_PT e demais) antes do merge.

close #160

---

[![GitPR](https://img.shields.io/badge/GitPR-1_error_%C2%B7_192_warnings-red)](https://gitpr.natanfiuza.dev.br/)