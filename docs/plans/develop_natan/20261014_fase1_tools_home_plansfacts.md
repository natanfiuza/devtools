# F1 — Registro de tools, home real, busca Cmd+K, LGPD/GA (execucao do `docs/SPEC.md`)

Sessao de execucao (pedido permanente: "continue para as proximas fases"),
plano aprovado pelo usuario antes do primeiro bloco. Branch `develop_natan`.

## Context

A F0 esta commitada (`c23bcef`). A **F1** e a proxima fase da secao 9 do SPEC:
*"Registro de tools + home + busca Cmd+K + banner LGPD + GA"* — mais o que a
propria F0 deixou para ca (sitemap e OG images).

Antes: `Home.vue` era placeholder, `SearchTrigger.vue` era um botao
`aria-disabled` com titulo "search_coming_soon", **nao existia registro de
tools**, `config/analytics.php` nao existia, nao havia uma linha de
consentimento/GA, `public/robots.txt` era estatico e permissivo e nao havia
sitemap nem OG.

**Resultado esperado**: home de verdade (grid, chips, destaque do AI SQL Builder
e precos lidos da tabela `plans`), palette Cmd+K sobre o registro, banner de
consentimento com GA4 carregado **so apos aceite**, sitemap/robots servidos pela
aplicacao e tags OG. As 8 tools do MVP entram registradas com `status: "soon"`:
grid, busca, sitemap e testes ja sao reais, e cada fase seguinte so troca o
status.

## Decisoes confirmadas pelo usuario (todas "Recomendado")

| # | Decisao |
|---|---|
| 1 | Home com as 8 tools do MVP registradas como `soon` (selo "em breve", card sem link); o Cmd+K encontra todas e o sitemap lista so as `available` |
| 2 | Fonte unica do registro em `resources/js/tools/<slug>/manifest.json`, lida pelo TS (Vite `import.meta.glob`) e pelo PHP (sitemap) — atualiza a SPEC secao 4, que dizia `manifest.ts` |
| 3 | F1 entrega tags OG + **uma imagem OG padrao do site**; a geracao por tool fica para a F2/F3 |
| 4 | A home ganha secao de precos lendo a tabela `plans` (mesmos dados do painel; nada de preco no codigo) |

Decisoes de mecanismo tomadas junto: a rota `/{locale}/tools/{slug}` entra na
**F2** (em F1 nada e `available`, entao nao ha link quebrado); `styleguide` sai
do indice e do sitemap; o GA e injetado **so no cliente** (nada de blade), com a
decisao do banner resolvida no servidor para nao piscar — mesmo padrao do tema
(ADR-021).

## Blocos de execucao

| # | Bloco | Estado |
|---|---|---|
| 0 | `docs/acceptance/FASE-1.md` com os criterios da fase | concluido |
| 1 | Registro de tools: 8 manifestos JSON, `registry.ts`, `categories.ts`, `icons.ts`, `App\Support\Tools`, `lang/*/tools.php` | concluido |
| 2 | Busca Cmd+K: `CommandPalette`, `useCommandPalette`, `SearchTrigger` real, i18n `app.search` | concluido |
| 3 | Home real: `HomeController`, `ToolCard`/`ToolGrid`/`PricingSection`, chips, destaque, precos da tabela | concluido |
| 4 | Consentimento LGPD + GA4: `config/analytics.php`, `Consent`/`Analytics`, `HandleConsent`, banner, tag no cliente | concluido |
| 5 | Meta/OG: `Seo::openGraph`, `app.blade.php`, `PageHead`, comando `seo:og-image` e `public/og-default.png` | concluido |
| 6 | Sitemap e robots servidos pela aplicacao; `public/robots.txt` apagado | concluido |
| 7 | Gates, smoke com SSR, docs (SPEC/ADR-007/ADR-013/DESIGN/CHANGELOG), aceite e relatorio | concluido |

## Arquivos criticos

Novos — `resources/js/tools/**` (8 manifestos, `registry.ts`, `categories.ts`,
`icons.ts`, `types.ts`), `resources/js/components/{CommandPalette,ConsentBanner,
AnalyticsScript,ToolCard,ToolGrid,PricingSection,PageHead}.vue`,
`resources/js/composables/{useCommandPalette,useConsent}.ts`,
`resources/js/lib/{analytics,platform}.ts`, `app/Support/{Tools,Sitemap,Consent,
Analytics}.php`, `app/Http/Controllers/{HomeController,RobotsController,
SitemapController}.php`, `app/Http/Middleware/HandleConsent.php`,
`app/Console/Commands/GenerateOgImage.php`, `config/{tools,analytics,seo}.php`,
5× `lang/*/tools.php`, `resources/views/sitemap.blade.php`,
`public/og-default.png`.

Alterados — `Home.vue`, `PublicLayout.vue`, `AuthLayout.vue`, `SearchTrigger.vue`,
`Privacy.vue`, `Terms.vue`, `routes/web.php`, `resources/views/app.blade.php`,
`app/Http/Middleware/HandleInertiaRequests.php`, `app/Support/{Seo,Format}.php`,
`bootstrap/app.php`, `config/locales.php`, `config/legal.php`, `lang/*/home.php`
(reescrito), `lang/*/app.php`, `lang/*/legal.php`, `.env.example`,
`tests/Feature/LocaleRoutingTest.php`.

Apagado — `public/robots.txt`.

## Riscos / pontos de atencao

- O registro em JSON muda uma linha da SPEC (secao 4) — o ADR-007 foi atualizado
  junto, senao a documentacao mente.
- `import.meta.glob` de JSON fora de `resources/js` seria um problema; por isso
  os manifestos ficam dentro de `resources/js/tools/` (o diretorio que a SPEC ja
  define) e o PHP le o mesmo caminho.
- Precos e limites so da tabela; nenhum valor no codigo. Numeros formatados no
  servidor com `App\Support\Format`.
- GA nao e testavel de ponta a ponta daqui (sem id real): a verificacao e por
  teste de componente e pela prop; o id real entra no `.env` do usuario.
- O eslint continua fora de controle no baseline (achado registrado na rodada
  anterior, sem mudanca aqui).
