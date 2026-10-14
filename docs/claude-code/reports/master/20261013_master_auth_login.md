# Relatorio - tela de login no design system, auth sob prefixo, login social e newsletter

Branch: `master`
Data: 2026-10-13
Tarefa: "Crie uma tela de login no estilo do design system" (grill de 6 rodadas)
Plano: `docs/plans/master/20261013_auth_login_plansfacts.md`
Levantamento: `docs/survey/20261013_auth_login_surveyfacts.md`

## 1. O que foi entregue

O pedido inicial era a tela de login. O grill mostrou que ela nao existia
sozinha: as telas do kit estavam em ingles, o auth nao tinha prefixo de idioma e
nao havia nada de conta social, newsletter ou paginas legais. O escopo fechado
foi o ciclo de autenticacao inteiro, em 10 blocos (4.1 a 4.10), todos concluidos.

| # | Bloco | Estado |
|---|---|---|
| 4.1 | Rotas e idioma: `fortify.paths` com `{locale}`, `Route::pattern`, `URL::defaults` | concluido |
| 4.2 | SEO: prop `seo.noindex` + corte de hreflang no blade | concluido |
| 4.3 | Banco e modelos: `social_accounts`, `users.password` anulavel, `newsletter_subscribers` | concluido |
| 4.4 | OAuth: Socialite, `config/services`, rotas `/auth/{provider}`, controller | concluido |
| 4.5 | Conta sem senha: rota `password.set` + controller | concluido |
| 4.6 | Newsletter: rotas, controller, mailable, checkbox no Register | concluido |
| 4.7 | i18n: `auth`, `newsletter`, `legal`, `mail`, `passwords` nos 5 idiomas | concluido |
| 4.8 | Telas: moldura nova, Login/Register/etc, newsletter, paginas legais | concluido |
| 4.9 | Testes: Pest + Vitest + ajuste da suite | concluido |
| 4.10 | Docs e fechamento: ADRs, SPEC, glossario, CHANGELOG, relatorio | concluido |

## 2. Decisoes novas (ADRs)

- **ADR-022 - auth sob prefixo de idioma**: todas as telas do Fortify sob
  `/{locale}` (nomes de rota preservados: `route('login')` continua valendo);
  rotas de maquina fora do prefixo (`/api`, `/webhooks`, `/admin`,
  `/auth/{provider}/*`). O idioma de uma ida ao provedor viaja na sessao.
- **ADR-023 - login social e conta sem senha**: Google e GitHub via Socialite,
  vinculo com conta existente **so** com e-mail confirmado pelo provedor, conta
  nova nasce com `password` nulo, primeira senha definida na propria tela de
  confirmar senha.
- **ADR-024 - newsletter com duplo opt-in**: confirmacao e descadastro decididos
  por POST (GET so mostra a pagina com o botao), token novo a cada envio,
  reinscricao de descadastrado volta como pendente.

Glossario: Conta social, Vinculo social, Conta sem senha, Double opt-in,
Descadastro, Pagina legal.

## 3. Banco de dados

Tres migrations, todas retrocompativeis (aditivas ou de nulabilidade), prontas
para o deploy por cron sem downtime:

- `2026_10_13_000001_create_social_accounts_table` - `user_id`, `provider`,
  `provider_id`, unico por (`provider`, `provider_id`).
- `2026_10_13_000002_make_users_password_nullable` - conta social nao tem senha.
- `2026_10_13_000003_create_newsletter_subscribers_table` - `email` (unico),
  `token`, `locale`, `source`, `confirmed_at`, `unsubscribed_at`.

## 4. Rotas

**Prefixedas** (`/{locale}`, slug validado pelo `Route::pattern` global):
`login`, `login.store`, `logout`, `register`, `register.store`,
`password.request`, `password.email`, `password.reset`, `password.update`,
`password.confirm`, `password.confirmation`, `verification.notice`,
`verification.verify`, `verification.send`, `privacy`, `terms`,
`password.set`, `newsletter.subscribe`, `newsletter.confirm(.store)`,
`newsletter.unsubscribe(.store)`.

**Sem prefixo**: `/` (resolve e redireciona), `dashboard`, `settings/*`,
`auth/{provider}/redirect`, `auth/{provider}/callback`.

## 5. Telas

- `AuthCard.vue` (card com borda de 1px, sem sombra), `AuthLayout.vue`
  reconstruido (barra de 56px com wordmark, seletor de idioma e tema; rodape com
  Privacidade/Termos), `SocialAuthButtons.vue` e `NewsletterForm.vue` novos.
- `Login.vue` reconstruido no design system; `Register.vue`, `ForgotPassword`,
  `ResetPassword`, `VerifyEmail` e `ConfirmPassword` traduzidos na moldura nova.
  `ConfirmPassword` ganhou o formulario de **definir senha** para conta sem senha.
- `Privacy.vue`, `Terms.vue` e `LegalDocument.vue` (as duas paginas sao o mesmo
  documento com conteudo diferente), `newsletter/Confirm.vue` e
  `newsletter/Unsubscribe.vue`.
- `AuthCardLayout.vue`, `AuthSimpleLayout.vue` e `AuthSplitLayout.vue` apagados
  (orfaos depois da moldura nova).
- `docs/design/DESIGN.md` ganhou a secao "Tela de acesso".

## 6. Bugs encontrados e corrigidos

Os cinco primeiros apareceram ao escrever os testes; o ultimo, no smoke de SSR.

1. **Ordem de middleware (bug de producao)** - o `Authenticate` esta na lista
   `$middlewarePriority` do framework e por isso rodava **antes** do
   `ResolveLocale`, que era anexado ao grupo `web`. Visitante sem sessao que
   abria `/dashboard` era redirecionado para o login antes de o idioma existir:
   `UrlGenerationException` no servidor. Mascarado na suite pelo
   `URL::defaults` do `TestCase`. Corrigido com
   `prependToPriorityList(before: AuthenticatesRequests::class, ...)`.
   Confirmado no smoke: `/dashboard` -> `/en/login`, `/pt-br/user/confirm-password`
   -> `/pt-br/login`.
2. **Link de token invalido** da newsletter montava a URL da home com o codigo
   do idioma (`pt_BR`) em vez do slug (`pt-br`).
3. **`User::preferredLocale()`** devolvia `null` sem idioma na conta: o texto
   saia no idioma do app e o link em outro (a fila nao tem idioma de requisicao
   para herdar). Passou a cair no fallback.
4. **Reinscricao** nao limpava `confirmed_at`: quem se descadastrava voltava
   para a lista sem confirmar o e-mail novo (double opt-in virado formalidade).
5. **`Legal::updatedAt()`** estourava com data invalida em `config/legal.php` e
   derrubava as paginas publicas; agora cai para hoje.
6. **`SocialProviders::emailIsVerified()`** lia o payload cru fora do contrato
   do Socialite (`getRaw()` so existe em `AbstractUser`), o que quebraria com
   outro driver.
7. **Placeholder de e-mail quebrava a tela de login inteira no SSR** - o `@` de
   `voce@exemplo.com` e sintaxe de mensagem vinculada do vue-i18n e a mensagem
   nao compilava; a pagina saia sem renderizacao de servidor (e, no navegador, o
   placeholder sairia cru). Os 20 textos afetados (5 idiomas) passaram a usar
   `{'@'}`, e `TranslationsParityTest` agora falha se aparecer `@` ou `|` cru
   numa traducao.

O bug 7 e o mais instrutivo: **nenhum teste pegava**. Os testes de componente
usam traducao marcadora (para denunciar texto fixo) e nunca compilam o texto
real; so o SSR, que compila as mensagens de verdade, expos a falha.

## 7. Desvios do plano (conscientes)

- **`LegalController` no lugar de closures `Route::inertia`** para
  `/{locale}/privacy` e `/{locale}/terms`: o contrato e `route('privacy', [...])`
  e o controller pode ser cacheado; a closure nao sobrevive ao `route:cache` do
  deploy.
- **`AuthCard` renderizado por cada pagina**, e nao titulo/subtitulo passados
  como props do layout: cada tela monta o proprio card (titulo, descricao, links
  do rodape) e o layout ficou so com a moldura (barra + fundo).
- **Botoes sociais tambem no Register**: o grill decidiu "sempre visiveis" e a
  tela de cadastro e o lugar mais provavel em que a pessoa vai querer o atalho.
- **i18n das telas de settings ficou de fora** (carry-over da F4): o escopo
  fechado era o ciclo de autenticacao; `settings/Profile.vue` e companhia
  continuam em portugues e entram na F4.
- **Mensagens de validacao nos 5 idiomas ficaram de fora**: o Laravel so traz
  `validation.php` em ingles, entao erro de formulario nas telas traduzidas sai
  em ingles. Registrado como carry-over da F4 (SPEC secao 11.2).
- **Newsletter no rodape publico + checkbox no cadastro** (era "rodape publico"
  na pergunta do grill, com o checkbox entrando como consequencia do Register
  traduzido): as duas origens entram com `source` distinto.

## 8. Verificacao executada

| Comando | Resultado |
|---|---|
| `composer test` (config:clear + Pint + PHPStan nivel 7 + Pest) | Pint limpo, **PHPStan 0 erros**, Pest **113 testes / 2010 assercoes** |
| `npm run test` (Vitest 5) | **75 testes em 5 arquivos** |
| `npm run types:check` (wayfinder + `vue-tsc --noEmit`) | limpo, TS estrito |
| `npm run build` | exit 0 |
| `npm run build:ssr` | exit 0 |

Testes novos: `LocalizedAuthPagesTest`, `SocialLoginTest`, `SetPasswordTest`,
`NewsletterTest`, `LegalPagesTest`, `MailLocaleTest` (Pest) e
`auth-login.test.ts`, `newsletter-form.test.ts` (Vitest) - ~1150 linhas. O
`SocialLoginTest` usa `Socialite::fake` (conta nova, vinculo por e-mail
verificado, recusa sem verificacao, provedor sem credencial).

## 9. Smoke de SSR

Banco SQLite descartavel, `php artisan serve` com `INERTIA_SSR_ENABLED=true` e o
bundle `node bootstrap/ssr/ssr.js` no 13714. Todas as telas novas renderizam no
servidor (`data-server-rendered="true"` no HTML):

| URL | HTTP | SSR | Tamanho |
|---|---|---|---|
| `/pt-br/login` | 200 | sim | 33.8 KB |
| `/pt-br/register` | 200 | sim | 37.4 KB |
| `/fr/forgot-password` | 200 | sim | 28.3 KB |
| `/es/reset-password/{token}` | 200 | sim | 30.9 KB |
| `/pt-br/newsletter/confirm/{token}` | 200 | sim | 32.7 KB |
| `/fr/newsletter/unsubscribe/{token}` | 200 | sim | 34.0 KB |
| `/en/privacy` | 200 | sim | 35.6 KB |
| `/pt-pt/terms` | 200 | sim | 36.1 KB |

Conferido tambem no HTML: `noindex, follow` + canonical e **sem** hreflang nas
telas de acesso; hreflang completo (5 idiomas + `x-default`) nas paginas legais;
`placeholder="voce@exemplo.com"` renderizado corretamente depois da correcao do
`@`; `<title>` da tela chegando no head (`Entrar - devtools`). Log do SSR sem
erro depois da correcao (o unico erro registrado foi o bug 7, encontrado ali).

Os 302 esperados: `/es/email/verify` -> `/es/login` e
`/pt-br/user/confirm-password` -> `/pt-br/login` (area autenticada, visitante
sem sessao), nos dois casos com o idioma certo na URL.

## 10. Nao verificado daqui

- **Newsletter ponta a ponta com e-mail real**: o mailable e `ShouldQueue` e o
  `QUEUE_CONNECTION` do `.env` e `database`; o fluxo completo esta coberto pelos
  testes (envio, confirmacao, descadastro, duplicado, token invalido), mas nao
  foi exercitado com um worker de verdade no smoke. O RUNBOOK cobre o worker.
- **Credenciais reais do Google e do GitHub**: o botao foi exercitado com
  `Socialite::fake`; o primeiro login contra os provedores de verdade depende de
  cadastrar cliente e URL de callback nos consoles (carry-over de operacao).
- **Visual**: a conferencia de layout/tema das telas novas e da revisao do
  usuario (`npm run dev` + navegador).

## 11. Carry-overs

- **F4**: i18n das telas de settings, mensagens de validacao nos 5 idiomas,
  decidir se 2FA/passkeys voltam, `users.appearance` gravado pela conta.
- **Operacao (usuario)**: credenciais Google/GitHub no `.env` e URLs de callback
  nos consoles; `MAIL_FROM_ADDRESS` revisado; chaves no VPS.
- **F1**: home real, registro de tools, Cmd+K, banner LGPD, GA4, sitemap.

## 12. Estado do repositorio

**Nenhum commit foi feito** (regra do `CLAUDE.md`). A arvore tem 81 entradas de
`git status` (57 arquivos modificados, 20 novos, 3 removidos - fora
`.gitpr/reports/`, que nao e desta tarefa) prontas para o usuario revisar e
commitar pelo gitpr.

Artefatos de build (`public/build`, `bootstrap/ssr`, `resources/js/actions`,
`resources/js/routes`) estao no `.gitignore` e continuam no disco, atualizados
pelos builds finais. O banco SQLite e os HTML do smoke foram apagados; nenhum
arquivo de depuracao ficou para tras.
