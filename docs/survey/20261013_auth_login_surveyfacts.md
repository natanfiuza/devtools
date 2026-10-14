# Levantamento - Tela de login, auth completo e newsletter

Data: 2026-10-13
Branch: master
Pedido original: "Crie uma tela de login no estilo do design system" (docs/design/DESIGN.md)
Plano: `docs/plans/master/20261013_auth_login_plansfacts.md`

## 1. Contexto da tarefa

O pedido nasceu como uma tela de login. O grill (6 rodadas, 21 decisoes) mostrou
que a tela depende de decisoes de rota, i18n, SEO e produto que estavam abertas
desde a F0, e o escopo evoluiu para uma antecipacao parcial da F4 do SPEC:
telas de acesso traduzidas, auth inteiro sob prefixo de idioma, login social
(Google e GitHub), contas sem senha, newsletter com double opt-in e paginas de
privacidade/termos.

## 2. Rodadas do grill

### Rodada 1 - escopo da tela

| Pergunta | Resposta |
|---|---|
| Quais telas entram? | Login + ciclo de senha |
| Moldura? | Tela focada, barra minima |
| Onde fica a rota? | Mover para `/{locale}/login` |
| Strings agora ou na F4? | Traduzir agora |

### Rodada 2 - consequencias da rota

| Pergunta | Resposta |
|---|---|
| Quais rotas de auth ganham prefixo? | **Prefixo em todo o auth** (Register, verificacao, confirmacao, logout, `/user/*`) |
| Login social agora? | **Sim, Google e GitHub** |
| Nivel de teste? | **Pest + Vitest da tela** |
| SEO das telas de acesso? | noindex + canonical, sem hreflang |

### Rodada 3 - desenho do login social

| Pergunta | Resposta |
|---|---|
| Onde registrar o vinculo? | **Tabela `social_accounts`** (Google e GitHub na mesma conta) |
| E-mail que ja tem conta? | **Vincula se o provedor confirmar**; sem confirmacao, recusa |
| Conta social nasce verificada? | **Verifica se o provedor confirmar** |
| Register entra na traducao? | **Sim, Register tambem** |

### Rodada 4 - operacao e limites

| Pergunta | Resposta |
|---|---|
| Botões sociais sem credencial? | **Sempre visiveis** (o clique volta com aviso, nunca 500) |
| Callback do provedor prefixado? | **Nao**: `/auth/{provider}/...` fora do prefixo |
| Newsletter (F4) entra agora? | **Entra agora** |
| Conta sem senha? | **Criar "definir senha" agora** |

### Rodada 5 - ciclo completo

| Pergunta | Resposta |
|---|---|
| VerifyEmail/ConfirmPassword e e-mails? | **Fechar o ciclo inteiro** (telas restantes + e-mails nos 5 idiomas) |
| Onde vive "definir senha"? | **Na propria tela de confirmar senha** |
| Onde a newsletter e oferecida? | **Register + rodape publico** |
| Descadastro? | **Pagina com botao de confirmar** (anti-prefetch) |

### Rodada 6 - producao e legal

| Pergunta | Resposta |
|---|---|
| Transporte de e-mail? | **SMTP generico por `.env`** (local em `log`) |
| Privacidade/termos? | **Criar as paginas agora** (texto revisado pelo usuario) |

### Decisoes minhas (documentadas no plano, nao perguntadas)

- Envio de campanhas da newsletter fica para o admin (F7): esta tarefa entrega
  captura, confirmacao, lista e descadastro.
- Slug igual em todos os idiomas para as paginas legais (`/privacy`, `/terms`),
  como o resto do site.
- Sem IP na tabela de inscritos (minimizacao de dados).
- Checkbox da newsletter desmarcado por padrao; consentimento nao e requisito
  para criar conta.
- `AuthCardLayout.vue`/`AuthSplitLayout.vue` sao orfaos e saem do repo.

## 3. Relatorio de fatos levantados

### 3.1 Dependencias

- `laravel/socialite` **nao** esta no `composer.json` (`composer.json:13-21`).
- `laravel/socialite:^5.31` resolve limpo (`v5.31.0`, `php ^8.1`,
  `guzzle ^6|^7|^8`, `illuminate ^6..^13`) e o `guzzlehttp/guzzle 8.2.0` ja
  esta instalado. `^6.0` nao existe estavel e `6.x-dev`/`dev-master` ainda exige
  `illuminate ^6..^10` - inutilizavel com Laravel 13.
- `minimum-stability: stable` + `prefer-stable: true` (`composer.json:119-120`).

### 3.2 Fortify e rotas

- `config/fortify.php:89` `prefix => ''`; `:76` `home => '/dashboard'`;
  `:148-152` features ativas: `registration`, `resetPasswords`,
  `emailVerification` (2FA e passkeys desligados).
- `RoutePath::for()` le `config('fortify.paths.*')` - da para prefixar com
  `{locale}` sem `Fortify::ignoreRoutes()`.
- O grupo de rotas do Fortify usa prefixo estatico (sem `whereIn`): a validacao
  do slug precisa de `Route::pattern('locale', ...)` global.
- Nomes que precisam sobreviver: `login`, `login.store`, `logout`,
  `password.request|email|reset|update|confirm|confirmation`,
  `register`, `register.store`, `verification.notice|verify|send`,
  `user-profile-information.update`, `user-password.update`.
- `routes/settings.php:20-22`: `settings/security` esta atras de
  `RequirePassword` - e a tranca da conta sem senha.
- `routes/settings.php:26` reusa o nome `user-password.update` (o do Fortify e
  `/user/password`); hoje o wayfinder gera a versao do settings
  (`resources/js/routes/user-password/index.ts:12-14`).

### 3.3 Middleware de idioma

- `app/Http/Middleware/ResolveLocale.php:24-25` le `$request->route('locale')`
  e cai na preferencia (`Locales::preferred`, `:30`) quando nao ha prefixo.
- **Nao** chama `URL::defaults` - com parametro obrigatorio, `route('login')`
  (usado por `Authenticate` e pelo link do e-mail de reset) quebraria.
- `app/Support/Locales.php:127-149` (`alternates()`) devolve hreflang para
  qualquer rota com `{locale}`; hoje `tests/Feature/LocaleRoutingTest.php:128-130`
  prova que `/login` nao tem hreflang.
- `resources/views/app.blade.php:49-56`: hreflang + `x-default` + canonical
  condicionados a `$alternates !== []`.

### 3.4 Frontend

- `resources/js/routes/index.ts:289` e `:304`: rota com parametro gera `args`
  obrigatorio + `applyUrlDefaults` (`resources/js/wayfinder/index.ts:132`);
  `setUrlDefaults`/`addUrlDefault` em `:116`/`:120`.
- Call sites de auth que passam a exigir o idioma: `pages/auth/Login.vue:11-13`,
  `Register.vue:10-11`, `ForgotPassword.vue:9-10`, `ResetPassword.vue:10`,
  `ConfirmPassword.vue:8`, `VerifyEmail.vue:6-7`, `pages/settings/Profile.vue:12-13`,
  `components/UserMenuContent.vue:11-12`.
- `resources/js/layouts/auth/AuthCardLayout.vue` e `AuthSplitLayout.vue`: orfaos.
  `AuthSimpleLayout.vue` e usado por `layouts/AuthLayout.vue`, que o
  `lib/inertiaApp.ts:20-26` resolve para `auth/*`.
- `resources/js/__tests__/i18n.test.ts:191` assume `/login` sem prefixo.
- Vitest: `vitest.config.ts` (jsdom, `resources/js/**/*.test.ts`), mock de
  `@inertiajs/vue3` ja usado em `__tests__/appearance.test.ts:17-23`.
- Rodape publico (`layouts/PublicLayout.vue`) nao tem links de privacidade/
  termos hoje (as chaves `app.footer.privacy|terms` existem sem uso).

### 3.5 Banco e modelos

- `users`: `email` unique, `email_verified_at` nullable, `password` **nao
  nullable**, `locale` string(5), `appearance` string(16)
  (`database/migrations/0001_01_01_000000_create_users_table.php:14-24`).
- Sem tabela de oauth/social.
- `app/Models/User.php:26` fillable so name/email/password; `:28`
  `MustVerifyEmail`; nao implementa `HasLocalePreference`.

### 3.6 i18n

- Nao existe `lang/*/auth.php`; namespaces expostos em `config/locales.php`
  sao `app`, `home`, `settings`, `styleguide`, `locales`.
- O framework so traz `en/{auth,passwords,validation,pagination}.php`; o
  `FileLoader` faz `array_replace_recursive` com o app por ultimo (arquivo
  parcial e seguro) - o e-mail de reset e as mensagens do Fortify saem em
  ingles hoje.
- `tests/Feature/TranslationsParityTest.php` exige paridade de chaves nos 5
  idiomas para cada arquivo.

### 3.7 Testes afetados

- `tests/Feature/LocaleRoutingTest.php:107-112` (login resolve idioma pela
  preferencia) e `:128-130` (login sem hreflang).
- Suite `tests/Feature/Auth/*` e `tests/Feature/Settings/*`: chamam
  `route('login')`, `route('register')`, `route('password.*')`,
  `route('logout')` sem parametro.
- `tests/TestCase.php:19-24` expoe `skipUnlessFortifyHas()`.

## 4. Encaminhamentos

1. Plano de execucao em 10 blocos (`docs/plans/master/20261013_auth_login_plansfacts.md`).
2. ADRs novos: 022 (auth sob prefixo), 023 (login social e contas sem senha),
   024 (newsletter double opt-in).
3. Glossario: Conta social, Vinculo social, Conta sem senha, Double opt-in,
   Descadastro, Pagina legal.
4. Relatorio final (regra nova do CLAUDE.md):
   `docs/claude-code/reports/master/20261013_master_auth_login.md`.
5. Sem commit do assistente: o usuario fecha pelo gitpr.
