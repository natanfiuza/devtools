# ADR-029-pagina-generica-da-tool
Status: Aceito
Data: 2026-10-14
Emenda: 2026-10-14 (F3) - secao no fim do arquivo

## Decisao
**Toda tool que funciona abre na mesma pagina, e nao numa pagina propria: o
servidor decide se a pagina existe, o registro diz o que ela mostra, e o
componente da ferramenta entra por um glob lazy dentro de um shell
compartilhado.**

- **Rota unica** `/{locale}/tools/{slug}` (`tools.show`), dentro do grupo de
  idioma (secao 3). O `/sitemap.xml` so lista as tools `available`, pela mesma
  rota (ADR-007).
- **Quem decide se a pagina existe e o servidor** (`App\Support\Tools` +
  `ToolController`): slug desconhecido, tool `soon` e tool `hidden` dao o
  **mesmo 404**. Nao ha pagina de "em breve" - um 200 desses seria uma segunda
  representacao do status de um dado que ja tem uma (o manifesto) e um link
  morto no indice de busca.
- **O slug e lido pelo nome, nunca pela posicao**: `$request->route('slug')`. Um
  `string $slug` na assinatura do metodo receberia o **idioma**, porque o
  Laravel preenche parametro escalar por posicao (`ResolvesRouteDependencies`) e
  a URL comeca pelo `{locale}`.
- **Props sao duas**: `slug` (do registro, nunca o cru da URL) e
  `maxInputBytes` (de `config/tools.php`). O limite de entrada e decisao de
  produto e mora na configuracao - o cliente so o exibe e o aplica.
- **A pagina e generica** (`resources/js/pages/tools/Show.vue`): cabecalho
  (nome, descricao e icone vindos do registro + i18n), breadcrumb e metas. Ela
  nao conhece ferramenta nenhuma; resolve o componente pelo slug em
  `tools/components.ts` (`import.meta.glob('./*/Tool.vue')`, **lazy**) e o
  encaixa. Sem `Tool.vue` no diretorio, a tela mostra o aviso de indisponivel em
  vez de estourar.
- **O shell e da pagina, nao da tool** (`components/tool/ToolShell.vue`): dois
  paineis (entrada | saida) com divisor arrastavel - `role="separator"` com
  `aria-valuenow`, setas do teclado e duplo clique restaurando -, barra de acoes
  com o `kbd` do atalho, faixa de status e faixa de atalhos. No mobile os mesmos
  dois paineis viram **abas** (o `Tabs` do shadcn-vue ja vendorizado), porque
  lado a lado nao cabe numa tela de telefone.
- **Os paineis do mobile nao desmontam**: `force-mount` no `TabsContent` mais
  uma classe de `display` escrita no shell - trocar de aba no celular nao pode
  matar o historico do editor. O breakpoint e lido em JS (`useMediaQuery`) para
  existir **uma** instancia do editor, e nao uma por layout.
- **SSR entrega a casca e as metas**: title, description, canonical, hreflang e
  `og:image` saem no HTML do servidor; o componente da tool monta no cliente
  (`ClientOnly`), porque CodeMirror e Web Worker dependem do DOM (ADR-018).

## Por que
Oito tools do MVP, todas com a mesma forma: entrada a esquerda, saida a
direita, acoes em cima. Uma pagina por tool copiaria esse layout oito vezes e
cada ajuste de espacamento viraria oito ajustes - e a F3, que entrega cinco
ferramentas, nao teria como ser so "o componente".

O 404 no servidor (e nao um aviso na tela) e a mesma regra do ADR-007 vista do
outro lado: o registro diz o que existe, e o servidor e quem responde por isso.
Assim o sitemap, o card da home, a busca e a pagina concordam por construcao, e
um link para tool `soon` nunca chega a existir.

O componente por glob lazy resolve o peso: CodeMirror e o worker nao entram no
chunk da home nem no bundle de SSR, e a F3 nao toca em arquivo de infraestrutura
- criar `resources/js/tools/<slug>/Tool.vue`, virar o `status` e pronto.

## Consequencias
- **A F3 acrescenta ferramentas sem tocar em pagina, shell ou rota**: um
  `Tool.vue` por diretorio, `status: available` no manifesto e as chaves de
  traducao. `ToolShellTest` e `ToolPageTest` continuam valendo.
- O `ToolShell` recebe um `splitKey` (a tool) e a divisao escolhida fica no
  `localStorage`: duas ferramentas abertas na mesma sessao nao herdam a divisao
  uma da outra.
- O limite de entrada chega por prop e nao por constante: mudar
  `TOOLS_MAX_INPUT_BYTES` no `.env` muda a tela sem deploy de front.
- **Nenhuma requisicao ao servidor depois do carregamento**: o texto colado ou
  aberto fica no navegador (CLAUDE.md), e a pagina nao tem endpoint de
  ferramenta.
- O bundle da F2 ficou com o CodeMirror num chunk proprio (`Tool-*.js`) e o
  worker em outro (`parser.worker-*.js`); nenhum dos dois entra no chunk da
  home. No `build:ssr` o chunk lazy da tool sai junto (o glob faz parte do
  grafo), mas **o editor nao executa no servidor** - o `ClientOnly` e o unico
  caminho de montagem, e o worker nem aparece no bundle do servidor.
- Uma tool que precise de outra forma de tela (o AI SQL Builder, que tem
  formulario, cota e resultado em abas) nao cabe neste shell: ela entra pela
  pagina propria dela, na F5, reaproveitando o editor e os tokens.

## Alternativas descartadas
- **Uma pagina por tool** (`pages/tools/JsonViewer.vue`, ...) - o layout
  copiado oito vezes, e o Inertia precisaria de um `resolvePageComponent`
  diferente por rota.
- **Rota por tool em `routes/web.php`** (uma `Route::get` por ferramenta) - a
  rota deixaria de seguir o registro: despublicar uma tool exigiria mexer em
  codigo e no manifesto.
- **200 com aviso de "em breve" para tool `soon`** - link morto no indice,
  segunda fonte de verdade sobre o status e conteudo duplicado do card da home.
- **Renderizar a tool no servidor** junto da casca - puxaria CodeMirror (e o
  acesso a `document`) para dentro do processo de SSR, que so precisa do HTML
  para o robo.
- **`eager: true` no glob dos componentes** - o chunk da home passaria a
  carregar o editor de todas as tools.
- **Um `aria-hidden`/`v-show` sem `force-mount` no mobile** - o reka-ui so
  esconde o painel inativo se ele estiver desmontado; com `force-mount` a
  escondida tem que ser escrita no shell, e foi.

## Emenda da F3 (2026-10-14)
A F3 entregou as seis tools simples. O que a fase confirmou e o que ela obrigou a
corrigir aqui:

- **A promessa se cumpriu**: nenhuma rota, nenhuma pagina e nenhuma linha de
  `Show.vue` mudaram para receber seis ferramentas. Cada uma foi um `Tool.vue`,
  um `lib/` proprio, o manifesto e as chaves de traducao (`json-to-code`,
  `sql-formatter`, `base64-url-hash`, `jwt-decoder`, `timestamp-uuid`,
  `regex-tester`).
- **A conta estava errada**: a secao "Por que" dizia cinco ferramentas na F3 -
  foram **seis** (o MVP tem oito, e o AI SQL Builder fica para a F5).
- **O shell aguentou as seis, mas nao sem uma excecao**: a consequencia que
  dizia "uma tool que precise de outra forma de tela nao cabe neste shell (o AI
  SQL Builder)" ficou incompleta. O **Regex Tester** tambem nao cabe na
  convencao - o padrao dele mora na **barra de acoes**, em linha inteira, e nao
  no painel de entrada -, e mesmo assim ele **nao** virou pagina propria: a
  excecao e do campo, nao da tela, e por isso ela cabe na casca e esta
  documentada no DESIGN (o porque esta no ADR-031). So o AI SQL Builder, que
  tem formulario, cota e resultado em abas, continua precisando de pagina
  propria.
- **A casca ganhou estados gerais, e nao de uma tool**: `tools.ui.*` deixou de
  falar de JSON - os estados da casca (`empty`, `parsing`, `too_large`,
  `file_type`, `file_unreadable`, `invalid_no_position` e `stale_output`) valem
  para qualquer ferramenta, e o erro **com linha e coluna** continua sendo do
  JSON Viewer, porque so ele analisa o texto inteiro de uma vez. A **ultima
  saida valida** deixou de ser "arvore defasada" e virou estado geral do shell
  (glossario): `json-to-code` e `sql-formatter` mostram a ultima saida boa com
  aviso enquanto a entrada nao fecha, e o Regex Tester e a excecao, porque no
  estouro de tempo nao fica resultado nenhum (ADR-031).
- **Os controles de produto moram no cabecalho do painel** que eles governam:
  `#input-header` e `#output-header` sao slots do shell desde a F2, e a F3 os
  ocupou com `Select` (o alvo e o nome da raiz do `json-to-code`; o dialeto e a
  caixa das palavras-chave do `sql-formatter`; a direcao do `base64-url-hash`)
  e com `Tabs` de modo **dentro** do painel de saida, no `base64-url-hash` - a
  unica tool da fase cuja entrada e uma so e as leituras sao tres. `ToolButton`
  ganhou `ariaLabel` por causa dos alternadores de flag do Regex Tester, que
  mostram o simbolo (`i`, `m`, `s`, `u`) e nao a frase.
- **A base comum saiu de dentro da primeira tool**: casca, editor parametrizado
  por linguagem, copiar, abrir arquivo com arrastar-e-soltar, baixar, tamanho em
  bytes, parse e tipos do JSON e o cliente de worker moram em
  `components/tool/`, `lib/` e `composables/`; o diretorio de uma tool guarda so
  o que e dela. O `json-viewer` passou a consumir os mesmos ajudantes **sem
  mudanca de comportamento** - o ADR nao prometia isso, e o custo entrou na
  fase: extrair mexeu em codigo ja commitado, e a suite do viewer foi a rede.

## Emenda da F5 (2026-10-20)

A F5 corrigiu a previsao do fim das consequencias - "o AI SQL Builder entra pela
pagina propria dela" - e mudou o contrato de props da pagina:

- **Nao houve pagina propria**: o AI SQL Builder abre na **mesma**
  `/{locale}/tools/{slug}`, com a mesma rota, o mesmo 404 do servidor e o mesmo
  glob lazy. O que muda e o **layout da tool**: ela nao usa o `ToolShell` de
  entrada | saida, e sim formulario | resultado, por ser um pedido que volta
  depois por polling (DESIGN, ADR-043). E a segunda excecao de layout, depois do
  Regex Tester, e a primeira que e da **tela**, nao de um campo.
- **Props sao tres**: `slug`, os **quatro** limites de `config/tools.php` (eram
  um) e o pacote **`toolProps`** por tool (`App\Support\ToolProps`), vazio para
  as tools de navegador (ADR-043). A pagina continua sem conhecer ferramenta
  nenhuma; ela repassa o pacote com `v-bind`.
- **O `ClientOnly` continua**: o formulario, o editor e o polling montam no
  cliente; o SSR entrega cabecalho, metas e o pacote de props. Nada de
  CodeMirror no processo do servidor.
- **As oito tools antigas nao mudaram**: recebem `{}` em `toolProps` e seguem
  com o mesmo shell e os mesmos testes.
