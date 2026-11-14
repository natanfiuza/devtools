# Plano — Colors Fase 6 (social e imagem)

Data: 2026-11-14
Branch: `feature/colors-fase-6`
Contrato: `docs/colors/PROMPT-Fase6-social_imagens.md`
Survey: `docs/survey/20261114_colors_fase6_surveyfacts.md`

## Contexto

A F5 fechou biblioteca pessoal, favoritos de paletas, presets e tokens. A F6
abre o social das paletas públicas (curtir, inclusive anônimo, e seguir) e a
exportação da paleta como imagem tipográfica, sem comentários, denúncias,
moderação, bloqueio, e-mail ou sino de notificação.

## Escopo

Dentro: likes de paleta pública com contador; identidade anônima minimizada e
reivindicação no login; follows com página pública `/{locale}/u/{username}`;
notificações no canal database (sem UI/e-mail); exportação SVG/PNG horizontal,
vertical e card na página pública e no editor.

Fora: comentários/threads; denúncia; moderação IA; bloqueio; listas de
seguidores/seguindo; edição de perfil; sino e preferências de notificação;
e-mail; analytics histórico; qualquer geração de imagem no servidor.

## Decisões estruturais

- Curtir ≠ favoritar: `color_palette_favorites` e a estrela da F5 ficam intactas.
- `color_palette_likes`: uma linha por `(paleta, ator)`, onde ator é `user_id` ou
  `anonymous_id` (XOR). `liked_at` nulo = descurtida; a linha permanece para o
  dedupe de notificação via `notified_at`.
- `user_follows`: uma linha por `(follower_id, followed_id)`; `followed_at` nulo =
  não segue; `notified_at` idem.
- Cookie `colors_anon`: first-party, cifrado, HttpOnly, SameSite=Lax, 365 dias,
  id aleatório de 32 bytes. O banco guarda o id; o IP só vira chave HMAC do
  limitador, em cache. Nenhum identificador sai na API.
- No evento `Illuminate\Auth\Events\Login`, o listener reivindica as curtidas do
  cookie para a conta em transação, mesclando conflitos e limpando o cookie.
- Notificações: `notifications` padrão do Laravel, classes
  `PaletteLiked`/`UserFollowed` no canal `database`; sem e-mail e sem UI.
  `notified_at` é o gate atômico de “uma por relação”.
- Perfil público por username; indexável, com canonical/hreflang, fora do
  sitemap. Sem listas sociais, bio ou e-mail.
- Imagem: reutiliza `renderSvg`, `rasterizeSvg`, `fonts`, `downloadBlob` e
  `sanitizePaletteName`; uma árvore SVG nova para os três layouts, com a fonte
  embutida na exportação e um único serializador sanitizado por construção.

## Etapas

### Etapa 1 — Base de dados, identidade anônima, notificações e config

Migrations aditivas:

- `database/migrations/2026_11_14_000001_create_color_palette_likes_table.php`
  — ULID; `color_palette_id` FK cascade; `user_id` nullable FK cascade;
  `anonymous_id` string(64) nullable; `liked_at`/`notified_at` nullable;
  timestamps; unique `(color_palette_id, user_id)`, unique
  `(color_palette_id, anonymous_id)`, índices por paleta+liked_at, user+liked_at
  e anonymous_id+liked_at.
- `..._000002_create_user_follows_table.php` — ULID; `follower_id`/`followed_id`
  FK users cascade; `followed_at`/`notified_at` nullable; unique
  `(follower_id, followed_id)`; índices por followed/follower + followed_at.
- `..._000003_create_notifications_table.php` — UUID, type, morphs notifiable,
  data, read_at, timestamps (stub padrão do Laravel).

Modelos: `ColorPaletteLike`, `UserFollow` (HasUlids, casts, scopes `active`,
relações). Em `ColorPalette`: `likes()`. Em `User`: `paletteLikes()`,
`following()`, `followers()`.

`app/Support/Colors/AnonymousIdentity.php`: ler/emitir o id, criar/esquecer o
cookie e derivar a chave HMAC de IP (`hash_hmac('sha256', 'colors-ip|'.$ip,
config('app.key'))`). O valor do cookie precisa casar `[a-f0-9]{64}`. Não incluir
`colors_anon` nas exceções do `EncryptCookies`.

Notificações: `app/Notifications/Colors/PaletteLiked.php` e `UserFollowed.php`,
`via()` somente `database`, dados sem identificador anônimo/IP/e-mail; usar
`ShouldQueueAfterCommit` (testes com fila sync).

Config: `colors.social.cookie_days` (365), `colors.social.profile_per_page` (12)
e `colors.rates.social_per_minute` (env `COLORS_SOCIAL_RATE_LIMIT`, 60).
`ColorsServiceProvider`: limitador `colors-social` (user:{id} ou ip HMAC) e
registro do listener de login.

Testes: unidade do `AnonymousIdentity` (formato, cookie, HMAC sem IP cru);
schema/uniques; limitador.

### Etapa 2 — Backend de curtidas

- `LikePalette`/`UnlikePalette`: transação, `firstOrNew` + catch de
  `UniqueConstraintViolationException` + re-leitura com lock; ativa/desativa
  `liked_at`; nunca apaga a linha; notificação reivindicada com um `UPDATE ...
  WHERE notified_at IS NULL` (at-most-once), sem notificar o próprio dono nem
  dono suspenso/anonimizado; marca `notified_at` mesmo quando não notifica.
- `Api\Colors\PaletteLikeController@store/destroy`: resolve a paleta por
  `publiclyVisible()` (404 uniforme), 403 para paleta própria logada, cria o
  cookie anônimo só no primeiro like bem-sucedido; resposta
  `{liked, likes_count}` sem identificadores.
- Rotas `POST/DELETE api/tools/colors/palettes/{colorPalette}/like`
  (`whereUlid`), fora do grupo auth, com `throttle:colors-social`.
- Testes: idempotência, descurtir/re-curtir, contagem ativa, 403/404, cookie
  emitido, 429, resposta sem `anonymous_id`/IP, notificação única (inclusive
  anônima sem ator), concorrência no unique.

### Etapa 3 — Backend de follows

- `FollowUser`/`UnfollowUser` com o mesmo desenho de linha permanente e
  `notified_at`.
- `Api\Colors\UserFollowController@store/destroy`: exige login (401), 403 para
  si, 404 para alvo inexistente/soft-deleted/suspenso/anonimizado/sem username;
  resposta `{following, followers_count}`.
- Rotas `POST/DELETE api/tools/colors/users/{user}/follow` em grupo
  `['auth','verified','active']` + `throttle:colors-social`.
- Testes: idempotência, auto-follow, alvos invisíveis, contagem ativa,
  notificação única, sem e-mail.

### Etapa 4 — Reivindicação, perfil público e paleta pública

- `ClaimAnonymousLikes::handle(User $user, string $rawId)`: transação; converte
  linhas ativas para a conta, mescla com linha existente (ativa vence; senão
  usa o timestamp anônimo; `notified_at` mantém o primeiro valor não nulo),
  descarta inativas, curtidas em paleta do próprio usuário e paletas
  desaparecidas; idempotente; catch de corrida no unique.
- `app/Listeners/Colors/ClaimAnonymousLikesOnLogin.php`: no `Login`, lê o
  cookie, chama a ação, esquece o cookie em sucesso; em falha, login segue,
  cookie permanece e o log não traz cookie/id/IP.
- `PublicProfileController` + `ProfilePresenter`: perfil por username, 404 para
  inexistente/soft-deleted/suspenso/anonimizado; paletas públicas e ativas,
  `withCount` de curtidas ativas, paginação `profile_per_page`, contadores de
  seguidores/seguindo; props `profile`, `palettes`, `following`, `canFollow`,
  `isOwnProfile`.
- Rota `GET {locale}/u/{username}` (`colors.profiles.show`, padrão
  `[a-z0-9_]{3,30}`), dentro do grupo de locale já existente.
- `Seo::pageMeta` ganha `colors.profiles.show` → `profileMeta` (title/description
  traduzidos, mesma resolução 404); não entra no sitemap.
- `PalettePresenter::public` ganha `author` (id, name, username, avatar; null se
  inelegível) e `likes_count`; `PublicPaletteController` ganha `liked`,
  `canLike`, `following`, `canFollow` e o autor com follow. `liked` anônimo lê o
  cookie; favoritos da F5 não mudam.
- Testes: claim por login/cadastro/login social, conflitos e cookie; perfil
  (conteúdo, visibilidade, username trocado, sem PII); SEO/canonical/sitemap;
  props sociais da paleta pública e favorito intacto.

### Etapa 5 — Frontend social

- Tipos em `resources/js/types/colors.ts` (autor, respostas de like/follow,
  perfil, paginador de perfil; `PalettePublic.author`/`likes_count`).
- `usePaletteLike` e `useUserFollow`: estado otimista com rollback e
  reconciliação; reaproveitam `colorsApi` (CSRF/credentials já prontos).
- `LikeButton.vue`, `FollowButton.vue` e `ProfilePaletteCard.vue` (prévia, nome,
  curtidas; sem ação de curtir no card).
- `pages/colors/PublicProfile.vue`: header com avatar, nome, `@username`,
  contadores e follow; grid de paletas; vazio; paginação preservando query.
- `PublicPalette.vue`: byline com link para o perfil, curtida e follow na linha
  de ações, sem interferir no favorito/duplicar/experimentar.
- Visitante: o follow leva ao login (sem intent novo); a curtida anônima
  funciona direto.
- i18n `colors.social`, `colors.profile`, `colors.image`,
  `colors.public.by` nos 5 idiomas (paridade exata).
- Testes Vitest de like/follow (otimismo, rollback, aria), perfil e paleta
  pública (byline, cards, favorito intacto).

### Etapa 6 — Imagem, ADRs, docs e gates

- Alargar `renderSvg` para `SvgDocument` (width/height/label/nodes) e extrair
  `documentSvg`/`documentPng` (fonte dinâmica, PNG 1x/2x do mesmo SVG).
- Novo módulo `resources/js/tools/palette-generator/palette-image/`:
  `types.ts`, `defaults.ts` (layouts 1600×500, 1080×1920, 1080×1080; watermark
  `devtools.natanfiuza.dev.br`), `templates/{horizontal,vertical,card}.ts`,
  `exportPaletteImage.ts` (nome sanitizado + layout + 1x/2x, MIME SVG) e
  `PaletteImagePanel.vue` (layout, prévia, SVG/PNG 1x/PNG 2x, erro traduzido).
- Conteúdo: nome, cada HEX (HEX8 com alpha) e tom apenas na escala; fonte
  Libre Franklin embutida na exportação; nenhuma referência externa; textos
  escapados pelo serializador fixo.
- Editor: aba **Imagem** em `PaletteEditor.vue` usando o nome/cores persistidos.
  Pública: seção recolhível em `PublicPalette.vue`, disponível a visitante.
- Testes Vitest: dimensões, watermark, HEX/HEX8, tons só na escala, escaping,
  ausência de `href`/`<image`/`<use`/script/URL externa, determinismo, fonte no
  SVG exportado e ausente no preview, tamanhos 1x/2x, nomes/MIME, painel e
  integração nas duas telas.
- ADRs novas: 134 perfil público; 135 curtidas/identidade anônima; 136 seguir;
  137 notificações no banco; 138 imagem tipográfica. Emendas datadas em
  ADR-033, ADR-098, ADR-106, ADR-107, ADR-112, ADR-113, ADR-115, ADR-124,
  ADR-126 e ADR-128.
- Docs: SPEC-COLORS v1.3 (§10, 11, 13, 14, 15, 16, 17), `docs/colors/social.md`,
  `paleta-publica.md`, `import-export.md`, novo `perfil-publico.md`, README e
  glossário (curtida, seguidor, perfil público). Relatório da fase em
  `docs/claude-code/reports/feature_colors-fase-6/`.
- Gates: `composer test`, `npm run lint:check`, `npm run types:check`,
  `npm run test`, `npm run build`, `npm run build:ssr`; `format:check` é
  pré-existente (248 arquivos) e será rodado e documentado. Smoke manual de
  cookie/claim, perfil, autor/curtida/follow, imagem e sitemap. Sem commit/push.

## Arquivos críticos

- `routes/colors.php`
- `app/Support/Colors/PalettePresenter.php`
- `app/Http/Controllers/Colors/PublicPaletteController.php`
- `app/Support/Seo.php`
- `app/Providers/ColorsServiceProvider.php`
- `resources/js/pages/colors/PublicPalette.vue`
- `resources/js/pages/dashboard/colors/PaletteEditor.vue`
- `resources/js/tools/palette-generator/prototype/{types,renderSvg,exportPrototype,fonts}.ts`
- `resources/js/tools/palette-generator/lib/download.ts`,
  `lib/exporters.ts`

## Riscos

- NULL em índice único (SQLite/MySQL) exige os dois uniques separados; o XOR do
  ator é validado nas actions.
- CSRF no like anônimo: a rota é web, não API stateless; não isentar.
- Cookie cifrado nos testes: usar o valor real da resposta/`Crypt`, não
  `withUnencryptedCookie`.
- Fila: `ShouldQueueAfterCommit` só grava depois do commit; worker necessário em
  produção; sem e-mail.
- Contagem sempre por `withCount`/escopo ativo, nunca contador denormalizado.
- Visibilidade: like sobrevive a despublicar/arquivar, mas página, contagem e
  endpoint respondem 404 até voltar a pública.
- Claim concorrente: catch de unique + re-leitura.
- Nunca logar cookie, id anônimo, IP ou payload; erro genérico.
- Fonte embutida: import dinâmico para o preview/SSR não carregarem ~290 KB.
- `format:check` pré-existente: não tratar como regressão desta fase.
