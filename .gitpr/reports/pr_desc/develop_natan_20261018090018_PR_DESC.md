# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: adiciona hub de gestão de assinaturas no painel admin
```

---

## 🎯 Resumo

Centraliza em um único hub administrativo as quatro visões do ciclo de receita e retenção — assinaturas vigentes, solicitações Pro, pagamentos e contas canceladas. Antes, o time precisava alternar entre telas isoladas (assinantes, intenções Pro e usuários) para acompanhar a saúde financeira e não tinha como reverter uma exclusão dentro da janela de carência. Esta entrega unifica esses fluxos, expõe métricas consolidadas (assinaturas ativas, solicitações pendentes, receita do mês e contas em carência) e habilita a restauração de contas em soft delete. Também direciona admins autenticados para o painel administrativo, evitando que caiam no dashboard de usuário comum.

## 🛠️ Mudanças Técnicas

- Cria `Admin\SubscriptionController` com abas `active`, `intents`, `payments` e `deleted`, busca textual, filtros por status/plano e paginação de 20 itens por aba.
- Adiciona métricas do hub: total de assinaturas correntes, intenções pendentes, receita paga no mês e contas em janela de carência de 30 dias.
- Implementa `UserActionController::restore` com autorização via `UserPolicy::restore`, auditoria em `AdminAction` (novo `AdminActionType::UserRestored`) e toast de sucesso.
- Registra rota `POST admin/users/{user}/restore` com `withTrashed()` e rota `GET admin/subscriptions`.
- Adiciona models scopes `User::scopeInDeletionGracePeriod` e `User::scopeAnonymized`, usados no hub.
- Cria `AdminActionType::UserRestored` para rastrear restaurações no log de auditoria.
- Redireciona usuários admin de `dashboard` para `admin.dashboard` em `OverviewController`.
- Nova página Inertia `admin/Subscriptions.vue` com tabelas, filtros, cadastro manual de pagamento, estorno e diálogo de confirmação de restauração, além do item de navegação no `AdminLayout`.
- Tipos TypeScript `AdminSubscriptionRow`, `AdminDeletedAccountRow` e `AdminSubscriptionsStats`.
- Traduções completas do módulo em `pt_BR`, `pt_PT`, `en`, `es` e `fr`.
- Testes de feature cobrindo controle de acesso do hub, cada aba, restauração com auditoria e bloqueio de restauração de conta anonimizada; testes de role atualizados para o novo redirecionamento.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migration nova. A restauração depende do soft delete existente (`deleted_at`) e do campo `anonymized_at`; contas já anonimizadas são irrecuperáveis por design e a ação retorna 403.
- **Configuração/Env:** sem novas variáveis. A receita mensal usa `config('billing.currency')` e `Format::money`; o valor exibido reflete apenas pagamentos com status `paid`.
- **Dependências:** nenhuma adição ao `composer.json` ou `package.json`; reutiliza componentes de UI e `AdminTable` já existentes.
- **Comportamento:** admins autenticados que acessarem o dashboard comum passam a ser redirecionados ao painel administrativo — ajuste esperado em fluxos e testes que assumiam o dashboard de usuário.
- **Operação:** a restauração reativa o login do usuário imediatamente; recomenda-se orientar o time de suporte sobre o uso e sobre a janela de 30 dias antes do expurgo definitivo.
