# ADR-134 - Perfil público por username
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-098 (paleta pública com SEO), ADR-124 (página pública, emenda), ADR-126 (protótipos), ADR-136 (seguir contas), ADR-137 (notificações no banco)

## Contexto
A ADR-124 fechou a página pública da paleta sem autor, curtidas nem seguidores, porque essas superfícies pertencem à F6. A F6 pediu seguir contas, e seguir precisa de um destino: o autor de uma paleta não tinha endereço público. O perfil também não podia expor dados da conta (e-mail, id de sessão, preferências) nem criar superfícies que a fase não decidiu, como listas de seguidores e bio.

## Decisão

### 1. Uma URL por username, em todos os idiomas
`GET /{locale}/u/{username}` (nome `colors.profiles.show`), dentro do grupo de locale já existente, com `username` casando `[a-z0-9_]{3,30}`. O username é global e não muda entre idiomas; canonical e hreflang usam as alternates de `Locales`, como a paleta pública. URL inválida nem casa com a rota.

### 2. O 404 é uniforme
Conta inexistente, suspensa, anonimizada ou sem username respondem o **mesmo 404**; não há tela “usuário não encontrado” que distinga os casos. Trocar de username não cria redirecionamento: a URL antiga passa a 404 e a nova é a única válida. A F6 não edita username nem bio.

### 3. A vitrine mostra paletas públicas, não a conta
O controller entrega:

- `profile`: `id`, `name`, `username`, `avatar` (URL pública quando há foto), `followers_count`, `following_count` e `member_since`;
- `palettes`: as paletas `publiclyVisible()` do autor (pública, ativa e com slug), ordenadas por `updated_at desc`, com `likes_count` de linhas ativas e paginação `colors.social.profile_per_page` (12, `COLORS_PROFILE_PER_PAGE`), preservando a query string;
- `following`, `canFollow` e `isOwnProfile` para quem olha.

E-mail, id de sessão, papel de conta, configurações e listas de seguidores/seguindo ficam de fora. A F6 não cria superfícies de lista; os contadores existem para a página funcionar como vitrine e para o botão de seguir.

### 4. Indexável, mas fora do sitemap
`Seo::profileMeta()` resolve título e descrição traduzidos a partir do mesmo perfil que a rota resolve; sem perfil visível, caem no fallback do site (o controller já respondeu 404 antes de renderizar). O perfil não entra no sitemap: é uma página pessoal, pode mudar de username e cresceria o sitemap com conteúdo que não precisa de descoberta.

### 5. O autor de uma paleta é a mesma visibilidade
`PalettePresenter::author()` devolve `id`, `name`, `username` e `avatar` apenas quando a conta tem username e continua visível. Conta suspensa, anonimizada ou sem username não vira byline, e sem byline não há o que seguir na página da paleta (ADR-136).

## Consequências
- O autor tem endereço estável para compartilhar e para o botão “seguir”.
- A página não vaza conta removida nem distingue motivos de ausência: a mesma 404 cobre todos.
- O perfil é indexável sem entrar na contagem do sitemap; canonical/hreflang não depende de query.
- Followers/following são números, não listas: a decisão de expor listas fica para uma fase que a peça.
- O custo é mais uma consulta de perfil por página e um presenter compartilhado entre perfil e byline.

## Alternativas rejeitadas
- **Perfil por id** (`/u/{id}`): expõe chave interna e não é endereço que alguém digita.
- **Redirecionar o username antigo**: exigiria histórico de usernames, que a F6 não cria; a nova URL é a única.
- **404 diferente para suspenso/anonimizado**: revelaria o estado da conta.
- **Listar seguidores/seguindo**: superfície não pedida, com paginação, privacidade e bloqueio próprios (bloqueio nem existe na F6).
- **Incluir o perfil no sitemap**: página pessoal não precisa de descoberta e o sitemap já tem teto e índice próprios.
- **Mostrar e-mail ou bio**: não são dados da vitrine e a F6 não edita perfil.
