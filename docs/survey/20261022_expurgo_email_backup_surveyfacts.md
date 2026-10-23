# Survey — Envio de E-mail com Backup JSON no Expurgo de Conta (2026-10-22)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Ao acabar a carencia dos 30 dias de uma conta excluida antes de anonimizar a conta envie por e-mail para o usuario avisando que sua conta foi excluida o arquivo json com os dados exportados.
- **Tela / Modulo:** `app/Console/Commands/PurgeDeletedUsers.php`, `App\Notifications\AccountPurged` (ou `AccountPurgedNotification`), `App\Actions\Account\ExportUserData`, `App\Actions\Account\AnonymizeUser`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada de refinamento cobrindo estrategia de tolerancia a falha, formato do payload/anexo e internacionalizacao de envio.
- **Arquivos-chave lidos:**
  - `app/Console/Commands/PurgeDeletedUsers.php`
  - `app/Actions/Account/AnonymizeUser.php`
  - `app/Actions/Account\ExportUserData.php`
  - `docs/adr/ADR-035-soft-delete-e-agendamento-assincrono.md`
  - `docs/adr/ADR-028-anonimizacao-e-exportacao.md`
  - `app/Notifications/PaymentConfirmed.php`
  - `lang/pt_BR/mail.php`

## 2. Decisoes das rodadas

### Rodada 1 — Estrategia de Disparo, Formato de Anexo e Localizacao
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Tolerancia a falhas e sequencia do expurgo | O e-mail e despachado antes da anonimizacao. Se a geracao da exportacao ou o envio do e-mail falharem, a conta **nao** e anonimizada nessa rodada, sendo logada para reprocessamento posterior. |
| Q2 | Formato e manipulacao do anexo JSON | O arquivo JSON sera anexado em memoria (`attachData`) na notificacao/mailable com codificacao JSON formatada (`JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`) e nome descritivo (`export-account-{id}-{data}.json`). |
| Q3 | Idioma e destinatario do e-mail | O envio usa o e-mail cadastrado antes da anonimizacao (`$user->email`) e o locale salvo no perfil do usuario (`$user->locale`), com fallback para o locale padrao da aplicacao, e traducoes completas nos 5 idiomas (`pt_BR`, `pt_PT`, `en`, `es`, `fr`). |

### Defaults assumidos (nao contestados)
- Utilizacao do canal de notificacoes padrao do Laravel (`App\Notifications\AccountPurgedNotification` via `toMail` / `attachData`) respeitando o ecossistema existente em `app/Notifications/`.
- Criacao de chaves nos 5 arquivos de idioma em `lang/{locale}/mail.php` (`account_purged.subject`, `account_purged.greeting`, `account_purged.intro`, `account_purged.subcopy`).
- Gravacao de log de aviso/sucesso ou falha estruturado no console output e logs da aplicacao.
- Atualizacao retrocompativel sem alteracoes no schema do banco de dados (reutilizando tabelas e colunas existentes).

## 3. Relatorio de fatos levantados
### Fato 1: Ponto de Inflexao Irreversivel na Anonimizacao
`AnonymizeUser` substitui `$user->email` por `removido+{id}@removido.invalid` e remove os registros de `ai_requests` e contas sociais. Se o e-mail nao for enviado antes ou se for enfileirado de forma postergada lendo a entidade do banco de dados apos a anonimizacao, o e-mail sera entregue para o dominio falso da RFC 2606 ou perdera os dados vinculados. Portanto, a geracao dos dados exportados e a composicao/envio sincronizado do e-mail (ou a transmissao do payload ja preparado) precisam ocorrer antes da chamada a `$anonymize($user)`.

### Fato 2: Padrao de Notificacoes e Marca
As notificacoes transacionais no projeto (`PaymentConfirmed`, `DonationReceipt`, etc.) usam `Illuminate\Notifications\Messages\MailMessage` e `App\Support\MailBrand::line(...)`. A nova notificacao deve seguir exatamente a mesma identidade visual e convencoes de traducao dos 5 idiomas.
