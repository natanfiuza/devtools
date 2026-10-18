# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: inicializa devtools com auth, i18n e tema claro/escuro
```

---

## 🎯 Resumo

Materializa a fase F0 do SPEC/DESIGN ao criar a base do devtools: aplicação Laravel 13 + Inertia 3 + Vue 3 + Tailwind 4, com tema claro/escuro e internacionalização (en, pt_BR, pt_PT, fr, es) resolvidos no servidor para garantir SEO correto e zero flash de tema/idioma. Entrega ainda autenticação (Fortify + login social), newsletter com double opt-in e um deploy com releases imutáveis que trava a operação de publicação como parte do próprio fluxo.

## 🛠️ Mudanças Técnicas

- **Configuração do projeto**: composer.json, package.json, phpstan (level 7), Pint, PHPUnit/Pest, ESLint/Prettier, components.json (shadcn-vue), .env.example e .gitpr/skill/.gitpr.linter.yml.
- **Autenticação (Fortify)**: Actions `CreateNewUser`/`ResetUserPassword`, `FortifyServiceProvider`, config/fortify.php com rotas prefixadas por `{locale}`, rate limiting de login e views Inertia.
- **Login social**: `SocialAuthController`, `Support\SocialProviders` e modelo `SocialAccount` (ADR-023), com captura opcional de senha via `SetPasswordController` para contas sem senha.
- **Newsletter com double opt-in (ADR-024)**: `SubscribeToNewsletter`, `NewsletterController`, Mailable de confirmação, `NewsletterSubscriber` e enum `NewsletterSource`.
- **Infraestrutura de locale/tema**: middlewares `ResolveLocale`, `HandleAppearance` e `HandleInertiaRequests`, `Support\Locales`/`Appearance`/`Seo`, `URL::defaults` por idioma e notificações próprias de reset/verificação com `HasLocalePreference`.
- **Models/migrations**: `users` (colunas `locale` e `appearance`), `social_accounts` (uniques provider+provider_id e user_id+provider; `password` nullable) e `newsletter_subscribers`.
- **Frontend Vue 3 + Inertia + SSR**: componentes shadcn-vue sobre reka-ui, composables (`useAppearance`, `useLocale`, `useRoutes`, `useCurrentUrl`, `useInitials`), libs (i18n espelhando `lang/`, cookies SameSite=Lax, `resolvePage` via `import.meta.glob`, `flashToast`), layouts (Public, Auth, App) e páginas (Home, Styleguide, Dashboard, telas de auth e settings).
- **SSR**: `ssr.ts` cria a instância i18n por requisição (evita vazamento de idioma entre requisições simultâneas) e `app.blade.php` resolve tema/idioma no servidor, com hreflang/canonical condicionados por SEO e `noindex` em telas de auth.
- **Rotas PHP**: `web.php`, `settings.php`, `auth-social.php` e `newsletter.php`.
- **Testes**: suíte Pest (Appearance, Auth, Dashboard, LocaleRouting, Settings, TranslationsParity) e Vitest (aparência, tokens de design, i18n).
- **Deploy**: `deploy/deploy.sh` reescrito com releases imutáveis e swap atômico (symlinks de `public/build` e `bootstrap/ssr`), `npm ci` condicional ao hash do lockfile, poda de releases antigas e reinício do SSR via `supervisorctl`; inclui `deploy/supervisor/devtools-ssr.conf` para o processo `inertia:start-ssr`.

## ⚠️ Impacto/Avisos

- **Banco de dados**: novas migrations (colunas `locale`/`appearance` em `users`, tabelas `social_accounts` e `newsletter_subscribers`, e `password` agora nullable).
- **Cookies**: dependência dos cookies `appearance` e `locale`; clientes sem JS caem na preferência da conta/navegador.
- **Dependências novas de front**: reka-ui, vue-i18n, vue-sonner, vue-input-otp, @lucide/vue, @vueuse/core, class-variance-authority, tailwind-merge e clsx.
- **Traduções**: há strings em inglês em telas de auth ainda não migradas para `lang/`, protegidas pelo teste `TranslationsParity`, que trava regressões de idioma.
- **Deploy**: agora faz build no servidor e requer `supervisorctl` disponível e PATH/SSR configurados; releases antigas são podadas.

close #1

---

[![GitPR](https://img.shields.io/badge/GitPR-18_errors_%C2%B7_105_warnings-red)](https://gitpr.natanfiuza.dev.br/)
