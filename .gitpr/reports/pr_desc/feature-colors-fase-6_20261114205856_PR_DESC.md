# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat(colors): adiciona curtidas, seguir e imagem tipográfica
```

---

## 🎯 Resumo

Entrega a fase 6 do Colors: interações sociais entre contas e visitantes nas paletas públicas. Visitantes passam a curtir paletas sem conta (via cookie anônimo first-party), contas podem curtir e seguir autores, o perfil público do autor vira vitrine indexável e a página da paleta ganha byline, curtida e botão de seguir. Em paralelo, chega a exportação da imagem tipográfica da paleta em três formatos, feita inteiramente no navegador. A base de notificações é criada agora apenas no canal `database`, com UI e e-mail reservados para a F7.

## 🛠️ Mudanças Técnicas

- **Actions** novas: `LikePalette`/`UnlikePalette`, `FollowUser`/`UnfollowUser` e `ClaimAnonymousLikes` (idempotentes, com merge race-safe via unique constraint + lock).
- **Controllers**: `PaletteLikeController` e `UserFollowController` (API) e `PublicProfileController`; `PublicPaletteController` passa a expor `author`, `liked`, `canLike`, `following` e `canFollow`.
- **Models**: `ColorPaletteLike` e `UserFollow` (estado inativo via `liked_at`/`followed_at` nulo, `notified_at` preserva aviso único), além de relações em `ColorPalette` e `User`.
- **Migrations**: `color_palette_likes`, `user_follows` e `notifications` (canal `database` do Laravel).
- **Notificações**: `PaletteLiked` e `UserFollowed`, ambas `ShouldQueueAfterCommit` e exclusivas do canal `database`.
- **Listener**: `ClaimAnonymousLikesOnLogin` reivindica curtidas do cookie no evento `Login`, sem quebrar o login em falha e limpando o cookie.
- **Suporte**: `AnonymousIdentity` (cookie `colors_anon` de 64 hex, HttpOnly, SameSite=Lax, 365 dias) e `ProfilePresenter` para cards de perfil.
- **Rate limiting**: novo limiter `colors-social` (conta por user id; visitante por HMAC do IP, sem IP em texto no cache).
- **Frontend**: `LikeButton`, `FollowButton`, `ProfilePaletteCard`, página `PublicProfile.vue`, composables `usePaletteLike`/`useUserFollow` com escrita otimista, e integração de curtir/seguir na `PublicPalette.vue`.
- **Imagem tipográfica**: `buildImage`/`exportImage`/`types`, `PaletteImagePanel` reutilizando serializador e rasterizador do protótipo, em 3 layouts fixos (horizontal 1600×500, vertical 1080×1920, card 1080×1080) com watermark `devtools.natanfiuza.dev.br`.
- **i18n**: novas chaves `profile`, `social` e `image` em en, es, fr, pt_BR e pt_PT (`palette.by` para a byline).
- **Rotas**: `/u/{username}` (perfil público indexável, fora do sitemap) e endpoints `POST/DELETE` para curtir (`/palettes/{id}/like`) e seguir (`/users/{id}/follow`).
- **Cobertura**: testes de schema único, HTTP (like/follow/perfil), unit do `AnonymousIdentity`, listener de login e suítes Vitest para `PublicPalette`, `PublicProfile`, painel e exportação da imagem.

## ⚠️ Impacto/Avisos

- **Banco de dados**: três novas migrations obrigatórias (`color_palette_likes`, `user_follows`, `notifications`). Rodar `php artisan migrate` antes do deploy. As tabelas usam ULIDs e `cascadeOnDelete`; as linhas são permanentes (descurtir/unfollow só desativam).
- **Variáveis de ambiente**: novas opcionais `COLORS_ANON_COOKIE_DAYS` (default 365), `COLORS_PROFILE_PER_PAGE` (default 12) e `COLORS_SOCIAL_RATE_LIMIT` (default 60/min). Nenhum valor obrigatório sem default.
- **Cookie novo**: `colors_anon` passa a ser emitido a partir do primeiro like anônimo e apagado no login (via `ClaimAnonymousLikesOnLogin`). É cifrado pelo `EncryptCookies`, HttpOnly e SameSite=Lax.
- **Notificações**: só gravam no canal `database` nesta fase; não há sino nem e-mail até a F7. O listener de login engole falhas com `Log::warning` fixo (sem payload/cookie/IP em log).
- **Sem quebra de contrato**: a estrela de favorito (F5) permanece independente da curtida; endpoints existentes não mudam de shape. O visitante anônimo não recebe identificador na resposta da API (apenas no cookie).
- **SEO/Rotas**: `/u/{username}` é indexável com metas próprias, mas propositalmente ausente do sitemap. Conta inexistente, suspensa, anonimizada ou username fora de `[a-z0-9_]{3,30}` responde 404 (mesmo comportamento da página pública).
- **Rate limit**: visitantes são limitados por HMAC do IP no cache; quem usa proxy reverso precisa garantir que o IP real esteja em `REMOTE_ADDR` para o limiter não colapsar todos no mesmo bucket.

close #158

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_67_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)