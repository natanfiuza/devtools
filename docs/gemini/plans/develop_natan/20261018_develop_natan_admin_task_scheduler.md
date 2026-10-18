# Plano - Painel Administrativo de Gestao e Agendamento de Tarefas (Task Scheduler)

Branch: `develop_natan`.
Data: 2026-10-18.
Objetivo: Planejamento detalhado para implementacao futura da tela de gerenciamento de agendamentos no painel administrativo (`/admin/tasks` ou `/admin/schedules`), espelhando a flexibilidade do padrao adotado no SIG-Novo.

---

## 1. Visao Geral da Funcionalidade

A infraestrutura base foi construida na Fase de Infraestrutura com:
- Tabela `task_schedules`
- Job assincrono `App\Jobs\ExecuteCommand`
- Scheduler dinamico no `bootstrap/app.php`

O modulo de gestao administrativa permitira aos administradores do sistema:
1. **Listar Tarefas Agendadas**:
   - Nome amigavel, assinatura do comando Artisan, parametros configurados.
   - Periodicidade amigavel (ex.: "Diariamente as 03:00" ou expressao cron).
   - Status (Ativo / Inativo).
   - Data e hora da ultima execucao (`last_run_at`) e status (sucesso / falha / em andamento).
2. **Criar e Editar Tarefas**:
   - Selecionar comando de uma lista branca de comandos permitidos (ex: `accounts:purge-deleted`, `aicache:clear`, `newsletter:send-weekly`, etc.).
   - Configurar periodicidade visual: Minutos, Horas, Diariamente com seletor de horario, Semanalmente, ou expressao cron customizada.
   - Definir parametros opcionais em formato JSON com validacao de schema.
3. **Disparo Manual Imediato (Run Now)**:
   - Botao "Executar agora" que despacha imediatamente `ExecuteCommand::dispatch($task->command, $task->parameters)` para a fila sem aguardar o horario agendado.
4. **Historico e Monitoramento de Execucao**:
   - Registro de execucoes em tabela de logs (`task_schedule_logs`), registrando:
     - `task_schedule_id`
     - `job_uuid`
     - `started_at`, `finished_at`, `duration_seconds`
     - `status` (`success`, `failed`, `running`)
     - `output` / `exception_message` (sem logar dados sensiveis ou schemas/prompts)

---

## 2. Modelagem de Dados Adicional

### 2.1. Tabela `task_schedule_logs`
```sql
CREATE TABLE task_schedule_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_schedule_id BIGINT UNSIGNED NOT NULL,
    job_uuid VARCHAR(36) NOT NULL,
    status ENUM('running', 'success', 'failed') DEFAULT 'running',
    started_at TIMESTAMP NOT NULL,
    finished_at TIMESTAMP NULL,
    duration_seconds DECIMAL(8,2) NULL,
    output TEXT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (task_schedule_id) REFERENCES task_schedules(id) ON DELETE CASCADE
);
```

---

## 3. Telas e Componentes do Frontend (Inertia + Vue 3 + shadcn-vue)

### 3.1. Rota e Pagina Principal (`/admin/schedules`)
- **Arquivo**: `resources/js/pages/admin/schedules/Index.vue`
- **Layout**: `resources/js/layouts/admin/AdminLayout.vue`
- **Componentes**:
  - Tabela com colunas: Nome, Comando, Frequencia, Status (com `Switch` para toggle imediato), Ultima Execucao, Acoes.
  - Dropdown de acoes: "Executar Agora", "Editar", "Historico", "Excluir".
  - Botao superior: "Novo Agendamento" (abre modal/drawer).

### 3.2. Modal de Criacao e Edicao (`ScheduleFormModal.vue`)
- Formulario com campos:
  - Nome amigavel.
  - Comando (Select com lista de comandos Artisan cadastrados e documentados).
  - Tipo de Frequencia:
    - Intervalo de Minutos (ex: a cada 5, 10, 15, 30 minutos).
    - Horario Fixo Diario (seletor de hora:minuto).
    - Dias Especificos da Semana.
    - Customizado (Cron).
  - Parametros adicionais (chave/valor ou editor de argumentos).
  - Status inicial (Ativo / Inativo).

### 3.3. Modal de Historico de Execucoes (`ScheduleHistoryModal.vue`)
- Tabela com as ultimas 50 execucoes da tarefa.
- Indicador visual de status (verde para sucesso, vermelho para falha com tooltip do erro, azul para em processamento).
- Duracao em segundos/minutos.

---

## 4. Endpoints e Controllers Backend

```php
Route::middleware(['auth', 'active', 'can:access-admin'])->prefix('admin')->group(function () {
    Route::get('schedules', [AdminScheduleController::class, 'index'])->name('admin.schedules.index');
    Route::post('schedules', [AdminScheduleController::class, 'store'])->name('admin.schedules.store');
    Route::put('schedules/{schedule}', [AdminScheduleController::class, 'update'])->name('admin.schedules.update');
    Route::patch('schedules/{schedule}/toggle', [AdminScheduleController::class, 'toggle'])->name('admin.schedules.toggle');
    Route::delete('schedules/{schedule}', [AdminScheduleController::class, 'destroy'])->name('admin.schedules.destroy');
    Route::post('schedules/{schedule}/run', [AdminScheduleController::class, 'runNow'])->name('admin.schedules.run');
    Route::get('schedules/{schedule}/logs', [AdminScheduleController::class, 'logs'])->name('admin.schedules.logs');
});
```

---

## 5. Auditoria Administrativa (`admin_actions`)

Conforme a convencao do projeto (ADR-025 e `GEMINI.md`):
- Toda criacao, edicao, exclusao, disparo manual ou alteracao de status de tarefa agendada devera registrar uma linha em `admin_actions`.
- Nunca registrar em log ou em `admin_actions` o conteudo de schemas ou prompts, registrando apenas metadados seguros (id da tarefa, comando disparado, status anterior e status novo).

---

## 6. Validacao e Testes do Modulo Futuro
- Testes de Feature com Pest cobrindo:
  - Permissao exclusiva de administrador (`access-admin`).
  - Criacao e atualizacao de agendamento com validacao de expressao cron.
  - Disparo manual enfileirando o job `ExecuteCommand`.
  - Toggle de status ativo/inativo.
  - Gravacao de auditoria em `admin_actions`.
