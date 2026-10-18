# Plano - Correcoes na Intencao de Assinatura e Painel de Faturas

Branch: `develop_natan`.
Data: 2026-10-18.
Escopo: Ajustes no formulario de intencao de assinatura (`/subscribe`), seletor de DDI para WhatsApp, documento de identificacao opcional, contador de redirecionamento de 30 segundos e aviso de intencao em analise na aba Faturas do usuario (`/dashboard/billing`).

---

## 1. Contexto e Diagnostico dos Requisitos (Grill com Docs)

### 1.1. Documento de identificacao opcional e internacionalizacao do rotulo
- **Requisito**: "Quando o usuario vai preencher a intensao de assinatura o campo CPF ou CNPJ nao pode ser obrigatorio troque para documento de identificacao para outros idiomas".
- **Analise do codigo e banco de dados**:
  - `database/migrations/2026_10_17_000001_update_pro_plans_pricing_and_add_subscription_intents.php`: a coluna `document` foi criada como `$table->string('document', 32);` (nao nula).
  - Como o deploy e continuo e sem downtime (ADR-014, RUNBOOK.md), criaremos uma nova migration retrocompativel: `$table->string('document', 32)->nullable()->change();`.
  - `StoreSubscriptionIntentRequest.php`: a regra atual e `'document' => ['required', 'string', 'min:11', 'max:32']`. Deve ser alterada para `'document' => ['nullable', 'string', 'max:32']`, e no `prepareForValidation()` converter string vazia para `null`.
  - `SubscriptionIntentSubmitted.php`: tratar caso onde `$intent->document` e nulo ou vazio (exibir `'—'`).
  - `admin/Intents.vue`: exibir `row.document || '—'`.
  - `Subscribe.vue`: remover o atributo `required` do input `#document`.
  - Rotulos e placeholders (`lang/*/home.php`):
    - `pt-BR`: "CPF ou CNPJ (opcional)" / "000.000.000-00 (opcional)"
    - `pt-PT`: "Documento de identificação (opcional)" / "NIF, Cartão de Cidadão ou passaporte"
    - `en`: "Identification document (optional)" / "Passport, Tax ID or ID number"
    - `es`: "Documento de identificación (opcional)" / "DNI, NIE, Pasaporte o documento fiscal"
    - `fr`: "Document d'identité (facultatif)" / "Passeport, CNI ou identifiant fiscal"

### 1.2. Seletor de codigo de pais (DDI) para o WhatsApp
- **Requisito**: "O campo telefone whatsapp deve ter a opcao de escolher o codigo do pais".
- **Analise de UI e dados**:
  - O campo telefone atualmente e um input de texto simples com `name="phone"`.
  - Implementaremos um componente de selecao de DDI (`Select` do Shadcn/Reka UI) acoplado ao campo de telefone.
  - O seletor exibira a bandeira, o nome do pais e o prefixo internacional (ex.: 🇧🇷 +55, 🇵🇹 +351, 🇺🇸 +1, 🇪🇸 +34, 🇫🇷 +33, 🇬🇧 +44, 🇩🇪 +49, 🇮🇹 +39, etc.).
  - A pre-selecao inicial do DDI sera baseada no idioma ativo na pagina (`pt-BR` -> `+55`, `pt-PT` -> `+351`, `en` -> `+1`, `es` -> `+34`, `fr` -> `+33`).
  - Se o usuario ja possuir `pendingIntent.phone` salvo (ex.: `+55 11 99999-9999`), o componente extrai automaticamente o DDI e isola o numero local.
  - Ao submeter o formulario, o valor unificado com DDI e enviado no campo `phone`. Alem disso, no backend (`StoreSubscriptionIntentRequest::prepareForValidation()`), caso receba `country_code` e `phone` separados ou numero sem DDI, realiza a juncao e normalizacao de forma segura.

### 1.3. Mensagem de confirmacao com contador decrescente de 30 segundos
- **Requisito**: "Apos confirmar a intensao de assinatura na mensagem que e exibida para da confirmacao deve existir um contador de 30 segundos decrescente informando que ele sera direcionado para a tela inicial do site".
- **Analise do fluxo**:
  - Quando a requisicao e processada, o controller redireciona com `.with('status', 'intent-submitted')`.
  - Em `Subscribe.vue`, o banner de confirmacao e exibido quando `status === 'intent-submitted'`.
  - Quando esse status estiver ativo, iniciamos um timer reativo decrescente de 30 segundos (`countdown = 30`).
  - A mensagem exibe: "Voce sera redirecionado para a pagina inicial em {seconds}s." (com suporte aos 5 idiomas).
  - Incluiremos acoes de conveniencia para o usuario: botao para ir imediatamente ("Ir para a pagina inicial") e opcao para pausar/cancelar o redirecionamento caso queira continuar lendo a pagina.
  - Ao zerar o contador (`0s`), o Inertia executa `router.visit(routes.home.value)`.
  - No `onUnmounted`, o `clearInterval` e executado rigorosamente para evitar vazamento de memoria.

### 1.4. Notificacao de pedido em analise na aba Faturas do usuario
- **Requisito**: "Quando o usuario enviar uma intensao de assinaturas deve ser exibido em seu painel na aba Faturas que a sua intensao de assinatura foi enviada e esta em analise".
- **Analise do dashboard**:
  - A rota `/dashboard/billing` e controlada por `App\Http\Controllers\Account\BillingController` e renderizada por `resources/js/pages/dashboard/Billing.vue`.
  - O controller consultara se o usuario autenticado possui `SubscriptionIntent` com `status = 'pending'`.
  - Se houver, envia `pendingIntent` para as props do Inertia com: `id`, `planName`, `planSlug`, `status` e `createdAt`.
  - Em `Billing.vue`, logo acima da secao de assinatura atual, renderizaremos um card/banner de destaque com:
    - Selo / badge "Em analise";
    - Titulo traduzido: "Intencao de assinatura em analise";
    - Mensagem com interpolacao: "Sua intencao de assinatura para o plano {plan} foi enviada em {date} e esta em analise pela nossa equipe. Entraremos em contato em breve para concluir a ativacao.";
    - Link/botao para visualizar ou atualizar o pedido (`/subscribe?plan={planSlug}`).
  - Atualizacao das definicoes de tipos em `resources/js/types/account.ts`.
  - Novas chaves de traducao em `lang/*/account.php` nos 5 idiomas (`pt_BR`, `pt_PT`, `en`, `es`, `fr`).

---

## 2. Arquivos Modificados e Criados

### 2.1. Banco de Dados e Backend
1. `database/migrations/2026_10_18_000001_make_document_nullable_on_subscription_intents.php` (novo):
   - Altera coluna `document` para `nullable`.
2. `app/Http/Requests/StoreSubscriptionIntentRequest.php`:
   - Regra de `document`: `['nullable', 'string', 'max:32']`.
   - `prepareForValidation()`: normaliza string vazia para `null`, e combina `country_code` com `phone` caso recebidos separadamente.
3. `app/Models/SubscriptionIntent.php`:
   - Docblock de `@property string|null $document`.
4. `app/Notifications/SubscriptionIntentSubmitted.php`:
   - Trata `document` nulo com fallback `'—'`.
5. `app/Http/Controllers/Account/BillingController.php`:
   - Carrega a intencao pendente mais recente do usuario e envia na prop `pendingIntent`.

### 2.2. Frontend e Componentes
1. `resources/js/types/account.ts` e `resources/js/types/home.ts`:
   - Adicionar `AccountPendingIntent` e tipar `pendingIntent` em `AccountBillingProps`.
   - Atualizar `PendingSubscriptionIntent.document` para `string | null`.
2. `resources/js/pages/Subscribe.vue`:
   - Adicionar seletor de DDI para telefone com lista de paises e deteccao automatica pelo idioma atual.
   - Tornar o campo `document` opcional (sem `required`).
   - Implementar contador decrescente de 30 segundos no banner de sucesso com redirecionamento automatico para a home.
3. `resources/js/pages/dashboard/Billing.vue`:
   - Renderizar card/banner de intencao de assinatura em analise quando `pendingIntent` estiver presente.
4. `resources/js/pages/admin/Intents.vue`:
   - Tratar exibicao segura de documento vazio (`row.document || '—'`).

### 2.3. Arquivos de Traducao (5 idiomas: pt_BR, pt_PT, en, es, fr)
1. `lang/{locale}/home.php`:
   - Atualizar `document_label` e `document_placeholder` em todos os idiomas.
   - Adicionar chaves para o contador e acoes de redirecionamento:
     - `redirect_countdown`: mensagem com `{seconds}`.
     - `redirect_now`: "Ir para a página inicial".
     - `redirect_cancel`: "Permanecer nesta página".
     - `phone_country_code`: "Código do país".
2. `lang/{locale}/account.php`:
   - Adicionar secao `pending_intent`:
     - `badge`: "Em análise".
     - `title`: "Intenção de assinatura em análise".
     - `description`: "Sua intenção de assinatura para o plano {plan} foi enviada em {date} e está em análise pela nossa equipe. Entraremos em contato para concluir a ativação."
     - `view_details`: "Ver ou alterar pedido".

---

## 3. Testes Automatizados

1. **Pest (Backend)**:
   - `tests/Feature/PricingTest.php`:
     - Testar envio de intencao sem documento (sucesso com `document` nulo).
     - Testar envio com documento informado (persistencia correta).
     - Testar envio de telefone com DDI internacional.
   - `tests/Feature/BillingTest.php`:
     - Testar que `/dashboard/billing` retorna `pendingIntent = null` para usuario sem pedidos pendentes.
     - Testar que `/dashboard/billing` retorna os dados corretos da intencao pendente quando existente.
2. **Vitest (Frontend)**:
   - `resources/js/__tests__/account-panel.test.ts`:
     - Testar renderizacao do banner de intencao em analise na tela de Faturas.
     - Testar que o banner nao e renderizado quando `pendingIntent` e nulo.
   - Teste unitario para o contador e seletor de DDI em `Subscribe.vue`.
3. **Validacao Geral**:
   - `composer test` (Pint, PHPStan, Pest).
   - `npm run test` (Vitest).
   - `npm run types:check` (Wayfinder + vue-tsc).

---

## 4. Pontos de Alinhamento (Grill com Docs)

1. **Comportamento do contador de 30s**: Se o usuario clicar em qualquer input do formulario durante os 30s ou clicar no botao "Permanecer nesta página", o timer sera pausado para nao interromper sua navegacao.
2. **DDI Padrao**: O DDI inicial respeitara o idioma da rota publica (`pt-BR` -> `+55`, `pt-PT` -> `+351`, `en` -> `+1`, `es` -> `+34`, `fr` -> `+33`), permitindo selecionar qualquer outro pais da lista.
3. **Intencao na aba Faturas**: O card sera exibido com destaque no topo da aba Faturas enquanto o status for `pending`. Assim que o admin aprovar (`fulfilled`) ou cancelar (`canceled`), o card e substituido pela assinatura ativa ou pelo estado normal.
