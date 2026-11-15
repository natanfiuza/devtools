# Social

Fases 6 e 7 (2026-11-14 e 2026-11-15, ADR-134 a ADR-143). A F6 entregou curtir,
seguir e o perfil público; a F7 acrescentou comentários, denúncias, moderação e
notificações completas. Bloqueio entre contas continua fora.

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

## Comentários (ADR-111, emenda; ADR-139)

- Escrever exige login, conta verificada e ativa, e a flag
  `COLORS_COMMENTS_ENABLED` ligada; ler é público quando a flag está ligada.
- Corpo de até 255 caracteres (`colors.comments.body_max_length`), em texto
  puro, sem HTML.
- Thread de dois níveis: raiz e respostas. Responder a uma resposta responde
  422 no servidor.
- O comentário nasce `pending` e fica visível com o selo "em análise" — nunca é
  apresentado como aprovado antes da aprovação. `flagged` usa o mesmo selo.
- Editar (`PATCH`) reabre a moderação e incrementa `revision`; a última versão
  aprovada fica em `approved_body`. Se a revisão editada for rejeitada, o texto
  volta à versão aprovada; sem versão aprovada, o comentário some.
- Excluir (`DELETE`) é soft delete do autor. Raiz com respostas vira tombstone
  ("comentário removido") e mantém o fio; raiz sem respostas e respostas
  excluídas somem da página. O admin continua vendo o texto.
- Anonimização mantém o texto e apresenta a autoria como "conta removida".
- A página pública recebe raízes paginadas (`comments.per_page`) com respostas
  em ordem crescente e limite `comments.replies_limit`; `comments` é `null`
  quando a flag está desligada.

## Denúncias (ADR-114, emenda; ADR-141)

- Só conta logada, verificada e ativa; nunca o próprio autor.
- Motivo em lista fixa (`spam`, `harassment`, `hate`, `sexual`, `other`), sem
  texto livre.
- Uma linha por par comentário/denunciante. Repetir enquanto `pending` ou
  `resolved` responde 422 sem criar linha; se estava `dismissed`, a mesma linha
  volta a `pending` com nova data.
- Denunciar não esconde nem altera o comentário.
- Admin resolve ou descarta em `/admin/comment-reports`; rejeitar ou excluir o
  comentário resolve as denúncias pendentes automaticamente.
- Sem notificação ao denunciante nesta fase.

## Notificações (ADR-113, emenda; ADR-137, emenda; ADR-142)

- O canal `database` é sempre ligado; o `mail` entra por preferência da conta,
  por tipo: `email_on_like` e `email_on_follow` desligados por padrão,
  `email_on_comment` e `email_on_reply` ligados.
- `PaletteLiked`, `UserFollowed`, `PaletteCommentApproved` e
  `CommentReplyApproved` são `ShouldQueueAfterCommit`.
- Um aviso por relação, garantido pelo `UPDATE ... WHERE notified_at IS NULL`;
  reativar a relação não gera segundo aviso. No comentário, `notified_at` garante
  um conjunto de avisos por comentário mesmo após reedição e reaprovação.
- Comentário aprovado avisa o dono da paleta; resposta aprovada avisa o autor do
  comentário pai e o dono, deduplicados e nunca o autor da ação.
  `pending`/`flagged` não notificam e rejeição não gera aviso social.
- Quem foi notificado não inclui o próprio dono da paleta, conta suspensa,
  anonimizada ou removida; nesses casos o carimbo é marcado de qualquer forma.
- O ator anônimo aparece só como `anonymous: true`, sem identificador, IP ou
  e-mail. O payload nunca carrega o corpo do comentário.
- Silenciar paleta é do dono e vale para curtida, comentário e resposta daquela
  paleta nos dois canais, avaliado no envio; follows não são afetados.
- Sino no cabeçalho da conta com contador de não lidas por prop Inertia, sem
  polling; página única `/dashboard/notifications` com a lista, marcar como
  lidas e os quatro toggles de e-mail.
- E-mail de marca, no locale da conta, com link para a paleta em
  `#comment-{id}` e para as preferências.

## Flag (ADR-143)

- `COLORS_COMMENTS_ENABLED=false` por padrão.
- Desligada: a página não consulta nem renderiza a thread e os endpoints de
  comentário, denúncia e mute respondem 404 uniforme via middleware
  `colors.comments`.

## Fora do escopo (F7)

- Bloqueio entre contas.
- Listas de seguidores/seguindo e edição de perfil.
- Notificação ao denunciante e recurso de decisão administrativa.
