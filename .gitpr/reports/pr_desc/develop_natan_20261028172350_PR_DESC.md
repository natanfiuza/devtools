# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona card da API e chamada de doação na home
```

---

## 🎯 Resumo

A home pública só apresentava o hero, o grid de ferramentas e o destaque do AI SQL Builder. Faltava comunicar dois pontos estratégicos do produto: (1) que o devtools pode ser consumido programaticamente via API REST v1, abrindo espaço para integrações em CI/CD, CLI, ferramentas internas e automações n8n; e (2) que o projeto é independente e se sustenta com doações via Pix.

Esta mudança adiciona duas seções informativas na home com CTAs claros, aumentando a descoberta da API por usuários técnicos e criando um canal explícito de sustentabilidade do projeto — sem introduzir nenhuma lógica de negócio nova no backend.

## 🛠️ Mudanças Técnicas

- Adiciona o bloco `home.api` e `home.donation` em todos os locales suportados (`pt_BR`, `pt_PT`, `en`, `es`, `fr`), incluindo badge, título, corpo, nota, rótulos de CTA e o exemplo de requisição cURL (`preview_code`).
- Cria a seção "API REST" em `resources/js/pages/Home.vue` com badge, descrição, preview estático de `curl` em `<pre><code>` e dois CTAs: documentação (`routes.apiDocs`) e gerenciamento de tokens (`apiTokens.url()`).
- Cria a seção de doação com badge, texto institucional e CTA apontando para `routes.donate`.
- Introduz `isAuthenticated` via `usePage().props.auth?.user` para alternar o CTA secundário entre "Gerenciar tokens de API" (usuário logado) e "Criar conta" (visitante).
- Importa os ícones `Terminal` e `HeartHandshake` de `@lucide/vue`.
- Amplia os testes de frontend (`home.test.ts`) com os novos fixtures de tradução e dois casos cobrindo o card da API (incluindo `href` dos CTAs) e a chamada de doação.
- Amplia `tests/Feature/HomeTest.php` para validar a existência das novas chaves de tradução no locale padrão.

## ⚠️ Impacto/Avisos

- **i18n:** as chaves `home.api.*` e `home.donation.*` passam a ser obrigatórias. Novos locales adicionados no futuro precisam incluir esse bloco, sob risco de exibir a chave crua na interface.
- **Rotas dependentes:** a renderização assume a existência de `routes.apiDocs`, `routes.donate` e `apiTokens.url()`. Ausência de qualquer uma delas quebra a página.
- **Props de autenticação:** o CTA de tokens depende de `auth.user` estar compartilhado nas props do Inertia na home pública. Caso esse shared prop não esteja disponível nesse contexto, o componente degrada silenciosamente para o CTA de registro.
- **Testes:** os seletores `data-test` (`home-api-docs-cta`, `home-api-register-cta`, `home-donate-cta`) passam a ser contrato dos testes de frontend — renomeá-los exige atualização conjunta.
- **Banco de dados / envs / dependências:** nenhuma migração, variável de ambiente ou nova dependência introduzida. `@lucide/vue` já era utilizado pela página.


close #107

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)