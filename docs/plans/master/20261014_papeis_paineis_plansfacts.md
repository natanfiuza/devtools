# Plano - papeis, painel administrativo, painel do usuario e modelo comercial

Data: 2026-10-14
Branch: `master`
Levantamento: `docs/survey/20261014_papeis_paineis_surveyfacts.md`
Origem: sessao de grill de 3 rodadas (Q1 a Q13), todas confirmadas pelo usuario.

## Contexto

O pedido era "criar os papeis de Admin e Usuario, um dashboard administrativo
para gerenciar cadastros e pagamentos, um dashboard de usuario e dar
continuidade as proximas fases". O grill mostrou que isso nao e uma fase do
SPEC: e um bloco que **antecipa partes da F4 (exportar/excluir conta, telas de
settings traduzidas), da F6 (planos, assinaturas, pagamentos) e da F7 (admin,
auditoria)**, e que depende de um modelo de dados comercial que ainda nao
existe. No meio do grill entrou um pedido acessorio - a escolha do idioma no
cadastro - que expos um bug de semantica: `users.locale` e hoje um espelho da
navegacao, nao uma preferencia.

Ao fim deste bloco: **parada para revisao, sem commit**. A F1 (registro de
tools, home real, Cmd+K, banner LGPD, GA4) fica para a sessao seguinte.

## Decisoes fechadas no grill

| # | Decisao |
|---|---|
| Q1 | Este bloco agora; F1 depois. Parada para revisao ao fim |
| Q2 | `users.role` com enum PHP (`user` \| `admin`); sem pacote; `Gate` + policy. Dois papeis |
| Q3 | Primeiro admin por comando `devtools:admin {email}` (idempotente, `--revoke`); os demais pela UI |
| Q4 | Modelo comercial adiantado: `plans` (3 semeados e editaveis), `subscriptions`, `payments`, `usage_ledger`; gateway fica na F6; admin concede e registra pagamento manual |
| Q5 | As 9 acoes do admin sobre a conta, com exclusao so sob confirmacao digitada e nunca sobre a propria conta; auditoria em `admin_actions` (sem conteudo de schema/prompt) |
| Q6 | Area autenticada unificada numa navegacao (Visao geral, Historico, Faturas, Conta), URLs atuais mantidas, com exportar e excluir conta |
| Q7 | Telas administrativas nos 5 idiomas (regra do `CLAUDE.md`) |
| Q8 | Um painel administrativo com abas: Contas, Assinantes, Planos, Pagamentos, Auditoria; IA entra na F5 |
| Q9 | `users.locale` vira preferencia explicita; cookie registra a navegacao; ordem sem prefixo: cookie -> conta -> Accept-Language -> fallback |
| Q10 | Select de idioma no cadastro (5 endonimos, pre-selecionado, opcional), sem trocar a interface na hora, editavel no painel depois |
| Q11 | Seed dos planos: Free 5 usos / 8 KB / 500; Pro mensal R$ 29,90 / 2.000.000 tokens / 64 KB / 4.000; Pro anual R$ 299,00 |
| Q12 | Exclusao **anonimiza** (sai o dado pessoal, fica `payments`/`subscriptions` por guarda fiscal); o dialogo oferece o download antes |
| Q13 | Exportacao = download imediato de um JSON com tudo |

## Bloco 1 - Papeis e autorizacao

**Objetivo**: existir a nocao de papel no sistema, com o primeiro admin criado
por comando e toda acao administrativa registrada.

- `app/Enums/UserRole.php` (`user`, `admin`), no padrao de
  `app/Enums/NewsletterSource.php`.
- Migracao `add_role_to_users_table`: `role` string(16), `default('user')`,
  indexado - retrocompativel (as contas existentes nascem `user`).
- `User`: cast para `UserRole`, `isAdmin()`, `hasPassword()` existente
  preservado; `role` **fora** do `#[Fillable]` (so a acao administrativa e o
  comando escrevem).
- `app/Enums/AdminActionType.php` + tabela `admin_actions` (ator, acao,
  `target_type`/`target_id`, `meta` json curto, ip, `created_at`) +
  `App\Models\AdminAction` + acao `App\Actions\Admin\RecordAdminAction`
  (helper unico, chamado por todo controlador administrativo). Nunca guardar
  schema, prompt ou conteudo sensivel.
- `App\Policies\UserPolicy`: `viewAny`/`view`/`update`/`manage` = papel admin;
  `suspend`/`delete`/`impersonate` = admin **e** alvo nao-admin (nem a propria
  conta) - uma trava unica e testavel.
- Comando `php artisan devtools:admin {email} [--revoke]`: promove/rebaixa,
  idempotente, erro claro quando o e-mail nao existe; registra auditoria.
- Middleware de acesso: `can:access-admin` no grupo de rotas (Gate definido no
  `AppServiceProvider`).
- `HandleInertiaRequests`: compartilhar `auth.user.is_admin` (e nao o papel cru)
  para o frontend decidir o que mostrar.
- `Seo::NOINDEX`: incluir `admin.*`, `dashboard` e `settings.*`.
- Testes: enum e cast; `isAdmin`; gate/middleware devolvendo 403 para usuario
  comum e visitante; comando promovendo, rebaixando e falhando com e-mail
  inexistente; linha de auditoria gravada.

## Bloco 2 - Modelo comercial (planos, assinaturas, pagamentos, ledger)

**Objetivo**: existir o dado que o painel administrativo gerencia e que o
painel do usuario mostra, sem gateway (que e F6).

- Migracoes (todas retrocompativeis e aditivas):
  - `plans`: slug unico, nome, `price_cents`, moeda, `interval`
    (`month`/`year`/nulo para o Free), `monthly_tokens`, `max_schema_bytes`,
    `max_prompt_chars`, `lifetime_free_uses`, `active`, `sort`, timestamps.
    **Insere os 3 planos da Q11 na propria migracao** (`insertOrIgnore`), porque
    o deploy roda `migrate --force` e nao `db:seed`. Os valores passam a viver
    na tabela e sao editaveis pelo admin (nunca no codigo).
  - `subscriptions`: `user_id`, `plan_id`, `gateway` (`manual` agora,
    `mercadopago` na F6), `gateway_ref` nulo, `status`, `current_period_start`,
    `current_period_end` (nulo = sem fim), timestamps, indice
    (`user_id`, `status`).
  - `payments`: `user_id`, `subscription_id` nulo, `plan_id` nulo,
    `amount_cents`, moeda, `method` (`pix`/`transfer`/`cash`/`other`),
    `status` (`paid`/`refunded`), `paid_at`, `note`, `gateway`, `gateway_ref`,
    timestamps.
  - `usage_ledger`: `user_id`, `ai_request_id` nulo (**sem FK**: a tabela
    nasce na F5), `type` (`debit`/`refund`/`grant`), `tokens`, `uses`,
    `created_at`, indice (`user_id`, `created_at`).
- Enums: `LedgerEntryType`, `SubscriptionStatus`, `PaymentMethod`,
  `PaymentStatus`.
- Modelos `Plan`, `Subscription`, `Payment`, `UsageLedger` com relacoes e
  factories.
- `User`: `activeSubscription()` (**status ativo e periodo nao vencido** - a
  validade e resolvida na leitura, porque o deploy nao roda `schedule:run`),
  `effectivePlan()` (assinatura ativa ou o plano Free), `payments()`.
- `App\Support\Quota`: estado da cota do usuario - Free conta **usos** (limite
  vitalicio menos debitos, mais estornos e concessoes), Pro conta **tokens** no
  periodo corrente (franquia menos debitos, mais estornos e concessoes).
  E a interface que a F5 vai consumir para debitar.
- Testes: plano efetivo sem assinatura, com assinatura ativa e com assinatura
  vencida; matematica da cota nos dois modos; `insertOrIgnore` idempotente;
  relacoes e escopos.

## Bloco 3 - Idioma da conta (Q9 e Q10)

**Objetivo**: o idioma escolhido no cadastro ser uma preferencia que dura.

- `Register.vue`: select de idioma (5 endonimos, vindo do prop compartilhado
  `availableLocales`), pre-selecionado com o idioma da pagina, opcional, com
  rotulo e ajuda via i18n.
- `CreateNewUser`: aceitar `locale` do request validado com
  `Rule::in(Locales::codes())` e cair no idioma da pagina quando ausente ou
  invalido; `locale` continua fora do `#[Fillable]` (atribuicao direta, como
  hoje).
- `ResolveLocale::remember()`: **parar de escrever em `users.locale`**; o
  cookie continua sendo gravado.
- `Locales::preferred()`: ordem **cookie -> conta -> Accept-Language ->
  fallback**.
- `settings/Profile.vue` + `ProfileController@update`: seletor de idioma da
  conta; ao salvar, grava `users.locale` **e** o cookie (a interface segue o
  idioma novo imediatamente).
- Testes: cadastro com idioma explicito; idioma invalido cai no da pagina;
  visitar `/fr/...` logado **nao** muda mais a conta; dashboard sem prefixo
  segue o cookie e, sem cookie, a conta; troca pelo perfil atualiza conta e
  cookie. Testes existentes que dependem do comportamento antigo sao ajustados
  de forma deliberada (o `LocaleRoutingTest` cobre a gravacao da conta).

## Bloco 4 - Painel administrativo

**Objetivo**: as telas que usam os blocos 1, 2 e 3.

- `routes/admin.php`: prefixo `admin`, nomes `admin.*`, middleware
  `auth` + `verified` + `can:access-admin`, **sem prefixo de idioma** (ADR-022).
- Controladores em `app/Http/Controllers/Admin/`: `DashboardController`,
  `UserController` (lista com busca `q`, filtro de papel/situacao e paginacao;
  detalhe com sessoes, vinculos sociais, inscricao, assinatura e faturas),
  `UserActionController` (verificar e-mail, reenviar verificacao, mandar reset,
  promover/rebaixar, suspender/reativar, conceder plano, ajustar cota,
  excluir), `ImpersonationController` (entrar como / sair),
  `SubscriberController` (lista, filtros, reescrever, remover, exportar CSV),
  `PlanController` (listar e editar), `PaymentController` (listar, registrar
  manual, estornar), `AuditController` (lista paginada).
- Acoes em `app/Actions/Admin/`: `GrantPlan` (cria/estende assinatura com
  `gateway = manual`), `GrantQuota` (linha `grant` no ledger),
  `RegisterPayment`, `RefundPayment` (marca o pagamento como estornado e
  encerra a assinatura vinculada - regra documentada).
- **Suspensao**: migracao `add_suspended_at_to_users_table`; o login recusa
  conta suspensa (mensagem propria, nao "credenciais invalidas"), o login
  social tambem recusa, e um middleware derruba a sessao de quem for suspenso
  logado.
- **Impersonation**: chave de sessao com o id do admin original, `Auth::login`
  no alvo; proibido encadear, impersonar admin ou conta suspensa; faixa de
  aviso com botao de sair em toda tela autenticada durante a impersonation; o
  painel administrativo fica inacessivel enquanto impersona (o usuario da
  sessao e o alvo, nao o admin).
- **Exclusao pelo admin**: exige o e-mail do alvo digitado, bloqueada na
  propria conta-e em qualquer conta admin; a acao e a mesma do usuario
  (`AnonymizeUser`, bloco 5).
- Frontend: `layouts/admin/AdminLayout.vue` (sidenav de secoes + barra com
  "voltar ao site" e o usuario) e paginas `pages/admin/*.vue`; a tabela densa
  entra como componente proprio (`AdminTable`), no estilo do DESIGN (borda 1px,
  sem sombra), com estados de vazio e carregando.
- i18n: namespace `admin` nos 5 idiomas (o `TranslationsParityTest` exige
  paridade imediata).
- Testes (Pest): 403 para visitante e para usuario comum; lista e filtros;
  cada uma das acoes gerando auditoria; suspensao bloqueando login e derrubando
  sessao; impersonation entrando, saindo e sendo recusada nos casos proibidos;
  exclusao bloqueada na propria conta; exportacao CSV dos assinantes.
  (Vitest): navegacao do admin, tabela com busca e estado vazio.

## Bloco 5 - Painel do usuario

**Objetivo**: a area autenticada deixar de ser o placeholder do starter kit.

- Navegacao unica: `layouts/account/AccountLayout.vue` substitui
  `layouts/settings/Layout.vue` - Visao geral (`/dashboard`), Historico
  (`/dashboard/history`), Faturas (`/dashboard/billing`) e a secao Conta
  (Perfil, Seguranca, Aparencia, com as URLs `settings/*` atuais preservadas).
- `Dashboard.vue` reescrito: plano atual, franquia/cota restante, proximo
  vencimento, assinatura e atalhos; estado vazio coerente para quem esta no
  Free sem uso. `Route::inertia('dashboard', ...)` vira `DashboardController`.
- `pages/dashboard/History.vue` (estado vazio - o conteudo chega na F5) e
  `pages/dashboard/Billing.vue` (assinatura + lista de pagamentos do usuario).
- Traduzir o que falta: `settings/Security.vue`, `DeleteUser.vue` e apagar o
  `Dashboard.vue` do starter kit. Limpeza dos mortos: `AppHeaderLayout.vue`,
  `AppHeader.vue` e os links "Repository"/"Documentation" do `AppSidebar.vue`.
- **Exportar dados**: `App\Actions\Account\ExportUserData` +
  `GET /settings/export` devolvendo um JSON para download (perfil,
  preferencias, vinculos sociais, inscricao, assinaturas, pagamentos e
  historico, quando existir).
- **Excluir conta**: `App\Actions\Account\AnonymizeUser` substitui o
  `delete()` do `ProfileController@destroy` - apaga/anonimiza o dado pessoal,
  remove sessoes e vinculos, e **preserva `payments` e `subscriptions`**
  ligados a conta anonimizada (guarda fiscal). O dialogo de confirmacao ganha o
  botao de baixar os dados antes de excluir.
- Testes (Pest): props do dashboard (plano, cota, vencimento); faturas do
  usuario; exportacao com o formato esperado; exclusao anonimizando e
  preservando pagamentos; usuario anonimizado nao consegue mais entrar.
  (Vitest): navegacao do painel, estados vazios de Historico e Faturas.

## Bloco 6 - Documentacao e fechamento

- **ADRs novos**: 025 (papeis, autorizacao, auditoria e como nasce o primeiro
  admin - fecha a pendencia da SPEC §11.2), 026 (idioma da conta x idioma de
  navegacao - muda comportamento da F0), 027 (venda manual: assinatura
  concedida e pagamento registrado fora do gateway), 028 (exclusao por
  anonimizacao com guarda fiscal, e exportacao em JSON).
- **Glossario**: Papel, Painel administrativo, Painel do usuario, Auditoria,
  Impersonacao (entrar como), Suspensao, Assinatura, Pagamento manual,
  Idioma da conta, Idioma de navegacao, Anonimizacao, Exportacao de dados.
  **Atualizar** as definicoes de Cota e Franquia de tokens (hoje "Cota" engloba
  as duas coisas).
- **SPEC**: §3 (precedencia do idioma), §6 (tabelas do comercial), §7 (contas,
  planos e o painel administrativo como ele fica), §9 (registro do adiantamento
  de F4/F6/F7), §10, §11.1 (decisoes novas) e §11.2 (**fechar duas**: primeiro
  admin e valores dos planos; **corrigir o carry-over errado** do i18n de
  settings, que nao esta em portugues e ja esta majoritariamente traduzido).
- **DESIGN.md**: secao do painel administrativo e do painel do usuario (o
  entregavel 6 ja devia o mockup do Admin desde a F0).
- **CHANGELOG.md**: secao nova do bloco.
- **Aceite**: `docs/acceptance/` com o documento do bloco, no padrao das fases.
- **Relatorio**: `docs/claude-code/reports/master/20261014_master_papeis_paineis.md`.

## Criterios de aceite do bloco

| # | Criterio | Como verificar |
|---|---|---|
| 1 | Existe papel; usuario comum recebe 403 no `/admin` e visitante vai para o login | Pest |
| 2 | O primeiro admin nasce pelo comando `devtools:admin`, idempotente, e pode ser rebaixado | Pest |
| 3 | Toda acao administrativa deixa linha de auditoria, sem conteudo sensivel | Pest + revisao das colunas |
| 4 | Planos, assinaturas, pagamentos e ledger existem; o plano efetivo respeita o vencimento | Pest |
| 5 | O admin lista e filtra contas, ve o detalhe e executa as 9 acoes, com as travas de auto-exclusao | Pest + smoke |
| 6 | Suspensao bloqueia login (senha e social) e derruba sessao ativa | Pest |
| 7 | Impersonation entra, avisa e sai; recusa encadear, impersonar admin ou suspenso | Pest |
| 8 | O painel do usuario mostra plano, cota e faturas, com estados vazios coerentes | Pest + smoke |
| 9 | Exportar devolve JSON completo; excluir anonimiza e preserva pagamentos | Pest |
| 10 | Idioma escolhido no cadastro sobrevive a navegacao em outro idioma | Pest |
| 11 | Zero string hardcoded nas telas novas (5 idiomas, paridade verde) | `TranslationsParityTest` + Vitest com traducao marcadora |
| 12 | Gates: `composer test`, `npm run test`, `npm run types:check`, `npm run build`, `npm run build:ssr` | os comandos |
| 13 | Smoke SSR das telas novas | `INERTIA_SSR_ENABLED=true` + `php artisan serve` + `node bootstrap/ssr/ssr.js` |
| 14 | Nenhum commit feito pelo assistente | `git log` |

## Verificacao

- Backend: `composer test` (Pint + PHPStan nivel 7 + Pest).
- Frontend: `npm run test` (Vitest), `npm run types:check`, `npm run build`,
  `npm run build:ssr`.
- Manual/SSR: `/admin` (como admin), `/dashboard`, `/dashboard/billing`,
  `/settings/profile` com o seletor de idioma, e as telas de erro 403.
- Nao verificavel daqui: o gateway (F6), a execucao real no VPS e o visual fino
  das telas (revisao do usuario).

## Riscos

- **`users.locale` deixa de espelhar a navegacao** (Q9): muda comportamento
  entregue na F0 e mexe em testes existentes. Mitigacao: teste dedicado para a
  nova ordem de precedencia e ajuste explicito dos testes antigos, com o ADR-026
  registrando o porque.
- **Modelo comercial antes do consumidor** (Q4): `usage_ledger` so passa a ter
  debito real na F5. Mitigacao: a matematica da cota nasce testada agora, para a
  F5 so chamar.
- **Exclusao por anonimizacao** (Q12): se um dia a justica pedir o dado, ele
  nao existe mais - e o desenho. Mitigacao: o ADR-028 registra o que sai e o que
  fica, e a exportacao existe justamente para o usuario levar o que e dele.
- **Densidade das telas administrativas**: e a primeira vez que o projeto faz
  tabela densa; o DESIGN nao cobre esse caso. Mitigacao: mockup textual no
  DESIGN.md antes de escrever as telas, e reuso dos tokens existentes.
- **Impersonation e seguranca**: e uma porta de entrada na conta alheia.
  Mitigacao: recusa para admin/suspenso, auditoria de cada uso, faixa visivel e
  proibicao de encadear.
