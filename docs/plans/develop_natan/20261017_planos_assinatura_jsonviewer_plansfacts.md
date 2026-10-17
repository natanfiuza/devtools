# Plano - Navegacao, Cards Clicaveis, Bug do JSON Viewer, Pagina de Planos e Intencao de Assinatura

Sessao de planejamento e grill (`grill-with-docs`). Branch `develop_natan`.
Duas rodadas de perguntas (**Q1 a Q4** e **A1 a A3**), todas respondidas pelo usuario.
Fatos levantados em `docs/survey/20261017_planos_assinatura_jsonviewer_surveyfacts.md`.

## Contexto

O pedido reune seis entregas complementares:
1. Retirar o link "Guia de estilo" da barra superior (`PublicLayout.vue`), preservando a rota `/{locale}/styleguide` e a pagina `Styleguide.vue` para inspecao visual.
2. Tornar os cartoes de ferramentas (`ToolCard.vue`) clicaveis em toda a sua extensao quando disponiveis (`href` presente).
3. Corrigir o erro em que digitar ou colar texto no editor de entrada do JSON Viewer desmontava toda a ferramenta (`formatBytes` em `resources/js/lib/bytes.ts` passava `locale` com underscore `pt_BR`/`pt_PT` para `new Intl.NumberFormat(locale)`, lancando `RangeError` no navegador).
4. Mover a secao de "Planos" da `Home.vue` para uma pagina publica dedicada (`/{locale}/pricing`), indexada no sitemap e ligada na navegacao principal.
5. Atualizar os precos dos planos Pro para R$ 8,90 no mensal (`890` centavos) e R$ 89,00 no anual (`8900` centavos) via migration no banco de dados.
6. Substituir o `mailto:` dos planos pagos por um fluxo completo de **Intencao de Assinatura**:
   - Visitantes nao autenticados que clicam em assinar um plano Pro sao redirecionados para o cadastro (`/{locale}/register`) com `url.intended` apontando para o formulario de assinatura daquele plano; apos criar a conta (ou entrar), sao levados automaticamente ao formulario.
   - O formulario permite escolher entre o plano mensal e o anual, reservar seu `@nomedeusuario` unico (`users.username`, tambem editavel em `/settings/profile`), informar telefone/WhatsApp, CPF/CNPJ, forma de pagamento preferida (Pix, Cartao, Boleto) e observacoes opcionais, alem de exibir as 5 vantagens do plano Pro.
   - O pedido e salvo na tabela `subscription_intents`, dispara notificacao por e-mail ao admin (quando `LEGAL_CONTACT_EMAIL` estiver configurado) e aparece na nova secao `/admin/intents` do painel administrativo com filtro por status e acoes para marcar como atendido ou cancelado.

## Decisoes (duas rodadas de grill)

| # | Pergunta | Decisao |
|---|---|---|
| Q1 | Onde exibir os planos | Remover da `Home.vue` e exibir exclusivamente na nova pagina dedicada `/{locale}/pricing` |
| Q2 | Destino do formulario de intencao de assinatura | Salvar na tabela `subscription_intents`, listar no painel Admin (`/admin/intents`) **e** enviar notificacao por e-mail ao admin |
| Q3 | Significado de `@nomedeusuario` | Identificador unico na conta do usuario (`users.username`), reservado para futuros links compartilhados/historico |
| Q4 | Vantagens Pro destacadas | Todas as 5 sugeridas: `@nomedeusuario` exclusivo, historico de 90 dias no AI SQL Builder, suporte prioritario/acesso antecipado, 2 meses gratis no anual e cota de 2M tokens/mes + schema 64 KB + prompt 4.000 chars |
| A1 | Campos do formulario de intencao | Plano (`pro-monthly`/`pro-yearly`), `@nomedeusuario`, telefone/WhatsApp, CPF/CNPJ, forma de pagamento preferida (`pix`, `credit_card`, `boleto`) e observacoes opcionais |
| A2 | Onde editar `@nomedeusuario` | Salvar em `users.username` ao enviar o formulario de assinatura **e** exibir/permitir editar em Configuracoes > Perfil (`/settings/profile`) |
| A3 | Gestao no painel Admin | Nova secao `/admin/intents` listando os pedidos com filtro de status (`pending`, `fulfilled`, `canceled`) e botoes para marcar como atendido ou cancelado (com registro em `admin_actions`) |

## Arquivos a criar ou modificar

### 1. Bugfix do JSON Viewer e `formatBytes`
| Arquivo | Mudanca |
|---|---|
| `resources/js/lib/bytes.ts` | Normalizar `locale.replace('_', '-')` antes de chamar `new Intl.NumberFormat(...)` para suportar `pt_BR` e `pt_PT` vindos do `vue-i18n` |
| `resources/js/__tests__/json-viewer.test.ts` | Adicionar teste com locale `'pt_BR'` (com underscore, igual ao runtime real) verificando que digitar na entrada renderiza a arvore e as estatisticas sem desmontar o componente |

### 2. Barra superior e Cartoes de ferramentas clicaveis
| Arquivo | Mudanca |
|---|---|
| `resources/js/layouts/PublicLayout.vue` | Remover os links de `styleguide` (desktop e mobile) e apontar o link de Precos/Planos para `routes.pricing.value` |
| `resources/js/components/ToolCard.vue` | Tornar o card inteiro clicavel quando `href` estiver presente usando `relative` + hover no `<li>` e `after:absolute after:inset-0 after:rounded-xl` no `<Link>` |
| `resources/js/__tests__/home.test.ts` | Atualizar testes da Home (sem secao de planos na Home, card clicavel via stretched link, sem link de styleguide no cabecalho) |

### 3. Pagina de Planos (`/{locale}/pricing`) e Precos (R$ 8,90 / R$ 89,00)
| Arquivo | Mudanca |
|---|---|
| `database/migrations/2026_10_17_000001_update_pro_plans_pricing_and_add_subscription_intents.php` | Atualizar `pro-monthly` para `890` centavos e `pro-yearly` para `8900` centavos; adicionar coluna `username` (nullable, unique) em `users`; criar tabela `subscription_intents` |
| `app/Http/Controllers/HomeController.php` | Simplificar `HomeController` (nao carrega mais `plans` nem `contactEmail`) |
| `resources/js/pages/Home.vue` | Remover a secao `#precos` e a prop `plans`/`contactEmail`; apontar CTAs de conhecer o Pro para `routes.pricing.value` |
| `app/Http/Controllers/PricingController.php` | **Novo**: renderiza `Pricing` com os planos ativos formatados e vantagens Pro |
| `resources/js/pages/Pricing.vue` | **Novo**: pagina publica de Planos com SEO (`Head`, `canonical`, `hreflang`, JSON-LD), comparativo de planos (`PricingSection.vue`) e lista de vantagens exclusivas do Pro |
| `resources/js/components/PricingSection.vue` | Substituir `mailto:` nos planos pagos por `<Link>` para a rota de intencao de assinatura (`/subscribe?plan=<slug>`), destacando a economia de 2 meses gratis no plano anual e as vantagens Pro |
| `app/Support/Sitemap.php` | Incluir `'pricing'` em `Sitemap::PAGES` |
| `routes/web.php` | Registrar `Route::get('pricing', PricingController::class)->name('pricing')` sob `{locale}` |
| `resources/js/composables/useRoutes.ts` | Expor `pricing` em `useRoutes()` |

### 4. `@nomedeusuario`, Formulario de Intencao de Assinatura e Admin
| Arquivo | Mudanca |
|---|---|
| `app/Enums/SubscriptionIntentStatus.php` | **Novo**: enum `pending`, `fulfilled`, `canceled` |
| `app/Enums/AdminActionType.php` | Adicionar `SubscriptionIntentFulfilled` (`intent.fulfilled`) e `SubscriptionIntentCanceled` (`intent.canceled`) |
| `app/Models/SubscriptionIntent.php` | **Novo**: model Eloquent com relacoes `user()`, `plan()`, `handler()` |
| `app/Models/User.php` | Adicionar `username` em `#[Fillable]`, relacao `subscriptionIntents()` |
| `app/Concerns/ProfileValidationRules.php` | Adicionar `usernameRules(?int $userId = null)` (`nullable`, `string`, `min:3`, `max:30`, `regex:/^[a-z0-9_]+$/`, `unique:users,username`) |
| `app/Http/Requests/Settings/ProfileUpdateRequest.php` | Permitir atualizar `username` opcionalmente em `/settings/profile` |
| `resources/js/pages/settings/Profile.vue` | Adicionar campo `@nomedeusuario` com explicacao e validacao |
| `app/Actions/Account/AnonymizeUser.php` | Limpar `username => null` e remover `subscriptionIntents()` ao anonimizar conta (ADR-028) |
| `app/Actions/Account/ExportUserData.php` | Incluir `username` em `account` e a lista `subscription_intents` no JSON exportado (ADR-028) |
| `app/Notifications/SubscriptionIntentSubmitted.php` | **Novo**: notificacao por e-mail enviada ao endereco `Legal::contactEmail()` quando o usuario registra intencao de assinatura |
| `app/Http/Controllers/SubscriptionIntentController.php` | **Novo**: `create` (se visitante, grava `redirect()->setIntendedUrl(...)` e redireciona para `route('register')`; se autenticado, renderiza `Subscribe` com planos pagos, dados atuais do usuario e ultima intencao pendente) e `store` (valida plano, `@username`, telefone/WhatsApp, CPF/CNPJ, metodo de pagamento e observacoes; salva `users.username`; cria/atualiza `SubscriptionIntent`; notifica admin por e-mail e redireciona com confirmacao) |
| `app/Http/Requests/StoreSubscriptionIntentRequest.php` | **Novo**: validacao dos campos do formulario de intencao de assinatura |
| `resources/js/pages/Subscribe.vue` | **Novo**: pagina do formulario de intencao de assinatura com seletor Mensal vs Anual (com badge de 2 meses gratis), campo `@nomedeusuario`, telefone/WhatsApp, CPF/CNPJ, forma de pagamento preferida, observacoes e painel lateral com as 5 vantagens do plano Pro |
| `app/Http/Controllers/Admin/SubscriptionIntentController.php` | **Novo**: listagem paginada em `/admin/intents` com filtro por status e acoes `fulfill` e `cancel` auditadas em `admin_actions` |
| `resources/js/pages/admin/Intents.vue` | **Novo**: tela administrativa de intencoes de assinatura |
| `resources/js/layouts/admin/AdminLayout.vue` | Adicionar item de navegacao para `/admin/intents` |
| `routes/web.php` e `routes/admin.php` | Registrar rotas `/subscribe` (`GET` e `POST`) e `/admin/intents` (`GET`, `POST fulfill`, `POST cancel`) |
| `lang/{pt_BR,pt_PT,en,es,fr}/{app,home,account,admin,settings}.php` | Novas chaves de traducao nos 5 idiomas mantendo paridade total |
| `docs/adr/ADR-033-intencao-de-assinatura-e-username.md` | **Novo**: documentar a decisao arquitetural do fluxo de intencao de assinatura e reserva de `@username` antes do gateway (F6) |
| `docs/SPEC.md`, `docs/glossario.md`, `docs/design/DESIGN.md` | Atualizar documentacao refletindo a pagina `/{locale}/pricing`, os novos precos, `users.username` e `subscription_intents` |

## Verificacao Planejada

1. `composer test` (Pint, PHPStan, Pest — incluindo `TranslationsParityTest`, `SitemapTest`, novos testes de `PricingTest`, `SubscriptionIntentTest`, `AdminSubscriptionIntentTest` e `ProfileTest`).
2. `npm run test` (Vitest — incluindo teste de regressao do `json-viewer` com locale `pt_BR`, `home.test.ts` e `pricing.test.ts`).
3. `npm run types:check` (Wayfinder + `vue-tsc --noEmit`).
