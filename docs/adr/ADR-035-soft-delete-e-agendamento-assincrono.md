# ADR-035-soft-delete-e-agendamento-assincrono
Status: Aceito
Data: 2026-10-18

## Decisao
**A exclusao de conta pelo usuario passa a operar sob janela de carencia de 30 dias via Soft Delete (`users.deleted_at`), com reativacao permitida no login e expurgo definitivo assincrono via fila no padrao SIG-Novo.**

1. **Janela de Carencia de 30 Dias**:
   - Ao confirmar a exclusao em `/settings/profile`, a conta recebe `deleted_at = now()`, a sessao e encerrada e o usuario e redirecionado com aviso claro de que a conta sera eliminada definitivamente em 30 dias.
   - Durante os 30 dias, se o usuario realizar login com suas credenciais validas em `/login`, o sistema detecta que a conta esta em carencia (`$user->trashed() && $user->deleted_at->gt(now()->subDays(30))`), cancela a exclusao com `$user->restore()` e emite mensagem de boas-vindas com reativacao bem-sucedida.
2. **Expurgo Definitivo**:
   - Decorridos os 30 dias, o comando Artisan `accounts:purge-deleted` localiza as contas cujo `deleted_at <= now()->subDays(30)`.
   - Para cada conta elegivel:
     - Remove o arquivo fisico da foto de perfil em `storage/app/data/user_profile_image/{photo_uuid}`.
     - Executa a rotina `AnonymizeUser` (ADR-028), limpando dados pessoais identificaveis, desvinculando contas sociais e gravando `anonymized_at = now()`, preservando a integridade fiscal de `payments` (`restrictOnDelete`).
3. **Agendamento Assincrono Dinamico (Padrao SIG-Novo)**:
   - Os comandos agendados sao registrados na tabela `task_schedules`, permitindo controle de status (`is_active`), periodicidade (`expression`, como `dailyAt:03:00` ou cron) e parametros.
   - O agendador do framework em `bootstrap/app.php` le as tarefas ativas e despacha cada execucao para a fila de background por meio do job assincrono `App\Jobs\ExecuteCommand` (com identificador `jobUuid`, timeout controlado e logs de duracao e status).
   - A rotina `accounts:purge-deleted` e cadastrada e ativada por padrao para execucao diaria as 03:00.

## Por que
Permite aos usuarios recuperarem contas excluidas por engano ou impulso dentro de um prazo razoavel de 30 dias, atendendo a uma demanda comum de experiencia do usuario, ao mesmo tempo em que garante conformidade com a LGPD e legislacao fiscal atraves da anonimizacao e expurgo de arquivos fisicos.
A execucao via fila com `ExecuteCommand` desacopla processos pesados de manutencao do ciclo de requisicao HTTP e do cron do servidor, espelhando o padrao consolidado no projeto SIG-Novo.

## Consequencias
- Contas em soft delete nao aparecem em listagens comuns de usuarios (exceto via `withTrashed()` ou `onlyTrashed()`).
- O expurgo fisico de fotos e anonimizacao fiscal continuam compativeis com a restricao `restrictOnDelete` de `payments.user_id`.
- A arquitetura de `task_schedules` deixa a base 100% preparada para a futura interface de gerenciamento no Dashboard de Administracao (`/admin/schedules`).
