# Relatorio de Tarefa — Planos dedicados, intencao de assinatura, reserva de username, cartoes clicaveis e correcao do JSON Viewer

- **Data:** 2026-10-17
- **Branch:** `develop_natan`
- **Referencia:** ADR-034, `docs/survey/20261017_planos_assinatura_jsonviewer_surveyfacts.md`, `docs/plans/develop_natan/20261017_planos_assinatura_jsonviewer_plansfacts.md`

---

## 1. Escopo Entregue

1. **Remocao do link "Guia de estilo" da barra superior (`PublicLayout.vue`)**:
   - O link para `/{locale}/styleguide` foi removido da barra superior desktop e do menu mobile (`Sheet`) em `resources/js/layouts/PublicLayout.vue`.
   - A rota `/{locale}/styleguide` (`routes/web.php`) e a pagina `resources/js/pages/Styleguide.vue` foram mantidas intactas (`noindex`) para referencia visual do sistema de design.

2. **Cartoes de ferramentas clicaveis em toda a extensao (`ToolCard.vue`)**:
   - Em `resources/js/components/ToolCard.vue`, o `<li>` recebeu posicionamento relativo (`relative`) e feedback de hover (`cursor-pointer hover:border-border-strong`) quando `href` esta presente (`available`).
   - O `<Link>` do titulo recebeu `after:absolute after:inset-0 after:rounded-xl` (padrao stretched link), estendendo a area clicavel para todo o cartao sem envolver blocos inteiros em `<a>` nem alterar o comportamento dos cartoes `soon`.

3. **Correcao do desaparecimento da tela no JSON Viewer ao digitar ou colar (`lib/bytes.ts`)**:
   - **Causa raiz**: `HandleInertiaRequests` compartilha o codigo de idioma com sublinhado (`pt_BR`, `pt_PT`). Ao digitar ou colar no editor do JSON Viewer, o calculo de `statsMessage` chamava `formatBytes(bytes, locale.value)`, que repassava `pt_BR` para `new Intl.NumberFormat(locale, ...)`. A API `Intl.NumberFormat` exige tag BCP 47 com hifen (`pt-BR`) e lancava `RangeError: Incorrect locale information provided`, desmontando a subarvore dentro de `<ClientOnly>` em `pages/tools/Show.vue` e deixando visivel apenas o cabecalho da pagina.
   - **Correcao**: `formatBytes` em `resources/js/lib/bytes.ts` passou a normalizar `const tag = locale.replaceAll('_', '-')` antes de instanciar `Intl.NumberFormat`, protegendo o JSON Viewer e todas as demais ferramentas que exibem tamanho em bytes.
   - **Teste**: `resources/js/__tests__/json-viewer.test.ts` passou a montar a suite com `locale: 'pt_BR'`, reproduzindo o formato real enviado pelo backend.

4. **Pagina publica dedicada de Planos (`/{locale}/pricing`)**:
   - Criados `app/Http/Controllers/PricingController.php` e `resources/js/pages/Pricing.vue`, expondo a rota publica localizada `/{locale}/pricing` (nomeada `pricing`), incluida no `sitemap.xml` (`app/Support/Sitemap.php`) e em `Seo::pageMeta()` (`app/Support/Seo.php`).
   - `HomeController` e `resources/js/pages/Home.vue` deixaram de carregar os planos na home, e os links de navegacao de `PublicLayout.vue` passaram a apontar para `routes.pricing.value`.
   - `Pricing.vue` exibe a grade de planos (`PricingSection.vue`) e a secao detalhada com as 5 vantagens do plano Pro.

5. **Atualizacao dos precos dos planos Pro para R$ 8,90 (mensal) e R$ 89,00 (anual)**:
   - Atualizada a migracao base `database/migrations/2026_10_14_000004_seed_plans_catalog.php` e criada a migracao retrocompativel `database/migrations/2026_10_17_000001_update_pro_plans_pricing_and_add_subscription_intents.php`, ajustando `pro-monthly` para `890` centavos (R$ 8,90) e `pro-yearly` para `8900` centavos (R$ 89,00).

6. **Formulario de intencao de assinatura (`/subscribe`), reserva de `@username` e fila no painel administrativo (`/admin/intents`)**:
   - **Banco e modelos**: adicionada a coluna `users.username` (`VARCHAR(30)`, `nullable`, `unique`) e a tabela `subscription_intents` (`user_id`, `plan_id`, `username`, `phone`, `document`, `payment_method`, `notes`, `status`, `handled_by`, `handled_at`), com o model `App\Models\SubscriptionIntent` e os enums `SubscriptionIntentStatus` e `SubscriptionIntentPaymentMethod`.
   - **Fluxo de autenticacao e `url.intended`**: em `PricingSection.vue`, os planos pagos apontam para `/subscribe?plan=<slug>`. Quando um visitante nao autenticado acessa `/subscribe`, `SubscriptionIntentController::show` grava a URL completa em `session('url.intended')` e redireciona para `/{locale}/register` (ou login), retornando automaticamente ao formulario apos concluir o cadastro.
   - **Reserva de `@nomedeusuario` e perfil**: ao enviar `POST /subscribe` (`StoreSubscriptionIntentRequest`), o `username` (normalizado em minusculas, `^[a-z0-9_]{3,30}$`, unico em `users.username`) e gravado imediatamente na conta do usuario, tambem ficando visivel e editavel em `/settings/profile` (`ProfileUpdateRequest`, `Profile.vue`). `AnonymizeUser` limpa `username` e remove as intencoes da conta; `ExportUserData` inclui `username` e `subscription_intents` no JSON exportado.
   - **Notificacao e painel admin**: o envio dispara a notificacao `SubscriptionIntentSubmitted` para `Legal::contactEmail()` e lista o pedido em `/admin/intents` (`Admin\SubscriptionIntentController`, `resources/js/pages/admin/Intents.vue`), com filtro por status e acoes auditadas em `admin_actions` (`intent.fulfilled` e `intent.canceled`).
   - **i18n**: todas as chaves novas foram adicionadas nos 5 idiomas (`pt_BR`, `pt_PT`, `en`, `es`, `fr`) em `home.php`, `settings.php`, `admin.php` e `mail.php`.

---

## 2. Arquivos Criados ou Modificados

- **Backend / Banco de Dados**:
  - `database/migrations/2026_10_14_000004_seed_plans_catalog.php`
  - `database/migrations/2026_10_17_000001_update_pro_plans_pricing_and_add_subscription_intents.php`
  - `app/Enums/SubscriptionIntentStatus.php`
  - `app/Enums/SubscriptionIntentPaymentMethod.php`
  - `app/Enums/AdminActionType.php`
  - `app/Models/SubscriptionIntent.php`
  - `app/Models/User.php`
  - `app/Concerns/ProfileValidationRules.php`
  - `app/Http/Requests/Settings/ProfileUpdateRequest.php`
  - `app/Http/Requests/StoreSubscriptionIntentRequest.php`
  - `app/Http/Controllers/HomeController.php`
  - `app/Http/Controllers/PricingController.php`
  - `app/Http/Controllers/SubscriptionIntentController.php`
  - `app/Http/Controllers/Admin/SubscriptionIntentController.php`
  - `app/Notifications/SubscriptionIntentSubmitted.php`
  - `app/Actions/Account/AnonymizeUser.php`
  - `app/Actions/Account/ExportUserData.php`
  - `app/Support/Seo.php`
  - `app/Support/Sitemap.php`
  - `routes/web.php`
  - `routes/admin.php`
- **Frontend / Traducoes**:
  - `resources/js/lib/bytes.ts`
  - `resources/js/components/ToolCard.vue`
  - `resources/js/components/PricingSection.vue`
  - `resources/js/layouts/PublicLayout.vue`
  - `resources/js/layouts/admin/AdminLayout.vue`
  - `resources/js/pages/Home.vue`
  - `resources/js/pages/Pricing.vue`
  - `resources/js/pages/Subscribe.vue`
  - `resources/js/pages/settings/Profile.vue`
  - `resources/js/pages/admin/Intents.vue`
  - `resources/js/composables/useRoutes.ts`
  - `resources/js/lib/inertiaApp.ts`
  - `resources/js/types/auth.ts`
  - `resources/js/types/home.ts`
  - `resources/js/types/admin.ts`
  - `vite.config.ts` (adicionado `php artisan route:clear` antes de `wayfinder:generate` para evitar que o cache de rotas do deploy anterior oculte rotas novas durante `vite build` no servidor)
  - `deploy/deploy.sh` (adicionado `php artisan route:clear` antes do `npm run build`)
  - `lang/{pt_BR,pt_PT,en,es,fr}/{home,settings,admin,mail}.php`
- **Testes e Documentacao**:
  - `tests/Feature/PricingTest.php`
  - `tests/Feature/HomeTest.php`
  - `tests/Feature/BillingTest.php`
  - `tests/Feature/AdminPanelTest.php`
  - `tests/Feature/SitemapTest.php`
  - `tests/Feature/Settings/ProfileUpdateTest.php`
  - `resources/js/__tests__/json-viewer.test.ts`
  - `resources/js/__tests__/home.test.ts`
  - `resources/js/__tests__/admin-panel.test.ts`
  - `docs/adr/ADR-034-pagina-de-planos-intencao-de-assinatura-e-username.md`
  - `docs/glossario.md`
  - `docs/SPEC.md`
  - `docs/design/DESIGN.md`

---

## 3. Verificacao Executada

- `npm run types:check`: Wayfinder + `vue-tsc --noEmit` — **0 erros**.
- `npm run test`: Vitest (47 arquivos, 662 testes) — **100% passando**.
- `composer test`: `config:clear`, Pint (`--test`), PHPStan e Pest (278 testes) — **100% passando**.
