# Levantamento - papeis, painel administrativo, painel do usuario e modelo comercial

Data: 2026-10-14
Branch: `master`
Pedido original: "Crie os papeis necessarios para Admin, Usuarios e um dashboard
administrativo para gerenciar cadastros pagamentos etc.. e um dashboard de
usuario. De continuidade as proximas fases do projeto."
Pedido que entrou no meio do grill: "Na tela de cadastro adicione a escolha do
idioma principal do usuario, crie um campo em users para isso".
Plano: `docs/plans/master/20261014_papeis_paineis_plansfacts.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

### 1.1 Rotas e areas que existem

- `routes/web.php`: `root` (`/` resolve idioma e redireciona), `home`
  (`/{locale}`), `styleguide`, `privacy`, `terms`, `password.set`
  (`POST /{locale}/user/password/set`) e **`dashboard`** (`GET /dashboard`,
  `Route::inertia('dashboard', 'Dashboard')`, middleware `auth` + `verified`,
  **sem prefixo de idioma**).
- `routes/settings.php`: `settings` (redirect), `profile.edit/update/destroy`,
  `security.edit` (com `RequirePassword`), `user-password.update`,
  `appearance.edit`.
- `routes/auth-social.php`: `auth.redirect` e `auth.callback`
  (`auth/{provider}/...`, fora do prefixo, ADR-022).
- `routes/newsletter.php`: inscricao, confirmacao e descadastro sob `/{locale}`.
- Fortify: `login`, `logout`, `register`, `password.*`, `verification.*`, todos
  sob `/{locale}`; `home` = `/dashboard`.
- **Nao existe** area administrativa, papel, gate, policy ou permissao:
  busca por `admin`/`role`/`polic` em `app/`, `routes/` e `resources/` nao
  retorna nada de autorizacao.

### 1.2 Banco (tabelas de hoje)

`users` (id, name, email unico, email_verified_at, password **anulavel**,
`locale` string(5) **anulavel**, appearance, remember_token, timestamps),
`password_reset_tokens`, `sessions`, `cache`/`cache_locks`,
`jobs`/`job_batches`/`failed_jobs`, `social_accounts` (provider+provider_id
unico, user+provider unico), `newsletter_subscribers` (email unico, token,
locale, source, confirmed_at, unsubscribed_at).

**Nao existem** `plans`, `subscriptions`, `payments`, `donations`,
`webhook_events`, `usage_ledger`, `ai_requests`, `ai_cache`, `output_targets`.

### 1.3 Codigo

- `app/Models/`: `User`, `SocialAccount`, `NewsletterSubscriber`. `User` usa
  atributos PHP `#[Fillable(['name','email','password'])]` e
  `#[Hidden([...])]`; `hasPassword()`, `preferredLocale()`, notificacoes
  proprias.
- `app/Actions/Fortify/CreateNewUser.php`: cria a conta e grava
  `$user->locale = app()->getLocale()` (linha 41) - o idioma vem da URL, **nao
  do formulario**; comentario no arquivo diz que `locale` esta fora do
  `Fillable` de proposito ("nao vem do request").
- `app/Actions/Fortify/ResetUserPassword.php`,
  `app/Actions/Newsletter/SubscribeToNewsletter.php`.
- `app/Enums/NewsletterSource.php` (string enum) - padrao a seguir para o
  enum de papel.
- `app/Policies/` **nao existe**. `app/Jobs/` **nao existe**.
- `app/Support/`: `Locales`, `Seo` (lista NOINDEX por nome de rota),
  `Appearance`, `Legal`, `SocialProviders`.
- `app/Http/Middleware/`: `ResolveLocale`, `HandleInertiaRequests`,
  `HandleAppearance`.

### 1.4 Frontend

- Layouts: `PublicLayout`, `AuthLayout`, `AppLayout` ->
  `layouts/app/AppSidebarLayout.vue` (casca autenticada do starter kit:
  `AppShell` + `AppSidebar` + `AppSidebarHeader` + `Toaster`),
  `layouts/app/AppHeaderLayout.vue` (**morto**, nenhuma pagina usa),
  `layouts/settings/Layout.vue` (sidenav de settings: Perfil, Seguranca,
  Aparencia).
- `Dashboard.vue` e o **placeholder do starter kit**: grade de 3 colunas com
  `PlaceholderPattern`, texto fixo em ingles. `AppSidebar.vue` tem os links
  "Repository"/"Documentation" do starter kit, tambem em ingles.
- **Estado do i18n das telas autenticadas** (conferido arquivo por arquivo):
  `settings/Profile.vue` e `settings/Appearance.vue` **ja usam `useI18n`**;
  continuam com texto fixo em ingles `Dashboard.vue`,
  `settings/Security.vue`, `components/DeleteUser.vue` e `AppSidebar.vue`.
  Consequencia: o carry-over da F4 na SPEC ("telas de perfil/settings em
  portugues sem i18n") esta errado nos dois lados.
- `SearchTrigger.vue` existe mas a paleta Cmd+K nao (aria-disabled) - e F1.
- `components/ui/*`: primitivas shadcn-vue vendorizadas.

### 1.5 Documentacao

- 24 ADRs, numeracao contigua de 001 a 024 (o proximo livre e **025**). Nenhum
  ADR trata de papel, permissao, autorizacao, painel ou historico do usuario.
  A area administrativa aparece uma unica vez, de passagem, no ADR-022 (rota de
  maquina fora do prefixo de idioma).
- Glossario com 27 termos. **Nao existem**: admin, papel, permissao,
  autorizacao, assinatura, pagamento, gateway, webhook, painel, historico,
  exclusao de conta, exportacao de dados.
- SPEC §7 define a area: "/admin restrito (role admin): usuarios, cotas
  (ajustar/conceder), planos, output_targets, ai_requests/erros, cache
  (limpar), assinantes".
- SPEC §11.2 tem duas pendentes que este bloco fecha: **como se cria o primeiro
  admin** e os **valores de `price_cents`, `monthly_tokens`,
  `max_schema_bytes`, `max_prompt_chars`, `lifetime_free_uses`**.
- `docs/design/DESIGN.md`, entregavel 6, ja pede "mockups textuais das telas
  Home, Tool, AI Builder, Pricing, **Admin**" - o mockup do admin esta devendo.

### 1.6 O bug de semantica que o pedido do idioma expos

`users.locale` **existe** e ja e preenchido no cadastro. Mas hoje ele nao e uma
preferencia: e um espelho da navegacao.

- `ResolveLocale::remember()` (`app/Http/Middleware/ResolveLocale.php:57`)
  sobrescreve `users.locale` com o idioma da URL **a cada GET** de quem esta
  logado, e tambem grava o cookie.
- `Locales::preferred()` (`app/Support/Locales.php:96`) resolve as rotas sem
  prefixo na ordem **conta -> cookie -> Accept-Language -> fallback**.

Ou seja: escolher um idioma no cadastro nao sobreviveria - bastaria uma visita
a `/fr/...` para a conta virar francesa. O pedido do usuario forcou a decisao
sobre o que a coluna significa (Q9).

## 2. Decisoes das rodadas

### Rodada 1 (Q1 a Q8)

| # | Decisao |
|---|---|
| Q1 | **(a)** Esta sessao = papeis + autorizacao + painel administrativo + painel do usuario; parada para revisao; F1 na sessao seguinte |
| Q2 | **(a)** Coluna `users.role` com enum PHP (`user` \| `admin`), sem pacote, com `Gate` + policies. Nenhum terceiro papel |
| Q3 | **(a)** Comando `php artisan devtools:admin {email}` idempotente (`--revoke` rebaixa) cria o primeiro admin; os demais sao promovidos pela UI |
| Q4 | **(b)** Adiantar o modelo comercial: `plans` (3 semeados e **editaveis pelo admin**), `subscriptions`, `payments`, `usage_ledger`; admin concede plano/cota e registra pagamento manual; gateway (Mercado Pago, ADR-017) fica na F6 |
| Q5 | Todas as 9 acoes do admin sobre uma conta entram, com duas travas: exclusao so com confirmacao digitada e **nunca sobre a propria conta do admin**; auditoria em tabela minima (`admin_actions`: quem, o que, alvo, quando, IP), sem conteudo de schema/prompt |
| Q6 | **(a)** Unificar a area autenticada numa navegacao unica (Visao geral, Historico, Faturas, Conta), mantendo as URLs atuais, e trazer exportar/excluir conta |
| Q7 | **(a)** Telas administrativas traduzidas nos 5 idiomas (regra do CLAUDE.md e `TranslationsParityTest` intactos) |
| Q8 | **(a)** Abas do mesmo painel administrativo: Contas, Assinantes, Planos, Pagamentos e Auditoria agora; as abas de IA (requisicoes, erros, cache) entram com a F5 |

### Rodada 2 (Q9 e Q10) - pedido do idioma no cadastro

| # | Decisao |
|---|---|
| Q9 | **(a)** `users.locale` passa a ser **preferencia explicita**: so muda por acao do usuario (cadastro e painel). O `ResolveLocale` para de sobrescrever a conta; o cookie passa a registrar a navegacao; nas rotas sem prefixo a ordem vira **cookie -> conta -> Accept-Language -> fallback**. Vira ADR (muda comportamento decidido na F0) e ganha dois termos no glossario: *Idioma da conta* x *idioma de navegacao* |
| Q10 | Select de idioma no cadastro, com os 5 idiomas em endonimo, **pre-selecionado com o idioma da pagina** e opcional; nao troca a interface na hora; vale a partir do redirecionamento pos-cadastro (`/dashboard`, sem prefixo); editavel depois no painel do usuario |

### Rodada 3 (Q11 a Q13)

| # | Decisao |
|---|---|
| Q11 | Valores iniciais dos planos (ponto de partida, editaveis na tela): **Free** 5 usos vitalicios / schema 8 KB / descricao 500; **Pro mensal** R$ 29,90 / 2.000.000 tokens / schema 64 KB / descricao 4.000; **Pro anual** R$ 299,00 com a mesma franquia mensal |
| Q12 | **(a)** Exclusao de conta **anonimiza**: apaga dados pessoais (nome, e-mail, senha, vinculos sociais, inscricao na newsletter, sessoes) e mantem `payments`/`subscriptions` ligados a conta anonimizada (guarda fiscal); o dialogo de exclusao oferece o download dos dados antes de confirmar |
| Q13 | **(a)** Exportacao = download imediato de um JSON com tudo (perfil, preferencias, vinculos sociais, inscricao na newsletter, assinaturas, pagamentos e historico de IA quando existir) |

## 3. Fatos que sustentam o plano

- **`users.locale` ja existe** (`string(5)`, anulavel, desde a migracao base):
  nao ha coluna nova a criar para o idioma do usuario - falta o campo na tela e
  aceitar o valor do request.
- **A exclusao de conta ja funciona** (`ProfileController@destroy` +
  `DeleteUser.vue`): falta a semantica de anonimizacao, a traducao e o export.
- **O deploy so roda `migrate --force`** (`deploy/deploy.sh`), nao `db:seed` -
  entao os 3 planos precisam nascer na propria migracao (dado de referencia,
  idempotente), e nao num seeder que nunca roda em producao.
- **Nao ha scheduler no deploy** (o cron chama `deploy.sh`, nao
  `schedule:run`): validade de assinatura tem de ser resolvida na leitura
  (assinatura vencida = plano Free), sem job diario.
- **A fila `database` e o Supervisor existem** (`deploy/supervisor/`), mas
  nenhum Job foi escrito ainda - o primeiro sera `ProcessAiQuery`, na F5.
- **`TranslationsParityTest`** exige as mesmas chaves nos 5 idiomas e falha com
  `@` ou `|` cru (vue-i18n): todo namespace novo nasce nos 5 de uma vez.
- **`Seo::NOINDEX`** e uma lista de nomes de rota: as rotas `/admin` entram
  nela.
- **`AppHeaderLayout.vue` e `AppHeader.vue` estao mortos** desde a F0 (nenhuma
  pagina usa) - entram na limpeza, junto dos links "Repository"/"Documentation"
  do `AppSidebar.vue`.
- **Padroes a seguir**: enums em `app/Enums` (como `NewsletterSource`), acoes
  em `app/Actions`, atributos PHP `#[Fillable]`/`#[Hidden]` no modelo (nao
  `$fillable`), textos de interface com acento e documentacao sem acento.
