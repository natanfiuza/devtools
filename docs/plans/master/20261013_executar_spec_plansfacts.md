# Plano de execucao — F0 Fundacao (devtools.natanfiuza.dev.br)

- **Data**: 2026-10-13
- **Branch**: master
- **Task**: executar `docs/SPEC.md`, comecando pela F0
- **Status**: aprovado pelo usuario; execucao em andamento

## Contexto

O repo tinha apenas documentacao (26 arquivos, zero commits, `master`, sem remote) e o SPEC v1.0 esta aprovado com 8 fases (F0–F7). O pedido foi executar o plano, comecando pela F0 ("Fundacao: projeto Laravel 13 + starter Vue, TS estrito, Tailwind tokens, shadcn-vue, i18n 5 idiomas, layout, tema dark, deploy.sh, supervisor conf, Pest/Vitest"). A fase termina com testes verdes, docs atualizados e **parada para revisao** — e **sem nenhum commit** feito pelo assistente.

## Decisoes fechadas no grilling

| # | Decisao |
|---|---|
| Escopo | Somente a **F0** nesta sessao; parada para revisao ao fim |
| Git | **Nunca commitar/pushar.** Commits e PR sao feitos pelo usuario via **gitpr**, com aprovacao de cada um; intervencao manual do usuario quando necessario. Regra gravada no `CLAUDE.md` |
| SPEC §11 | Regras ja escritas valem como fechadas; a secao 11 foi reconstruida e a referencia morta a "Q1" removida |
| Assets | **Build no servidor** (`npm ci` condicional + `npm run build` no `deploy.sh`) |
| Banco | Testes em **SQLite `:memory:`** (+ grupo opcional `mysql`); dev local em **MariaDB `devtools`**; VPS tambem roda **MariaDB 11.x** |
| Bootstrap | **Kit oficial + enxugar**: Pest 4, vue-i18n, tokens do DESIGN.md, passkeys/2FA desligados no config do Fortify, `vite-plus` -> `vite` puro. Mantidos Fortify, shadcn-vue vendorizado e **wayfinder** |
| i18n | **Fonte unica** em `lang/{locale}/*.php`, exposta ao Inertia escopada por namespace, consumida pelo vue-i18n |
| Docs | Portugues **sem acentos**, como SPEC/ADRs/CLAUDE.md |
| SSR | Cabear na F0 e **ligar em producao** (gate por `INERTIA_SSR_ENABLED`; local desligado por padrao) |
| Tema | **Toggle claro/escuro/sistema ja na F0**, com validacao AA do tema claro (o DESIGN.md marcava como "validar") |

## Fatos que sustentam o plano

- PHP 8.3.19 local; Node 22.18; MariaDB 11.4 na 3306; VPS com Node 22+ e MariaDB 11.x (informado pelo usuario).
- **Pest 5 exige PHP ^8.4** -> pin **Pest 4.7.x + pest-plugin-laravel 4.1.x**.
- **TypeScript `latest` = 7.0.2** e quebra o vue-tsc (compilador nativo, sem `lib/typescript.js`) -> pin **`typescript ~5.9.3`**.
- Kit tagueado `v1.0.2` = **Laravel 12**; Laravel 13 so em `main` -> bootstrap por `dev-main` (scaffold de uso unico, versoes travadas no lockfile).
- `laravel/fortify` **exige** `laravel/passkeys` -> passkeys/2FA sao desligados no config, nao removidos do composer.
- Composer local 2.9.5 com advisory (corrigido em 2.10.3).

## Plano de execucao (aprovado)

### 1. Preparacao (docs e regra)
- [x] Adicionar ao `CLAUDE.md` a regra de git (nenhum commit/push; gitpr com aprovacao).
- [x] Gravar a memoria de trabalho (commits so via gitpr).
- [x] Criar `docs/acceptance/FASE-0.md` com os criterios de aceite.
- [x] Reconstruir `docs/SPEC.md` §11 e remover a referencia morta a "Q1".

### 2. Bootstrap
- [ ] `composer create-project laravel/vue-starter-kit:dev-main` em diretorio temporario e mover o conteudo para a raiz do repo **sem tocar** em `.git/`, `docs/`, `deploy/`, `CLAUDE.md`.
- [ ] `composer.json` (`name: natanfiuza/devtools`), `.env.example` (`DB_CONNECTION=mysql`, `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION=database`, `INERTIA_SSR_ENABLED`, chaves de IA vazias).
- [ ] Local: banco/usuario `devtools` no MariaDB (SQL entregue ao usuario; senha direto no `.env`).

### 3. Toolchain (Pest / Vitest / TS)
- [ ] Pest 4 + `pest-plugin-laravel` 4.1; `tests/Pest.php`; converter os testes do kit; script `composer test`.
- [ ] Pin `typescript ~5.9.3`; `types:check` (`vue-tsc --noEmit` apos gerar o wayfinder).
- [ ] Remover `vite-plus`; scripts `dev`, `build`, `build:ssr`, `dev:ssr` em `vite` puro; ESLint 9 (flat) + Prettier leves.
- [ ] Vitest 5 + `@vue/test-utils` + `jsdom`; script `npm run test`.
- [ ] `tsconfig` estrito (`strict`, `noUncheckedIndexedAccess`, `noImplicitOverride`).

### 4. Design system (tokens + componentes)
- [ ] Reescrever `resources/css/app.css` com os tokens do `DESIGN.md` (dark-first + tema claro) em `@theme inline`; sem sombras; foco 2px `--accent`.
- [ ] **Validar AA do tema claro** e anotar os ratios no `DESIGN.md`.
- [ ] Fontes self-hosted no build: Inter + JetBrains Mono.
- [ ] `/styleguide` nos dois temas; wordmark `devtools_` em SVG; favicon.

### 5. i18n (5 idiomas)
- [ ] `lang/{en,pt_BR,pt_PT,fr,es}/*.php` com namespaces base; mapear `pt_BR`/`pt_PT` (dir) para `pt-br`/`pt-pt` (URL).
- [ ] Middleware de locale: prefixo da URL -> `user.locale` -> cookie -> `Accept-Language` -> `en`; `/` redireciona para `/en`; `/api`, `/webhooks` e `/admin` fora do prefixo.
- [ ] Shared props: `locale`, `availableLocales` e mensagens **so do locale ativo, por namespace**; vue-i18n em `app.ts` e `ssr.ts`.
- [ ] Seletor de idioma no header (persiste) + `hreflang` no head.
- [ ] Testes: paridade de chaves entre os 5 idiomas; resolucao/redirect de locale; composable (Vitest).

### 6. Tema claro/escuro
- [ ] Persistencia: usuario autenticado (coluna) + cookie para anonimo; `prefers-color-scheme` como padrao.
- [ ] **Sem flash**: classe do `<html>` resolvida no servidor a partir do cookie.
- [ ] Pagina Appearance adaptada (claro/escuro/sistema), i18n'd.
- [ ] Vitest de contraste WCAG dos tokens nos dois temas.
- [ ] **ADR-021** (tema claro/escuro + estrategia anti-FOUC).

### 7. Layout
- [ ] `AppLayout` conforme DESIGN (container 1120px, header 56px sticky com blur, wordmark, links, seletor de idioma, placeholder do Cmd+K, Login; footer minimo).
- [ ] Home **placeholder** (a home real e a F1); teclado, focus ring, aria-labels, mobile empilhado.
- [ ] Nenhuma string hardcoded.

### 8. Deploy e infra (arquivos)
- [ ] `deploy/deploy.sh`: PATH explicito; fetch + comparacao de HEAD + `pull --ff-only`; `composer install --no-dev`; `npm ci` **so quando o lockfile mudar**; build do Vite em diretorio temporario + **troca atomica**; `migrate --force`; `optimize`; `queue:restart`; reinicio do SSR (kill + `autorestart`); `command -v node` com erro claro.
- [ ] `deploy/supervisor/devtools-ssr.conf` (novo) e revisao do conf do worker.
- [ ] `README.md` (setup local) e `docs/RUNBOOK.md` (deploy, fila, SSR, backup, rotacao de chaves).

### 9. Fechamento da fase
- [ ] `composer test` + `npm run test` + `npm run build` + `npm run build:ssr` verdes.
- [ ] Smoke manual (idioma, tema, 5 URLs).
- [ ] Docs atualizados: `SPEC.md` §11, `ADR-014`, `ADR-021`, `DESIGN.md`, `CHANGELOG.md`.
- [ ] **Parar para revisao** — sem commit.

## Criterios de aceite

Ver `docs/acceptance/FASE-0.md`.

## Riscos

- **`dev-main` do kit e alvo movel** -> scaffold de uso unico; fallback: `laravel/laravel` 13.x + montagem manual.
- **Pest 5 incompativel com PHP 8.3** -> Pest 4 obrigatorio.
- **SSR ligado sem processo node** derruba as paginas -> gate `INERTIA_SSR_ENABLED`.
- **Composer 2.9.5 com advisory** -> sugerido `composer self-update` (acao global, precisa de ok do usuario).

## Herdado para outras fases

- F1: registro de tools, home real, Cmd+K, banner LGPD, GA, sitemap, OG images.
- F2: conflito do `DESIGN.md` sobre `--elevated` como cor de pontuacao JSON.
- F5: conflito `ADR-011` `dialetos` x SPEC `supported_dialects(json)` + `sort`.
- F7: SEO final e o que sobrar do hardening de tema.
