# ADR-018-editor-codemirror
Status: Aceito
Data: 2026-10-05
Atualizado: 2026-10-14 (a F2 implementou: conjunto de pacotes, tema pelos tokens do DESIGN e o parse fora da thread principal)

## Decisao
**CodeMirror 6 para entrada/saida de codigo (JSON, SQL, PHP, Python), carregado
sob demanda (lazy) por tool. O editor e montado extensao por extensao, e nao
pelo `basicSetup`; a cor de cada token e um `var(--json-*)` do DESIGN, o mesmo
token que a arvore usa; e o trabalho pesado sai da thread principal.**

- **Conjunto minimo de pacotes**: `@codemirror/state`, `view`, `language`,
  `commands`, `lang-json`, `lint`, `search`, `autocomplete` e
  `@lezer/highlight`. O meta-pacote `codemirror` (que embrulha o `basicSetup`)
  nao entrou: ele traz autocompletar completo, dobra de codigo e o painel de
  busca do motor - os tres fora do que a F2 usa, e o painel com rotulos em
  ingles fixo.
- **`createEditor()` monta o estado item por item** (`createEditor.ts`):
  numeros de linha, historico, `drawSelection`, pareamento de colchetes,
  `indentOnInput`, `json()`, `lintGutter`, `EditorView.lineWrapping`,
  `syntaxHighlighting(jsonHighlightStyle)` e o keymap. Trocar o documento
  inteiro (formatar, minificar, ordenar) entra como **mudanca comum**, e nao
  como estado novo, para o Ctrl+Z desfazer a acao como desfaz uma digitacao.
- **O tema nao tem paleta paralela** (`theme.ts`): o `HighlightStyle` mapeia
  `tags.propertyName`, `tags.string`, `tags.number`, `tags.bool`/`tags.null` e
  `tags.brace`/`tags.squareBracket`/`tags.separator` para `--json-key`,
  `--json-string`, `--json-number`, `--json-literal` e `--json-punct`. O token
  `--json-punct` ganhou valor proprio nos dois temas (o item da SPEC 11.2 que
  apontava o `--elevated` como cor de pontuacao fecha aqui).
- **Nao existe JS de tema.** O tema claro/escuro do projeto e uma classe no
  documento (ADR-021), e nao a preferencia do sistema; por isso o editor **nao**
  se declara escuro para o motor (`{dark: true}` deixaria o outro tema com
  selecao e paineis nas cores erradas). A classe troca as variaveis e o
  CodeMirror repinta sozinho. Ha teste que recusa cor literal no tema.
- **Chunk proprio, so na pagina da tool**: o componente entra por glob lazy
  (`tools/components.ts`, ADR-029), entao CodeMirror e o worker nao pesam na
  home nem no bundle de SSR.
- **Busca**: o painel do motor (`searchKeymap`) **nao** entrou - rotulo fixo em
  ingles. O Cmd/Ctrl+F continua funcionando e aponta para a busca da tela, que e
  uma so para o documento inteiro. `indentWithTab` tambem nao entrou: prenderia
  o foco do teclado dentro do editor.
- **O parse roda em Web Worker** (`lib/parser.worker.ts`), com o cliente
  (`lib/parseClient.ts`) **caindo para a thread principal** quando nao ha
  `Worker` ou a criacao falha, e descartando resposta velha (a digitacao tem
  debounce). A criacao fica isolada em `parser.workerHost.ts`, a unica linha do
  viewer que depende de recurso de navegador.

## Por que
O projeto tem cinco idiomas e toda string visivel e traducao: um editor com
interface propria (painel de busca, menus de contexto, rotulos de dobra) traz
texto em ingles que nao da para traduzir sem reescrever os pacotes. Montar as
extensoes item por item deixa de fora justamente o que tem rotulo - e o que a
F2 nao usa.

As cores saem dos tokens porque o editor e a arvore mostram **o mesmo JSON**: se
cada um tivesse a sua paleta, a mesma chave teria duas cores na mesma tela, e
trocar de tema exigiria reconfigurar o editor por JavaScript. Com `var()`, a
troca de tema e so CSS, e o teste de contraste do DESIGN continua valendo para
os dois.

O parse sai da thread principal porque o limite de entrada e de 5 MB
(`config/tools.php`): analisar esse texto na thread da interface congela a
digitacao. E ele **cai** para a thread principal em vez de exigir worker porque
o caminho de queda e o que roda no SSR, no jsdom (todos os testes de componente)
e num navegador com CSP restritiva - se ele nao existisse, a tool nao teria
teste nenhum de comportamento real.

## Consequencias
- `package.json` ganhou oito dependencias de `@codemirror/*` e o
  `@lezer/highlight`; o build da F2 isolou o conjunto num chunk proprio
  (`Tool-*.js`, 394,78 kB no cliente) e o worker em outro
  (`parser.worker-*.js`, 4,5 kB), e nenhum dos dois entra no chunk da home. No
  `build:ssr` o chunk lazy da tool **sai** (89,66 kB, porque o glob faz parte do
  grafo) e o worker **nao** - e o editor nao executa no servidor, que nunca
  monta o componente (`ClientOnly`).
- O erro de sintaxe chega ao editor por `setDiagnostics` (marcacao na linha via
  `lintGutter`), com a posicao vinda do parser da tool - nao ha
  `jsonParseLinter` do pacote, porque a mensagem precisa sair traduzida.
- **A F3 herda o editor pronto**: uma tool nova importa `createEditor` e o tema,
  sem reinstalar nem reconfigurar nada. Trocar de linguagem = trocar o pacote
  `lang-*` na chamada, mantendo os tokens.
- Os valores de `--json-*` vivem em `resources/css/app.css` e estao no DESIGN;
  mexer neles muda o editor e a arvore juntos.

## Alternativas descartadas
- **Meta-pacote `codemirror` (`basicSetup`)** - o caminho normal, e o que este
  ADR descartou: traz o painel de busca com rotulos em ingles, dobra de codigo e
  autocompletar que a tool nao usa.
- **CodeMirror 5** - modo JSON e tema por CSS, mas sem arvore de sintaxe, sem
  tipos e sem o modelo de extensoes que a F3 (SQL) vai querer.
- **Monaco** - pesado (ordem de MB), feito para o VS Code, e com interface
  propria em ingles.
- **`<textarea>` com destaque por cima** - o que sobra quando se recusa um
  editor de verdade: sem numeracao de linha, sem marcacao de erro, sem historico
  proprio e com um `textarea` transparente que quebra a acessibilidade.
- **Escrever a paleta no `theme.ts`** - duas fontes para a mesma cor, e o teste
  de contraste do DESIGN passaria a nao cobrir o editor.
- **`indentWithTab` e `searchKeymap`** - ver acima: foco preso no editor e
  painel sem traducao.
- **Exigir worker (sem queda)** - uma linha a menos e nenhuma cobertura de
  teste: no jsdom a tool nao seria exercitada.
