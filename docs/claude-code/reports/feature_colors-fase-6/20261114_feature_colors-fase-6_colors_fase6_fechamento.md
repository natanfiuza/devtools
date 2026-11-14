# Relatório — Colors Fase 6 (social e imagem)

- Data: 2026-11-14
- Branch: `feature/colors-fase-6`
- Status: **implementada, aguardando revisão** (nenhum commit ou push feito)
- Contrato: `docs/colors/PROMPT-Fase6-social_imagens.md`
- Plano: `docs/plans/feature_colors-fase-6/20261114_colors_fase6_plansfacts.md`
- ADRs: 134 (perfil público), 135 (curtidas e identidade anônima), 136 (seguir),
  137 (notificações no banco), 138 (imagem tipográfica) — mais emendas datadas em
  ADR-033, 098, 106, 107, 112, 113, 115, 124, 126 e 128.

## Escopo entregue

1. **Curtir paleta pública** — conta pelo login, visitante por identidade anônima
   first-party; contador só de linhas ativas; idempotente; dono recebe 403 e
   paleta não pública 404 uniforme.
2. **Reivindicação no login** — curtidas anônimas passam para a conta, com
   mesclagem de conflitos, sem duplicar e sem segundo aviso; cookie descartado.
3. **Seguir contas** — login obrigatório, 401/403/404 uniforme, linha permanente
   por par e contagem ativa.
4. **Perfil público** — `/{locale}/u/{username}`, indexável, fora do sitemap,
   404 uniforme para conta inexistente/suspensa/anonimizada.
5. **Notificações** — `PaletteLiked` e `UserFollowed` só no canal `database`,
   `ShouldQueueAfterCommit`, um aviso por relação via `notified_at` atômico, sem
   e-mail nem UI.
6. **Imagem tipográfica** — horizontal 1600×500, vertical 1080×1920 e card
   1080×1080, com nome, HEX e marca fixa; SVG sanitizado por construção, PNG
   1×/2× do mesmo SVG, fonte embutida só na exportação, tudo no navegador.

Fora do escopo (permanece para a F7): comentários/threads, denúncia, moderação,
bloqueio, listas de seguidores/seguindo, edição de perfil, sino/preferências,
e-mail e analytics de eventos.

## Etapas

### Etapa 1 — Base de dados, identidade, notificações e config

- Migrations aditivas: `color_palette_likes`, `user_follows` e `notifications`,
  todas retrocompatíveis e com ULID/UUID e índices de contagem ativa.
- Modelos `ColorPaletteLike` e `UserFollow` com escopo `active`, casts e
  relações; `ColorPalette::likes()` e relações sociais em `User`.
- `AnonymousIdentity` (cookie `colors_anon`, HMAC do IP para o limitador).
- `PaletteLiked`/`UserFollowed` (`via() = ['database']`, `AfterCommit`).
- Config `colors.social.cookie_days`/`profile_per_page` e
  `colors.rates.social_per_minute`; limitador `colors-social` e listener no
  provider.

### Etapa 2 — Curtidas

- `LikePalette`/`UnlikePalette`: transação, `firstOrCreate` + catch de unique +
  releitura com lock, linha permanente e `notified_at` atômico.
- `PaletteLikeController`: visibilidade 404, próprio dono 403, cookie só após
  sucesso, payload `{liked, likes_count}` sem identificadores.
- Rotas fora do grupo autenticado, com CSRF e `throttle:colors-social`.

### Etapa 3 — Follows

- `FollowUser`/`UnfollowUser` com o mesmo desenho de linha permanente e aviso
  único; auto-seguimento recusado.
- `UserFollowController` no grupo `auth/verified/active`: 401 sem sessão, 403
  para si, 404 uniforme para alvo invisível, payload `{following,
  followers_count}`.

### Etapa 4 — Reivindicação, perfil e paleta pública

- `ClaimAnonymousLikes` + listener `ClaimAnonymousLikesOnLogin`: valida formato,
  reivindica em transação, mescla, limpa cookie; falha nunca derruba o login.
- `PublicProfileController` e `ProfilePresenter`: perfil, paletas públicas
  paginadas com curtidas, contadores e estado de follow.
- Rota `colors.profiles.show` com regex de username; `Seo::profileMeta()` e
  `Seo::profile()`; perfil fora do sitemap.
- `PalettePresenter::public` ganhou `author`/`likes_count`; paleta pública
  ganhou `liked`/`canLike`/`following`/`canFollow`.

### Etapa 5 — Frontend social

- `usePaletteLike`, `useUserFollow`, `LikeButton`, `FollowButton` e
  `ProfilePaletteCard`.
- `PublicProfile.vue` com cabeçalho, contadores, follow, grade, vazio e
  paginação; `PublicPalette.vue` com byline, curtida, follow e seção de imagem.
- i18n `colors.social`/`colors.profile` nos 5 idiomas e `colors.public.by`.

### Etapa 6 — Imagem, ADRs, docs e gates

- Módulo `resources/js/tools/palette-generator/image/`: tipos e medidas,
  `buildImage` (três layouts, escaping, filtro de HEX, marca fixa),
  `exportImage` (prévia sem fonte, SVG/PNG com fonte, 1×/2×, downloads) e
  `PaletteImagePanel.vue`.
- Aba **Imagem** no editor e seção recolhível na página pública.
- `PrototypeBuildKind = Exclude<PrototypeDocumentKind, 'image'>` para manter a
  imagem fora do catálogo de protótipos.
- ADRs, emendas datadas, SPEC v1.3, docs de social/paleta/perfil/import-export,
  README e glossário.

## Arquivos principais

Backend:

- `database/migrations/2026_11_14_00000{1,2,3}_*.php`
- `app/Models/ColorPaletteLike.php`, `app/Models/UserFollow.php`
- `app/Actions/Colors/{LikePalette,UnlikePalette,FollowUser,UnfollowUser,ClaimAnonymousLikes}.php`
- `app/Notifications/Colors/{PaletteLiked,UserFollowed}.php`
- `app/Listeners/Colors/ClaimAnonymousLikesOnLogin.php`
- `app/Support/Colors/{AnonymousIdentity,ProfilePresenter,PalettePresenter}.php`
- `app/Http/Controllers/Api/Colors/{PaletteLikeController,UserFollowController}.php`
- `app/Http/Controllers/Colors/PublicProfileController.php`
- `app/Support/Seo.php`, `app/Providers/ColorsServiceProvider.php`,
  `config/colors.php`, `routes/colors.php`

Frontend:

- `resources/js/composables/{usePaletteLike,useUserFollow}.ts`
- `resources/js/components/colors/{LikeButton,FollowButton,ProfilePaletteCard}.vue`
- `resources/js/pages/colors/{PublicPalette,PublicProfile}.vue`
- `resources/js/pages/dashboard/colors/PaletteEditor.vue`
- `resources/js/tools/palette-generator/image/{types,buildImage,exportImage}.ts`
  e `PaletteImagePanel.vue`
- `resources/js/tools/palette-generator/prototype/{types,defaults,renderSvg}.ts`
- `resources/js/types/colors.ts`, `lang/*/colors.php`

Testes novos: `ClaimAnonymousLikesTest`, `PaletteLikeHttpTest`,
`UserFollowHttpTest`, `PublicProfileHttpTest`, `PublicPaletteSocialTest`,
`SocialSchemaTest`, `AnonymousIdentityTest`, `palette-image-export.test.ts`,
`palette-image-panel.test.ts`, `public-profile.test.ts`; extensões em
`palette-editor.test.ts` e `palette-public.test.ts`.

## Matriz de aceite

| Critério da F6 | Como foi atendido | Verificação |
|---|---|---|
| Curtir público, inclusive anônimo | Conta ou cookie anônimo; ator XOR validado | `PaletteLikeHttpTest`, `AnonymousIdentityTest` |
| Nenhum identificador na API pública | Payload só `{liked, likes_count}` / `{following, followers_count}` | testes de shape e privacidade |
| IP só antiabuso | HMAC do IP como chave do limitador; nunca gravado | `AnonymousIdentityTest`, teste de 429 |
| Reivindicação no login | Listener no `Login`, transação, mescla, cookie limpo | `ClaimAnonymousLikesTest` |
| Sem duplicata concorrente | Unique + `firstOrCreate` + lock/`sole` | testes de concorrência |
| Um aviso por relação | `UPDATE ... WHERE notified_at IS NULL` | testes de notificação única |
| Notificação só no banco | `via() = ['database']`, sem mail | `SocialSchemaTest`/testes de ação |
| Seguir exige login | Grupo `auth/verified/active`; 401 sem sessão | `UserFollowHttpTest` |
| Perfil público indexável e fora do sitemap | `Seo::profileMeta`, sitemap inalterado | `PublicProfileHttpTest` |
| 404 uniforme | Inexistente, suspenso e anonimizado iguais | `PublicProfileHttpTest` |
| Imagem nos 3 formatos | Medidas fixas e composição própria | `palette-image-export.test.ts` |
| Marca fixa e HEX | Texto `devtools.natanfiuza.dev.br` e HEX normalizados | `palette-image-export.test.ts` |
| SVG sem referência externa | Serializador só `rect`/`text`, escaping | teste de sanitização |
| PNG 1×/2× do mesmo SVG | `rasterizeSvg` com escala | teste de exportação |
| Fonte só na exportação | Prévia sem `<style>`; export com data URI | teste de prévia/export |
| Editor e página pública | Aba e seção recolhível | `palette-editor`, `palette-public`, `palette-image-panel` |
| i18n nos 5 idiomas | `colors.social`/`colors.profile`/`colors.image` | testes de i18n |

## Gates executados (2026-11-14)

| Gate | Resultado |
|---|---|
| `composer test` | **passou** — Pint ok, PHPStan 0 erros, Pest 1630 testes / 28877 asserções |
| `npm run test` | **passou** — 123 arquivos, 1511 testes |
| `npm run types:check` | **passou** (Wayfinder + `vue-tsc --noEmit` sem erros) |
| `npm run lint:check` | **0 erros**, 1193 warnings (baseline do repositório) |
| `npm run build` | **passou** |
| `npm run build:ssr` | **passou** (exit 0) |
| `npm run format:check` | 258 arquivos com estilo — **pré-existente** (248 antes da fase); os demais são arquivos novos/alterados da F6. Não faz parte do gate e não foi aplicado `--write` para não reformatar o repositório inteiro |

Observação: na primeira execução de `npm run test` em paralelo com o PHPStan,
dois testes alheios à fase (`badgeforge-custom` e `i18n-messages`) estouraram o
timeout de 5 s por carga; executados isoladamente passaram (18/18) e a suíte
completa passou em seguida. Não é regressão da F6.

## Smoke manual sugerido

1. **Anônimo**: abrir uma paleta pública em janela anônima, curtir, recarregar e
   confirmar curtida e contador; descurtir e confirmar. Em DevTools, o cookie
   `colors_anon` deve existir só depois do primeiro like, cifrado e `HttpOnly`.
2. **Reivindicação**: com o mesmo cookie, criar conta ou entrar; conferir que a
   curtida continua na paleta, que o dono não recebeu segundo aviso e que o
   cookie `colors_anon` sumiu.
3. **Perfil**: abrir `/{locale}/u/{username}`; conferir paletas públicas, os
   contadores e o 404 igual para username inexistente e conta suspensa.
4. **Social na paleta**: conferir byline com link, botão de seguir (logado, não
   dono), curtir e contador; a estrela de favorito da F5 continua independente.
5. **Imagem**: na página pública e na aba **Imagem** do editor, alternar os três
   formatos, conferir a prévia, baixar SVG e PNG 1×/2× e validar nome, HEX e
   marca; testar nome muito longo e paleta com cores inválidas.
6. **SEO/sitemap**: conferir título/descrição e canonical do perfil; confirmar
   que o perfil não aparece no sitemap.

## Pendências e observações

- Nenhum commit ou push foi feito; o working tree está com todas as mudanças
  para revisão do usuário.
- O worker de fila é necessário em produção para os avisos `database`
  aparecerem.
- `format:check` continua vermelho por dívida pré-existente; a fase não o
  alterou como gate.
- Comentários, denúncia, moderação, bloqueio, e-mail e sino pertencem à F7.
