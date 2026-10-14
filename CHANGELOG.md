# CHANGELOG - devtools.natanfiuza.dev.br

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versoes por fase do [SPEC](docs/SPEC.md); datas em AAAA-MM-DD.

## F0 - Fundacao - 2026-10-13

### Adicionado

- Projeto Laravel 13 + Inertia 3 + Vue 3 + TypeScript estrito sobre o starter
  kit oficial do Laravel, com Fortify. `public/build` e `bootstrap/ssr` sao
  artefatos de build (nao vao para o git).
- Tokens do DESIGN.md em `resources/css/app.css` (Tailwind 4, dark-first) com
  tema claro/escuro/sistema, e `/styleguide` com os componentes nos dois temas.
- i18n em 5 idiomas (en, pt-BR, pt-PT, fr, es): traducoes em
  `lang/{locale}/*.php`, expostas ao Inertia por namespace e consumidas pelo
  vue-i18n; rotas publicas com prefixo de idioma, `/` resolve e redireciona,
  seletor no header, `hreflang` e `canonical` no head.
- Tema resolvido no servidor (cookie do visitante, coluna do usuario, sistema
  como padrao) para a pagina chegar sem flash; `/settings/appearance` com os
  tres estados e i18n.
- SSR da Inertia cabeado (cliente + servidor com `registerDirectives`
  compartilhado) e ligado em producao pelo gate `INERTIA_SSR_ENABLED`.
- Testes: Pest 4 (paridade de traducoes, roteamento de locale, tema,
  autenticacao do kit) e Vitest 5 (i18n, tema, contraste AA dos tokens).
- Deploy: `deploy/deploy.sh` (flock, `--ff-only`, `npm ci` condicionado ao
  lockfile, build em release propria com troca atomica de symlink, 3 releases
  guardadas, `migrate --force`, `optimize`, `queue:restart`, reinicio do SSR),
  `deploy/supervisor/devtools-worker.conf` (revisado) e
  `deploy/supervisor/devtools-ssr.conf` (novo).
- Documentacao: `README.md` (setup local), `docs/RUNBOOK.md` (deploy, fila,
  SSR, backup, rotacao de chaves, diagnostico), `docs/acceptance/FASE-0.md`,
  ADR-021 (tema e anti-FOUC) e ADR-014 revisado (assets no servidor).

### Decidido

- Assets construidos no servidor, em release propria com troca atomica de
  symlink (ADR-014).
- Cache hit do AI SQL Builder (F5) nao debita cota; ver SPEC secao 11.1.
- Documentacao do repo em portugues sem acentos; strings de interface com
  acentuacao correta.

### Fora do escopo (proximas fases)

- Home real, registro de tools, Cmd+K, banner LGPD, GA4, sitemap, OG images (F1).
- JSON Viewer com CodeMirror, tools simples (F2/F3).
- Auth completo (OAuth, verificacao, newsletter) e `users.appearance`
  gravado pela conta (F4).
- AI SQL Builder, planos, pagamentos e admin (F5 a F7).
