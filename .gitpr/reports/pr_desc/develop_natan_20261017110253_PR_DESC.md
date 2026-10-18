# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: adiciona intenção de assinatura Pro e página de planos dedicada
```

---

## 🎯 Resumo

Substitui o CTA de venda manual (mailto) por um fluxo estruturado de **intenção de assinatura Pro**: o usuário escolhe o plano, reserva seu `@username` exclusivo, informa telefone/documento/forma de pagamento e o pedido entra numa fila administrativa. O preço do Pro cai para **R$ 8,90/mês** e **R$ 89,00/ano** (2 meses grátis no anual) e os planos saem da home para uma página dedicada (`/{locale}/pricing`) — melhor conversão, melhor SEO e base pronta para o checkout self-service do F6.

## 🛠️ Mudanças Técnicas

- **Domínio**: nova tabela `subscription_intents` com estados `pending/fulfilled/canceled`, forma de pagamento (`pix`, `credit_card`, `boleto`), `handled_by`/`handled_at` e ação auditável (`AdminActionType::SubscriptionIntentFulfilled/Canceled`). Model com casts enum, relations `user`/`plan`/`handler` e `Fillable` por atributo.
- **Username público**: coluna `users.username` (`nullable`, `unique`, 3–30, `[a-z0-9_]`) com regra compartilhada em `ProfileValidationRules::usernameRules()` e normalização (lowercase + strip `@`) em `ProfileUpdateRequest` e `StoreSubscriptionIntentRequest`.
- **Formulário `/subscribe`**: `SubscriptionIntentController` redireciona visitante para cadastro guardando `url.intended`; usuário autenticado vê os planos pagos ativos, preenche/atualiza o pedido com upsert transacional por intenção `pending`. Reserva o `username` na conta na mesma transação e dispara `SubscriptionIntentSubmitted` (queueable) para `Legal::contactEmail()`.
- **Página `/pricing`**: novo `PricingController` + `Pricing.vue`, com grade de planos e bloco de vantagens Pro, CTA `plan-subscribe-<slug>` para `/subscribe?plan=<slug>`. `HomeController`/`Home.vue` ficam sem catálogo (hero, grid e busca), e `PricingSection.vue` perde `contactEmail`.
- **Admin `/admin/intents`**: `SubscriptionIntentController` (index com filtro por status + paginação, `fulfill`/`cancel` idempotentes), entrada no menu (`ClipboardList`) e página `admin/Intents.vue` com tabela, badge de status e ações.
- **Preços**: `pro-monthly` 2990→890 e `pro-yearly` 29900→8900 na migration de seed e numa nova migration idempotente (só `UPDATE` em `plans`).
- **LGPD**: `AnonymizeUser` limpa `username` e remove `subscriptionIntents`; `ExportUserData` inclui `username` e o array `subscription_intents` com plano, telefone, documento e status.
- **i18n**: strings novas em `pt_BR`, `pt_PT`, `en`, `es`, `fr` (`admin.intents.*`, `home.pricing.advantages.*`, `home.subscribe.*`, `mail.intent.*`, `settings.profile.username_*`).
- **UI/SEO/Infra**: `ToolCard` vira card clicável por inteiro (`after:inset-0`); `useRoutes` ganha `pricing` e `subscribe(plan?)`; `lag`/`sitemap` incluem `pricing` e excluem `subscribe` do `noindex`; `formatBytes` converte `pt_BR`→`pt-BR` (BCP 47) para o `Intl.NumberFormat`; `styleguide` sai do menu público.
- **Testes**: `PricingTest` cobrindo 5 idiomas, formatação (8,90/89,00), filtro de plano inativo, redirect com `url.intended`, registro+reserva+notificação, colisão de `username` e ciclo admin (fulfill/cancel + auditoria); ajuste em `HomeTest`, `AdminPanelTest`, `BillingTest`, `ProfileUpdateTest`, `SitemapTest` e nos testes Vue (`home.test.ts`, `admin-panel.test.ts`, `json-viewer.test.ts`).

## ⚠️ Impacto/Avisos

- **Banco de dados**: nova tabela `subscription_intents` e coluna `users.username` (`unique`, `nullable`, com `down()` recriando o schema sem dados). Migration `2026_10_14_000004_seed_plans_catalog` teve valores alterados para 890/8900 — ambientes já migrados **não** refletem o preço automatizado; a nova migration `2026_10_17_000001_...` é a que atualiza produção.
- **Preço comercial**: redução drástica do Pro (R$ 29,90 → R$ 8,90; R$ 299,00 → R$ 89,00). Comunicação e contratos com assinantes atuais precisam de alinhamento antes do deploy.
- **Comportamento público**: home deixa de exibir planos; links `#precos` e o `mailto:` de venda são removidos. Verificar campanhas/UTMs e menções externas que apontavam para `/#precos`.
- **Email**: nova notificação enfileirada para `Legal::contactEmail()` — depende de `queue` funcional e de `contactEmail` configurado (sem ele, o registro funciona mas ninguém é avisado).
- **Segurança/LGPD**: `/subscribe` (GET) usa middleware `active` sem `auth` para permitir o redirect com `url.intended`; o POST exige `auth`+`active` (sem `verified` para não travar cadastro recente). Dados pessoais (telefone, CPF/CNPJ) passam a trafegar no payload — export e anonimização já cobrem o ciclo.
- **Dependências**: nenhuma nova. Apenas o fluxo Inertia/Fortify existente.

close #26

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_22_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)
