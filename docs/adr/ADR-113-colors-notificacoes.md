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
