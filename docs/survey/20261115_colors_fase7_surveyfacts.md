# Survey — Colors Fase 7

- Data: 2026-11-15
- Branch: `feature/colors-fase-7`
- Task: comentários, moderação IA, denúncias e notificações
- Fonte: `docs/colors/PROMPT-Fase7-comentarios_deepseek.md`, SPEC §12/§16,
  ADR-110/111/113/114/137

## Contexto

A F6 fechou curtir (inclusive anônimo), seguir, perfil público, notificações
`database` e exportação de imagem. A F7 é a última frente de produto antes da
auditoria final (F8): comentários em paletas públicas, moderação DeepSeek,
denúncias e notificações completas (sino, e-mail, preferências, silenciar por
paleta), atrás de uma flag desligada por padrão.

## Fatos levantados no repositório

- Não existe código, tabela, rota, componente ou teste de comentários,
  denúncias, moderação de comentário, filtro de palavras, preferências de
  notificação ou mutes.
- `ai_cache` é singular e **não** tem `purpose`; `expires_at` é NOT NULL e
  indexado. `AiCacheRepository::find/store` e `ai:prune-cache` são específicos
  do SQL builder e tratam a tabela como TTL obrigatório.
- `LlmProvider::complete(array $messages): LlmCompletion` e
  `DeepSeekProvider` já fazem JSON mode, mapeiam erro sem vazar corpo e leem
  `config/ai.php` (`AI_BASE_URL`, `AI_MODEL`, `AI_API_KEY`, timeouts, tokens).
- `PromptLoader` carrega `resources/prompts/{slug}.v{n}.md` com slug
  `[a-z0-9_]+`; `PromptBuilder`/`ContractValidator` são acoplados ao SQL.
- `StartAiQuery` sempre reserva cota e grava `usage_ledger`; não há caminho de
  IA interno sem cota. A moderação precisa chamar o provider direto.
- Notificações existentes: `PaletteLiked` e `UserFollowed`,
  `ShouldQueueAfterCommit`, `via()=['database']`, sem e-mail. O gate
  `notified_at` usa `UPDATE ... WHERE notified_at IS NULL`.
- Fila `database`, `config/queue.php after_commit=false`, worker com
  `--tries=2 --timeout=120`.
- Admin em `can:access-admin`, `RecordsAdminActions`, `AdminActionType`,
  `AdminTable`/`AdminLayout`.
- E-mail de marca em `resources/views/vendor/notifications/email.blade.php`,
  `MailBrand`, textos server-only em `lang/*/mail.php`.
- `AnonymizeUser` não toca em dados Colors; `ExportUserData` também não exporta
  Colors (lacuna pré-existente).
- `HandleInertiaRequests` não compartilha notificações; `AppSidebarHeader` só
  tem trigger + título; `useAccountNav` não tem notificações.
- `routes/colors.php` já separa leitura pública (com `{locale}`) e escrita
  autenticada; limitadores em `ColorsServiceProvider`.
- i18n Colors em 5 locales, namespace `colors`; Vitest centralizado em
  `resources/js/__tests__/`.

## Decisões da sessão de grill

1. Comentário `pending` visível para todos com selo “em análise”; nunca
   rotulado como aprovado.
2. Rejeição de comentário novo esconde; edição rejeitada volta ao
   `approved_body` anterior; sem versão aprovada, esconde.
3. Exclusão do pai vira tombstone quando há respostas; sem respostas, some. O
   admin mantém o texto.
4. Threads de 2 níveis; responder a resposta é 422.
5. `ai_cache.expires_at` nullable; `NULL` = permanente; poda ignora NULL.
6. Filtro local síncrono e bloqueante, 422 genérico, nada gravado.
7. Falha definitiva da IA vira `flagged`, visível como “em análise”, sem
   reenfileirar para sempre.
8. E-mail ligado por padrão para comentário/resposta; curtida/follow só no app.
9. Silenciar por paleta é do dono e corta curtidas, comentários e respostas em
   app + e-mail; follows não.
10. Resposta notifica autor do pai + dono, deduplicados, nunca o ator.
11. Denúncia única por par; pending/resolved repetida = 422; dismissed reabre.
12. Admin aprova, rejeita e exclui, tudo auditado sem texto.
13. Anonimização mantém texto e mostra “conta removida”; purge definitivo
    anula `user_id`.
14. Flag `COLORS_COMMENTS_ENABLED=false` por padrão; off => 404 uniforme.
15. Listas de palavras por idioma em `resources/colors/moderation/`, fallback
    inglês, cache em memória.
16. Motivo de denúncia é enum fixo, sem texto livre.
17. Sino no cabeçalho da conta com contador por props Inertia; página única
    `/dashboard/notifications` com lista + preferências.
18. Escrita exige login + verified + active; leitura é pública com a flag.

## Tensões resolvidas

- “Nunca publicar revisão não aprovada” => nunca exibir como aprovada; pending e
  flagged aparecem com selo. Emenda datada na ADR-110/SPEC.
- Sentinela de cache => nullable, decidido na F7 (ADR-110 emendada).
- “Flagged” na IA => status `flagged` próprio, visível como pending.

## Pendências registradas

- Exportação de dados da conta não inclui Colors (nem antes da F7).
- Cache permanente guarda texto de comentário; TTL opcional e expurgo por
  `purpose` serão documentados.
