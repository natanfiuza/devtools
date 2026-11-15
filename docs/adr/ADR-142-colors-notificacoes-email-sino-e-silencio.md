# ADR-142 - Notificações: e-mail, sino, preferências e silenciar
Status: Aceito
Data: 2026-11-15
Relacionados: ADR-113 (notificações, emenda), ADR-137 (notificações no banco, emenda), ADR-135 (curtidas), ADR-136 (seguir), ADR-139 (comentários), ADR-034 (fila/worker)

## Contexto
A ADR-113 prometeu e-mail e app para curtida, comentário, resposta e follow, com opção de silenciar por paleta. A F6 entregou só o canal `database` para curtida e follow (ADR-137), sem e-mail, sino, página ou preferências. A F7 abre o restante e precisa decidir o que chega por e-mail sem virar spam, como deduplicar destinatários e onde o silêncio é avaliado.

## Decisão

### 1. `database` sempre, `mail` por preferência
Toda notificação grava no app. O e-mail é opcional por tipo: `email_on_like`/`email_on_follow` nascem **desligados**; `email_on_comment`/`email_on_reply` nascem **ligados**. A preferência mora em `color_notification_preferences` (PK `user_id`), lida no `via()` no momento do envio; `database` nunca é desligado.

### 2. Quatro avisos nesta fase
`PaletteLiked`, `UserFollowed`, `PaletteCommentApproved` (dono) e `CommentReplyApproved` (autor do comentário pai). Comentário e resposta só avisam na **aprovação**: `pending`, `flagged` e `rejected` não notificam. Toda notificação é `ShouldQueueAfterCommit`.

### 3. Destinatários, dedupe e `notified_at`
Numa resposta, o aviso vai ao autor do comentário pai e ao dono da paleta, deduplicados; o autor da ação nunca recebe. O comentário reivindica o envio com `UPDATE ... WHERE notified_at IS NULL`, então reeditar e aprovar de novo (ou o admin mexer depois da IA) não duplica o aviso. Rejeitado e flagged não geram aviso social; se o comentário for apagado, nada é enviado.

### 4. Silenciar é do dono e vale no envio
`color_palette_mutes` guarda o par (paleta, conta). O botão só aparece para o dono, com comentários ligados, e silencia curtidas, comentários e respostas **daquela paleta**, em app e e-mail. O silêncio é avaliado no `via()` (no worker), não na escrita, então ligar/desligar depois do fato vale para o próximo envio. Follows não são afetados.

### 5. E-mail com marca, locale e link direto
O e-mail usa o template de marca (`MailBrand`), o locale do destinatário (`HasLocalePreference`) e o link para a paleta com `#comment-{id}`, além do atalho para as preferências. Assunto e corpo ficam em `lang/{locale}/mail.php` nos cinco idiomas. O corpo do comentário **não** entra no e-mail nem no payload: o aviso leva ao comentário.

### 6. Sino por props, sem polling
`HandleInertiaRequests` compartilha `notifications.unread` apenas para conta autenticada; o `NotificationBell` no cabeçalho da conta mostra o contador (99+) e leva à página única `/dashboard/notifications`. A página lista, marca uma/todas como lidas (404 se a notificação não for da conta) e edita as quatro preferências com escrita otimista.

### 7. Privacidade
O payload `database` leva `kind`, ids, nome/slug da paleta e nome/username do ator; do ator anônimo, nada além de `anonymous: true`. Nunca carrega corpo do comentário, e-mail, IP ou cookie. Conta suspensa, anonimizada ou removida não recebe.

## Consequências
- O app é a fonte de verdade; o e-mail é um extra que o usuário controla por tipo.
- Um aviso por comentário aprovado, mesmo com reedição/admin, porque o carimbo é atômico.
- Silenciar corta os dois canais sem apagar histórico; o efeito é imediato no próximo envio.
- Sem polling, o contador reflete o último carregamento de página — suficiente para sininho e barato em servidor.
- O e-mail nunca reproduz o texto moderado, o que reduz o dano de um eventual erro de template.

## Alternativas rejeitadas
- **E-mail para tudo por padrão**: a F6 nasceu sem e-mail e curtida/follow em volume virariam ruído.
- **Silenciar na escrita**: exigiria propagar o estado para linhas antigas e não cobriria mudança de preferência.
- **Contador em API com polling**: custo e complexidade sem ganho para um sino.
- **Incluir o corpo do comentário no e-mail**: vaza texto que pode ter sido editado/rejeitado e amplia o alcance de abuso.
- **Notificar múltiplas vezes por comentário**: `notified_at` já resolve e evita aviso duplicado no retrabalho.
