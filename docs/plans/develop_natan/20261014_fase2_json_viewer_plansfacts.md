# F2 — JSON Viewer, rota da tool e imagem OG por tool (execucao do `docs/SPEC.md`)

Sessao de execucao (pedido permanente: "continue para as proximas fases"),
plano aprovado pelo usuario antes do primeiro bloco. Branch `develop_natan`.

## Context

A F1 esta commitada (`1f00431`). A **F2** e a proxima fase da secao 9 do SPEC:
*"F2 JSON Viewer (com CodeMirror 6 e testes)"* — mais o que a F1 deixou
explicitamente para ca: a **rota `/{locale}/tools/{slug}`** (SPEC secao 4) e a
**imagem OG por tool** (DESIGN, entregavel 5; SPEC secao 11.1).

Antes: as 8 tools estavam registradas com `status: "soon"`, **nenhuma
funcionava**, nao existia rota de tool, `resources/js/tools/json-viewer/` tinha
so o `manifest.json` (nenhum componente), **CodeMirror nao estava instalado**,
nao havia lib de virtualizacao e a unica imagem de compartilhamento era a
padrao (`public/og-default.png`).

**Resultado esperado**: `json-viewer` vira a primeira tool `available` — card da
home com link, entrada no Cmd+K, presenca no sitemap e pagina propria com SSR —
e essa pagina e um **shell generico** (2 paineis redimensionaveis, barra de
acoes, atalhos visiveis, abas no mobile) onde qualquer tool se encaixa: a F3
acrescenta o componente e nada mais.

## Decisoes confirmadas pelo usuario (todas "Recomendado")

| # | Decisao |
|---|---|
| 1 | O painel de saida e a **arvore** (sem abas Arvore/Formatado): formatar, minificar e ordenar agem no texto da esquerda |
| 2 | "Abrir arquivo" e arrastar-e-soltar entram na F2 (lidos com `FileReader`; nada sai do navegador) |
| 3 | `Tabs` do shadcn-vue vendorizado (`resources/js/components/ui/tabs/`) para as abas do mobile — reka-ui ja era dependencia, nenhum pacote novo |
| 4 | `--json-punct` ganha valor proprio nos dois temas; o item da SPEC 11.2 fecha e o DESIGN passa a citar o valor real |

Decisoes de mecanismo tomadas junto (registradas no relatorio da fase):

- **CodeMirror 6 minimo** — `@codemirror/state`, `view`, `language`, `commands`,
  `lang-json`, `lint`, `search`, `autocomplete` e `@lezer/highlight`, em chunk
  proprio, carregado so na pagina da tool (ADR-018).
- **A pagina da tool e generica**: `pages/tools/Show.vue` + shell compartilhado,
  e cada tool traz o proprio componente em `resources/js/tools/<slug>/Tool.vue`,
  resolvido por glob **lazy** (ADR-029).
- **Virtualizacao propria** (janela sobre linhas de altura uniforme), com a
  conta numa funcao pura testavel — sem dependencia nova.
- **Parse no Web Worker**, com queda para a thread principal quando nao ha
  `Worker` — que e o caminho exercitado no jsdom.
- **Slug desconhecido, `soon` e `hidden` = 404** (ADR-007).
- **O SSR entrega a casca e as metas**; editor e arvore montam no cliente
  (`ClientOnly`).
- **A imagem OG por tool e decidida no servidor** (`Seo::openGraph()` le a
  rota); sem PNG gerado, cai na padrao (ADR-030).

## Blocos de execucao

| # | Bloco | Estado |
|---|---|---|
| 0 | `docs/acceptance/FASE-2.md` com os criterios da fase | concluido |
| 1 | Rota, `ToolController`, `config/tools.php`, `pages/tools/Show.vue`, `ClientOnly`, layout e registro | concluido |
| 2 | Shell da tool: `ToolShell`/`ToolActionBar`/`ToolButton`, `useSplitPane`, `Tabs` vendorizado, i18n `tools.ui.*` | concluido |
| 3 | Editor: `createEditor.ts` e `theme.ts` (tokens `--json-*`), `JsonEditor.vue` | concluido |
| 4 | Funcoes puras: `parse`, `tree`, `path`, `transform`, `window`, `bytes`, `analyze`, `download` | concluido |
| 5 | Web Worker: `parser.worker.ts`, `parser.workerHost.ts`, `parseClient.ts` (queda para a thread principal) | concluido |
| 6 | Workspace do JSON Viewer: `Tool.vue`, `JsonTree.vue`, `TreeRow.vue`, i18n `tools.json-viewer.*` | concluido |
| 7 | `status: "available"` e a atualizacao dos testes da F1 (home, registry) | concluido |
| 8 | Imagem OG por tool: comando, `config/seo.php`, `Seo::openGraph()`, `public/og/json-viewer.png` | concluido |
| 9 | Gates, smoke com SSR, docs (SPEC, ADR-018/029/030, DESIGN, glossario, CHANGELOG), aceite e relatorio | concluido |

## Arquivos criticos

Novos — `app/Http/Controllers/ToolController.php`,
`resources/js/pages/tools/Show.vue`, `resources/js/components/ClientOnly.vue`,
`resources/js/components/tool/{ToolShell,ToolActionBar,ToolButton}.vue`,
`resources/js/components/ui/tabs/**`, `resources/js/composables/useSplitPane.ts`,
`resources/js/tools/components.ts`, `resources/js/tools/json-viewer/{Tool,
JsonTree,TreeRow}.vue`, `resources/js/tools/json-viewer/editor/{JsonEditor.vue,
createEditor.ts,theme.ts}`, `resources/js/tools/json-viewer/lib/{parse,tree,path,
transform,window,bytes,analyze,download,parseClient,parser.worker,
parser.workerHost,parser.protocol,types}.ts`, `public/og/json-viewer.png`,
`docs/acceptance/FASE-2.md`, `docs/adr/ADR-029-*.md`, `docs/adr/ADR-030-*.md` e os
11 arquivos de teste de Vitest da fase.

Alterados — `routes/web.php`, `config/{tools,seo}.php`, `app/Support/{Seo,Tools,
Sitemap}.php`, `app/Console/Commands/GenerateOgImage.php`,
`resources/js/tools/json-viewer/manifest.json`,
`resources/js/components/ToolCard.vue`, `resources/js/lib/inertiaApp.ts`,
`resources/css/app.css` (`--json-punct`), `resources/views/app.blade.php`,
`lang/*/tools.php` (5 idiomas), `vite.config.ts` (formato do worker),
`.env.example` (`TOOLS_MAX_INPUT_BYTES`), `package.json` e os testes da F1
(`home.test.ts`, `tools-registry.test.ts`, `SitemapTest`, `SeoMetaTest`,
`ToolsRegistryTest`).

## Riscos / pontos de atencao

- **CodeMirror no bundle de SSR**: o componente da tool so entra por glob lazy e
  so renderiza no cliente; o `build:ssr` continuou verde (primeiro item
  conferido depois de instalar os pacotes).
- **Worker no build**: `new Worker(new URL(...), { type: 'module' })` exige
  `worker.format = 'es'` no Vite; sem isso o build sai em `iife` e o worker
  quebra.
- **`var(--json-*)` no tema do CodeMirror**: o `style-mod` emite o valor como
  veio, entao o token funciona sem fallback.
- **`SitemapTest`**: com a rota real existindo, o teste deixou de registrar uma
  rota de fixture e passou a usar `tools.path` com a fixture do registro.
- **`--json-punct` literal** nao quebrou o `design-tokens.test.ts`: o contraste
  contra `--surface` passa nos dois temas.
- **Traducoes novas em 5 idiomas** (`tools.ui.*` e `tools.json-viewer.*`): a
  paridade e automatica, a qualidade do texto fica para a revisao.
- O `eslint` continua fora de controle no baseline (achado das rodadas
  anteriores, sem mudanca aqui).
