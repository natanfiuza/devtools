# ADR-137 - Notificações no canal database
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-113 (notificações, emenda), ADR-135 (curtidas), ADR-136 (seguir contas), ADR-034 (fila/worker), ADR-133 (tokens)

## Contexto
A ADR-113 prometeu notificações imediatas por e-mail e no app para curtida, comentário, resposta e follow, com opção de silenciar por paleta. A F6 entrega curtida e follow, mas não comentários e não abre sino, preferências nem e-mail. Era preciso decidir o que gravar agora, como garantir “um aviso por relação” mesmo com requisições concorrentes, e o que de fato entra no payload — sem PII e sem dar ao ator anônimo uma identidade que a ADR-135 evitou.

## Decisão

### 1. Somente o canal `database`
`PaletteLiked` e `UserFollowed` declaram `via() = ['database']`. A F6 grava na tabela `notifications` padrão do Laravel (UUID, type, morphs `notifiable`, `data`, `read_at`, timestamps); não há e-mail, SMS, push, sino, contador de não lidas nem tela de preferências. O sino e as preferências são da F7, e o e-mail não entra nesta fase.

### 2. Depois do commit
As duas notificações implementam `ShouldQueueAfterCommit`: a linha só é escrita quando a transação de curtida/seguir confirmou. Uma transação desfeita não deixa aviso apontando para relação que não existe. Em produção o worker é necessário; os testes usam a fila síncrona.

### 3. `notified_at` é o gate atômico
Antes de notificar, a ação reivindica o aviso com `UPDATE ... WHERE notified_at IS NULL` e só segue quando uma linha foi afetada:

- duas requisições concorrentes para a mesma relação: só uma escreve a notificação;
- descurtir e curtir de novo não gera segundo aviso;
- a relação notificada uma vez mantém o carimbo mesmo que a notificação não se concretize.

### 4. Quem não é notificado, mas conta como avisado
`notified_at` é marcado de qualquer forma quando:

- o dono da paleta não existe, foi suspenso ou anonimizado;
- quem curtiu é o próprio dono da paleta (na curtida);
- o seguido foi suspenso ou anonimizado (no follow).

Assim a relação não fica “pendente” para sempre e não produz aviso atrasado se a conta voltar.

### 5. Payload sem PII e sem identidade anônima
`PaletteLiked` carrega `kind`, `palette_id`, `palette_name`, `palette_slug`, e do ator apenas `actor_id`, `actor_name`, `actor_username` **quando é conta**; o anônimo vira `anonymous: true`, sem identificador, IP ou e-mail. `UserFollowed` carrega `follower_id`, `follower_name` e `follower_username`. Cookie, identificador anônimo, IP e `data` da notificação nunca entram em log.

### 6. Uma por relação, não por evento
A decisão é por relação (paleta + ator, seguidor + seguido), não por clique: a primeira curtida ou o primeiro follow avisa; reativações não. Isso combina com a linha permanente da ADR-135/ADR-136, que guarda o estado e o carimbo.

## Consequências
- O dono recebe no banco um aviso por relação, sem e-mail e sem interface na F6.
- A atomicidade do carimbo dispensa lock de aplicação e funciona com concorrência real.
- O payload é mínimo: não expõe identidade anônima, IP, e-mail nem conteúdo de cookie.
- A F7 pode construir sino, contador, preferências e e-mail sobre esta base sem mudar o formato gravado.
- O worker passa a ser obrigatório para os avisos aparecerem; sem ele a relação fica marcada como notificada e o aviso não é reenviado.
- Não há silenciar por paleta nesta fase: a linha permanente já impede repetição, e a preferência pertence à F7.

## Alternativas rejeitadas
- **E-mail já na F6**: a fase não pede e-mail e o payload ainda não está fechado para destinatário; a ADR-113 fica emendada, não reescrita.
- **Notificação síncrona**: poderia gravar aviso de transação desfeita e não tem ganho sobre o `AfterCommit`.
- **Gate por coluna booleana na relação**: não é atômico sem lock; o `UPDATE` condicional resolve com uma instrução.
- **Não marcar `notified_at` quando não notifica**: deixaria a relação pendente e poderia avisar depois de suspensão/reativação.
- **Incluir o identificador anônimo no payload**: contraria a ADR-135 e a privacidade da fase.
- **Notificar comentário/resposta**: não existem comentários na F6.

## Emenda 2026-11-15

O canal deixa de ser exclusivamente `database` (ADR-142). `database` continua
sempre ligado; `mail` entra por preferência da conta (`email_on_like`,
`email_on_follow`, `email_on_comment`, `email_on_reply`), lida no `via()` no
momento do envio. Somam-se `PaletteCommentApproved` e `CommentReplyApproved`,
também `ShouldQueueAfterCommit` e com `notified_at` atômico no comentário.
Silenciar a paleta zera os dois canais para curtida, comentário e resposta
daquela paleta. O sino e a página `/dashboard/notifications` leem o canal
`database` já gravado, sem mudar o formato da linha.
