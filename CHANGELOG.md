# CHANGELOG - devtools.natanfiuza.dev.br

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versoes por fase do [SPEC](docs/SPEC.md); datas em AAAA-MM-DD.

## F0 - Papeis, modelo comercial e paineis - 2026-10-14

Bloco antecipado por decisao do grill: papeis e o painel administrativo (F7), o
modelo comercial com venda manual (F6) e o que faltava da F4 (telas de settings
traduzidas, exportar e excluir conta). Plano em
`docs/plans/master/20261014_papeis_paineis_plansfacts.md`.

### Adicionado

- **Papeis e autorizacao** (ADR-025): `users.role` com o enum `UserRole`
  (`user` | `admin`), Gate `access-admin` no grupo `/admin` e a `UserPolicy`
  como unico lugar da autorizacao; primeiro admin por
  `php artisan devtools:admin {email} [--revoke]`, idempotente e auditado.
- **Auditoria** na tabela `admin_actions` (ator, acao, alvo, `meta`, IP), com a
  tela `/admin/audit` filtravel; nada de schema ou prompt entra ali.
- **Suspensao** (`users.suspended_at` + middleware `active` em toda rota
  autenticada): a conta suspensa nao entra e a sessao aberta cai.
- **Impersonation** (`entrar como`): faixa no topo de toda tela autenticada,
  saida por `DELETE /impersonation` fora do grupo do painel.
- **Modelo comercial** (ADR-027): tabelas `plans`, `subscriptions`, `payments` e
  `usage_ledger`; catalogo inicial (Free, Pro mensal R$ 29,90, Pro anual
  R$ 299,00) carregado por migracao com `insertOrIgnore`; venda manual com
  `GrantPlan`, `GrantQuota`, `RegisterPayment` e `RefundPayment`; cota calculada
  do livro-razao em `App\Support\Quota`, sem coluna de saldo.
- **Idioma da conta** (ADR-026): `users.locale`, escolha do idioma no cadastro
  (5 endonimos, pre-selecionado, opcional) e troca no perfil; sem prefixo na URL
  a ordem e cookie -> conta -> `Accept-Language` -> en.
- **Painel administrativo** (`/admin`, 5 idiomas): Visao geral, Contas,
  Assinantes, Planos, Pagamentos e Auditoria; o detalhe da conta
  (`/admin/users/{id}`) traz as nove acoes (verificar e-mail, reenviar
  verificacao, enviar redefinicao de senha, papel, suspender, reativar, plano,
  cota, excluir) e as secoes de assinatura, consumo, logins sociais, sessoes e
  pagamentos.
- **Painel do usuario**: navegacao unica na area autenticada - Visao geral
  (plano, cota, assinatura e vencimento), Historico, Faturas e Conta -, com
  exportacao dos dados em JSON e exclusao da conta (ADR-028).
- **Exclusao por anonimizacao** (ADR-028): nome, e-mail, senha, vinculos e
  preferencias saem, e `payments`/`subscriptions` ficam pela guarda fiscal (a FK
  `restrictOnDelete` garante no banco); sessoes abertas caem e a conta nao entra
  mais.
- Testes: `AdminRoleTest`, `AdminPanelTest`, `AdminAccountActionsTest`,
  `AdminBillingTest`, `AdminImpersonationTest`, `QuotaTest`, `BillingTest` e
  `AccountPanelTest` (Pest), mais `admin-panel.test.ts` e `account-panel.test.ts`
  (Vitest).

### Corrigido

- Telas de perfil/seguranca/aparencia e a navegacao de settings passaram a ser
  traduzidas nos 5 idiomas (o carry-over dizia que estavam em portugues sem
  i18n; ja estavam majoritariamente traduzidas e faltava fechar).
- `history` e `billing` do painel do usuario nao existiam; agora existem com
  estado vazio explicado, e a visao geral aponta para elas.

### Decidido

- ADR-025 (papeis, autorizacao, auditoria e o primeiro admin por comando - fecha
  a pendencia da SPEC secao 11.2), ADR-026 (idioma da conta x idioma de
  navegacao), ADR-027 (venda manual, com `gateway` pronto para a F6) e ADR-028
  (anonimizacao com guarda fiscal e exportacao em JSON).
- Valores dos planos definidos (Free 5 usos / 8 KB / 500 caracteres; Pro mensal
  e anual com 2.000.000 de tokens, 64 KB e 4.000 caracteres) e gravados na
  tabela `plans`, nunca no codigo.
- Venda manual nao tem botao no painel do usuario: quem concede e quem registra
  o pagamento e o admin; o gateway (Mercado Pago) entra na F6.

### Fora do escopo (proximas fases)

- `output_targets`, `ai_requests`, cache, DeepSeek, polling e o consumo real da
  cota (F5).
- Mercado Pago/interface `PaymentGateway`, doacao e webhooks, com a renovacao da
  franquia por periodo (F6).
- Historico de uso real, SEO final e hardening (F7); mensagens de validacao nos
  5 idiomas segue aberto (SPEC secao 11.2).

## F0 - Autenticacao, login social e newsletter - 2026-10-13

Bloco antecipado da F4, por decisao do grill: o ciclo de conta entrou junto da
F0, antes da home real. Plano em
`docs/plans/master/20261013_auth_login_plansfacts.md`.

### Adicionado

- Telas de acesso reconstruidas no design system (barra de 56px, card com borda
  de 1px, sem sombra): Login, Register, ForgotPassword, ResetPassword,
  VerifyEmail e ConfirmPassword, todas traduzidas nos 5 idiomas e com foco
  inicial, `aria-live`, `aria-invalid` e rotulos ligados aos campos.
- **Auth sob `/{locale}`** (ADR-022): `config/fortify.php` passa o caminho com o
  `{locale}` e o `Route::pattern` global valida o slug; os nomes de rota seguem
  os do Fortify (`route('login')`, `route('password.reset')`), resolvidos pelo
  `URL::defaults` do `ResolveLocale`. Endpoints de maquina ficam fora do
  prefixo: `/auth/{provider}/redirect|callback`.
- **Login social com Google e GitHub** (ADR-023): tabela `social_accounts`,
  vinculo com conta existente so com e-mail confirmado pelo provedor, conta nova
  sem senha (`users.password` anulavel) e botao sempre visivel.
- **Definir a primeira senha** em `POST /{locale}/user/password/set` a partir da
  tela de confirmar senha, com a area sensivel liberada em seguida.
- **Newsletter com duplo opt-in** (ADR-024): inscricao pelo rodape publico e
  pelo cadastro (checkbox desmarcado), confirmacao e descadastro decididos por
  POST com pagina intermediaria, token novo a cada envio, e-mail na fila no
  idioma do inscrito e rodape com o formulario.
- **Paginas de Privacidade e Termos** sob `/{locale}`, nos 5 idiomas,
  indexaveis, com hreflang normal, contato e data de revisao vindos de
  `config/legal.php`.
- E-mails traduzidos: `App\Notifications\ResetPassword` e `VerifyEmail` no lugar
  das notificacoes do framework, e `lang/{locale}/mail.php` para os assuntos e
  corpos.
- Testes: `LocalizedAuthPagesTest`, `SocialAuthTest`, `SetPasswordTest`,
  `NewsletterTest`, `LegalPagesTest` (Pest) e `auth-login.test.ts` +
  `newsletter-form.test.ts` (Vitest), com traducoes marcadoras para denunciar
  texto fixo nas telas.

### Corrigido

- **Ordem de middleware**: o `Authenticate` esta na lista de prioridade do
  framework e por isso rodava **antes** do `ResolveLocale`, que era anexado ao
  grupo `web`. O visitante sem sessao que abria `/dashboard` era redirecionado
  para o login antes de o idioma existir — `UrlGenerationException` no servidor
  (mascarada nos testes pelo `URL::defaults` do `TestCase`). Correcao:
  `prependToPriorityList(before: AuthenticatesRequests::class, ...)`.
- Link de token invalido da newsletter montava a URL da home com o codigo do
  idioma (`pt_BR`) em vez do slug (`pt-br`).
- Reinscricao de quem havia se descadastrado nao limpava `confirmed_at`: a
  pessoa voltava para a lista sem ter confirmado o e-mail novo.
- Data de revisao invalida em `config/legal.php` derrubava as paginas legais;
  agora cai para hoje.
- `SocialProviders::emailIsVerified()` lia o payload cru fora do contrato do
  Socialite (`AbstractUser`), o que quebraria com outro driver.
- **Placeholder de e-mail quebrava a tela inteira no SSR**: o `@` de
  `voce@exemplo.com` e sintaxe de mensagem vinculada do vue-i18n, e a mensagem
  nao compilava — a tela de login deixava de renderizar no servidor. Os 20
  textos afetados (5 idiomas) usam `{'@'}`, e `TranslationsParityTest` passou a
  falhar se aparecer `@` ou `|` cru em qualquer traducao. Achado no smoke de SSR,
  nao na suite: os testes de componente usam traducao marcadora.

### Decidido

- ADR-022 (auth sob o prefixo de idioma + rotas de maquina fora), ADR-023 (login
  social e conta sem senha) e ADR-024 (newsletter com duplo opt-in).
- Verificacao do e-mail pelo provedor: Google usa `email_verified`, GitHub usa a
  existencia do e-mail primario verificado; provedor que nao confirma nao vincula.
- `noindex, follow` + canonical e **sem** hreflang nas telas de acesso e paginas
  de token; as paginas legais seguem indexaveis com hreflang normal.

## F0 - Fundacao - 2026-10-13

### Adicionado

- Projeto Laravel 13 + Inertia 3 + Vue 3 + TypeScript estrito sobre o starter
  kit oficial do Laravel, com Fortify. `public/build` e `bootstrap/ssr` sao
  artefatos de build (nao vao para o git).
- Tokens do DESIGN.md em `resources/css/app.css` (Tailwind 4, dark-first) com
  tema claro/escuro/sistema, e `/styleguide` com os componentes nos dois temas.
- i18n em 5 idiomas (en, pt-BR, pt-PT, fr, es): traducoes em
  `lang/{locale}/*.php`, expostas ao Inertia por namespace e consumidas pelo
  vue-i18n; rotas publicas com prefixo de idioma, `/` resolve e redireciona,
  seletor no header, `hreflang` e `canonical` no head.
- Tema resolvido no servidor (cookie do visitante, coluna do usuario, sistema
  como padrao) para a pagina chegar sem flash; `/settings/appearance` com os
  tres estados e i18n.
- SSR da Inertia cabeado (cliente + servidor com `registerDirectives`
  compartilhado) e ligado em producao pelo gate `INERTIA_SSR_ENABLED`.
- Testes: Pest 4 (paridade de traducoes, roteamento de locale, tema,
  autenticacao do kit) e Vitest 5 (i18n, tema, contraste AA dos tokens).
- Deploy: `deploy/deploy.sh` (flock, `--ff-only`, `npm ci` condicionado ao
  lockfile, build em release propria com troca atomica de symlink, 3 releases
  guardadas, `migrate --force`, `optimize`, `queue:restart`, reinicio do SSR),
  `deploy/supervisor/devtools-worker.conf` (revisado) e
  `deploy/supervisor/devtools-ssr.conf` (novo).
- Documentacao: `README.md` (setup local), `docs/RUNBOOK.md` (deploy, fila,
  SSR, backup, rotacao de chaves, diagnostico), `docs/acceptance/FASE-0.md`,
  ADR-021 (tema e anti-FOUC) e ADR-014 revisado (assets no servidor).

### Decidido

- Assets construidos no servidor, em release propria com troca atomica de
  symlink (ADR-014).
- Cache hit do AI SQL Builder (F5) nao debita cota; ver SPEC secao 11.1.
- Documentacao do repo em portugues sem acentos; strings de interface com
  acentuacao correta.

### Fora do escopo (proximas fases)

- Home real, registro de tools, Cmd+K, banner LGPD, GA4, sitemap, OG images (F1).
- JSON Viewer com CodeMirror, tools simples (F2/F3).
- Auth completo (OAuth, verificacao, newsletter) e `users.appearance`
  gravado pela conta (F4) — entregue antecipado no bloco acima; restam as telas
  de settings traduzidas e as mensagens de validacao nos 5 idiomas.
- AI SQL Builder, planos, pagamentos e admin (F5 a F7).
