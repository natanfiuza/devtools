# Levantamento - Layout, Wordmark `devtools_` e Traducao dos E-mails Transacionais

Data: 2026-10-18
Branch: develop_natan
Pedido original:
1. No e-mail de confirmacao de cadastro no sistema, deixar o nome `devtools` -> `devtools_` (com o underline da cor `--brand`).
2. Deixar o e-mail de confirmacao na newsletter igual ao e-mail de confirmacao de cadastro, com a mesma alteracao no nome `devtools_`.
3. No e-mail de confirmacao de cadastro, traduzir a mensagem em ingles (`If you're having trouble clicking the "..." button, copy and paste the URL below into your web browser:`) de acordo com o idioma do usuario.
4. Adicionar tambem essa opcao do link alternativo (`subcopy`) no e-mail de confirmacao da newsletter.

Plano: `docs/gemini/plans/develop_natan/20261018_develop_natan_email_wordmark_newsletter_layout.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

Branch `develop_natan`. Stack Laravel 13 + Inertia 3 (SSR) + Vue 3 + TypeScript estrito, i18n em 5 idiomas (`en`, `pt_BR`, `pt_PT`, `fr`, `es`), Pest 4 + Vitest 5.

## 2. Fatos levantados por item

### 2.1. Como funciona o e-mail de confirmacao de cadastro (`VerifyEmail`)
- `app/Notifications/VerifyEmail.php` estende `Illuminate\Auth\Notifications\VerifyEmail` e implementa `ShouldQueue`.
- Monta um `MailMessage`:
  ```php
  return (new MailMessage)
      ->subject(__('mail.verify.subject'))
      ->line(__('mail.verify.intro', ['app' => config('app.name')]))
      ->action(__('mail.verify.action'), $url)
      ->line(__('mail.verify.ignore'));
  ```
- O `MailMessage` renderiza a view padrao do Laravel `vendor/laravel/framework/src/Illuminate/Notifications/resources/views/email.blade.php` dentro de `<x-mail::message>`, que inclui:
  - Header com `config('app.name')` (`devtools`);
  - Saudacao `# @lang('Hello!')` (ou `# @lang('Whoops!')` se erro);
  - Linhas de introducao (`$introLines`);
  - Botao de acao (`<x-mail::button>`);
  - Linhas finais (`$outroLines`);
  - Assinatura (`@lang('Regards,')<br>{{ config('app.name') }}`);
  - `<x-slot:subcopy>` com a mensagem `"If you're having trouble clicking the \":actionText\" button, copy and paste the URL below into your web browser:"` e o link clicavel `[{{ $displayableActionUrl }}]({{ $actionUrl }})`;
  - Rodape `© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}`.

### 2.2. Como funciona o e-mail de confirmacao na newsletter (`NewsletterConfirmation`)
- `app/Mail/NewsletterConfirmation.php` e um `Mailable implements ShouldQueue` que usa `markdown: 'mail.newsletter-confirmation'`.
- `resources/views/mail/newsletter-confirmation.blade.php` hoje **nao** usa `<x-mail::message>` nem a estrutura do `notifications::email`: ele escreve apenas texto solto e `@component('mail::button')`, sem header/layout completo de `MailMessage`, sem saudacao padrao, sem assinatura (`Regards,`) e sem `<x-slot:subcopy>` com a URL alternativa.
- Se `NewsletterConfirmation` renderizar a mesma estrutura de `MailMessage` (via `(new MailMessage)->line(...)->action(...)->line(...)->render()` ou usando o mesmo template `notifications::email`), o layout fica 100% identico ao e-mail de confirmacao de cadastro, incluindo header, saudacao, botao, assinatura, `subcopy` com o link alternativo e rodape.

### 2.3. Wordmark `devtools_` com `_` na cor `--brand`
- No frontend (`resources/js/components/AppWordmark.vue`), o wordmark e renderizado em fonte monoespacada com `devtools` e o `_` colorido com `text-brand`.
- Em `resources/css/app.css` e `docs/design/DESIGN.md`, o token `--brand` no tema claro (fundo `#fafafb` / `#ffffff`, identico ao fundo `#fafafa` / `#ffffff` do template de e-mail do Laravel) e `#0f7f8a` (contraste validado WCAG AA >= 4.5:1).
- Quando `MailMessage` recebe linhas de texto, `Illuminate\Notifications\Messages\SimpleMessage::formatLine()` converte strings comuns com `e()` (escapando HTML), mas preserva objetos que implementam `Illuminate\Contracts\Support\Htmlable` (`Illuminate\Support\HtmlString`).
- Criar um helper central `App\Support\MailBrand` (ou metodo dedicado) que retorna o wordmark `HtmlString` (`devtools<span style="color: #0f7f8a;">_</span>`) para interpolacao segura nas linhas dos e-mails e publicar/customizar as views de e-mail (`resources/views/vendor/mail/html/message.blade.php`, `resources/views/vendor/mail/text/message.blade.php` e `resources/views/vendor/notifications/email.blade.php`) garante que todas as ocorrencias (header, corpo, saudacao final e rodape) exibam `devtools_` com o `_` em `#0f7f8a` no HTML e `devtools_` no texto puro.

### 2.4. Strings em ingles no template de e-mail
- O Laravel busca `'Hello!'`, `'Whoops!'`, `'Regards,'`, `'All rights reserved.'` e `"If you're having trouble clicking..."` no tradutor JSON (`lang/{locale}.json`), que nao existe no projeto (todas as traducoes vivem em `lang/{locale}/*.php`).
- Substituir essas chamadas nas views de e-mail publicadas (`resources/views/vendor/notifications/email.blade.php`, `resources/views/vendor/mail/html/message.blade.php` e `resources/views/vendor/mail/text/message.blade.php`) por chaves estruturadas em `lang/{locale}/mail.php` (`mail.template.greeting`, `mail.template.error_greeting`, `mail.template.salutation`, `mail.template.subcopy`, `mail.template.rights`) resolve a traducao nos 5 idiomas (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) seguindo a arquitetura de i18n do projeto.
