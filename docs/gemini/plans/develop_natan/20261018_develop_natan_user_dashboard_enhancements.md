# Plano - Melhorias no Dashboard do Usuario, Foto de Perfil, 2FA, Soft Delete e Agendador Assincrono

Sessao de planejamento e alinhamento arquitetural (`/plan` e `/grill-with-docs`).
Branch: `develop_natan`.
Data: 2026-10-18.
Decisoes confirmadas via rodada interativa de perguntas com o usuario.
Referencia de agendamento e execucao de jobs: `C:\Users\nataniel\projetos\gjb\SIG-Novo`.

---

## 1. Contexto e Objetivos

O usuario solicitou um conjunto de melhorias visuais, de seguranca, de gestao de perfil e de infraestrutura de tarefas no Dashboard do Usuario:

1. **Favicon no Dashboard**: Exibir o icone vetorial `public/favicon.svg` no canto superior esquerdo da area autenticada.
2. **Wordmark `devtools_`**: Alterar a exibicao textual do nome do app no topo da sidebar de `devtools` para `devtools_` (com o sublinhado na cor `--brand`).
3. **Foto de Perfil**:
   - Adicionar opcao de envio e remocao de foto na tela de Perfil (`settings/Profile`).
   - Fotos salvas em `storage/app/data/user_profile_image/` com o nome de arquivo sendo exclusivamente um UUID.
   - Coluna `photo_uuid` adicionada na tabela `users`.
   - Rota publica sem prefixo de idioma para servir a imagem: `GET /users/profile/photo/{uuid}`.
   - Integracao com o componente de avatar existente (`UserInfo.vue`, menus e sidebar).
4. **Autenticacao em Dois Fatores (2FA / TOTP)**:
   - Ativar 2FA usando o Laravel Fortify com aplicativo gerador de tokens (Google Authenticator, Authy, etc.).
   - Migracao com colunas do Fortify (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`).
   - Gerenciamento na tela de Seguranca (`settings/Security`): geracao de QR Code SVG, chave manual, confirmacao de codigo de 6 digitos, codigos de recuperacao e desativacao.
   - Tela de desafio no login: `auth/TwoFactorChallenge` sob `/{locale}/two-factor-challenge`.
5. **Soft Delete de Conta com Janela de 30 Dias e Expurgo Assincrono**:
   - Ativar soft delete na tabela `users` (`deleted_at`) e no model `User`.
   - Atualizar a interface de exclusao em `DeleteUser.vue` e traducoes nos 5 idiomas para informar a carencia de 30 dias.
   - Ao tentar efetuar login dentro dos 30 dias, permitir a reativacao da conta (decisao Q1).
   - Comando Artisan `accounts:purge-deleted` para expurgar contas que ultrapassaram os 30 dias, executando a anonimizacao fiscal (ADR-028) e removendo o arquivo fisico da foto (decisao Q2).
   - Implementar o padrao arquitetural do SIG-Novo (decisao Q3):
     - Job assincrono `App\Jobs\ExecuteCommand` na fila.
     - Tabela e model `task_schedules` com migracao retrocompativel.
     - Scheduler dinamico no `bootstrap/app.php` lendo tarefas ativas e despachando `ExecuteCommand`.
     - Seed com a tarefa `accounts:purge-deleted` agendada diariamente.
     - Plano dedicado para futura gestao dessas rotinas no Dashboard Administrativo.

---

## 2. Decisoes Alinhadas no Grill (`/grill-with-docs`)

| # | Pergunta | Decisao Selecionada |
|---|---|---|
| **Q1** | Ao solicitar a exclusao da conta (soft delete), o que deve acontecer se o usuario tentar logar durante a janela de 30 dias? | **Reativacao permitida**: O sistema detecta a conta em soft delete dentro da janela de 30 dias, informa a carencia e reativa a conta com sucesso. |
| **Q2** | Como deve ser feita a eliminacao definitiva no expurgo de 30 dias (considerando que `payments.user_id` possui `restrictOnDelete` no banco por exigencia fiscal da ADR-028)? | **Anonimizacao (ADR-028) + Exclusao da foto fisica**: Limpa dados pessoais identificaveis, desassocia contas sociais, remove fisicamente a foto de perfil em `storage/app/data/user_profile_image/` e preserva os registros contabeis/fiscais desvinculados. |
| **Q3** | Qual abordagem adotar para o agendamento da rotina via fila no padrao do SIG-Novo? | **Padrao completo do SIG-Novo**: Criar a migracao da tabela `task_schedules`, o Job `ExecuteCommand`, a leitura no `bootstrap/app.php` com o comando de expurgo semeado, e elaborar plano dedicado para o futuro Dashboard de Admin. |

---

## 3. Arquitetura Detalhada e Mudancas por Componente

### 3.1. Marca e Identidade Visual (Favicon e Wordmark)
- **`resources/js/components/AppLogo.vue`**:
  - Substitui o icone geometrico padrao pelo SVG do favicon (`public/favicon.svg`), renderizando com dimensoes adequadas (`size-8`).
  - Substitui o texto puro `{{ name }}` pelo componente `AppWordmark` (`devtools_` com o cursor `_` estilizado com `text-brand`).
- **`resources/js/components/AppFavicon.vue`**:
  - Componente dedicado para renderizar o vetor do favicon com suporte a classes Tailwind.

### 3.2. Foto de Perfil do Usuario
- **Banco de Dados**:
  - Migracao `database/migrations/2026_10_18_000001_add_photo_uuid_to_users_table.php` adicionando `photo_uuid` (string, nullable, unique) apos `avatar` / `email`.
- **Model `App\Models\User`**:
  - `photo_uuid` adicionado em `#[Fillable]`.
  - Accessor `avatar`: retorna `route('users.profile.photo', ['uuid' => $this->photo_uuid])` quando `photo_uuid` estiver preenchido, ou `null`.
  - Inclusao de `'avatar'` no `$appends` do modelo para serializacao automatica nas props de autenticacao do Inertia.
- **Armazenamento Fisico**:
  - Diretorio dedicado: `storage/app/data/user_profile_image/` (criado automaticamente caso nao exista).
  - O arquivo e salvo com o nome exato do UUID gerado (`$uuid`), garantindo conformidade estrita com o requisito.
- **Controlador e Rotas**:
  - Rota publica sem prefixo de idioma: `GET /users/profile/photo/{uuid}` apontando para `App\Http\Controllers\ProfilePhotoController@show`.
    - Valida o formato UUID.
    - Localiza o arquivo em `storage/app/data/user_profile_image/{uuid}`.
    - Retorna `response()->file($path, ['Content-Type' => mime_content_type($path), 'Cache-Control' => 'public, max-age=86400'])`.
  - Rotas autenticadas em `routes/settings.php`:
    - `POST /settings/profile/photo` (`ProfilePhotoController@update`): valida a imagem (`image|mimes:jpeg,png,webp,jpg|max:2048`), gera UUID, remove a foto anterior do disco se existir, salva a nova e atualiza o usuario.
    - `DELETE /settings/profile/photo` (`ProfilePhotoController@destroy`): remove o arquivo do disco e anula `photo_uuid`.
- **Frontend (`resources/js/pages/settings/Profile.vue`)**:
  - Secao visual de foto de perfil com preview, avatar atual / iniciais via `useInitials`, input de upload de arquivo estilizado com botao "Enviar foto", botao "Remover foto" e feedback de erro de validacao.

### 3.3. Autenticacao em Dois Fatores (2FA / TOTP)
- **Configuracao do Fortify (`config/fortify.php`)**:
  - Habilitar `Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])`.
  - Configurar caminho de login: `'two-factor' => ['login' => '/{locale}/two-factor-challenge']`.
- **Banco de Dados**:
  - Migracao `database/migrations/2026_10_18_000002_add_two_factor_columns_to_users_table.php` adicionando:
    - `two_factor_secret` (text, nullable)
    - `two_factor_recovery_codes` (text, nullable)
    - `two_factor_confirmed_at` (timestamp, nullable)
- **Model `App\Models\User`**:
  - Uso da trait `Laravel\Fortify\TwoFactorAuthenticatable`.
  - `two_factor_secret` e `two_factor_recovery_codes` adicionados em `#[Hidden]`.
  - Helper/prop `two_factor_enabled` exposto para o frontend saber o estado do 2FA.
- **`FortifyServiceProvider.php`**:
  - Registrar view de desafio: `Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'))`.
- **Frontend de Seguranca (`resources/js/pages/settings/Security.vue`)**:
  - Secao "Autenticacao em dois fatores":
    - Badge de status (Ativo / Inativo).
    - Fluxo de ativacao: chamada `POST /user/two-factor-authentication`, requisicao do SVG do QR Code via `GET /user/two-factor-qr-code` e chave de configuracao manual via `GET /user/two-factor-secret-key`.
    - Input para digitacao do token TOTP de 6 digitos e confirmacao via `POST /user/confirmed-two-factor-authentication`.
    - Estado confirmado: visualizacao e regeneracao de codigos de recuperacao em modal seguro, e botao de desativacao (`DELETE /user/two-factor-authentication`).
- **Frontend de Desafio no Login (`resources/js/pages/auth/TwoFactorChallenge.vue`)**:
  - Tela sob `/{locale}/two-factor-challenge` com formulario para codigo TOTP de 6 digitos e opcao de alternar para codigo de recuperacao.

### 3.4. Soft Delete, Janela de 30 Dias e Expurgo Assincrono
- **Banco de Dados**:
  - Migracao `database/migrations/2026_10_18_000003_add_soft_deletes_to_users_table.php` adicionando `$table->softDeletes()`.
- **Model `App\Models\User`**:
  - Adicao da trait `Illuminate\Database\Eloquent\SoftDeletes`.
  - Cast `'deleted_at' => 'datetime'`.
- **Fluxo de Exclusao e Reativacao**:
  - `ProfileController@destroy`:
    - Executa `$user->delete()`.
    - Encerra a sessao ativa (`Auth::logout()`, `$request->session()->invalidate()`).
    - Redireciona para a raiz com aviso de que a conta entrou em periodo de carencia de 30 dias.
  - `AuthenticateUser.php`:
    - Consulta `User::withTrashed()->where('email', $email)->first()`.
    - Se a conta estiver com `deleted_at` dentro da janela de 30 dias (`$user->trashed() && $user->deleted_at->gt(now()->subDays(30))`):
      - Restaura a conta com `$user->restore()`.
      - Permite o login normalmente emitindo flash/toast de reativacao.
- **Comando Artisan de Expurgo**:
  - `App\Console\Commands\PurgeDeletedUsers` (`accounts:purge-deleted {--days=30}`):
    - Localiza contas com `deleted_at <= now()->subDays($days)`.
    - Remove o arquivo fisico da foto de perfil em `storage/app/data/user_profile_image/{photo_uuid}` caso exista.
    - Executa `AnonymizeUser` para anonimizar os dados fiscais de forma irreversivel sem violar restricoes de integridade de `payments`.
- **Job Assincrono no Padrao SIG-Novo (`App\Jobs\ExecuteCommand`)**:
  - Classe `App\Jobs\ExecuteCommand` implementando `ShouldQueue`.
  - Propriedades de timeout, tries, identificacao de UUID e registro de atividade.
  - Executa comandos Artisan via `Artisan::call($this->commandName, $this->parameters)`.
- **Tabela de Agendamento Dinamico (`task_schedules`)**:
  - Migracao `database/migrations/2026_10_18_000004_create_task_schedules_table.php`:
    - `id`
    - `name` (string): descricao amigavel da tarefa (ex: "Expurgo de contas inativas").
    - `command` (string): assinatura do comando Artisan (ex: `accounts:purge-deleted`).
    - `parameters` (json, nullable): parametros adicionais do comando.
    - `expression` (string): periodicidade (ex: `dailyAt:03:00` ou cron `0 3 * * *`).
    - `is_active` (boolean, default true).
    - `last_run_at` (timestamp, nullable).
    - `timestamps()`.
  - Model `App\Models\TaskSchedule`.
  - Seed inicial cadastrando o comando `accounts:purge-deleted` como ativo diariamente as 03:00.
- **Integracao no Scheduler (`bootstrap/app.php`)**:
  - No metodo `withSchedule()`, busca tarefas com `is_active = true` envolvidas em bloco try/catch defensivo (como no SIG-Novo).
  - Agenda o despacho em fila: `ExecuteCommand::dispatch($task->command, $task->parameters)`.

---

## 4. Plano de Implementacao Passo a Passo

### Fase 1: Interface do Dashboard (Favicon e Wordmark)
1. Criar componente `AppFavicon.vue` contendo o vetor de `public/favicon.svg`.
2. Atualizar `resources/js/components/AppLogo.vue` para exibir o favicon e o componente `AppWordmark`.
3. Verificar a exibicao na barra lateral (`AppSidebar.vue`) nos modos expandido e recolhido.

### Fase 2: Foto de Perfil (Backend e Frontend)
1. Criar migracao para adicionar `photo_uuid` na tabela `users`.
2. Atualizar o model `User` (`#[Fillable]`, accessor e appends de `avatar`).
3. Criar `ProfilePhotoController` com os metodos `show`, `update` e `destroy`.
4. Registrar as rotas:
   - Publica: `users/profile/photo/{uuid}` em `routes/web.php`.
   - Autenticadas: `POST /settings/profile/photo` e `DELETE /settings/profile/photo` em `routes/settings.php`.
5. Atualizar `resources/js/pages/settings/Profile.vue` com os controles de envio e remocao de foto.
6. Adicionar strings de traducao nos 5 idiomas (`lang/{locale}/settings.php`).

### Fase 3: Autenticacao em Dois Fatores (2FA)
1. Criar migracao das colunas do 2FA na tabela `users`.
2. Adicionar a trait `TwoFactorAuthenticatable` no model `User`.
3. Atualizar `config/fortify.php` habilitando a feature e definindo a rota `two-factor.login`.
4. Atualizar `FortifyServiceProvider` registrando a view `auth/TwoFactorChallenge`.
5. Criar a tela de login `resources/js/pages/auth/TwoFactorChallenge.vue`.
6. Implementar a secao de 2FA em `resources/js/pages/settings/Security.vue`.
7. Adicionar strings de traducao de 2FA nos 5 idiomas (`lang/{locale}/auth.php` e `settings.php`).

### Fase 4: Soft Delete, Expurgo e Motor Assincrono SIG-Novo
1. Criar migracao adicionando `softDeletes` na tabela `users`.
2. Adicionar a trait `SoftDeletes` no model `User`.
3. Atualizar `ProfileController@destroy` para realizar o soft delete.
4. Atualizar `resources/js/components/DeleteUser.vue` e as mensagens em `lang/{locale}/account.php` (aviso de 30 dias).
5. Atualizar `AuthenticateUser` para tratar e permitir a reativacao de contas dentro dos 30 dias.
6. Criar o comando Artisan `App\Console\Commands\PurgeDeletedUsers`.
7. Criar o Job `App\Jobs\ExecuteCommand` inspirado no modelo do SIG-Novo.
8. Criar a migracao da tabela `task_schedules` e o model `TaskSchedule`, com seeder para a rotina de expurgo.
9. Configurar o agendamento dinamico em `bootstrap/app.php`.

### Fase 5: Plano do Dashboard Administrativo
1. Criar arquivo de plano detalhado para o futuro Dashboard de Admin:
   `docs/gemini/plans/develop_natan/20261018_develop_natan_admin_task_scheduler.md`.

### Fase 6: Validacao e Testes
1. Rodar suite de testes e formatacao: `composer test` (Pint, PHPStan, Pest).
2. Rodar suite de testes de frontend: `npm run test` (Vitest).
3. Validar tipagem estrita e rotas: `npm run types:check`.

---

## 5. Arquivos a Criar e Modificar

### Backend (PHP/Laravel)
| Arquivo | Acao | Descricao |
|---|---|---|
| `database/migrations/2026_10_18_000001_add_photo_uuid_to_users_table.php` | Novo | Coluna `photo_uuid` em `users` |
| `database/migrations/2026_10_18_000002_add_two_factor_columns_to_users_table.php` | Novo | Colunas `two_factor_*` em `users` |
| `database/migrations/2026_10_18_000003_add_soft_deletes_to_users_table.php` | Novo | Coluna `deleted_at` em `users` |
| `database/migrations/2026_10_18_000004_create_task_schedules_table.php` | Novo | Tabela para agendamento dinamico de tarefas |
| `app/Models/User.php` | Modificar | Traits `TwoFactorAuthenticatable` e `SoftDeletes`, accessor `avatar`, fillable |
| `app/Models/TaskSchedule.php` | Novo | Model Eloquent para a tabela `task_schedules` |
| `app/Http/Controllers/ProfilePhotoController.php` | Novo | Servir imagem publica, upload e remocao de foto |
| `app/Console/Commands/PurgeDeletedUsers.php` | Novo | Comando de expurgo de contas apos 30 dias |
| `app/Jobs/ExecuteCommand.php` | Novo | Job enfileiravel no padrao SIG-Novo para execucao assincrona de commands |
| `app/Actions/Fortify/AuthenticateUser.php` | Modificar | Suporte a reativacao de contas em soft delete |
| `app/Providers/FortifyServiceProvider.php` | Modificar | Registro da view `TwoFactorChallenge` |
| `config/fortify.php` | Modificar | Ativacao de `twoFactorAuthentication` e caminhos |
| `bootstrap/app.php` | Modificar | Scheduler dinamico lendo `task_schedules` e despachando `ExecuteCommand` |
| `routes/web.php` | Modificar | Rota publica `users/profile/photo/{uuid}` |
| `routes/settings.php` | Modificar | Rotas de upload e delecao de foto |

### Frontend (Vue/TypeScript)
| Arquivo | Acao | Descricao |
|---|---|---|
| `resources/js/components/AppFavicon.vue` | Novo | Renderiza o icone do favicon SVG |
| `resources/js/components/AppLogo.vue` | Modificar | Exibe `AppFavicon` e `AppWordmark` |
| `resources/js/pages/settings/Profile.vue` | Modificar | Secao de upload e gerenciamento de foto de perfil |
| `resources/js/pages/settings/Security.vue` | Modificar | Secao completa de 2FA (QR Code, confirmacao, codigos de recuperacao) |
| `resources/js/pages/auth/TwoFactorChallenge.vue` | Novo | Tela de desafio de dois fatores no login |
| `resources/js/components/DeleteUser.vue` | Modificar | Informar janela de exclusao em 30 dias |

### Traducoes (`lang/{locale}/*`)
- Atualizacao das chaves em `en`, `pt_BR`, `pt_PT`, `fr`, `es` para `settings.php`, `auth.php` e `account.php`.

### Documentacao e Planos
- `docs/gemini/plans/develop_natan/20261018_develop_natan_admin_task_scheduler.md` (Plano futuro do painel admin).
- Novo ADR se aplicavel (`docs/adr/ADR-035-soft-delete-e-agendamento-assincrono.md`).
