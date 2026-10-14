# Plano - Tela de login, auth completo e newsletter

Data: 2026-10-13
Branch: master
Origem: sessao de grill "Crie uma tela de login no estilo do design system"
Levantamento: `docs/survey/20261013_auth_login_surveyfacts.md`
Fontes: `docs/SPEC.md` (F4 antecipada em parte), `docs/design/DESIGN.md`, `CLAUDE.md`

## 1. Contexto

O pedido inicial era uma tela de login no estilo do DESIGN.md. O grill expandiu
para: telas de acesso na moldura nova e nos 5 idiomas, todo o auth sob prefixo
de idioma, login social (Google e GitHub), contas sem senha com fluxo de
definir senha, newsletter com double opt-in e paginas de privacidade/termos.
e uma antecipacao parcial da F4 do SPEC.

## 2. Decisoes fechadas (grill)

| # | Decisao |
|---|---|
| 1 | Escopo de telas: Login, Register, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword - todas na moldura nova e traduzidas |
| 2 | Moldura: tela focada com barra minima (wordmark + idioma + tema), sem nav e sem busca, rodape de uma linha (Privacidade, Termos) |
| 3 | **Todo o auth sob `/{locale}`** (era so a tela de login; virou todo o grupo) |
| 4 | Login social **Google e GitHub**, com botões **sempre visiveis** no Login e no Register; sem chave no `.env` o clique volta ao login com aviso, nunca 500 |
| 5 | Vinculo social em tabela **`social_accounts`** (os dois provedores na mesma conta) |
| 6 | Vinculo a conta existente **so quando o provedor confirma o e-mail**; sem confirmacao, recusa com mensagem clara |
| 7 | Conta social nasce com `email_verified_at` **se o provedor confirmar**; senao cai no `/verify-email` |
| 8 | Conta sem senha: o formulario de **definir senha vive na propria tela de confirmar senha** (que hoje a tranca por causa do `RequirePassword`); ao definir, a sessao fica confirmada e a pessoa segue |
| 9 | Newsletter com **double opt-in entra agora**: checkbox no Register + formulario no rodape publico |
| 10 | Descadastro e confirmacao em **pagina com botao** (GET mostra, POST aplica) - evita scanner de e-mail aplicando sozinho |
| 11 | **Envio de campanhas da newsletter fica para o admin (F7)**; esta tarefa entrega captura, confirmacao, lista e descadastro |
| 12 | **E-mails** (reset, verificacao, newsletter) traduzidos nos 5 idiomas |
| 13 | **Paginas de privacidade/termos criadas agora** (`/{locale}/privacy` e `/{locale}/terms`), texto minimo e honesto nos 5 idiomas, revisado pelo usuario antes de publicar |
| 14 | SEO: telas de acesso e paginas de token com **`noindex, follow` + canonical, sem hreflang** |
| 15 | Testes: **Pest** para todo o fluxo + **Vitest** para `Login.vue` e o formulario da newsletter |
| 16 | E-mail de producao por **SMTP generico no `.env`** (local segue `log`); escolha do provedor e do usuario na publicacao |
| 17 | Nenhum commit do assistente; o usuario fecha pelo gitpr |

## 3. Fatos que sustentam o plano

- `laravel/socialite:^5.31` resolve limpo (v5.31.0 suporta `illuminate ^13`;
  `guzzlehttp/guzzle 8.2.0` ja instalado). `^6.0` nao existe estavel e o
  `dev-master` (6.x-dev) exige `illuminate ^6..^10` - inutilizavel aqui.
- Fortify registra as rotas com prefixo estatico e le os caminhos em
  `config('fortify.paths.*')` via `RoutePath::for()` - da para prefixar com
  `{locale}` sem `Fortify::ignoreRoutes()`.
- `Route::pattern('locale', ...)` global cobre rotas registradas por terceiros
  (o Fortify nao expoe `whereIn` no grupo dele).
- Wayfinder gera `args` **obrigatorio** para rota com parametro
  (`resources/js/routes/index.ts:289` e `:304`); `applyUrlDefaults` existe
  (`resources/js/wayfinder/index.ts:132`) e `setUrlDefaults`/`addUrlDefault`
  em `:116`/`:120`. Todo call site de auth passa a receber o slug do idioma.
- `require-de-password`: `routes/settings.php:20-22` poe `RequirePassword` em
  `settings/security` - e o que tranca a conta sem senha.
- `users.password` nao e nullable (`database/migrations/0001_01_01_000000_create_users_table.php:19`).
- Nao existe `lang/*/auth.php`; o framework so tem `en/{auth,passwords,validation,pagination}.php`
  e o `FileLoader` faz `array_replace_recursive` com o app por ultimo - arquivo
  parcial e seguro.
- `app/Http/Middleware/ResolveLocale.php` NAO chama `URL::defaults` hoje; sem
  isso `route('login')` quebraria com parametro obrigatorio.
- `Locales::alternates()` (`app\Support\Locales.php:127`) devolve hreflang para
  qualquer rota com `{locale}` - as telas de acesso precisam do corte por
  `noindex`.
- Testes que mudam: `tests/Feature/LocaleRoutingTest.php:107-112` e `:128-130`
  (login sem prefixo / sem hreflang), suite `tests/Feature/Auth/*` (route() sem
  locale), `resources/js/__tests__/i18n.test.ts:191`.
- `AuthCardLayout.vue` e `AuthSplitLayout.vue` sao orfaos (nenhum import).
- `User` nao implementa `HasLocalePreference` - e o gancho para os e-mails
  sairem no idioma da conta.
- `.env.example` nao tem secao `SERVICES_*`; `config/services.php` so tem
  mailgun/postmark/ses/slack/resend.
- Rodape publico hoje nao tem links de privacidade/termos (so as chaves
  `app.footer.privacy`/`terms` existem, sem uso).

## 4. Plano de execucao

### 4.1 Rotas e idioma (fundacao)

- [ ] `config/fortify.php`: adicionar `paths` com `{locale}` em cada caminho
  (login, logout, register, password.request/email/reset/update/confirm, user
  confirm-password, verification.*, user profile-information/user-password).
- [ ] `Route::pattern('locale', implode('|', Locales::slugs()))` no
  `AppServiceProvider::boot()` (cobre rotas do Fortify e as nossas).
- [ ] `ResolveLocale`: `URL::defaults(['locale' => Locales::slug($localized)])`
  para `route('login')` e o e-mail de reset funcionarem sem passar o parametro.
- [ ] Comentarios/docblocks atualizados (o middleware hoje diz "demais rotas:
  login, dashboard, settings").
- [ ] `config/locales.php`: expor os namespaces `auth` e `legal` ao front.

### 4.2 SEO (noindex)

- [ ] Blade (`resources/views/app.blade.php`): quando `seo.noindex` for true,
  emitir `<meta name="robots" content="noindex, follow">` + canonical da propria
  pagina e **nao** emitir hreflang; caso contrario, comportamento atual.
- [ ] Prop `seo` nos renders de auth (FortifyServiceProvider) e nas paginas de
  token da newsletter; as paginas legais ficam fora (indexaveis, com hreflang).

### 4.3 Banco e modelos

- [ ] Migration `create_social_accounts_table`: `user_id` (FK, cascade),
  `provider` string(32), `provider_id` string(255), timestamps,
  unique(provider, provider_id). Retrocompativel (tabela nova).
- [ ] Migration `make_users_password_nullable` (`->nullable()->change()`,
  nativo no Laravel 13). Retrocompativel.
- [ ] Migration `create_newsletter_subscribers_table`: email unique,
  token string(64) unique, locale string(5), source string(16),
  confirmed_at, unsubscribed_at, timestamps. **Sem IP** (minimizacao).
- [ ] `App\Models\SocialAccount` + relacao `User::socialAccounts()`;
  `User::hasPassword(): bool`; `User implements HasLocalePreference`
  (`preferredLocale()` devolvendo `users.locale`).
- [ ] `App\Models\NewsletterSubscriber` (casts de data, scopes confirmed /
  pending).
- [ ] `App\Support\SocialProviders` (lista google/github + `configured()`).

### 4.4 Login social (OAuth)

- [ ] `composer require laravel/socialite:^5.31` (lock atualizado; sem commit).
- [ ] `config/services.php` + `.env.example`: google/github com
  client_id/secret/redirect; URLs de callback documentadas (local e producao:
  `https://devtools.natanfiuza.dev.br/auth/{provider}/callback`).
- [ ] `routes/auth-social.php` (carregado pelo `web.php`):
  `GET /auth/{provider}/redirect` (`auth.redirect`) e
  `GET /auth/{provider}/callback` (`auth.callback`), **sem prefixo de idioma**
  (endpoint de maquina, como `/api` e `/webhooks`), `whereIn` nos provedores e
  `throttle`.
- [ ] `SocialAuthController`: sem credencial -> volta ao login com aviso
  (i18n, via `status`); callback -> busca/cria vinculo, vincula por e-mail so
  com `email_verified` do provedor, cria usuario sem senha com `locale` atual e
  `email_verified_at` conforme o provedor, `Auth::login($user, true)` e
  `redirect()->intended(route('dashboard'))`.
- [ ] `App\Actions\Fortify\CreateNewUser`: le `newsletter` do input e inscreve
  (double opt-in) quando marcado.
- [ ] `SocialAuthButtons.vue` (Google + GitHub, full-width, icone + rotulo,
  divisoria "ou") usado no Login e no Register; **sempre visiveis** (decisao
  4).

### 4.5 Conta sem senha

- [ ] Rota `POST /{locale}/user/password/set` (`password.set`), `auth` +
  `verified`, throttle, controller que so aceita usuario com `password` nulo,
  valida `Password::defaults()` + confirmed, grava e marca
  `session('auth.password_confirmed_at')`, depois `redirect()->intended(route('security.edit'))`.
- [ ] `ConfirmPassword.vue`: prop `hasPassword`; sem senha mostra "defina sua
  senha" (postando em `password.set`), com senha mantem o comportamento atual.
- [ ] `FortifyServiceProvider`: passar `hasPassword` na view de confirmacao.

### 4.6 Newsletter (double opt-in)

- [ ] Rotas sob o prefixo de idioma: `POST /{locale}/newsletter`
  (`newsletter.subscribe`, throttle), `GET|POST /{locale}/newsletter/confirm/{token}`
  (`newsletter.confirm` / `.store`), `GET|POST /{locale}/newsletter/unsubscribe/{token}`
  (`newsletter.unsubscribe` / `.store`).
- [ ] `NewsletterController` (subscribe idempotente: ja confirmado responde
  "voce ja esta inscrito"; pendente reenvia), paginas de confirmar/descadastar
  com botao, `noindex`.
- [ ] Mail `NewsletterConfirmation` (fila, `ShouldQueue`) no idioma do inscrito
  (`->locale($subscriber->locale)`).
- [ ] `NewsletterForm.vue` no rodape do `PublicLayout` (email + botao, estados
  idle/enviado/erro) + checkbox de consentimento no Register (desmarcado por
  padrao) com o texto de finalidade e links para Privacy/Terms.

### 4.7 E-mails traduzidos

- [ ] `lang/{locale}/auth.php`: strings das telas de auth **e** as chaves que o
  Fortify consome (`failed`, `throttle`, `password`, ...).
- [ ] `lang/{locale}/mail.php`: assuntos e corpos de reset, verificacao e
  confirmacao de newsletter (namespace nao exposto ao front).
- [ ] Notifications proprias (`App\Notifications\ResetPassword` e
  `VerifyEmail`) com chaves limpas, ligadas via `User` (override de
  `sendPasswordResetNotification`/`sendEmailVerificationNotification`), no
  idioma da conta.
- [ ] `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` revisados no `.env.example`.

### 4.8 Telas (design system)

- [ ] `AuthLayout.vue`/`auth/AuthSimpleLayout.vue` refeitos: barra de 56px
  (wordmark + `LocaleSwitcher` + `ThemeToggle`), conteudo centrado em card com
  borda (sem sombra), rodape de uma linha com Privacidade/Termos.
- [ ] Apagar `AuthCardLayout.vue` e `AuthSplitLayout.vue` (orfaos).
- [ ] `Login.vue` reconstruido: botões sociais + divisoria, e-mail (foco
  inicial), `PasswordInput`, "Esqueci minha senha" condicional, botao primario
  com `Spinner`, banner de `status`, erros com `aria-live`/`aria-invalid`,
  link para Register; `data-test="login-button"` preservado; zero texto
  hardcoded.
- [ ] `Register.vue`: mesma moldura + `passwordRules` + checkbox da newsletter
  + microcopy de termos; `ForgotPassword`, `ResetPassword`, `VerifyEmail` e
  `ConfirmPassword` traduzidos na moldura nova.
- [ ] Call sites do wayfinder: passar o slug do idioma (composable/props) nos
  ~13 pontos (`Login`, `Register`, `ForgotPassword`, `ResetPassword`,
  `ConfirmPassword`, `VerifyEmail`, `settings/Profile.vue`,
  `components/UserMenuContent.vue`); `npm run types:check` acusa o que faltar.
- [ ] Paginas `Privacy.vue` e `Terms.vue` sob `/{locale}`, no `PublicLayout`,
  com conteudo i18n (`legal`) e links no rodape publico.
- [ ] `DESIGN.md`: secao "Tela de acesso" (moldura, card, botões sociais).

### 4.9 Testes

- [ ] `TestCase::setUp`: `URL::defaults(['locale' => '<fallback>'])` para a
  suite existente continuar chamando `route('login')` etc.
- [ ] Ajustar `LocaleRoutingTest` (login agora prefixado; noindex/canonical no
  lugar de "sem hreflang"; caso "sem prefixo" vai para `/dashboard`) e
  `i18n.test.ts:191`.
- [ ] Novos Pest: prefixo do auth e nomes de rota preservados; noindex nas
  telas de acesso; OAuth (novo usuario, vinculo por e-mail verificado, recusa
  sem verificacao, provedor sem credencial volta com aviso, `Socialite::fake`);
  definir senha (guardas, sessao confirmada, redireciono); newsletter
  (inscrever/confirmar/descadastar/duplicado/throttle/token invalido);
  paginas legais (rota, hreflang); e-mails no idioma da conta.
- [ ] Vitest: `Login.vue` (traduzido, botões sociais, link de reset
  condicional, erro exibido) e `NewsletterForm.vue` (estados), com o padrao de
  mock de `@inertiajs/vue3` que ja existe em `appearance.test.ts:17`.

### 4.10 Docs e fechamento

- [ ] ADR-022 (auth sob prefixo de idioma + rotas de maquina fora do prefixo),
  ADR-023 (login social e contas sem senha), ADR-024 (newsletter double opt-in
  e anti-prefetch).
- [ ] `docs/glossario.md`: Conta social, Vinculo social, Conta sem senha,
  Double opt-in, Descadastro, Pagina legal.
- [ ] `docs/SPEC.md`: §3 (auth prefixado, callbacks fora), §7 (tabelas novas),
  §9 (F4 encolhe), §10, §11.
- [ ] `docs/acceptance/FASE-0.md` (carry-overs antecipados) e `CHANGELOG.md`.
- [ ] `docs/claude-code/reports/master/20261013_master_auth_login.md`
  (relatorio final, regra do CLAUDE.md).
- [ ] `composer test`, `npm run test`, `npm run types:check`, `npm run build`,
  `npm run build:ssr` verdes + smoke SSR (telas novas renderizam no servidor).
- [ ] Parar para revisao - sem commit.

## 5. Criterios de aceite

1. `/pt-br/login`, `/pt-br/register`, `/pt-br/forgot-password`,
   `/pt-br/reset-password/{token}`, `/pt-br/verify-email`,
   `/pt-br/user/confirm-password` respondem 200 e traduzem nos 5 idiomas.
2. Todo o auth sob `/{locale}`; `/auth/{provider}/*` e `/api` fora do prefixo;
   nomes de rota preservados (`route('login')` funciona no PHP e nos testes).
3. Login social: novo usuario, vinculo por e-mail verificado, recusa sem
   verificacao do provedor, provedor sem credencial volta ao login com aviso.
4. Conta sem senha define a senha na tela de confirmacao e chega em
   `settings/security` sem ficar presa.
5. Newsletter: inscrever, confirmar (pagina com botao), descadastar (pagina com
   botao), duplicado e token invalido tratados; e-mail de confirmacao no idioma
   do inscrito.
6. Telas de acesso e paginas de token com `noindex, follow` + canonical, sem
   hreflang; paginas legais com hreflang normal.
7. `/{locale}/privacy` e `/{locale}/terms` nos 5 idiomas, linkadas no rodape.
8. Zero string hardcoded nas telas tocadas; nada de modelo/preco/limite de IA.
9. `composer test`, `npm run test`, `npm run types:check`, `npm run build` e
   `npm run build:ssr` verdes.
10. Nenhum commit feito pelo assistente.

## 6. Verificacao

- `composer test` (Pint + PHPStan 7 + Pest), `npm run test` (Vitest),
  `npm run types:check`, `npm run build`, `npm run build:ssr`.
- Smoke local: `php artisan serve` + `npm run dev`; percorrer as telas nos 5
  idiomas, alternar tema, conferir foco/teclado, inscrever/confirmar/descadastar
  newsletter com `MAIL_MAILER=log` e ler o e-mail no `storage/logs`.
- Smoke SSR com `INERTIA_SSR_ENABLED=true`: `/pt-br/login` e as paginas novas
  chegando renderizadas e o `noindex` no head.
- OAuth de ponta a ponta **nao e verificavel sem credenciais**: coberto por
  teste com `Socialite` falso; o fluxo real fica para quando o usuario criar os
  apps.

## 7. Riscos

- **`fortify.paths` com `{locale}`**: se o `Route::pattern` nao cobrir o
  parametro, a rota aceita qualquer string. Teste de 404 fora da lista cobre.
- **Nome duplicado `user-password.update`** (Fortify `/user/password` x
  `routes/settings.php:26`): conferir os arquivos gerados do wayfinder depois de
  regenerar; hoje o settings ganha.
- **Churn do wayfinder**: ~13 call sites passam a exigir o idioma; o
  `types:check` acusa cada um (nada fica silencioso).
- **Suite de testes**: `route()` sem locale quebra em varios arquivos; mitigado
  pelo `URL::defaults` no `TestCase`.
- **Dois e-mails no Register com a newsletter marcada** (verificacao +
  confirmacao): aceito - consentimentos independentes; documentado.
- **Textos legais**: escritos por mim como minimo honesto; o usuario revisa
  antes de publicar.
- **Newsletter sem campanha**: a lista existe, mas o envio e o admin (F7); nao
  ha como "mandar a newsletter" ao fim desta tarefa - por decisao.

## 8. Pendencias do usuario

- Criar os apps OAuth no Google e no GitHub e preencher
  `GOOGLE_*`/`GITHUB_*` no `.env` (local e producao).
- Revisar os textos de privacidade/termos antes de publicar.
- Escolher o SMTP de producao na publicacao (RUNBOOK).

## 9. Carry-overs

- **F4**: i18n das telas de settings do kit (Profile, Security, Appearance,
  DeleteUser) e dos breadcrumbs; conectar/desconectar conta social dentro das
  configuracoes; gravar `users.appearance` pela conta.
- **F1**: banner LGPD, GA4, sitemap, OG images, registro de tools, home real,
  Cmd+K (privacidade/termos saem da lista - antecipados aqui).
- **F7**: envio de campanhas da newsletter e gestao da lista no admin.
- **Operacao**: `composer self-update` (advisory da 2.9.x), banco `devtools`
  local, confirmacoes do VPS (ja registrados na F0).
