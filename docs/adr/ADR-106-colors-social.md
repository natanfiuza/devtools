# ADR-106 - Social
Status: Aceito

## Decisão
Permitir favoritar, seguir usuário, curtir e comentar em paletas públicas.

## Consequências
Aumenta engajamento; exige moderação e anti-spam.

## Emenda 2026-11-14

A F6 entregou o subconjunto social de curtir (inclusive anônimo) e seguir
(ADR-135/ADR-136), com perfil público (ADR-134) e notificações apenas no canal
`database` (ADR-137). Favoritar já existia desde a F5 (ADR-128) e não mudou.
Comentários, moderação, anti-spam além do throttle, denúncias e bloqueio
continuam não implementados; a consequência de "exige moderação e anti-spam"
fica pendente para a fase que abrir comentários.

## Emenda 2026-11-15

A F7 abriu o restante do social prometido aqui: comentários em thread de dois
níveis (ADR-139), moderação local + IA com cache (ADR-140), denúncia (ADR-141)
e notificações com e-mail/sino/preferências/silenciar (ADR-142), tudo atrás da
flag `COLORS_COMMENTS_ENABLED` (ADR-143). Anti-spam continua restrito a
throttle por conta/IP e filtro de palavras; bloqueio entre contas segue não
implementado.
