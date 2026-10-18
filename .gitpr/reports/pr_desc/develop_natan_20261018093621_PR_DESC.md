# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona hub de assinaturas e restauração de contas no admin
```

---

## 🎯 Resumo

O painel administrativo carecia de uma visão consolidada sobre assinaturas e sobre o ciclo de vida de contas removidas. Esta mudança entrega um **hub único de gestão de assinaturas** (ativas, intenções, pagamentos e canceladas) com métricas de receita e carência, além de habilitar a **restauração de contas em soft delete** por administradores — tudo com auditoria e controle de acesso. O objetivo é reduzir o esforço operacional de suporte (antes pulverizado em telas/fluxos distintos) e dar rastreabilidade às ações administrativas sensíveis.

## 🛠️ Mudanças Técnicas

- **Backend — Assinaturas:** novo `Admin/SubscriptionController@index` expondo abas `active`/`intents`/`payments`/`deleted`, com filtros, paginação, estatísticas de receita e período de carência; rota `GET admin/subscriptions` registrada.
- **Backend — Restauração de contas:** novo caso `AdminActionType::UserRestored`, método `UserActionController@restore` protegido por `Gate::authorize('restore')`, policy `UserPolicy::restore` (apenas contas comuns em soft delete e não anonimizadas) e scopes `inDeletionGracePeriod`/`anonymized` em `User`; rota `POST admin/users/{user}/restore` com `withTrashed`.
- **Frontend:** nova página Inertia `admin/Subscriptions.vue` (tabelas por aba, formulário de pagamento manual, diálogos de estorno e restauração) e item de navegação no `AdminLayout` (ícone `BadgePercent`); novos tipos `AdminSubscriptionRow`, `AdminDeletedAccountRow` e `AdminSubscriptionsStats`.
- **UX/Roteamento:** `Account/OverviewController` passa a redirecionar administradores para o painel admin.
- **i18n:** chaves de assinaturas e restauração adicionadas nos cinco locales (`en`, `es`, `fr`, `pt_BR`, `pt_PT`).
- **Testes:** novo `AdminSubscriptionsTest` (acesso ao hub, métricas, abas, restauração com auditoria em `AdminAction` e bloqueio de conta anonimizada com 403); `AdminPanelTest` atualizado para 19 `actionTypes`; `AdminRoleTest` cobre o redirecionamento e as asserções de Inertia para `admin.dashboard`; testes de navegação admin passam a esperar oito seções.
- **Estilo:** reformatação massiva via Prettier/ESLint em YAML, TS, Vue, CSS e testes (quebra de linhas, ordem de imports, aspas simples, indentação), sem mudança de comportamento.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migration nova; a feature reutiliza soft delete e a tabela de auditoria `AdminAction` existentes. O novo valor `UserRestored` amplia o enum de `action_type` — consumidores que validam essa lista precisam ser atualizados (testes já refletem 19 tipos).
- **Permissões:** a restauração exige a nova ability `restore` na `UserPolicy`; papéis administrativos sem essa permissão passam a receber 403.
- **Regra de negócio:** contas anonimizadas são irrecuperáveis por design e retornam 403; contas fora da janela de carência também são bloqueadas pela policy.
- **Sem novas variáveis de ambiente ou dependências** adicionadas.
- **Ruído de review:** o diff contém volume elevado de reformatação automática — recomenda-se revisar por commit/arquivo para isolar a mudança funcional.

close #33

---

[![GitPR](https://img.shields.io/badge/GitPR-3_errors_%C2%B7_11_warnings-red)](https://gitpr.natanfiuza.dev.br/)