# ADR-135 - Curtidas em paletas e identidade anônima
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-112 (curtida anônima, emenda), ADR-128 (favoritos de paletas), ADR-134 (perfil público), ADR-136 (seguir contas), ADR-137 (notificações no banco)

## Contexto
A ADR-112 fixou que a curtida pode ser anônima por um identificador assinado de primeira parte, com IP apenas como sinal antiabuso, e que seguir exige login. Faltava decidir a forma da linha, como a descurtida convive com a reativação, como o anônimo curte antes de entrar e como uma conta recebe essa curtida no login sem duplicar registros nem notificações. “Curtir” não podia ser confundido com o favorito da F5 (ADR-128), que é organização pessoal e não interação.

## Decisão

### 1. Uma linha por paleta e ator
`color_palette_likes` guarda `color_palette_id` (ULID, cascade), `user_id` **ou** `anonymous_id` (string de 64 caracteres, um dos dois, nunca ambos nem nenhum, validado na ação), `liked_at` e `notified_at` nulos ou preenchidos, e timestamps. Como o banco não compara NULL, existem dois únicos separados: `(color_palette_id, user_id)` e `(color_palette_id, anonymous_id)`. Índices por paleta, por conta e por identificador, sempre com `liked_at`, sustentam a contagem ativa.

### 2. A linha é permanente; descurtir só desativa
`liked_at` nulo significa “não curte”. `UnlikePalette` limpa `liked_at` e nunca apaga a linha: o unique continua sendo o ponto de encontro do dedupe e `notified_at` sobrevive para que a mesma relação não gere um segundo aviso ao voltar. Curtir, descurtir e curtir de novo é a mesma linha reativada, e a contagem sempre conta linhas ativas.

### 3. As escritas são transacionais e idempotentes
`LikePalette` e `UnlikePalette` rodam em transação. A criação tenta `firstOrCreate` com a linha inativa; duas abas que inserem juntas caem no unique, e a ação relê a linha vencedora com `lockForUpdate()`. Reativar é só preencher `liked_at`. Qualquer combinação de POST/DELETE repetida produz o mesmo estado final.

### 4. Identidade anônima mínima
O visitante recebe o cookie `colors_anon`: first-party, cifrado pelo `EncryptCookies`, `HttpOnly`, `SameSite=Lax`, `Secure` conforme a sessão, validade de 365 dias (`colors.social.cookie_days`, `COLORS_ANON_COOKIE_DAYS`) e valor aleatório de 32 bytes em hex. O valor não tem relação com IP, sessão, e-mail ou conta, e só é aceito se casar `[a-f0-9]{64}`. O cookie é emitido **somente depois** de uma curtida bem-sucedida; um POST que falha na visibilidade não deixa identificador. O IP só vira chave HMAC-SHA256 do limitador, em cache; nunca é gravado nem devolvido.

### 5. A reivindicação acontece no login
O listener `ClaimAnonymousLikesOnLogin`, registrado no evento `Illuminate\Auth\Events\Login` (o mesmo disparado por cadastro e login social), lê o cookie e chama `ClaimAnonymousLikes` em transação:

- linhas ativas passam a `user_id` da conta e perdem `anonymous_id`;
- quando a conta já curtiu a mesma paleta, as duas viram uma: a ativa vence; `notified_at` guarda o primeiro valor não nulo, então o dono nunca recebe um segundo aviso;
- linhas inativas, de paleta da própria conta e de paleta desaparecida são removidas;
- a operação é idempotente e tolera corrida (unique + releitura).

O login nunca falha por causa disso: em erro, a conta entra, o cookie permanece e a tentativa volta no próximo login. O log é uma frase fixa; cookie, identificador, IP e payload nunca entram em log.

### 6. A API não carrega identificador
`POST/DELETE /api/tools/colors/palettes/{palette}/like` ficam **fora** do grupo autenticado porque o visitante curte sem conta, mas continuam rotas `web` com sessão e CSRF válidos. O throttle é `colors-social` (60/min, `COLORS_SOCIAL_RATE_LIMIT`), com chave `user:{id}` ou o HMAC do IP. A paleta precisa ser pública, ativa e com slug (senão 404 uniforme, igual à página); o dono logado não curte a própria paleta (403). A resposta é `{liked, likes_count}`, contando apenas linhas ativas, sem id anônimo, IP ou qualquer outro identificador. A página pública lê `liked` pelo `user_id` ou pelo cookie, sem criar identidade.

## Consequências
- O visitante curte sem cadastro e a conta leva as curtidas antigas no primeiro login, sem duplicar.
- Não há inflação por reenvio: o estado é idempotente e a contagem é sempre de linhas ativas.
- O identificador é opaco, cifrado e descartável; o IP só existe como chave derivada do limitador.
- A linha permanente guarda o histórico mínimo do vínculo e permite o dedupe de notificação.
- O custo é um cookie a mais, um listener no login e duas consultas de visibilidade/contagem nas rotas.
- Sem comentários, denúncias, moderação ou bloqueio: a F6 não cria essas peças.

## Alternativas rejeitadas
- **Apagar a linha ao descurtir**: perderia `notified_at` e faria a mesma relação gerar outro aviso; além disso, o unique deixaria de ser o ponto de encontro do dedupe.
- **Contador denormalizado na paleta**: diverge em concorrência e precisa de recálculo; `withCount`/escopo ativo é a fonte.
- **Identidade anônima por IP**: liga a curtida à rede, quebra troca de rede/privacidade e não é o que a ADR-112 pediu.
- **Cookie emitido em qualquer visita**: criaria rastreador sem ação do usuário; emitir só após o like mantém a identidade ligada a um ato.
- **Reivindicar por e-mail ou sessão inteira**: o login é o único evento em que conta e cookie coexistem com segurança.
- **Isentar CSRF na rota de like**: a rota é `web`; a sessão e o CSRF continuam valendo.
- **Notificar a cada reativação**: a ADR-137 fixa um aviso por relação; `notified_at` é o gate.
