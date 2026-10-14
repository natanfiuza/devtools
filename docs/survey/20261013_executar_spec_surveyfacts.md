# Survey — execucao do `docs/SPEC.md` (F0)

- **Data**: 2026-10-13
- **Branch**: master
- **Task**: executar o plano `docs/SPEC.md`, comecando pela F0
- **Metodo**: grilling em 4 rodadas + reconhecimento do ambiente e do ecossistema

## 1. Contexto da tarefa

Repo com apenas documentacao (26 arquivos), zero commits, branch `master`, sem remote. SPEC v1.0 com 8 fases (F0–F7), nenhuma linha de codigo escrita. CLAUDE.md manda trabalhar por fase, terminar com testes verdes e parar para revisao.

## 2. Fatos levantados

### 2.1 Maquina local (sem bloqueios)

| Item | Valor |
|---|---|
| PHP | 8.3.19 (`C:\tools\php8.3`), todas as extensoes exigidas pelo Laravel 13 + `pdo_mysql`, `zip`, `curl`, `gd`, `imagick`, `intl`. **Sem** `redis` |
| Composer | 2.9.5 (desatualizado; 8 advisories, corrigidos em 2.10.3) |
| Node / npm | 22.18.0 / 11.5.2 |
| Laravel Installer | 5.2.1 (**antigo**: nao tem `--vue`) |
| git | 2.55.0.windows.5 |
| Banco | MariaDB 11.4.2 rodando na 3306 (servico `MariaDB`); cliente `mysql`/`mariadb` **fora do PATH**; `my.ini` lido e o da pasta 11.2 |
| Docker / Redis | ausentes (ADR-019 ja preve fila `database`) |
| Repo | sem `composer.json`/`package.json`/`artisan`/`.env`; zero commits; sem remote |

### 2.2 Ecossistema (registries acessiveis)

- Laravel 13 existe: `laravel/laravel` v13.11.0, `laravel/framework` v13.36.0.
- Pest 5.3.2 (**exige PHP ^8.4** — nao roda no PHP 8.3 local) x Pest 4.7.8 / pest-plugin-laravel 4.1.0 (PHP ^8.3, Laravel 13 OK).
- TypeScript `latest` = **7.0.2** (compilador nativo, sem `lib/typescript.js`, que o vue-tsc/volar carrega) -> pin `~5.9.3`.
- Tailwind 4.3.3, shadcn-vue 2.8.2 (reka-ui), Vue 3.5.44, `@inertiajs/vue3` 3.9.1, vue-i18n 11.4.14, Vite 8.3.4, Vitest 5.0.4, vue-tsc 3.3.13.

### 2.3 Starter kit oficial (`laravel/vue-starter-kit`)

- **Tag `v1.0.2` = Laravel 12** (Inertia 2, Ziggy, PHPUnit 11). **Laravel 13 apenas na `main`** (`laravel/framework ^13.17`, Inertia 3, Fortify, wayfinder, Tailwind 4, Vite 8).
- Entrega: shadcn-vue vendorizado (`reka-ui`), Fortify + **passkeys + 2FA**, SSR cabeado (`config/inertia.php` `ssr.enabled=true`, script `build:ssr`, sem entry proprio — `@inertiajs/vite` cai no fallback `app.ts`), `phpunit.xml` com **SQLite `:memory:`**, `.gitignore` ignorando `/public/build` e `/bootstrap/ssr` (compativel com build no servidor).
- **Nao** entrega: i18n (nenhum `vue-i18n`, nenhum `lang/`), Pest (usa PHPUnit 12), Vitest.
- Toolchain incomum: **`vite-plus`** (scripts `vp build`/`vp dev`, pin exato `0.3.0`) e **wayfinder** (gera `resources/js/{actions,routes,wayfinder}`, gitignored).
- `laravel/fortify` 1.41 **exige** `laravel/passkeys` -> nao da para remover o pacote mantendo Fortify; desliga-se no config.

### 2.4 Documentacao — buracos e contradicoes

- `docs/SPEC.md:94` — secao 11 ("Perguntas abertas") **vazia**, delegando para fora do repo ("ver resposta ao usuario"). Uma pergunta citada (`Q1`, `docs/SPEC.md:43`, cache hit nao debita cota) e **indefinida em qualquer lugar**. Q2/Q3 nao existem.
- `ADR-011` diz `dialetos` x `SPEC.md:60` diz `supported_dialects(json)` + `sort`.
- `DESIGN.md:5` proibe `--elevated` em texto pequeno, mas `DESIGN.md:16` usa `#5d707f` como cor de pontuacao JSON.
- `deploy/deploy.sh` **nao tem passo de assets** e puxa `origin main`; repo local em `master`, sem remote.
- Citados e inexistentes: `docs/acceptance/FASE-N.md`, README, `docs/ARQUITETURA.md`, `docs/API.md`, `docs/RUNBOOK.md`, `CHANGELOG.md`.

## 3. Decisoes das rodadas

### Rodada 1
- **Escopo**: somente a F0 nesta sessao; parada para revisao ao fim.
- **SPEC §11**: regras ja escritas valem como fechadas; o assistente reconstroi a secao 11 e remove a referencia morta a "Q1". (Resposta do usuario, opcao "Reconstruir no SPEC".)
- **Git**: **o assistente nunca commita/pushа**; commits e PR pelo usuario via **gitpr**, com aprovacao de cada um; pedir intervencao manual quando necessario. Regra a gravar no `CLAUDE.md`.
- **Assets**: **build no servidor** (escolha do usuario, contra a recomendacao de versionar `public/build`).

### Rodada 2
- **Banco**: SQLite `:memory:` nos testes (+ grupo opcional `mysql`) e MariaDB `devtools` no dev local.
- **Bootstrap**: kit oficial + enxugar (Pest 4, vue-i18n, tokens do DESIGN.md, passkeys/2FA desligados no config, `vite-plus` -> `vite` puro; mantidos Fortify, shadcn-vue e wayfinder).
- **i18n**: fonte unica em `lang/{locale}/*.php`, exposta ao Inertia escopada por namespace.

### Rodada 3
- **VPS tem Node 22+ e npm** (informado pelo usuario) e roda **MariaDB 11.x** — o MariaDB 11.4 local e fiel ao prod.
- **Wayfinder**: manter (rotas tipadas), com composable que injeta o locale.
- **Docs**: portugues **sem acentos**, como SPEC/ADRs/CLAUDE.md.

### Rodada 4
- **SSR**: cabear na F0 e **ligar em producao**; local desligado por padrao (`INERTIA_SSR_ENABLED`).
- **Tema**: **toggle claro/escuro/sistema ja na F0** (escolha do usuario, contra a recomendacao de dark fixo), o que puxa para a fase a validacao AA do tema claro.

### Perguntas respondidas e pendentes

- **Respondidas**: as 12 acima.
- **Pendentes (nao bloqueiam a F0, entram no RUNBOOK)**: PHP 8.3 no VPS; usuario do cron do deploy; se o assistente tem acesso SSH ao VPS; se o usuario autoriza `composer self-update` (advisory alto na 2.9.5).

## 4. Entregaveis desta sessao

- `docs/plans/master/20261013_executar_spec_plansfacts.md` (plano aprovado)
- `docs/survey/20261013_executar_spec_surveyfacts.md` (este arquivo)
- Regra de git no `CLAUDE.md`
- `docs/acceptance/FASE-0.md` + `docs/SPEC.md` §11 reconstruida
- F0 implementada (sem commit)
