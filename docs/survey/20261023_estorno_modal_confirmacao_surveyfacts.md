# Survey — Modal de Confirmação de Estorno no Dashboard Admin (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "No dashboard de Admin na aba Pagamentos quando clica em Estornar deve abrir um modal de confirmação, hoje esta mostrando um dialog padrão."
- **Tela / Modulo:** Painel administrativo (`/admin/payments` e `/admin/subscriptions`), especificamente `resources/js/pages/admin/Payments.vue` e `resources/js/pages/admin/Subscriptions.vue`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** Rodada unica de grill com decisao de 3 questoes arquiteturais e de UX, esgotamento de fronteira e definicao de componentes reutilizaveis.
- **Arquivos-chave lidos:**
  - `resources/js/pages/admin/Payments.vue`
  - `resources/js/pages/admin/Subscriptions.vue`
  - `resources/js/components/admin/ConfirmIntentPaymentDialog.vue`
  - `resources/js/components/ui/dialog/Dialog.vue`
  - `routes/admin.php`
  - `app/Http/Controllers/Admin/PaymentController.php`
  - `lang/pt_BR/admin.php`
  - `lang/en/admin.php`

## 2. Decisoes das rodadas

### Rodada 1 — Arquitetura de Componentes, Visual e Concorrencia
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Componentização do Modal | **Opção A (Recomendado)**: Criar o componente reutilizável `resources/js/components/admin/ConfirmRefundDialog.vue` e consumi-lo tanto em `Payments.vue` quanto em `Subscriptions.vue`. Elimina duplicação de lógica e padroniza o fluxo de estorno. |
| Q2 | Detalhamento Visual do Alvo | **Opção B (Recomendado)**: Exibir título, descrição de advertência (`admin.payments.refund_confirm`) e um card compacto com resumo da transação (`account`, `plan`, `amount` e `method`), garantindo clareza total ao operador antes de uma ação financeira destrutiva. |
| Q3 | Proteção contra Cliques Duplos | **Opção A (Recomendado)**: Utilizar estado de carregamento (`isRefunding = ref(false)`) desabilitando os botões de ação e cancelamento durante o envio do `router.post`, prevenindo chamadas concorrentes enquanto o backend/gateway processa. |

### Defaults assumidos (nao contestados)
- Utilização estrita dos componentes de UI já existentes em `@/components/ui/dialog` (shadcn-vue / reka-ui).
- Utilização das chaves de i18n existentes em `admin.payments.*` e `admin.common.cancel`, presentes nos 5 idiomas suportados (`en`, `pt-BR`, `pt-PT`, `fr`, `es`).
- Rota de submissão permanece `admin.payments.refund.url(payment.id)` com método POST e `preserveScroll: true`.

## 3. Relatorio de fatos levantados

### Precedentes no repositório
1. Em `resources/js/pages/admin/Payments.vue`, a ação de estorno estava acoplada a um `window.confirm(t('admin.payments.refund_confirm'))` nativo do navegador, destoando de toda a identidade visual de modais do projeto.
2. Em `resources/js/pages/admin/Subscriptions.vue`, um `<Dialog>` inline já era usado para estorno, porém sem card detalhado de confirmação e sem proteção contra múltiplos cliques durante latência de gateway.
3. Em `resources/js/components/admin/ConfirmIntentPaymentDialog.vue`, existe o precedente ideal de diálogo extraído em `components/admin/` para ações financeiras do painel, fornecendo padrão consistente de props (`intent | payment`), eventos (`close`), apresentação em `<dl>` estilizado com `bg-muted/40` e botões de rodapé.
