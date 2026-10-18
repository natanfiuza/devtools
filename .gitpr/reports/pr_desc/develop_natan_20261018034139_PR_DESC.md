# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: adiciona 2FA, foto de perfil e exclusão com carência de 30 dias
```

---

## 🎯 Resumo

Redesenha o ciclo de vida da conta e fortalece a segurança do acesso de usuários:

- **Exclusão de conta deixa de ser imediata e irreversível.** Passa a aplicar _soft delete_ com janela de carência de 30 dias, permitindo que o usuário recupere a conta apenas fazendo login dentro do prazo. Após a janela, um comando agendado expurga definitivamente os dados pessoais, preservando registros fiscais (pagamentos/assinaturas) — substituindo a anonimização síncrona do ADR-028.
- **Autenticação em dois fatores (TOTP) nativa via Fortify**, com confirmação obrigatória, chave manual, códigos de recuperação e página dedicada de desafio no login.
- **Foto de perfil** com upload, pré-visualização, remoção e serviço público por UUID, sem expor o diretório de storage.
- **Agendador de tarefas persistido em banco**, permitindo cadastrar comandos Artisan (como o expurgo de contas) sem alterar código, executados via fila para não bloquear o scheduler.

## 🛠️ Mudanças Técnicas

- `suspended_at`/`deleted_at` e fluxo de soft delete em `User`, com reativação automática em `AuthenticateUser` (busca com `withTrashed()` e restauração dentro de 30 dias).
- Nova `accounts:purge-deleted {--days=30}` que reutiliza `AnonymizeUser`, agora também limpando `photo_uuid`, arquivo em disco e segredos de 2FA.
- `ProfilePhotoController` com `show`, `update` e `destroy`, validação `image|mimes:jpeg,png,jpg,webp|max:2048` e armazenamento em `storage/app/data/user_profile_image`.
- Componentes Vue `ProfilePhoto.vue` e `TwoFactorAuthentication.vue`, página `auth/TwoFactorChallenge.vue` e integração nas telas de Perfil e Segurança.
- `App\Models\TaskSchedule`, `App\Jobs\ExecuteCommand` e `withSchedule()` em `bootstrap/app.php` lendo agendamentos ativos do banco e disparando o job na fila.
- Novas colunas/migrations: `photo_uuid` (uuid único), colunas de 2FA (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`), `softDeletes()` em `users` e tabela `task_schedules` (com seed do expurgo diário às 03:00).
- Fortify com `Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])` e `twoFactorChallengeView`.
- Novo logo e wordmark (`AppLogo.vue`, `AppLogoIcon.vue`, `AppWordmark.vue`).
- i18n completo em `en`, `es`, `fr`, `pt_BR` e `pt_PT` para exclusão com carência, foto e 2FA.
- Testes novos/atualizados: `ProfilePhotoTest`, `TaskScheduleTest` e reescrita de `ProfileUpdateTest` cobrindo soft delete, reativação por login e expurgo após 30 dias.

## ⚠️ Impacto/Avisos

- **Banco de dados:** quatro novas migrations (`add_photo_uuid_to_users_table`, `add_two_factor_columns_to_users_table`, `add_soft_deletes_to_users_table`, `create_task_schedules_table`). A migration de `task_schedules` insere um registro padrão (`accounts:purge-deleted --days=30`); revise o horário antes de subir em produção.
- **Fila obrigatória:** o agendador enfileira `ExecuteCommand`, portanto requer `queue:work` ativo (Horizon/worker). Sem worker, nenhuma tarefa agendada é executada.
- **Cron do scheduler:** é necessário manter `php artisan schedule:run` configurado no servidor.
- **Regressão funcional esperada:** a exclusão não anonimiza mais imediatamente. Dados pessoais permanecem no banco por até 30 dias — valide com jurídico/LGPD e ajuste a política de privacidade.
- **Sessões abertas não são mais derrubadas na exclusão imediata**; a revogação ocorre apenas no expurgo. Avalie se é aceitável.
- **Foto de perfil é pública:** qualquer pessoa com o UUID acessa a imagem (`GET /users/profile/photo/{uuid}`), sem autenticação. O UUID funciona como _capability_, mas não é controle de acesso.
- **Sem variáveis de ambiente novas**, mas o diretório `storage/app/data/user_profile_image` precisa existir/ter permissão de escrita (criado automaticamente pelo controller).
- **Documentação:** ADR-028 e ADR-035 foram ajustados; glossário e planos em `docs/gemini` atualizados no mesmo conjunto de mudanças.

close #31

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_21_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)
