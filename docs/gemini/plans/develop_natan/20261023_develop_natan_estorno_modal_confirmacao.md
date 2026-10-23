# Plano de Implementação — Modal de Confirmação de Estorno no Admin

- **Data:** 2026-10-23
- **Branch:** `develop_natan`
- **Identificador:** `estorno_modal_confirmacao`
- **Survey facts:** `docs/survey/20261023_estorno_modal_confirmacao_surveyfacts.md`

## 1. Objetivo
Substituir o diálogo nativo do navegador (`window.confirm`) acionado pelo botão "Estornar" na tela de pagamentos do dashboard administrativo (`/admin/payments`) por um modal dedicado (`Dialog` do shadcn-vue), padronizando a experiência com a aba de pagamentos de assinaturas (`/admin/subscriptions`) através de um componente compartilhado `ConfirmRefundDialog.vue`.

## 2. Escopo de Alterações

### 2.1 Novo Componente: `resources/js/components/admin/ConfirmRefundDialog.vue`
- Criar componente recebendo:
  - Prop: `payment: AdminPaymentRow | null`
  - Emit: `close: []`
- Estrutura:
  - `<Dialog :open="payment !== null" @update:open="setOpen">`
  - `<DialogHeader>` com `DialogTitle` (`admin.payments.refund`) e `DialogDescription` (`admin.payments.refund_confirm`).
  - Card de resumo com os dados do pagamento alvo (`account`, `plan`, `amount`, `method`).
  - `<DialogFooter>` contendo botão Cancelar (variante `ghost`) e botão Estornar (variante `destructive`).
  - Estado reativo `isRefunding = ref(false)` desabilitando os botões durante a submissão via `router.post(admin.payments.refund.url(props.payment.id))` com `preserveScroll: true`.

### 2.2 Atualização em `resources/js/pages/admin/Payments.vue`
- Remover o uso de `window.confirm`.
- Declarar o estado `refundTarget = ref<AdminPaymentRow | null>(null)`.
- Atualizar a ação `#actions="{ row }"` para invocar `confirmRefund(row)`, atribuindo `refundTarget.value = row`.
- Instanciar `<ConfirmRefundDialog :payment="refundTarget" @close="refundTarget = null" />`.

### 2.3 Padronização em `resources/js/pages/admin/Subscriptions.vue`
- Substituir o diálogo de estorno inline existente pelas tags de `<ConfirmRefundDialog :payment="refundTarget" @close="refundTarget = null" />`.
- Remover a função `executeRefund` duplicada, aproveitando a lógica encapsulada no novo componente.

## 3. Verificação e Testes
- Checagem estática de tipos do Vue e TypeScript: `npm run types:check`
- Lint e formatação: `npm run lint:check`
- Testes unitários do frontend: `npm run test`
- Testes do backend: `composer test`
