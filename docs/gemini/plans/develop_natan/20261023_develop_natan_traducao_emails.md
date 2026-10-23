# Plano de Implementacao — Traducao e Locale de E-mails Transacionais

**Data:** 2026-10-23  
**Branch:** `develop_natan`  
**Objetivo:** Garantir que o e-mail de intencao de assinatura (`SubscriptionIntentSubmitted`) e todos os demais e-mails transacionais do sistema sejam enviados no idioma correto do usuario/admin destinatario, auditando as traducoes nos 5 idiomas (`en`, `pt_BR`, `pt_PT`, `es`, `fr`).

---

## 1. Contexto e Diagnostico
- O e-mail com assunto `[devtools] New subscription intent` e disparado via `Notification::route('mail', $contactEmail)->notify(new SubscriptionIntentSubmitted($intent))`.
- Como `Notification::route` cria um `AnonymousNotifiable`, ele nao possui `HasLocalePreference`.
- A notificacao implementa `ShouldQueue`. Na fila assincrona, o worker adota o locale padrao da aplicacao (`en`), ignorando o idioma do usuario ou do admin.
- As traducoes das chaves `mail.intent.*` ja estao devidamente implementadas nos 5 idiomas em `lang/{locale}/mail.php`.

---

## 2. Etapas de Modificacao

### Etapa 1: SubscriptionIntentController e SubscriptionIntentSubmitted
- Em `SubscriptionIntentController.php`:
  - Buscar se o `$contactEmail` pertence a um `User` administrador (`User::query()->where('email', $contactEmail)->first()`).
  - Determinar o locale: se o admin existir, usar `$admin->preferredLocale()`; senao, usar `$user->preferredLocale()`.
  - Passar explicitamente `->locale($targetLocale)` ao instanciar ou disparar `SubscriptionIntentSubmitted($intent)`.
- No proprio `SubscriptionIntentSubmitted.php`:
  - Garantir que se a notificacao for instanciada sem chamada externa de `locale()`, use fallback seguro baseado no `$intent->user->preferredLocale()`.

### Etapa 2: Blindagem do Locale em Notificacoes Enfileiradas
- Verificar chamadas de despacho em `app/Actions/Admin/RegisterPayment.php`, `app/Actions/Admin/RefundPayment.php`, `app/Actions/Payments/HandleRenewalFailure.php` para garantir que o `$account->notify(...)` mantenha explicitamente o `->locale($account->preferredLocale())` antes de entrar na fila.
- Validar se os e-mails de notificacao direta ao usuario respeitam sempre o `preferredLocale()`.

### Etapa 3: Auditoria dos Arquivos de Idioma (`lang/*/mail.php`)
- Auditar integralmente todas as chaves em `lang/en/mail.php`, `lang/pt_BR/mail.php`, `lang/pt_PT/mail.php`, `lang/es/mail.php` e `lang/fr/mail.php`.
- Garantir coerencia, ortografia e pontuacao em todos os 5 idiomas.

### Etapa 4: Testes Automatizados
- Adicionar/ajustar testes em `tests/Feature/PricingTest.php` e `tests/Feature/MailTest.php`:
  - Testar o envio de `SubscriptionIntentSubmitted` quando o usuario solicitante tem locale `pt_BR`, verificando se a notificacao recebe e renderiza o assunto e corpo em portugues.
  - Testar quando o e-mail de contato administrativo pertence a um admin com locale especifico (ex: `es` ou `fr`).
  - Executar a suite completa: `composer test`, `npm run test`, `npm run types:check`.

---

## 3. Criterios de Aceite
- E-mail `SubscriptionIntentSubmitted` e gerado no idioma do admin correspondente ou do usuario requerente.
- Todos os testes de notificacoes e pagamentos passam com 100% de sucesso.
- Codigo segue o padrao rigoroso do projeto (Pint, PHPStan level max/project, Pest).
