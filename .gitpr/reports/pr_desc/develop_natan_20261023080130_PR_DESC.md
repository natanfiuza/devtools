# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: traduz email de intenção conforme locale do destinatário
```

---

## 🎯 Resumo

Os emails de notificação de intenção de assinatura eram sempre renderizados no idioma padrão da aplicação, independentemente de quem os recebia. Isso gerava notificações em português para usuários e contatos administrativos que operam em outros idiomas.

Agora o conteúdo do email é traduzido no idioma preferido do destinatário: quando o contato administrativo é um usuário cadastrado, usa-se o locale desse admin; caso contrário, recorre-se ao locale do usuário que solicitou a assinatura.

## 🛠️ Mudanças Técnicas

- `SubscriptionIntentController@store`: busca o usuário pelo email de contato (`User::where('email', ...)`) e resolve o locale alvo com `$admin?->preferredLocale() ?? $user->preferredLocale()`, aplicando-o via `->locale($targetLocale)` na notificação on-demand.
- `SubscriptionIntentSubmitted::toMail`: extrai a montagem da mensagem para um closure `$render` e resolve `$targetLocale` a partir de `$this->locale` (fallback para o locale do usuário da intenção).
- Uso de `app()->setLocale()` com `try/finally` para renderizar as strings traduzidas e restaurar o locale anterior, evitando vazamento de estado entre requisições.
- Testes de feature adicionados em `PricingTest`: cenário de usuário com locale `fr` e cenário de admin cadastrado com locale `pt_BR`, validando locale da notificação e conteúdo traduzido de assunto e corpo.
- Documentação atualizada: plano, relatório e survey sobre tradução de emails.

## ⚠️ Impacto/Avisos

- Sem migrações de banco, novas variáveis de ambiente ou dependências adicionais.
- Passa a existir uma consulta extra à tabela `users` por `email` a cada intenção registrada — recomenda-se índice em `users.email` para manter a performance.
- O locale da aplicação é alterado temporariamente durante a renderização; o `finally` garante a restauração, mas qualquer código executado dentro do closure herda o locale do destinatário.
- Comportamento observável alterado: notificações on-demand deixam de usar o locale padrão da app e passam a respeitar o idioma do destinatário.


close #68

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)