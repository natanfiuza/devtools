# Social

Implementado na Fase 6 (2026-11-14, ADR-134 a ADR-137). O escopo é curtir,
seguir e o perfil público; comentários, denúncias, moderação, bloqueio e e-mail
não existem nesta fase.

## Curtir (ADR-112, ADR-135)

- A paleta pública, ativa e com slug pode ser curtida por uma conta ou por um
  visitante anônimo; o ator é sempre um dos dois, nunca ambos.
- A linha em `color_palette_likes` é permanente: `liked_at` nulo significa “não
  curte” e descurtir só limpa o timestamp. O par (paleta, ator) é único por dois
  índices separados, um para conta e um para anônimo.
- Curtir é idempotente: `firstOrCreate` + releitura com lock quando o unique
  dispara. A contagem é sempre de linhas ativas.
- O dono não curte a própria paleta (403); paleta não pública responde o mesmo
  404 da página.
- A API devolve `{liked, likes_count}` e nenhum identificador.
- A estrela da F5 continua sendo favorito, uma relação diferente, e não muda.

## Identidade anônima (ADR-135)

- O visitante recebe o cookie first-party `colors_anon`, cifrado, `HttpOnly`,
  `SameSite=Lax`, com 365 dias (`colors.social.cookie_days`) e valor aleatório de
  32 bytes em hex.
- O cookie só nasce depois de uma curtida concluída; não é emitido em visita,
  nem em POST que falhou.
- O IP não é identidade: vira só a chave HMAC-SHA256 do limitador `colors-social`
  e nunca é gravado nem devolvido.
- No login (`Login`, inclusive cadastro e login social), o listener reivindica
  as curtidas do cookie para a conta em transação: mescla conflitos, mantém o
  primeiro `notified_at` e esquece o cookie. Idempotente e tolerante a corrida;
  se falhar, o login segue e o cookie permanece para a próxima tentativa.

## Seguir (ADR-136)

- Exige login; o visitante não tem o botão.
- `user_follows` é permanente por par (`follower_id`, `followed_id`):
  `followed_at` nulo significa “não segue” e deixar de seguir só desativa.
- Auto-seguimento responde 403; alvo inexistente, sem username, suspenso ou
  anonimizado responde 404 uniforme; a resposta é
  `{following, followers_count}`.
- O perfil público mostra `followers_count`/`following_count`; não existem
  listas de seguidores ou seguindo.

## Perfil público (ADR-134)

Ver [perfil-publico.md](perfil-publico.md): `/{locale}/u/{username}`, 404
uniforme, paletas públicas do autor, contadores e SEO indexável fora do sitemap.

## Notificações (ADR-113, emenda; ADR-137)

- Só o canal `database`, na tabela padrão `notifications`.
- `PaletteLiked` e `UserFollowed` são `ShouldQueueAfterCommit`.
- Um aviso por relação, garantido pelo `UPDATE ... WHERE notified_at IS NULL`;
  reativar a relação não gera segundo aviso.
- Quem foi notificado não inclui o próprio dono da paleta, conta suspensa,
  anonimizada ou removida; nesses casos o carimbo é marcado de qualquer forma.
- O ator anônimo aparece só como `anonymous: true`, sem identificador, IP ou
  e-mail.
- Não há e-mail, sino, contador de não lidas nem preferência por paleta; a F7
  abre essas superfícies.

## Fora da Fase 6

- Comentários e threads de 2 níveis (ADR-111), edição e exclusão de comentário.
- Denúncia, filtro de palavras e moderação IA com cache em `ai_cache`.
- Bloqueio entre contas.
- Listas de seguidores/seguindo e edição de perfil.
- E-mail, sino de notificações e silenciar por paleta.
