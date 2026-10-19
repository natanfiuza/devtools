# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: retorna ao cadastro apos login social iniciado nele
```

---

## 🎯 Resumo

O login social (Google/GitHub) só existia na tela de login: quem iniciava o fluxo a partir do cadastro era devolvido ao login em caso de erro ou provedor não configurado, perdendo o contexto e a mensagem de aviso. Esta mudança propaga a origem do fluxo (`login` ou `register`) do front-end até o retorno do provedor, garantindo que o usuário volte à tela em que começou, com o aviso traduzido e visível. Também diferencia o rótulo dos botões sociais ("Entrar com" vs "Cadastrar com") para deixar explícito o efeito da ação em cada tela.

## 🛠️ Mudanças Técnicas

- `SocialAuthController` passa a aceitar `Request` em `redirect()` e grava a origem em sessão (`auth.social.from`), normalizando qualquer valor diferente de `register` para `login`.
- `backWithStatus()` usa essa origem para escolher a rota de destino (`register` ou `login`), mantendo a mensagem de status traduzida no idioma do visitante.
- `FortifyServiceProvider` injeta `status` da sessão na view Inertia `auth/Register`, já que a tela de cadastro não exibia mensagens flash.
- `SocialAuthButtons.vue` ganha a prop `action` (`'login' | 'register'`, padrão `login`), que monta a URL com `?from=register` e seleciona o rótulo via `login_with`/`register_with`.
- Novas chaves de tradução `auth.social.login_with` e `auth.social.register_with` em `en`, `es`, `fr`, `pt_BR` e `pt_PT`, com fallback para `continue_with` quando a chave não existir (`te()`), preservando compatibilidade com traduções ainda não atualizadas.
- `Login.vue` declara explicitamente `action="login"`; `Register.vue` usa `action="register"` e renderiza o aviso de status com `role="status"` e `aria-live="polite"`.
- Testes: novo `auth-register.test.ts` cobrindo i18n, action/method do formulário, link para login, rótulos sociais e URLs com `from=register`; `auth-login.test.ts` atualizado para os novos rótulos; testes de feature garantem retorno ao cadastro em provedor não configurado e em autorização recusada, mantendo o usuário como guest.

## ⚠️ Impacto/Avisos

- **Sem migrações de banco.** A origem do fluxo usa a sessão existente (`auth.social.from`); sessões antigas caem no fallback `login`.
- **Sem novas variáveis de ambiente ou dependências.**
- **Traduções:** aplicações com traduções customizadas que sobrescrevam `auth.social.*` continuam funcionando pelo fallback para `continue_with`, mas perdem o rótulo específico até incluir as novas chaves.
- **Comportamento observável:** usuários que iniciam o login social pelo cadastro passam a permanecer no cadastro após falha/indisponibilidade, em vez de serem redirecionados ao login.

close #43

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)