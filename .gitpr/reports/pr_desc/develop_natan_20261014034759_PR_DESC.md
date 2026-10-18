# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: adiciona painel de conta e painel administrativo com papéis
```

---

# feat: adiciona painel de conta e painel administrativo com papéis

## 🎯 Resumo

Entrega o bloco F4 do produto: o usuário passa a ter uma área de conta real (visão geral, assinatura/cota, histórico de consumo e exportação/anonimização de dados) e existe um painel administrativo com papéis, auditoria e operações financeiras. O objetivo é tirar o produto do estado de "starter kit" — onde não havia fronteira entre área pública, conta e administração — e viabilizar operação comercial: venda manual de assinatura, concessão de plano/cota, estorno de pagamento, suspensão de conta, impersonation para suporte e conformidade com privacidade (exportação e anonimização).

Em paralelo, o idioma da conta deixa de ser sobrescrito pela navegação por `/{locale}` (ADR-026), autenticação passa a recusar contas suspensas e a derrubar sessões abertas (ADR-025), e o double opt-in da newsletter (ADR-024) e o login social (ADR-023) ficam consistentes no frontend e no backend.

As decisões estão registradas em ADR-022 a ADR-028.

## 🛠️ Mudanças Técnicas

**Domínio e dados**

- Novos modelos `Plan`, `Subscription`, `Payment`, `UsageLedger` (tabela singular `usage_ledger`, apenas insert) e `AdminAction`; `User` ganha `role`, `suspended_at`, `anonymized_at`, relations e helpers (`isAdmin`, `isSuspended`, `isAnonymized`, `activeSubscription`, `effectivePlan`), com `role` fora de `#[Fillable]`/`#[Hidden]`.
- Enums: `UserRole`, `SubscriptionStatus` (sem `expired` gravado — vigência resolvida na leitura), `PaymentStatus`, `PaymentMethod`, `LedgerEntryType` (debit/refund/grant) e `AdminActionType`.
- Cota derivada do livro-razão (`App\Support\Quota`), sem coluna de saldo: `usado = débitos − créditos`, em `uses` no Free e `tokens` nos planos pagos.
- Actions `AnonymizeUser` (transação que cancela assinaturas, zera dados identificáveis, migra e-mail para domínio `removido.invalid`/RFC 2606, remove social accounts e sessões), `ExportUserData` (JSON ISO 8601, sem credenciais) e, no admin, `GrantPlan`, `GrantQuota`, `RecordAdminAction`, `RegisterPayment` e `RefundPayment`.
- Migrações (role, admin_actions, plans com carga do catálogo, subscriptions, payments, usage_ledger, suspended_at, anonymized_at) e factories correspondentes.

**Backend / rotas**

- `routes/admin.php` com Gate `access-admin`: dashboard, users e ações (verificar, reenviar, reset de senha, alterar papel, suspend/unsuspend, plano, cota, exclusão), impersonation, subscribers com export CSV, plans, payments/refund e audit.
- Controllers de conta (Overview, Billing, History, Export) e rotas legais via `LegalController`; área autenticada passa a usar `auth, verified, active`.
- Controllers admin (Dashboard, Users, UserAction, Payments, Plans, Subscribers, Audit, Impersonation), auditoria via trait `RecordsAdminActions` e autorização via `UserPolicy` (manage/changeRole/impersonate).
- `AuthenticateUser` substitui o `Auth::attempt` do Fortify para tratar conta suspensa com mensagem própria; login social também recusa suspensos.
- Middleware `EnsureAccountIsActive` (alias `active`) derruba a sessão de contas suspensas; `ResolveLocale` é prepend na lista de prioridade, antes de `Authenticate`.
- Impersonation via chave de sessão (`Impersonation`), com banner compartilhado pelo Inertia e rota de saída fora do painel.

**Idioma (ADR-026)**

- `ResolveLocale` deixa de reescrever `users.locale` ao navegar por `/{locale}` — agora grava apenas cookie de navegação.
- Ordem resolvida: cookie → idioma da conta → `Accept-Language` → fallback (`Locales::fallback()` aplicado em `preferredLocale()`).
- Cadastro/perfil aceitam `locale` validado contra `Locales::codes()`.

**Frontend**

- Reestruturação em três áreas: `AccountLayout` (navegação única via novos composables `useAccountNav`/`useActiveSection`), `AdminLayout` próprio e `AuthLayout` reescrito como moldura única das telas de acesso (wordmark, `LocaleSwitcher`, `ThemeToggle`, rodapé legal); `PublicLayout` ganha `NewsletterForm` e links de privacidade/termos.
- Remoção dos layouts genéricos do starter kit (`AppLayout`, `AppHeaderLayout`, `AppSidebarLayout`, `settings/Layout`, `layouts/auth/*`) e de componentes não usados (`AppHeader`, `Breadcrumbs`, `NavFooter`, `PlaceholderPattern`).
- `useRoutes` centraliza todas as rotas com prefixo de idioma e expõe `routes.form.*` prontas para `v-bind` no `<Form>`.
- Páginas de auth migradas para i18n + `AuthCard`/`SocialAuthButtons`, com `aria-invalid`/`aria-describedby`; `ConfirmPassword` alterna entre confirmar e definir senha conforme `hasPassword`.
- Novas páginas: Privacy/Terms (via `LegalDocument`), `newsletter/Confirm` e `newsletter/Unsubscribe` (double opt-in em dois passos), `dashboard/Billing`, `dashboard/History` e `Dashboard.vue` reescrito como Visão geral consumindo props de plano/cota/assinatura já formatadas.
- Bloco admin: `admin/Dashboard`, `Users`, `UserDetail` (9 ações), `Subscribers`, `Plans`, `Payments` e `Audit`, tipados por `types/admin.ts`/`types/account.ts`, com ampliações em `auth`/`global` (`isAdmin`, impersonation, `seo.noindex`).
- i18n: novos namespaces `account` e `admin` nos 5 idiomas e ajustes em `auth`, `settings`, `app` e `newsletter` (incluindo `{'@'}` nos placeholders para o vue-i18n).
- Suporte: `App\Support\Format` (Intl, formatação server-side), `App\Support\Legal`, `Seo` com `dashboard`/`settings`/`admin` fora de indexação e `SocialProviders::emailIsVerified` restrito a `AbstractUser`.

**Testes**

- Novos: `AccountPanelTest`, `AdminAccountActionsTest`, `AdminBillingTest`, `AdminImpersonationTest`, `AdminPanelTest`, `AdminRoleTest`, `Auth/LocalizedAuthPagesTest`, `Auth/SetPasswordTest`, `Auth/SocialLoginTest`, `BillingTest`, `LegalPagesTest`, `MailLocaleTest`, `NewsletterTest`, `QuotaTest`, além dos testes de frontend (account-panel, admin-panel, auth-login, newsletter-form).
- Ajustes em reset/verificação (notificações próprias do app), registro (locale), `ProfileUpdateTest` (anonimização, troca de idioma, cascata de sessão) e `TranslationsParityTest` (escape de `@`/`|` do vue-i18n).
- `TestCase` passa a fixar `URL::defaults` de `{locale}`, evitando `UrlGenerationException` fora de requisição.

## ⚠️ Impacto/Avisos

- **Banco de dados:** 8 novas migrações (`role`, `admin_actions`, `plans` com carga do catálogo via migração, `subscriptions`, `payments`, `usage_ledger`, `suspended_at`, `anonymized_at`). A carga inicial de planos dentro de migração é um ponto de atenção para ambientes que gerenciam catálogo fora do deploy.
- **Variáveis de ambiente:** nova `BILLING_CURRENCY` (config `billing.currency`) — defina antes do deploy para evitar valores divergentes em cobrança e exibição.
- **Sessão/autenticação:** o middleware `active` invalida sessões de contas suspensas. Papel `role` não é mass-assignable nem serializado por padrão — qualquer código legado que dependa de `User::fill`/`toArray` para papel precisará de ajuste.
- **Idioma:** mudança de comportamento — navegar por `/{locale}` não altera mais `users.locale`, apenas o cookie de navegação. Usuários que esperavam que a navegação alterasse o idioma da conta (e-mails, notificações) podem notar diferença.
- **Anonimização é irreversível:** cancela assinaturas, remove social accounts/sessões e substitui o e-mail por domínio `removido.invalid`. Ações administrativas destrutivas (exclusão, estorno) passam a ser auditadas em `admin_actions`.
- **Rotas novas:** `routes/admin.php` exige o Gate `access-admin`; a área autenticada passa a exigir `verified` explicitamente no middleware — contas não verificadas perderão acesso até concluir a verificação.
- **Frontend:** remoção de layouts e componentes do starter kit é breaking para qualquer tela ainda não migrada para `AccountLayout`/`AdminLayout`/`AuthLayout`.

close #3

---

[![GitPR](https://img.shields.io/badge/GitPR-1_error_%C2%B7_169_warnings-red)](https://gitpr.natanfiuza.dev.br/)
