# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: extrai modal de confirmacao de estorno em componente reutilizavel
```

---

## 🎯 Resumo

O fluxo de estorno de pagamentos estava duplicado e inconsistente no painel administrativo: em `Payments.vue` a confirmação usava `window.confirm` (nativo, sem contexto do pagamento e sem bloqueio durante a requisição) e em `Subscriptions.vue` existia um `Dialog` inline com implementação própria. Esta mudança centraliza o fluxo em um único componente reutilizável, garantindo comportamento idêntico nas duas telas, exibindo o resumo do pagamento antes da ação destrutiva e impedindo cliques duplicados enquanto a requisição está em andamento.

## 🛠️ Mudanças Técnicas

- Cria `resources/js/components/admin/ConfirmRefundDialog.vue` com props `payment: AdminPaymentRow | null` e evento `close`, controlando abertura via `Dialog` (`:open="payment !== null"`).
- Adiciona estado `isRefunding` que desabilita os botões de cancelar e confirmar e bloqueia o fechamento do modal (`setOpen`) durante a requisição, evitando duplo envio de estorno.
- Dispara `router.post(admin.payments.refund.url(id), {}, { preserveScroll: true, onFinish })`, encerrando o loading e emitindo `close` no `onFinish`.
- Renderiza um `dl` com conta, plano (fallback para `admin.payments.plan_none`), valor e forma de pagamento traduzida por `admin.payments.methods.<method>`.
- `pages/admin/Payments.vue`: substitui `refund()` com `window.confirm` por `confirmRefund()`, que apenas define `refundTarget` e abre o novo modal.
- `pages/admin/Subscriptions.vue`: remove o `Dialog` inline e a função `executeRefund` locais, passando a usar o componente compartilhado.
- Adiciona `resources/js/__tests__/confirm-refund-dialog.test.ts` cobrindo estado fechado, conteúdo renderizado, emissão de `close` no cancelamento e POST com botão travado durante o voo da requisição.

## ⚠️ Impacto/Avisos

- **Sem alterações de banco de dados, rotas de backend ou dependências.** Nenhum novo pacote adicionado; usa `vitest`, `@vue/test-utils` e Inertia já existentes.
- **Mudança de UX:** a confirmação em `Payments.vue` deixa de ser o `confirm()` nativo do navegador e passa a ser um modal; usuários não conseguem mais cancelar o estorno com o modal aberto após o clique em confirmar.
- **i18n:** o componente depende das chaves `admin.payments.refund`, `admin.payments.refund_confirm`, `admin.payments.account`, `admin.payments.plan`, `admin.payments.plan_none`, `admin.payments.amount`, `admin.payments.method`, `admin.payments.methods.*` e `admin.common.cancel`. Ausência de `admin.payments.methods.<method>` para novos meios de pagamento exibirá a chave crua.
- **Contrato de evento:** `Payments.vue` e `Subscriptions.vue` passam a depender do evento `close` para limpar `refundTarget`; qualquer novo consumidor deve seguir o mesmo padrão.
- O modal reutiliza o helper de rotas `admin.payments.refund`, portanto depende da rota nomeada continuar existindo.

close #74

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)