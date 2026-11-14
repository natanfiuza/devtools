# ADR-136 - Seguir contas
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-106 (social), ADR-112 (curtida anônima), ADR-134 (perfil público), ADR-135 (curtidas), ADR-137 (notificações no banco)

## Contexto
Seguir uma conta é a segunda interação social da F6 e a única que a ADR-112 reservou para identidades persistentes: o visitante curte, mas seguir exige login. O destino do “seguir” é o perfil público da ADR-134. Faltava decidir a linha, a idempotência, o que o alvo invisível responde e qual contagem a tela mostra — sem listas de seguidores, e-mail ou bloqueio, que a fase não tem.

## Decisão

### 1. Uma linha permanente por par
`user_follows` guarda `follower_id` e `followed_id` (FK para `users`, cascade), `followed_at` e `notified_at` nulos ou preenchidos, e timestamps, com `unique(follower_id, followed_id)` e índices por seguido e por seguidor, sempre com `followed_at`. `followed_at` nulo significa “não segue”; deixar de seguir limpa o timestamp e **não apaga** a linha. O par é a chave do dedupe e do aviso único ao seguido (ADR-137).

### 2. Escritas transacionais e idempotentes
`FollowUser` e `UnfollowUser` rodam em transação. A criação tenta `firstOrCreate` da linha inativa; a corrida entre duas abas cai no unique e a ação relê a linha com `lockForUpdate()` antes de reativar `followed_at`. Seguir a si mesmo é erro de programação: a ação lança `InvalidArgumentException`, e o controller responde 403 antes de chegar nela. Repetir POST ou DELETE converge para o mesmo estado.

### 3. A API exige conta e não revela o alvo
`POST/DELETE /api/tools/colors/users/{user}/follow` ficam no grupo `['auth', 'verified', 'active']` com `throttle:colors-social`. A resposta e o comportamento:

- sem sessão: 401;
- alvo igual ao seguidor: 403 (`forbidden`);
- alvo inexistente, sem username, suspenso ou anonimizado: **404 uniforme**, sem revelar qual caso é;
- sucesso: `{following: true|false, followers_count}`, contando apenas linhas ativas do alvo.

A resposta não carrega e-mail, username do alvo, id de sessão nem qualquer identificador do seguidor.

### 4. A contagem é sempre derivada
`followers_count` e `following_count` vêm de `withCount`/escopo ativo, nunca de contador denormalizado. O perfil público mostra os dois números no cabeçalho; a página da paleta mostra o botão junto à byline do autor, sem contador.

### 5. As superfícies
- Perfil público: `followers_count`, `following_count`, `following` e `canFollow` (logado e não dono); o botão é otimista com rollback e `aria-pressed`.
- Paleta pública: o autor visível ganha o botão de seguir quando quem olha está logado e não é o dono; sem autor visível, não há o que seguir.
- Sem conta, o botão de seguir não existe; a curtida anônima continua vindo da ADR-135.

## Consequências
- Seguir é estável e idempotente, e o mesmo par nunca gera dois avisos.
- O 404 uniforme impede descobrir contas removidas ou suspensas por tentativa.
- O visitante entende que seguir exige conta pela ausência do botão, sem um fluxo de login novo.
- Listas de seguidores/seguindo, bloqueio e edição de perfil continuam fora da F6.
- O custo é um par de consultas de contagem por perfil e uma consulta de existência na paleta/página.

## Alternativas rejeitadas
- **Seguir anonimamente**: a ADR-112 reservou seguir para login; sem conta, o aviso ao seguido não teria destinatário claro.
- **Apagar a linha ao deixar de seguir**: perderia `notified_at` e refaria o aviso na próxima vez.
- **Contador denormalizado**: diverge sob concorrência e precisa de recálculo.
- **404 diferente para suspenso/anonimizado**: revelaria o estado da conta.
- **Listar seguidores/seguindo na F6**: superfícies não pedidas, com paginação e privacidade próprias.
- **Seguir a conta sem username**: não há perfil público para onde ir; o alvo sem username responde 404.
- **Alvo polimórfico** (seguir paleta/tag): a F6 só pede contas; um polymorphic traria colunas e regras sem uso.
