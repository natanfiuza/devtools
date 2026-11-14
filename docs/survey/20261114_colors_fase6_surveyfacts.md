# Survey — Colors Fase 6 (social e imagem)

Data: 2026-11-14
Branch: `feature/colors-fase-6`
Contrato: `docs/colors/PROMPT-Fase6-social_imagens.md`
Estado: fronteira de decisões esgotada; decisões do usuário registradas abaixo.

## Contexto

A Fase 5 fechou a organização pessoal e os tokens. A Fase 6 entrega o primeiro
social das paletas públicas (curtir, inclusive anônimo, e seguir usuários) e a
exportação da paleta como imagem tipográfica (PNG/SVG em horizontal, vertical e
card). Comentários, denúncias, moderação, bloqueio de usuários, sino e e-mail de
notificação continuam fora desta fase.

## Fatos levantados

### Social, likes, follows e notificações

- Não existe nenhuma tabela, modelo, rota, action, controller, policy ou teste de
  like, follow, bloqueio, denúncia ou notificação de Colors.
- `color_palette_favorites` (F5) é um vínculo da conta, único por
  `(user_id, color_palette_id)`, sem coluna de ator anônimo. Curtir e favoritar
  são sinais distintos.
- `User` usa `Notifiable` e `SoftDeletes`, tem `username` opcional, único, de 3 a
  30 caracteres (`[a-z0-9_]`), `suspended_at`, `anonymized_at`, `avatar` append e
  relations `colorPalettes()`/`favoritePalettes()`. Não existem `follows`,
  `followers` nem `blocks`.
- Não existe tabela `notifications`, nenhuma notificação usa o canal `database`,
  não há sino/preferências/rotas de notificação e todos os textos de notificação
  existentes são somente e-mail. `QUEUE_CONNECTION=database` em produção e
  `sync` nos testes.
- Não há página, rota, controller ou presenter de perfil público. As rotas de
  usuário são de admin. `username` já é público no badge pessoal
  `/b/{username}/{badge}.{ext}` (ADR-033).
- O envelope `ApiError` vale para `api/*` e `b/*`. As rotas de Colors ficam em
  `routes/colors.php`, carregado por `routes/web.php`; portanto têm sessão e CSRF.
- Limitadores de Colors: `colors-management`, `colors-og` e `colors-library`,
  chaveados por `user:{id}` ou `guest:{ip}`; valores em `config/colors.php`.
  Não existe limitador social.
- Cookies: o `EncryptCookies` cifra tudo exceto `appearance`, `locale`,
  `sidebar_state` e `consent`. Não há cookie assinado próprio; a única assinatura
  existente é `URL::temporarySignedRoute` na verificação de e-mail.
- `PrivacyHash` (BadgeForge) é HMAC-SHA256 com `app.key`, sem separação de
  domínio. `TrustProxies` garante `request->ip()` útil atrás do proxy.
- `PublicPaletteController@show` entrega `palette`, `favorited`, `canFavorite` e
  `ogUrl`; `PalettePresenter::public()` não expõe autor, curtidas nem papéis. A
  documentação e as ADR-098/124 dizem que a página não mostra autor nem controles
  sociais; a F6 emenda isso.
- `PalettePresenter` tem `row`, `editor`, `favoriteRow` e `public`. O preview é
  privado e corta os HEX.
- Não há eventos/listeners (`app/Events`, `app/Listeners`) nem
  `EventServiceProvider`.
- `composer test` = config:clear + Pint + PHPStan + Pest; `npm test` = Vitest.
  Última linha de base: 1585 testes Pest / 28338 asserções e 1471 testes Vitest
  verdes. `format:check` tem falha pré-existente em 248 arquivos (Prettier ×
  `vue/html-indent`), não é gate.

### Imagem de paleta

- O serializador `prototype/renderSvg.ts` é puro e sanitizado por construção:
  apenas `rect`, `circle` e `text`; todo texto e cor passam por `escapeXml`; não
  emite `image`, `use`, `href`, script nem URL externa; a fonte entra opcionalmente
  como `<style>` com `@font-face` em data URI.
- `prototype/rasterize.ts` rasteriza o mesmo SVG em PNG 1x/2x via canvas e revoga
  a object URL; `exportPrototype.ts` embute a fonte só na exportação
  (`fonts.ts`, Libre Franklin Regular/Bold, ~290 KB em chunk dinâmico).
- Tamanhos existentes: site 1200×900, mobile 390×844, OG 1200×630. A OG é o único
  template tipográfico; não existem templates horizontal, vertical ou card, nem
  painel/botões de imagem de paleta.
- `lib/download.ts` baixa texto/blob localmente. `lib/exporters.ts` tem
  `sanitizePaletteName` (32 caracteres). Não há endpoint de imagem no servidor.
- A página pública recebe `palette.colors` (base + items com `hex` e, na escala,
  `tone`) e não recebe papéis. O editor recebe `colors` e `roles`.
- `prototype/types.ts` define `SvgNode` com `role`/`section` opcionais e
  `PrototypeDocument` com `kind`/`theme`; `renderSvg` só usa `width`, `height`,
  `label` e `nodes`, então o tipo pode ser alargado sem quebrar os protótipos.
- Strings do painel precisam de i18n nos 5 idiomas; o nome da paleta, os HEX e o
  domínio fixo são dados, não tradução. `TranslationsParityTest` exige paridade
  exata das folhas.

## Decisões do usuário

### Rodada 1

1. **Curtir × favoritar**: separados. Curtir é um sinal público com contador;
   favoritar continua privado e inalterado da F5.
2. **Seguir**: haverá **página de perfil público** por username, não apenas um
   botão na paleta.
3. **Anônimo → login**: as curtidas anônimas são **reivindicadas** pela conta no
   login/cadastro, numa transação, sem duplicar.
4. **Notificações**: **criar database notifications** já na F6.

### Rodada 2

5. **URL do perfil**: `/{locale}/u/{username}`, usando o `username` único já
   existente. Quem não tem username não tem perfil e não pode ser seguido.
6. **Conteúdo do perfil**: paletas públicas e ativas + contadores de seguidores e
   seguindo; sem listas, bio ou e-mail.
7. **Notificações**: infraestrutura sem UI. Tabela `notifications`, classes
   Curtida/NovoSeguidor no canal database e disparo idempotente; sem e-mail e sem
   sino nesta fase.
8. **Imagem**: disponível na página pública (inclusive visitante) e no editor de
   paleta salva.

### Rodada 3

9. **Autor na paleta**: byline com nome ligado ao perfil e botão Seguir também na
   página da paleta. Emenda ADR-098/124.
10. **SEO do perfil**: indexável, com title/description/canonical/hreflang por
    locale, mas **fora do sitemap**.
11. **Dimensões**: horizontal 1600×500, vertical 1080×1920, card 1080×1080.
12. **Dedupe de notificação**: uma por relação. Descurtir/deixar de seguir e
    repetir a ação não cria outra notificação.

### Rodada 4

13. **Identidade anônima**: cookie first-party `colors_anon` cifrado pelo
    `EncryptCookies`, HttpOnly, SameSite=Lax, validade de 1 ano, id aleatório de
    32 bytes. O banco guarda o id aleatório; o IP só existe como chave HMAC do
    rate limiter em cache, nunca persistido nem exposto.
14. **Estado do like/follow**: linha permanente com estado. `liked_at` nulo quando
    descurtida; `notified_at` marca a primeira notificação; re-curtir reativa a
    mesma linha. Seguir usa `followed_at`/`notified_at` com o mesmo desenho.
15. **Conteúdo da imagem**: nome, HEX por cor (HEX8 quando há alpha) e rótulo do
    tom apenas na escala; marca fixa `devtools.natanfiuza.dev.br` no rodapé.
16. **UI da imagem**: nova aba **Imagem** no editor e seção recolhível na página
    pública, com seletor de layout e PNG 1x/2x + SVG.

### Rodada 5

17. **Curtida anônima notifica**: sim, com `anonymous=true`, sem id, IP ou rótulo
    de pessoa; entra no mesmo dedupe.
18. **Troca de username**: o link antigo passa a 404; sem alias/redirecionamento.
    Seguidores e paletas não se perdem porque os vínculos usam o id da conta.
19. **Fonte da imagem**: embutir Libre Franklin no SVG e no PNG, como no
    protótipo; o PNG rasteriza o mesmo SVG.
20. **Cards do perfil**: prévia, nome e contador de curtidas; a ação de curtir
    fica na página da paleta.

## Restrições que permanecem

- Não comentar, denunciar, moderar nem bloquear usuários nesta fase.
- Não expor identificador anônimo, IP ou e-mail em props, JSON, notificação, log
  ou mensagem de erro.
- Nada de geração de imagem no servidor: tudo no navegador, sem requisição.
- Nenhuma promessa absoluta de antifraude: o identificador anônimo é um sinal
  minimizado de primeira parte para deduplicação e limitação de abuso.
- Migrations aditivas e retrocompatíveis.
- Nenhum limite ou nome de modelo fixado no código sem `config`/`.env`.
- Toda string visível via i18n nos 5 idiomas.
- Nunca commit/push; o usuário commita e abre PR pelo gitpr.
