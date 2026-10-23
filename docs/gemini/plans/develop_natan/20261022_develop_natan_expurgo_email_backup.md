# Plano de Implementacao: Notificacao por E-mail com Backup JSON no Expurgo de Conta

## Contexto e Objetivo
Conforme deliberado no `/grill-with-docs` (ver `docs/survey/20261022_expurgo_email_backup_surveyfacts.md`), ao atingir os 30 dias de carencia de uma conta em soft delete, antes de anonimizar os dados fiscais e pessoais em definitivo, o sistema deve exportar os dados do usuario em formato JSON legivel e envia-lo como anexo em um e-mail de aviso de exclusao definitiva da conta.

---

## 1. Componentes Envolvidos

1. **Notificacao / Mailable**:
   - `app/Notifications/AccountPurgedNotification.php`:
     - Recebe o array com os dados exportados gerados por `ExportUserData` e o nome do arquivo.
     - Monta o `MailMessage` com o assunto, mensagem de despedida/notificacao informando a exclusao definitiva da conta.
     - Anexa em memoria via `attachData($jsonContent, $fileName, ['mime' => 'application/json'])`.
     - Respeita o idioma preferido do usuario (`$notifiable->locale`).

2. **Textos de Internacionalizacao (i18n)**:
   - `lang/pt_BR/mail.php`
   - `lang/pt_PT/mail.php`
   - `lang/en/mail.php`
   - `lang/es/mail.php`
   - `lang/fr/mail.php`
   - Chave `account_purged`:
     - `subject`
     - `greeting`
     - `intro`
     - `attached_notice`

3. **Comando de Expurgo**:
   - `app/Console/Commands/PurgeDeletedUsers.php`:
     - Injeta `App\Actions\Account\ExportUserData`.
     - Para cada usuario em soft delete `>= 30 dias`:
       - Gera os dados de backup usando `$exportUserData($user)`.
       - Codifica o JSON com `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
       - Envia o e-mail via `$user->notify(new AccountPurgedNotification($jsonString, $fileName))`.
       - Em caso de sucesso no envio, executa `$anonymize($user)` e contabiliza como purgado.
       - Em caso de excecao no envio do e-mail, loga o erro, avisa no console e preserva a conta sem anonimizar para que a tentativa seja refeita no ciclo seguinte (respeitando a decisao Q1).

4. **Testes Automatizados (Pest)**:
   - `tests/Feature/Console/PurgeDeletedUsersTest.php`:
     - Testa o fluxo completo: usuario em soft delete ha mais de 30 dias recebe a notificacao por e-mail com o anexo JSON antes de ser anonimizado.
     - Valida que se o disparo de notificacao falhar, a conta nao e anonimizada.
     - Valida que contas com menos de 30 dias nao sao notificadas nem expurgadas.

---

## 2. Passo a Passo de Execucao

### Fase 1: i18n
Adicionar as mensagens nos 5 idiomas em `lang/{locale}/mail.php`.

### Fase 2: Criacao da Notificacao
Criar `app/Notifications/AccountPurgedNotification.php` estendendo `Illuminate\Notifications\Notification` e configurando `toMail` com `attachData`.

### Fase 3: Integracao em `PurgeDeletedUsers`
Atualizar o comando `accounts:purge-deleted` para orquestrar a exportacao dos dados, envio da notificacao e anonimizacao protegida por try/catch.

### Fase 4: Testes e Validacao
- Criar e rodar testes Pest em `tests/Feature/Console/PurgeDeletedUsersTest.php`.
- Executar `composer test` (Pint, PHPStan, Pest) e `npm run types:check`.

### Fase 5: Documentacao e Relatorio
Gerar o relatorio final em `docs/gemini/reports/develop_natan/20261022_develop_natan_expurgo_email_backup.md`.
