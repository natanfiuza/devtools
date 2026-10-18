# Plano - Wordmark `devtools_`, Unificacao do Layout da Newsletter e Traducao do Template de E-mail

Sessao de planejamento e grill (`grill-with-docs`). Branch `develop_natan`.
Duas rodadas de perguntas (**Q1 a Q3** e **A1**), todas respondidas pelo usuario.
Fatos levantados em `docs/survey/20261018_email_wordmark_newsletter_layout_surveyfacts.md`.

## Contexto

O pedido reune quatro entregas complementares para os e-mails transacionais do sistema:
1. Exibir o wordmark `devtools_` (com o `_` na cor `--brand` `#0f7f8a`) em todas as ocorrencias visiveis do template de e-mail (header no topo, corpo onde houver `:app`, assinatura final e rodape).
2. Deixar o e-mail de confirmacao de inscricao na newsletter (`NewsletterConfirmation`) com layout e estrutura identicos ao e-mail de confirmacao de cadastro (`VerifyEmail`), usando a saudacao padrao traduzida, o wordmark `devtools_` no texto de introducao, o botao de acao, a frase de ignorar, a assinatura e o bloco de link alternativo (`subcopy`).
3. Traduzir para os 5 idiomas do sistema (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) a mensagem do link alternativo (`"If you're having trouble clicking the \":actionText\" button, copy and paste the URL below into your web browser:"`) e os demais textos padrao do template de e-mail (`Hello!`, `Whoops!`, `Regards,`, `All rights reserved.`) em `lang/{locale}/mail.php`.
4. Garantir paridade de chaves e cobertura automatizada nos testes de e-mail e newsletter (`MailLocaleTest`, `NewsletterTest` e `TranslationsParityTest`).

## Decisoes (duas rodadas de grill)

| # | Pergunta | Decisao |
|---|---|---|
| Q1 | Onde aplicar o wordmark `devtools_` (com `_` em `--brand`) | Em todas as ocorrencias visiveis do template de e-mail (header no topo, corpo onde houver `:app`, saudacao final e rodape) |
| Q2 | Qual valor hexadecimal de `--brand` usar no HTML do e-mail | `#0f7f8a` (token `--brand` do tema claro em `resources/css/app.css`, pois o fundo do e-mail e claro `#fafafa`/`#ffffff` e garante contraste WCAG AA) |
| Q3 | Tratamento das strings em ingles do template padrao do Laravel | Traduzir todos os textos padrao do template (`Hello!`, `Whoops!`, `Regards,`, `All rights reserved.` e a mensagem `If you're having trouble clicking...`) em `lang/{locale}/mail.php` para todos os e-mails transacionais |
| A1 | Saudacao e estrutura do e-mail da newsletter | Usar a saudacao padrao traduzida (`Olá!` / `Hello!` / etc.) igual ao e-mail de confirmacao de cadastro, incorporando o `devtools_` na primeira linha do texto (`mail.newsletter.intro`) |

## Arquivos a criar ou modificar

### 1. Suporte ao Wordmark `devtools_` nos E-mails
| Arquivo | Mudanca |
|---|---|
| `app/Support/MailBrand.php` | **Novo**: helper central com `MailBrand::wordmark(): HtmlString` (retorna `devtools<span style="color: #0f7f8a;">_</span>`) e `MailBrand::line(string $key, array $replace = []): HtmlString` (interpola `:app` com o wordmark colorido escapando os demais parametros com seguranca) |
| `resources/views/vendor/mail/html/message.blade.php` | **Novo**: override da view HTML de `<x-mail::message>` exibindo o wordmark `devtools_` (com `font-family` monoespacada e `_` em `#0f7f8a`) no header e no rodape, alem de `__('mail.template.rights')` |
| `resources/views/vendor/mail/text/message.blade.php` | **Novo**: override da view texto puro de `<x-mail::message>` exibindo `devtools_` e `__('mail.template.rights')` |
| `resources/views/vendor/notifications/email.blade.php` | **Novo**: override do template de notificacao do Laravel usando `__('mail.template.greeting')`, `__('mail.template.error_greeting')`, `__('mail.template.salutation')`, o wordmark `devtools_` na assinatura e `__('mail.template.subcopy', ['actionText' => $actionText])` no `<x-slot:subcopy>` |

### 2. Notificacoes e E-mail de Confirmacao da Newsletter
| Arquivo | Mudanca |
|---|---|
| `app/Notifications/VerifyEmail.php` | Usar `MailBrand::line('mail.verify.intro')` para renderizar `devtools_` com o `_` em `--brand` no corpo |
| `app/Notifications/ResetPassword.php` | Usar `MailBrand::line('mail.reset.intro')` para manter consistencia do wordmark `devtools_` no corpo |
| `app/Mail/NewsletterConfirmation.php` | Unificar a renderizacao com o layout de `VerifyEmail` (usando a mesma estrutura de `notifications::email` com `introLines`, `actionText`, `actionUrl`, `displayableActionUrl`, `outroLines` e `MailBrand::line('mail.newsletter.intro')`) |
| `resources/views/mail/newsletter-confirmation.blade.php` | Atualizar (ou delegar para `notifications::email`) para renderizar exatamente o mesmo layout do e-mail de confirmacao de cadastro, incluindo o `<x-slot:subcopy>` com o link alternativo |

### 3. Traducoes (`lang/{locale}/mail.php`) nos 5 Idiomas
| Arquivo | Mudanca |
|---|---|
| `lang/pt_BR/mail.php` | Adicionar bloco `template` (`greeting`, `error_greeting`, `salutation`, `subcopy`, `rights`) e atualizar `newsletter.intro` para incluir `:app` (`Falta um clique para você começar a receber a newsletter do :app: é só confirmar que este e-mail é seu.`), removendo `newsletter.greeting` |
| `lang/pt_PT/mail.php` | Adicionar bloco `template` e atualizar `newsletter.intro` com `:app`, removendo `newsletter.greeting` |
| `lang/en/mail.php` | Adicionar bloco `template` e atualizar `newsletter.intro` com `:app`, removendo `newsletter.greeting` |
| `lang/es/mail.php` | Adicionar bloco `template` e atualizar `newsletter.intro` com `:app`, removendo `newsletter.greeting` |
| `lang/fr/mail.php` | Adicionar bloco `template` e atualizar `newsletter.intro` com `:app`, removendo `newsletter.greeting` |

### 4. Testes e Documentacao
| Arquivo | Mudanca |
|---|---|
| `tests/Feature/TranslationsParityTest.php` | Incluir verificacao de paridade de chaves para `mail.php` nos 5 idiomas |
| `tests/Feature/MailLocaleTest.php` | Verificar presenca do wordmark `devtools_` (`<span style="color: #0f7f8a;">_</span>`), da saudacao traduzida e do `subcopy` traduzido no idioma da conta (`fr`, `pt_PT`, `en`) |
| `tests/Feature/NewsletterTest.php` | Verificar que o e-mail `NewsletterConfirmation` renderiza o mesmo layout com wordmark `devtools_`, saudacao traduzida e `subcopy` com a URL de confirmacao no idioma do inscrito |
| `docs/design/DESIGN.md` | Documentar a convencao de e-mails transacionais (wordmark `devtools_` com `_` em `--brand` `#0f7f8a`, layout unificado e subcopy traduzido) |

## Verificacao Planejada

1. `composer test` (Pint, PHPStan, Pest — incluindo `TranslationsParityTest`, `MailLocaleTest` e `NewsletterTest`).
2. `npm run test` (Vitest).
3. `npm run types:check` (Wayfinder + `vue-tsc --noEmit`).
