# Relatorio - F2: JSON Viewer, pagina generica da tool e imagem OG por tool

Branch: `develop_natan`
Data: 2026-10-14
Tarefa: "De continuidade as proximas fases do projeto" (F2 da secao 9 do SPEC)
Plano: `docs/plans/develop_natan/20261014_fase2_json_viewer_plansfacts.md`
Aceite: `docs/acceptance/FASE-2.md`

## 1. O que foi entregue

A F2 da secao 9 do SPEC — o **JSON Viewer** com CodeMirror 6 — mais as duas
heranças que a F1 tinha deixado explicitamente para ca: a **rota
`/{locale}/tools/{slug}`** (com a pagina da tool) e a **imagem OG por tool**.
Dez blocos, todos concluidos.

| # | Bloco | Estado |
|---|---|---|
| 0 | `docs/acceptance/FASE-2.md` com os criterios da fase | concluido |
| 1 | Rota, `ToolController`, `config/tools.php`, `pages/tools/Show.vue`, `ClientOnly`, layout e registro | concluido |
| 2 | Shell da tool: `ToolShell`/`ToolActionBar`/`ToolButton`, `useSplitPane`, `Tabs` vendorizado, i18n `tools.ui.*` | concluido |
| 3 | Editor: `createEditor.ts` e `theme.ts` (tokens `--json-*`), `JsonEditor.vue` | concluido |
| 4 | Funcoes puras: `parse`, `tree`, `path`, `transform`, `window`, `bytes`, `analyze`, `download` | concluido |
| 5 | Web Worker: `parser.worker.ts`, `parser.workerHost.ts`, `parseClient.ts` (queda para a thread principal) | concluido |
| 6 | Workspace do JSON Viewer: `Tool.vue`, `JsonTree.vue`, `TreeRow.vue`, i18n `tools.json-viewer.*` | concluido |
| 7 | `status: "available"` e a atualizacao dos testes da F1 (home, registry, sitemap) | concluido |
| 8 | Imagem OG por tool: comando, `config/seo.php`, `Seo::openGraph()`, `public/og/json-viewer.png` | concluido |
| 9 | Gates, smoke com SSR, docs (SPEC, ADR-018/029/030, DESIGN, glossario, CHANGELOG), aceite e relatorio | concluido |

O `json-viewer` e a **primeira tool `available`**: o card da home passa a
linkar, o Cmd+K a abrir a pagina e o sitemap a listar nos 5 idiomas. E a pagina
onde ela abre e **generica** — a F3 acrescenta ferramentas criando um
`Tool.vue`, virando o status e escrevendo as traducoes, sem tocar em rota,
pagina ou shell.

## 2. Decisoes novas (ADRs)

- **ADR-018 - editor (revisado)**: o ADR era um esboco de duas linhas ("CodeMirror
  6, lazy por tool") e ganhou o que a fase decidiu de verdade — o **conjunto
  minimo de pacotes** (oito `@codemirror/*` + `@lezer/highlight`, sem o
  meta-pacote `codemirror`/`basicSetup`, que traz o painel de busca com rotulos
  fixos em ingles), o **tema pelos tokens** (`HighlightStyle` mapeando
  `tags.*` para `var(--json-*)`, sem paleta paralela e sem JS de tema, porque o
  tema do projeto e uma classe no documento e nao a preferencia do sistema) e o
  **parse em Web Worker com queda** para a thread principal. Alternativas
  descartadas registradas: CodeMirror 5, Monaco, `textarea` com destaque por
  cima, `indentWithTab`/`searchKeymap` e exigir worker sem queda (o caminho de
  queda e o unico exercitavel em jsdom).
- **ADR-029 - pagina generica da tool** (novo): rota unica
  `/{locale}/tools/{slug}`, com o servidor decidindo se a pagina existe (slug
  desconhecido, `soon` e `hidden` dao o **mesmo 404**), cabecalho e shell
  compartilhados e o componente da tool vindo de `tools/<slug>/Tool.vue` por
  glob **lazy**. Registra tambem as duas props (`slug`, `maxInputBytes`), o
  divisor arrastavel com `role="separator"` e as abas do mobile com
  `force-mount`.
- **ADR-030 - imagem OG por tool** (novo): uma imagem por tool `available`, em
  `public/og/<slug>.png`, desenhada pelo mesmo comando da padrao e **sem frase
  em idioma nenhum** — o nome traduzido da ferramenta vai no `og:image:alt` —,
  com a escolha no **servidor** (`Seo::openGraph()`) e queda para a imagem
  padrao quando o arquivo nao existe.

Decisoes menores, na SPEC secao 11.1: a rota/pagina da tool, a imagem por tool e
a pagina cair na padrao sem PNG proprio. O item da **11.2 sobre o `--elevated`
como cor de pontuacao do JSON fechou**: `--json-punct` passou a ter valor
proprio nos dois temas (`#46545f` no claro, `#71828e` no escuro) e o DESIGN cita
o valor real.

## 3. Banco de dados

**Nenhuma migration nesta fase.** O deploy da F2 e codigo + um PNG novo em
`public/og/` — nada para o `migrate --force` do cron aplicar, e nenhuma janela
de indisponibilidade.

## 4. Rotas

**Nova**: `GET /{locale}/tools/{slug}` (`tools.show`), dentro do grupo de
idioma, apontando para `ToolController` (invokable). E a primeira rota publica
de conteudo criada fora da F1 — o `Sitemap::urls()` ja perguntava por
`Route::has('tools.show')` desde a F1, entao o sitemap passou a listar a tool
sem nenhuma mudanca estrutural.

**Inalteradas**: home, Privacidade, Termos, styleguide, area autenticada,
`/admin`, `/sitemap.xml` e `/robots.txt`.

## 5. Telas e componentes

- **Pagina da tool** (`pages/tools/Show.vue`): breadcrumb, cabecalho (icone,
  nome, descricao e `intro` do registro), `PageHead` com as metas e o
  componente da tool em `ClientOnly` por glob lazy. Ela nao conhece ferramenta
  nenhuma.
- **`ClientOnly.vue`**: monta o filho so no cliente, para o SSR nao tentar
  criar CodeMirror nem Worker.
- **Shell** (`components/tool/ToolShell.vue`): barra de acoes, dois paineis com
  divisor arrastavel (`useSplitPane`: arrasto por ponteiro, setas do teclado,
  duplo clique restaura, `localStorage` por `splitKey`), faixa de status e de
  atalhos; abaixo de 768px, `Tabs` com `force-mount` — trocar de aba no celular
  nao pode desmontar o editor (o breakpoint e lido em JS justamente para existir
  uma unica instancia).
- **`Tabs` do shadcn-vue** vendorizado em `components/ui/tabs/` (padrao
  new-york-v4, como os outros 24 primitivos) — reka-ui ja era dependencia, sem
  pacote novo para o layout.
- **JSON Viewer** (`tools/json-viewer/Tool.vue`): barra com exemplo, abrir
  arquivo, limpar, copiar e baixar; no cabecalho da entrada, formatar,
  minificar e ordenar chaves; no da saida, busca e expandir/recolher tudo;
  editor CodeMirror com realce por token, erro marcado na linha e Ctrl+Z; arvore
  com colapso por no, copia do caminho JSONPath, contagem de chaves/itens e
  tamanho; arrastar-e-soltar com sobreposicao; faixa de estado (vazio, vazio por
  limite, erro com linha e coluna) e de atalhos.
- **`ToolCard.vue`** passa a ser `Link` quando `toolPath()` devolve caminho (o
  docblock do componente ja previa esse momento na F1).

## 6. O JSON Viewer, em numeros

53 chaves de traducao novas em cada um dos 5 `lang/*/tools.php` (`tools.ui.*`
para o que o shell compartilha e `tools.json-viewer.*` para o que e so desta
ferramenta), 13 modulos em `lib/` (oito de logica pura, tres do worker e dois de
tipos/protocolo), tres componentes de editor/arvore, 11 arquivos de teste de
Vitest novos e 10 testes de Pest a mais. O bundle final isola o CodeMirror em
`Tool-CFspuwfK.js` (394,78 kB, 128,43 kB gzip) e o worker em
`parser.worker-Dt5FzRbk.js` (4,5 kB) — nenhum dos dois no chunk da home. No
`build:ssr` o chunk lazy da tool sai junto (89,66 kB, porque o glob faz parte do
grafo), mas o worker nao existe no bundle do servidor e o editor nao executa la
(`ClientOnly`).

## 7. Bugs e descobertas

1. **O `Controller` recebia o idioma no lugar do slug — e a fase inteira dava
   404.** A rota e `{locale}/tools/{slug}` e o metodo era
   `__invoke(string $slug)`: o Laravel preenche parametro **escalar por
   posicao** (`Illuminate\Routing\ResolvesRouteDependencies`), entao `$slug`
   recebia `"en"` e o registro nunca encontrava a tool. O sintoma enganava — a
   rota casava, o `Tools::find('json-viewer')` devolvia `available` e ainda
   assim todo idioma respondia 404. Resolvido lendo o slug **pelo nome**
   (`$request->route('slug')`), com o porque escrito no proprio controller e um
   teste que tenta `..%2F..%2Fcomposer.json` como slug. E a armadilha que vale
   registrar: qualquer rota futura com `{locale}` na frente tem a mesma
   pegadinha.
2. **`force-mount` no `Tabs` do reka-ui inverte a expectativa**: com o painel
   sempre montado, o componente deixa de esconder o inativo (ele so esconde o
   que desmonta) e os dois paineis ficam empilhados na tela. A classe de
   `display` teve que ser escrita no `ToolShell` — e o comentario no arquivo
   explica o porque, senao o proximo a mexer "limpa" a classe e quebra o mobile.
3. **O worker precisa de `worker.format = 'es'` no Vite**: o
   `new Worker(new URL(...), { type: 'module' })` so vale se o build emitir o
   worker como modulo ES; no padrao (`iife`) o `import` de dentro dele quebra.
   Entrou em `vite.config.ts` com o comentario.
4. **O `style-mod` (CodeMirror) aceita `var(--json-*)` sem reclamar** — o risco
   previsto no plano nao se confirmou, entao nao foi preciso um bloco de CSS
   paralelo. O editor segue o tema claro/escuro sozinho, sem uma linha de JS.
5. **PHPStan nivel 7 contra `$request->route()`**: o nullsafe numa expressao
   nao-nulavel virou erro ("Using nullsafe method call on non-nullable type").
   Resolvido subindo o `$route` para uma variavel e chamando `->parameter()`
   direto, sem `ignore` e sem baseline.
6. **A divisao de paineis pediu `pointerdown` e nao `mousedown`**: com o
   ponteiro, o mesmo caminho cobre mouse, toque e caneta — e o teste do shell
   consegue exercitar sem sintetizar eventos de dispositivo.
7. **O glob lazy nao tira o CodeMirror do `build:ssr`** (e eu tinha escrito o
   contrario antes de conferir o bundle): o chunk da tool sai no bundle do
   servidor com 89,66 kB, porque o `import.meta.glob` faz parte do grafo mesmo
   sem ser resolvido no servidor. O **worker** e que nao aparece la (nenhum
   arquivo `parser.worker*` em `bootstrap/ssr/`). O que importa esta de pe: o
   editor **nao executa** no servidor — o `ClientOnly` e o unico caminho de
   montagem e o HTML do SSR nao tem markup do editor (conferido no smoke).

## 8. Desvios do plano (conscientes)

- **A busca da arvore e por chave e valor, nao por caminho JSONPath** (o
  criterio 7 do aceite foi corrigido junto, em vez de ficar valendo um criterio
  que o codigo nao cumpre). O caminho existe — e o que a arvore copia —, mas
  buscar por ele exigiria um segundo parser de JSONPath no cliente, e nao valia
  o peso nesta fase.
- **O painel de saida e a arvore so** (decisao confirmada antes do bloco 1): nao
  ha aba "Formatado", porque formatar/minificar/ordenar agem no texto da
  esquerda e a arvore ja mostra o resultado.
- **Nao entraram** (e nao estavam na linha do SPEC): o painel de busca do
  proprio CodeMirror (Cmd+F continua funcionando, mas aponta para a busca da
  tela — o painel do motor tem rotulo fixo em ingles), `indentWithTab` e
  validacao por schema.
- **A imagem OG padrao da F1 saiu byte a byte igual** da refatoracao
  (`git diff --stat` de `public/og-default.png` vazio): o desenho dela so foi
  extraido para `drawDefault()`.
- **`.gitpr/reports/`**: os dois arquivos de `.gitpr/` que aparecem no
  `git status` sao da rodada anterior do usuario, nao desta fase.

## 9. Verificacao executada

| Comando | Resultado |
|---|---|
| `composer test` (config:clear + Pint + PHPStan nivel 7 + Pest) | Pint limpo, **PHPStan 0 erros**, Pest **266 testes / 6.375 assercoes** |
| `npm run test` (Vitest 5) | **299 testes em 23 arquivos** |
| `npm run types:check` (wayfinder + `vue-tsc --noEmit`) | limpo, TS estrito |
| `npm run build` | exit 0 — CodeMirror em `Tool-CFspuwfK.js` (394,78 kB) e worker em `parser.worker-Dt5FzRbk.js` (4,5 kB) |
| `npm run build:ssr` | exit 0 — sem worker no bundle do servidor; o chunk lazy `Tool-Bx66KMnb.js` (89,66 kB) sai porque o glob faz parte do grafo, mas nao executa (ver secao 7, item 7) |

Contra o fim da F1 (256 testes / 6.011 assercoes e 154 testes em 12 arquivos):
**+10 testes de Pest e +145 de Vitest, em 11 arquivos novos**. Os novos de
Vitest: `json-parse`, `json-tree`, `json-path`, `json-transform`, `json-window`,
`json-worker`, `json-bytes`, `json-editor-theme`, `json-viewer`, `tool-shell` e
`tool-page`; no Pest, `ToolPageTest` novo e `SeoMetaTest`/`SitemapTest`/
`ToolsRegistryTest` estendidos.

## 10. Smoke de SSR

Receita da F1: SQLite descartavel, `php artisan migrate --force` com
`config:clear`, `artisan serve` com `INERTIA_SSR_ENABLED=true` e
`node bootstrap/ssr/ssr.js` no 13714. **Zero erro no log da aplicacao e no do
SSR**; os arquivos temporarios (banco, logs e o HTML salvo) foram apagados
depois.

| URL | HTTP | Observado |
|---|---|---|
| `/{locale}/tools/json-viewer` (5 idiomas) | 200 | 58–62 KB, SSR ligado |
| `/pt-br/tools/nao-existe` e `/pt-br/tools/ai-sql-builder` (`soon`) | 404 | mesmo corpo de erro |
| `/sitemap.xml` | 200 | `application/xml`, **20 URLs** (as 5 da tool dentro) |

Conferido **no HTML do servidor** (nao no DOM): title, description,
`og:title`, `og:description`, canonical, os 6 hreflang,
`og:image` = `/og/json-viewer.png` com o `alt` traduzido, `<h1>JSON Viewer</h1>`
e o breadcrumb; na home, o card do `json-viewer` como `<a>` e os 7 selos; e a
ausencia do editor no HTML do servidor — por desenho, e o que o `ClientOnly`
existe para garantir.

## 11. Nao verificado daqui

- **O layout real do CodeMirror**, o arrasto do ponteiro no divisor, o `drop`
  de arquivo de verdade e o **worker de verdade**: nada disso existe em jsdom.
  O que os cerca esta coberto — a conta da janela de renderizacao e uma funcao
  pura, o caminho de **queda** do worker e o que o jsdom exercita, e o chunk do
  worker existe no build conferido.
- **Visual e teclado de verdade**: digitar e ver a arvore acompanhar, abrir um
  arquivo grande, arrastar o divisor, trocar de tema com o editor aberto e
  conferir as abas no celular — a lista de conferencia manual do aceite.
- **Cartao social de verdade**: o PNG existe, com mime e dimensoes conferidos
  por teste, mas nenhum crawler leu a pagina.
- **SSR em producao**: depende do processo do supervisor no VPS (RUNBOOK); aqui
  o bundle foi exercitado local.

## 12. Carry-overs

- **F3**: as tools simples 3 a 8 do MVP — cada uma entra pelo shell e pela
  pagina que a F2 deixou prontos, com um `Tool.vue`, `status: available` e as
  chaves de traducao. Vale rodar `php artisan seo:og-image` de novo para cada
  tool que virar `available` (o PNG vai comitado).
- **F5/F6/F7**: AI SQL Builder (que nao cabe neste shell — tem formulario, cota
  e resultado em abas; entra por pagina propria, reaproveitando o editor e os
  tokens), gateway/doacao e historico/SEO final.
- **Aberto (SPEC 11.2)**: mensagens de validacao nos 5 idiomas (F4), nome da
  coluna de dialetos (F5), Laravel AI SDK x cliente HTTP (F5) e os itens de
  operacao no VPS. O item do `--elevated` **fechou** nesta fase.
- **Baseline**: o eslint continua fora de controle, como registrado nas rodadas
  anteriores.

## 13. Docs atualizados

`docs/SPEC.md` (secao 4 com a rota e a pagina generica, secao 5 com o JSON
Viewer como entregue, secao 9 com a F2 concluida, 11.1 com as decisoes novas e
11.2 com o item do `--elevated` fechado), `ADR-018` revisado, **ADR-029** e
**ADR-030** novos, `docs/design/DESIGN.md` (pagina de tool com mockup textual,
tokens de pontuacao corrigidos nos dois temas, cores de sintaxe e entregaveis),
`docs/glossario.md` (10 termos novos: pagina da tool, shell, arvore,
virtualizacao, worker com queda, caminho JSONPath, imagem OG por tool, entre
outros), `CHANGELOG.md` (secao da F2), `docs/acceptance/FASE-2.md` (tabela
preenchida) e o plano em `docs/plans/develop_natan/`.

**Nenhum commit foi feito** (regra do `CLAUDE.md`): as mudancas ficam na arvore
de trabalho, para o usuario revisar e commitar pelo gitpr.
