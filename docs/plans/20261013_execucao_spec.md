# F0 — Fundacao (execucao do `docs/SPEC.md`)

## Context

O repo tem apenas documentacao (26 arquivos, zero commits, `master`, sem remote) e o SPEC v1.0 esta aprovado com 8 fases. O pedido e executar o plano comecando pela F0 ("Fundacao: projeto Laravel 13 + starter Vue, TS estrito, Tailwind tokens, shadcn-vue, i18n 5 idiomas, layout, tema dark, deploy.sh, supervisor conf, Pest/Vitest"). A fase termina com testes verdes, docs atualizados e **parada para revisao** — e sem nenhum commit meu (regra nova, decidida no grilling).

## Decisoes fechadas

| #         | Decisao                                                                                                                                                                                                                                           |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Escopo    | Somente a **F0** nesta sessao; parada para revisao ao fim                                                                                                                                                                                         |
| Git       | **Eu nunca commito/pusho.** Commits e PR sao feitos pelo usuario via **gitpr**, com aprovacao de cada um. Vira regra no `CLAUDE.md`                                                                                                               |
| SPEC §11  | Regras ja escritas valem como fechadas; eu reconstruo a secao 11 e removo a referencia morta a "Q1"                                                                                                                                               |
| Assets    | **Build no servidor** (`npm ci` condicional + `npm run build` no `deploy.sh`)                                                                                                                                                                     |
| Banco     | Testes em **SQLite `:memory:`** (+ grupo opcional `mysql`); dev local em **MariaDB `devtools`**; VPS tambem roda **MariaDB 11.x** (fiel ao local 11.4)                                                                                            |
| Bootstrap | **Kit oficial + enxugar**: converter para Pest 4, adicionar vue-i18n, tokens do DESIGN.md, desligar passkeys/2FA no config do Fortify, trocar `vite-plus` por `vite` puro. Manter Fortify, shadcn-vue vendorizado e **wayfinder** (rotas tipadas) |
| i18n      | **Fonte unica** em `lang/{locale}/*.php`, exposta ao Inertia escopada por namespace, consumida pelo vue-i18n                                                                                                                                      |
| Docs      | Portugues **sem acentos**, como SPEC/ADRs/CLAUDE.md                                                                                                                                                                                               |
| SSR       | Cabear na F0 e **ligar em producao** (gate por `INERTIA_SSR_ENABLED`; local desligado por padrao)                                                                                                                                                 |
| Tema      | **Toggle claro/escuro/sistema ja na F0**, com validacao AA do tema claro (o DESIGN.md marca como "validar")                                                                                                                                       |

## Fatos que sustentam o plano

- PHP 8.3.19 local; Node 22.18; MariaDB 11.4 na 3306; VPS com Node 22+ (informado pelo usuario).
- **Pest 5 exige PHP ^8.4** → pin **Pest 4.7.x + pest-plugin-laravel 4.1.x** (suportam Laravel 13 no PHP 8.3).
- **TypeScript `latest` = 7.0.2** (compilador nativo, sem `lib/typescript.js`, que o vue-tsc/volar carrega) → pin **`typescript ~5.9.3`**.
- Kit tagueado `v1.0.2` = **Laravel 12** (Inertia 2, Ziggy, PHPUnit 11). Laravel 13 so em `main` → bootstrap por `dev-main`, aceitavel porque starter kit e scaffold de uso unico.
- Kit ja entrega: Tailwind 4 nativo, shadcn-vue vendorizado (`reka-ui`), Fortify + passkeys/2FA, SSR cabeado em `config/inertia.php`, `phpunit.xml` com SQLite `:memory:`, `.gitignore` ignorando `/public/build` e `/bootstrap/ssr` (compativel com build no servidor). **Nao** entrega i18n nem Pest.
- `laravel/fortify` **exige** `laravel/passkeys` — entao passkeys/2FA sao **desligados no config**, nao removidos do composer.
- Composer local 2.9.5 tem advisory (corrigido em 2.10.3) → sugerir `composer self-update`.

## Plano de execucao

### 1. Preparacao (docs e regra)
- [ ] Adicionar ao `CLAUDE.md` a regra de git: nenhum commit/push; commits e PR via gitpr, com aprovacao; pedir intervencao manual quando necessario.
- [ ] Gravar a memoria de trabalho (commits so via gitpr).
- [ ] Criar `docs/acceptance/FASE-0.md` com os criterios de aceite abaixo.
- [ ] Reconstruir `docs/SPEC.md` §11 (o que esta respondido x o que segue aberto) e remover a referencia morta a "Q1".

### 2. Bootstrap
- [ ] `composer create-project laravel/vue-starter-kit:dev-main` em diretorio temporario e mover o conteudo para a raiz do repo **sem tocar** em `.git/`, `docs/`, `deploy/`, `CLAUDE.md`.
- [ ] Trocar `composer.json` (`name: natanfiuza/devtools`), ajustar `.env.example` (`DB_CONNECTION=mysql` apontando para MariaDB, `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION=database`, `INERTIA_SSR_ENABLED`, chaves de IA vazias).
- [ ] Local: criar banco/usuario `devtools` no MariaDB (SQL entregue ao usuario; senha direto no `.env`).

### 3. Toolchain (Pest / Vitest / TS)
- [ ] Pest 4 + `pest-plugin-laravel` 4.1; `tests/Pest.php`; converter os testes PHPUnit do kit; script `composer test` (config:clear + pint --test + pest).
- [ ] Pin `typescript ~5.9.3`; script `types:check` (`vue-tsc --noEmit` apos gerar o wayfinder).
- [ ] Remover `vite-plus`; scripts `dev`, `build`, `build:ssr`, `dev:ssr` em `vite` puro; ESLint 9 (flat) + Prettier leves.
- [ ] Vitest 5 + `@vue/test-utils` + `jsdom`; script `npm run test`.
- [ ] `tsconfig` realmente estrito (`strict`, `noUncheckedIndexedAccess`, `noImplicitOverride`).

### 4. Design system (tokens + componentes)
- [ ] Reescrever `resources/css/app.css` com os tokens do `DESIGN.md` (dark-first + tema claro), mapeados em `@theme inline` do Tailwind 4; sem sombras; foco 2px `--accent`.
- [ ] **Validar AA do tema claro** (escurecer a familia de acentos) e anotar os ratios no `DESIGN.md`.
- [ ] Fontes self-hosted no build (plugin `fonts` do `laravel-vite-plugin`): Inter + JetBrains Mono no lugar de Instrument Sans.
- [ ] `/styleguide` com todos os componentes do DESIGN nos dois temas; wordmark `devtools_` em SVG; favicon.

### 5. i18n (5 idiomas)
- [ ] `lang/{en,pt_BR,pt_PT,fr,es}/*.php` com namespace base (`app`, `home`, `auth`, `footer`); mapear `pt_BR`/`pt_PT` (dir) para `pt-br`/`pt-pt` (URL).
- [ ] Middleware de locale: prefixo da URL -> `user.locale` (autenticado) -> cookie -> `Accept-Language` -> `en`; `/` redireciona para `/en`; `/api`, `/webhooks` e `/admin` ficam **fora** do prefixo.
- [ ] Shared props do Inertia: `locale`, `availableLocales` e as mensagens **so do locale ativo, por namespace**. vue-i18n instanciado em `app.ts` e `ssr.ts`.
- [ ] Seletor de idioma no header (persiste) + `hreflang` no head.
- [ ] Testes: paridade de chaves entre os 5 idiomas; resolucao/redirect de locale; composable de i18n (Vitest).

### 6. Tema claro/escuro
- [ ] Persistencia: usuario autenticado (coluna) + cookie para anonimo; `prefers-color-scheme` como padrao.
- [ ] **Sem flash** de tema errado: classe do `<html>` resolvida no servidor a partir do cookie (nada de script inline bloqueante).
- [ ] Página Appearance do kit adaptada (claro/escuro/sistema), i18n'd.
- [ ] Vitest calculando contraste WCAG dos pares de tokens nos dois temas (falha abaixo de AA).
- [ ] Registrar a decisao em **ADR-021** (tema claro/escuro + estrategia anti-FOUC).

### 7. Layout
- [ ] `AppLayout` conforme DESIGN: container 1120px, header 56px sticky com blur, wordmark, links Tools/Pricing/Docs, seletor de idioma, placeholder do Cmd+K, Login; footer minimo (idioma, privacidade, termos, doar).
- [ ] Home **placeholder** (a home real e a F1); navegacao por teclado, focus ring, aria-labels, mobile empilhado.
- [ ] Nenhuma string hardcoded: tudo via i18n.

### 8. Deploy e infra (arquivos)
- [ ] `deploy/deploy.sh`: PATH explicito (cron); fetch + comparacao de HEAD + `pull --ff-only`; `composer install --no-dev`; `npm ci` **so quando o lockfile mudar** (hash); build do Vite em diretorio temporario + **troca atomica** (evita servir HTML novo com assets ausentes); `migrate --force`; `optimize`; `queue:restart`; reinicio do SSR (kill + `autorestart`); `command -v node` com erro claro.
- [ ] `deploy/supervisor/devtools-ssr.conf` (novo, `autorestart=true`) e revisao do conf do worker.
- [ ] `README.md` (setup local) e `docs/RUNBOOK.md` (deploy, fila, SSR, backup, rotacao de chaves) — o usuario executa no VPS.

### 9. Fechamento da fase
- [ ] `composer test` + `npm run test` + `npm run build` + `npm run build:ssr` verdes.
- [ ] Smoke manual: `php artisan serve` + `npm run dev`, trocar idioma, trocar tema, navegar nas 5 URLs.
- [ ] Docs atualizados: `SPEC.md` §11, `ADR-014` (assets + passos do deploy), `ADR-021` (tema), `DESIGN.md` (ratios AA do light, fontes), `CHANGELOG.md` (novo).
- [ ] **Parar para revisao** — sem commit; a arvore fica pronta para o usuario commitar via gitpr.

## Criterios de aceite da F0 (viram `docs/acceptance/FASE-0.md`)

1. App sobe local do zero seguindo o `README.md` (composer install, npm install, `.env`, migrate, dev).
2. `composer test` verde (Pest 4), incluindo paridade de chaves i18n e resolucao/redirect de locale.
3. `npm run test` verde (Vitest 5), incluindo contraste AA dos tokens nos dois temas.
4. `npm run build` e `npm run build:ssr` sem erro; `vue-tsc --noEmit` limpo com TS estrito.
5. As 5 URLs (`/en`, `/pt-br`, `/pt-pt`, `/fr`, `/es`) funcionam; `/` redireciona para `/en`; seletor troca e persiste; `hreflang` presente.
6. Tema claro/escuro/sistema funciona com persistencia e **sem flash**; `/styleguide` renderiza os componentes nos dois temas.
7. Zero string hardcoded nas telas (tudo via i18n) e nada de modelo/preco/limite de IA no codigo.
8. `deploy/deploy.sh` + `deploy/supervisor/devtools-ssr.conf` prontos para o VPS; README e RUNBOOK escritos.
9. Docs atualizados e **nenhum commit feito por mim**.

## Verificacao

- Local: `composer test`, `npm run test`, `npm run build`, `npm run build:ssr`, `npm run types:check`.
- Manual: subir `php artisan serve` + `npm run dev`; percorrer as 5 URLs, trocar idioma (persistencia), alternar tema (sem flash ao recarregar), conferir `/styleguide`.
- SSR: subir `npm run dev:ssr` e conferir que o HTML chega renderizado (curl na home e ver texto no HTML).
- Deploy: **nao roda local** (script e para o VPS, com `flock`/cron). Fica coberto pelo RUNBOOK e e exercitado no primeiro deploy feito pelo usuario.
- Nao verificavel daqui: PHP 8.3 e usuario do cron no VPS (pendente de confirmacao do usuario).

## Riscos

- **`dev-main` do kit e alvo movel** → scaffold de uso unico: apos instalar, as versoes ficam travadas no lockfile. Fallback: `laravel/laravel` 13.x + montagem manual de Inertia/Vue/Tailwind/shadcn.
- **Pest 5 incompativel com PHP 8.3** → Pest 4 e obrigatorio (se o composer nao resolver, parar e decidir).
- **SSR ligado sem processo node** derruba as paginas → gate `INERTIA_SSR_ENABLED` (false no dev, true no servidor).
- **Composer 2.9.5 com advisory** → sugerir `composer self-update` (acao global, precisa do seu ok).

## Herdado para outras fases

- F1: registro de tools, home real, Cmd+K, banner LGPD, GA, sitemap, OG images.
- F2: conflito do `DESIGN.md` sobre `--elevated` como cor de pontuacao JSON.
- F5: conflito `ADR-011` `dialetos` x SPEC `supported_dialects(json)` + `sort`.
- F7: light theme hardening (se algo ficar pendente), SEO final.
