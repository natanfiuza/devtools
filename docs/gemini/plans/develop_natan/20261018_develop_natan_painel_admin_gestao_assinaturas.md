# Plano - Controle de Acesso Admin e Hub de Gestao de Assinaturas

Branch: `develop_natan`.
Data: 2026-10-18.
Decisoes validadas com o usuario via /grill-with-docs:
1. Hub unificado de Assinaturas em `/admin/subscriptions` com 4 abas integradas: Assinaturas Vigentes, Solicitacoes (Intencoes), Pagamentos e Contas Excluidas (Carencia 30d e Anonimizadas).
2. Redirecionamento automatico do admin de `/dashboard` para `/admin` ao acessar o painel.

---

## 1. Confronto com a Documentacao e Decisoes de Arquitetura (Grill com Docs)

### 1.1. Controle de Acesso: Papeis (Roles) vs Rules
- **O que ja existe na base (ADR-025 e docs/SPEC.md secao 7)**:
  - O sistema ja possui controle estrito de papeis implementado via coluna `users.role` (`string(16)`, indexada) com o enum `App\Enums\UserRole` (`user` e `admin`).
  - O acesso ao painel `/admin` e protegido pelo Gate `access-admin` (`$user->isAdmin()`), combinado com middlewares `auth`, `verified` e `active`.
  - Operacoes sobre contas sao mediadas pela `UserPolicy`:
    - `viewAny`, `view`, `update` para admins;
    - `manage` (suspender, excluir, impersonar) bloqueia acoes sobre outros admins e sobre a propria conta;
    - `changeRole` impede rebaixamento da propria conta;
    - `impersonate` impede impersonar conta suspensa.
  - **Decisao de Arquitetura (ADR-025)**: Pacotes pesados de permissoes (como `spatie/laravel-permission`) foram descartados propositalmente para evitar tabelas extras, cache e sobrecarga desnecessaria para apenas dois papeis estaveis (`user` e `admin`).

### 1.2. Comando Operacional para o Usuario 1: Tinker vs Artisan
- **Requisito do usuario**: Comando tinker para alterar o usuario de `id = 1` para admin.
- **Confronto com ADR-025**:
  - Toda alteracao de papel deve obrigatoriamente registrar rastro na tabela `admin_actions` (tipo `user.role_granted`).
  - Um comando Tinker puro (`$user->forceFill(['role' => 'admin'])->save()`) alteraria o banco sem gerar auditoria, violando a regra da ADR-025.
  - O sistema ja possui o comando oficial de CLI idempotente:
    `php artisan devtools:admin <email>`
  - Portanto, apresentamos tanto a execucao via Tinker com registro de auditoria quanto o comando Artisan oficial nativo.

### 1.3. Redirecionamento de Dashboard para Admin
- **Comportamento definido**:
  - Quando um usuario autenticado com `role = admin` acessar a rota `/dashboard` (que e o painel pessoal do cliente), ele sera redirecionado automaticamente para `/admin`.
  - Caso o admin precise consultar suas proprias faturas pessoais ou consumo pessoal, ele podera acessar `/dashboard/history` ou `/dashboard/billing` explicitamente pelo menu de usuario.

---

## 2. Comandos para Promover o Usuario 1 em Producao

### 2.1. Opcao A: Comando Artisan Oficial (Recomendado pela ADR-025)
No terminal do servidor de producao:
```bash
# 1. Identificar o e-mail da conta de id 1 (se necessario)
php artisan tinker --execute="echo \App\Models\User::find(1)?->email . PHP_EOL;"

# 2. Executar o comando nativo que promove e audita automaticamente:
php artisan devtools:admin email_do_usuario_1@dominio.com
```

### 2.2. Opcao B: Comando Tinker Direto (Com Registro de Auditoria)
Se for necessario rodar especificamente dentro da sessao interativa do Tinker:
```php
php artisan tinker
```
```php
$user = \App\Models\User::find(1);

if ($user) {
    $user->forceFill(['role' => \App\Enums\UserRole::Admin])->save();

    app(\App\Actions\Admin\RecordAdminAction::class)(
        null,
        \App\Enums\AdminActionType::UserRoleGranted,
        $user,
        ['via' => 'tinker', 'para' => \App\Enums\UserRole::Admin->value]
    );

    echo "Usuario 1 ({$user->email}) promovido para Admin com auditoria registrada com sucesso.\n";
} else {
    echo "Usuario 1 nao encontrado.\n";
}
```

---

## 3. Arquitetura da Solucao: Hub de Gestao de Assinaturas

### 3.1. Estrutura de Rotas e Navegacao
1. **Redirecionamento em `App\Http\Controllers\Account\OverviewController`**:
   - Se `$request->user()->isAdmin()`, redirecionar para `route('admin.dashboard')`.
2. **Nova Rota e Hub Centralizado**:
   - `GET /admin/subscriptions`: Rota centralizada no grupo `admin.` apontando para `App\Http\Controllers\Admin\SubscriptionController`.
   - Sidebar do `AdminLayout.vue` atualizada com o item "Assinaturas" (`CreditCard` ou `BadgePercent`).
3. **As 4 Abas do Hub de Assinaturas**:
   - **Aba 1: Assinaturas Vigentes**:
     - Listagem de assinaturas ativas com usuario, plano, data de inicio, data de termino e badges de vigencia.
     - Acao rapida para abrir o detalhe do usuario (`/admin/users/{user}`).
   - **Aba 2: Solicitacoes (Intencoes)**:
     - Tabela das intencoes registradas (`SubscriptionIntent`), com filtros de status (pendente, atendida, cancelada).
     - Acoes de atendimento (`fulfill`) e cancelamento (`cancel`).
   - **Aba 3: Pagamentos**:
     - Tabela de historico de cobrancas (`Payment`), valor formatado, metodo e status (pago, estornado).
     - Botao para registrar pagamento manual e acao de estorno com auditoria.
   - **Aba 4: Contas Excluidas / Cancelamentos**:
     - Lista de contas em processo de cancelamento/exclusao:
       - Contas em janela de carencia de 30 dias via Soft Delete (ADR-035: `deleted_at != null` e `deleted_at > now()->subDays(30)`), exibindo contagem regressiva de dias restantes para o expurgo definitivo e botao de acao para o admin restaurar a conta (`$user->restore()`).
       - Contas ja expurgadas/anonimizadas (ADR-028: `anonymized_at != null`), mostrando o identificador fiscal preservado e a data da anonimizacao.

### 3.2. Ajustes nos Models e Policies
- **`App\Models\User`**:
  - `scopeInDeletionGracePeriod(Builder $query)`: Contas com soft delete dentro dos 30 dias de carencia.
  - `scopeAnonymized(Builder $query)`: Contas com `anonymized_at != null`.
- **`App\Policies\UserPolicy`**:
  - Adicionar metodo `restore(User $actor, User $target)`: Permite que admins restaurem contas que estao em soft delete.
- **`App\Http\Controllers\Admin\UserActionController`**:
  - Adicionar metodo `restore(Request $request, int $id)` que busca a conta com `withTrashed()`, autoriza e restaura com auditoria (`AdminActionType::UserRestored` ou equivalente).

---

## 4. Sugestoes de Novas Funcionalidades para o Dashboard Admin

1. **Gestao Visual de Agendamentos e Filas (Task Scheduler - ADR-035)**:
   - Interface visual em `/admin/schedules` para inspecionar tarefas agendadas, monitorar a rotina diaria de expurgo definitivo (`accounts:purge-deleted`), acompanhar tempos de execucao e disparar comandos sob demanda ("Run Now").
2. **Metricas Financeiras e Churn (MRR & Health Score)**:
   - Card de MRR (Receita Mensal Recorrente) e projecao de renovacoes dos proximos 15 e 30 dias.
   - Indicador de conversao de intencoes de compra (intencoes criadas vs intencoes atendidas).
3. **Painel de Monitoramento de Cota de IA e Consumo de Tokens (F5/F6)**:
   - Grafico de calor de consumo do `usage_ledger`, identificando usuarios com cota prestes a esgotar e anomalias de requisicoes com erro.
4. **Central de Comunicados Globais (Broadcast / Maintenance Banner)**:
   - Capacidade de ativar uma faixa de comunicacao no topo do site para avisos de manutencao ou lancamento de novas ferramentas diretamente pelo painel.
5. **Auditoria Visual com Destaque de Risco**:
   - Destaque automatico na tela de auditoria (`/admin/audit`) para acoes criticas (como estornos de pagamentos, exclusoes de contas e alteracoes manuais de cota).

---

## 5. Criterios de Aceite e Fases de Implementacao

### Fase 1: Backend & Operacao
- Adicionar escopos e queries de assinaturas ativas e contas excluidas (soft-deleted e anonimizadas).
- Adicionar endpoint de restauracao de conta (`POST /admin/users/{id}/restore`) com auditoria.
- Adicionar redirecionamento de admin no `OverviewController`.
- Criar `SubscriptionController` para agregar os dados das 4 abas.

### Fase 2: Frontend (Inertia + Vue 3 + Tailwind 4)
- Criar `resources/js/pages/admin/Subscriptions.vue` com abas (Vigentes, Solicitacoes, Pagamentos, Contas Excluidas).
- Atualizar menu lateral do `AdminLayout.vue`.
- Adicionar traducoes completas nos 5 idiomas (`pt_BR`, `en`, `es`, `fr`, `pt_PT`).

### Fase 3: Testes e Validacao
- Testes de Feature Pest para permissao, redirecionamento, restauracao e listagem.
- Validacao completa com `composer test`, `npm run test` e `npm run types:check`.
