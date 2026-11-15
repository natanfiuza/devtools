# ADR-113 - Notificações
Status: Aceito

## Decisão
Notificações imediatas por e-mail e no app (database channel) para curtida, comentário, resposta e follow. Silenciar por paleta.

## Consequências
Usar Laravel Notifications; tabela notifications.

## Emenda 2026-11-14

A F6 gravou apenas o canal `database` (ADR-137): `PaletteLiked` e
`UserFollowed`, `ShouldQueueAfterCommit`, um aviso por relação via
`notified_at` atômico, sem e-mail, sem sino e sem preferências. O ator anônimo
não carrega identificador nem IP no payload. Comentário e resposta não existem
na fase. Silenciar por paleta, e-mail, contador de não lidas e tela de
notificações continuam para a F7.

## Emenda 2026-11-15

A F7 abriu as superfícies restantes (ADR-142): `database` continua sempre
ligado e o `mail` passa a ser opcional por tipo — curtida e follow desligados
por padrão, comentário e resposta ligados —, lido no `via()` no envio. Somam-se
`PaletteCommentApproved` (dono) e `CommentReplyApproved` (autor do pai + dono,
deduplicados, nunca o autor da ação), ambos só na aprovação e com
`notified_at` atômico. Silenciar por paleta é do dono, vale para curtida,
comentário e resposta naquele envio e não afeta follows. O sino lê
`notifications.unread` compartilhado, sem polling, e a página única é
`/dashboard/notifications`.
