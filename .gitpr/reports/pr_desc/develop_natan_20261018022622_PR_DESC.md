# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: padroniza e-mails transacionais com wordmark devtools_
```

---

## 🎯 Resumo

Unifica a identidade visual dos e-mails transacionais do produto. Antes, cada template montava seu próprio cabeçalho/saudação e a marca `devtools_` aparecia de forma inconsistente (texto simples, sem destaque de cor). Agora existe uma fonte única de verdade para o wordmark e um layout compartilhado entre notificações do framework (reset de senha, verificação de e-mail) e a confirmação de newsletter, garantindo consistência entre idiomas e tipos de mensagem.

A mudança também elimina textos hardcoded em inglês nos templates de notificação: saudação, despedida, subcopy de fallback do botão e rodapé passam a ser traduzidos para os cinco locales suportados.

## 🛠️ Mudanças Técnicas

- Adiciona `App\Support\MailBrand` com helpers `wordmark()` (HTML com `_` em `--brand` `#0f7f8a`), `plainWordmark()` (texto puro) e `line()`, que escapa o texto traduzido e interpola `:app` com o wordmark colorido.
- Atualiza `ResetPassword` e `VerifyEmail` para usar `MailBrand::line()` em vez de interpolar `config('app.name')` diretamente na linha de introdução.
- Adiciona bloco `mail.template` (`greeting`, `error_greeting`, `salutation`, `subcopy`, `rights`) em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Ajusta `mail.newsletter.intro` para incorporar `:app` na frase e remove a chave `mail.newsletter.greeting`, agora coberta pelo template genérico.
- Publica/sobrescreve `resources/views/vendor/mail/html/message.blade.php`, `.../mail/text/message.blade.php` e `.../notifications/email.blade.php` com header/footer contendo o wordmark, ano dinâmico e saudações/subcopy traduzidas.
- Refatora `resources/views/mail/newsletter-confirmation.blade.php` para renderizar via `@include('notifications::email', ...)`, reaproveitando o mesmo layout das notificações.
- Expande `MailLocaleTest`, `NewsletterTest` e `TranslationsParityTest` para validar saudação, despedida, subcopy, rodapé e a marca colorida, além de incluir o namespace `mail` na verificação de paridade entre locales.

## ⚠️ Impacto/Avisos

- **Views vendorizadas:** os arquivos em `resources/views/vendor/**` sobrescrevem os templates padrão do Laravel. Precisam ser revisados a cada upgrade de framework para evitar regressões silenciosas.
- **Traduções:** a chave `mail.newsletter.greeting` foi removida; qualquer referência externa a ela deixará de resolver. O namespace `mail` agora é exigido em todos os locales pelo teste de paridade.
- **Banco de dados / envs / dependências:** sem migrações, sem novas variáveis de ambiente e sem novas dependências.
- **Acessibilidade:** a cor `#0f7f8a` no `_` foi validada para contraste AA sobre fundo claro; alterações futuras de tema devem manter essa verificação.

close #29

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_4_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)