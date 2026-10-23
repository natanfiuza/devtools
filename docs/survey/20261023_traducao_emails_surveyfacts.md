# Survey — Traducao e Locale de E-mails Transacionais (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "O email '[devtools] New subscription intent' esta todo em ingles, os e-mails devem ser traduzidos de acordo com o idioma do usuario mesmo os usuarios admin. O e-mail de intenção e inscrição de assinatura (assunto vem '[devtools] New subscription intent' ) esta vindo todo em ingles traduza. Verifique todos os outros e-mails e traduza todos"
- **Tela / Modulo:** Notificacoes e Mailables (`app/Notifications/`, `app/Mail/`, `app/Http/Controllers/SubscriptionIntentController.php`, `lang/*/mail.php`)
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** Inspecao profunda de todas as classes de notificacao, fluxos de despacho, modelos e arquivos de traducao nos 5 idiomas (`en`, `es`, `fr`, `pt_BR`, `pt_PT`).
- **Arquivos-chave lidos:**
  - `app/Notifications/SubscriptionIntentSubmitted.php`
  - `app/Http/Controllers/SubscriptionIntentController.php`
  - `app/Models/User.php`
  - `app/Support/Locales.php`
  - `lang/{en,es,fr,pt_BR,pt_PT}/mail.php`
  - `tests/Feature/PricingTest.php`

## 2. Decisoes das rodadas

### Rodada 1 — Definicao do Locale e Auditoria
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Locale do e-mail de intencao administrativa (`SubscriptionIntentSubmitted`) | **Opcao B (com fallback para o usuario requerente)**: se o e-mail de contato administrativo pertencer a um admin cadastrado no sistema, respeita seu `preferredLocale()`; senao, adota o `preferredLocale()` do usuario que submeteu a intencao. Garante chamada explicita a `->locale(...)`. |
| Q2 | Escopo de verificacao e blindagem de todos os outros e-mails | **Opcao A**: Auditar integralmente todas as chaves em `lang/*/mail.php` nos 5 idiomas, e blindar o despacho de todas as notificacoes que entram em fila para garantir a atribuicao explicita do locale correto em jobs de queue assincronos. |

### Defaults assumidos (nao contestados)
- As 5 linguas suportadas pelo projeto sao `en`, `es`, `fr`, `pt_BR` e `pt_PT` (definidas em `config/locales.php`).
- Notificacoes enviadas a instancias de `User` herdam a interface `HasLocalePreference`, mas quando disparadas ou enfileiradas pelo framework podem sofrer fallback para o locale default do worker caso nao explicitadas via `->locale($user->preferredLocale())`.
- Nao ha necessidade de novo ADR, pois a arquitetura existente ja preve `HasLocalePreference` e `lang/{locale}/mail.php` (ADR-026 e ADR-033); trata-se de correcao de desvio de locale no despacho de notificacao sob demanda (AnonymousNotifiable).

## 3. Relatorio de fatos levantados
### Fato 1: Causa-raiz do e-mail "[devtools] New subscription intent"
Em `SubscriptionIntentController.php`:
```php
$contactEmail = Legal::contactEmail();
if ($contactEmail !== null) {
    Notification::route('mail', $contactEmail)
        ->notify(new SubscriptionIntentSubmitted($intent));
}
```
O método `Notification::route('mail', ...)` instancia uma rota sob demanda (`AnonymousNotifiable`), que nao implementa `HasLocalePreference`. Como a notificacao implementa `ShouldQueue` e nao recebia `->locale(...)`, a execucao na fila rodava com o locale padrao da aplicacao (`config('app.locale') = 'en'`).

### Fato 2: Situacao dos arquivos de traducao `lang/*/mail.php`
Os arquivos de idioma `lang/{en,es,fr,pt_BR,pt_PT}/mail.php` ja possuiam as chaves traduzidas para `intent`, `reset`, `verify`, `newsletter`, `payment.confirmed`, `payment.refunded`, `payment.renewal_failed`, `donation.receipt` e `account_purged`. O problema que fazia o e-mail chegar em ingles nao era a falta da traducao no arquivo `mail.php`, mas sim o fato do Laravel selecionar o locale padrao `en` durante o processamento da fila.

### Fato 3: Resolucao de locale para administradores e usuarios
Se o destinatario for o e-mail de contato do site (`Legal::contactEmail()`), o sistema deve verificar se existe um usuario correspondente a este e-mail no banco de dados com a role `admin`. Se existir e tiver locale definido, usa `$admin->preferredLocale()`. Caso contrario, adota o locale da conta do usuario que solicitou a assinatura (`$intent->user->preferredLocale()`).
