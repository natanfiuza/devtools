# CHANGELOG - devtools.natanfiuza.dev.br

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versoes por fase do [SPEC](docs/SPEC.md); datas em AAAA-MM-DD.

## Medicao inativa: a fila do gtag - 2026-10-16

A medicao foi ligada em producao e o GA4 **nao recebeu nenhum hit**. A tag
carregava (detectada no Tag Assistant, `dataLayer` com os quatro comandos, a
requisicao `gtag/js?id=G-...` respondendo 200) e nada chegava ao Google - sem um
erro no console. Causa: os comandos iam para a fila como **Arrays**, e o gtag.js
so despacha item que seja um objeto `arguments`; um Array cai no ramo dos "campos
de modelo" e e ignorado em silencio. O snippet oficial do Google sempre fez
`dataLayer.push(arguments)` - a "simplificacao" para rest parameter foi o
defeito.

### Corrigido

- `resources/js/lib/analytics.ts`: o shim `gtag` enfileira `arguments`, nao o
  rest parameter. O tipo de `window.dataLayer` passa a representar o que o
  gtag.js aceita (`IArguments | Record<string, unknown>`), entao enfileirar um
  Array volta a ser **erro de compilacao**, com a razao no docblock e no ponto
  do `push`.
- `resources/js/__tests__/consent-banner.test.ts`: as assercoes leem a fila como
  lista e um teste novo afirma o formato de **cada** item (`[object Arguments]`,
  nunca Array). Era o teste que faltava: os 663 testes verdes conviviam com a
  medicao zerada.
- `docs/guides/google-analytics.md`: a secao 6 ganha "A fila do gtag" (o
  contrato que nao se pode "simplificar") e a secao 3 deixa de tratar "tag
  detectada, zero hits" como falso alarme - era o sintoma de um defeito real.

## Medicao declarada no banner e ativacao do GA4 - 2026-10-15

O banner dizia "Google Analytics: paginas vistas, **sem dado pessoal**" enquanto
a medicao, uma vez aceita, grava o cookie `_ga` - um identificador persistente.
Agora os dois lados falam a mesma lingua: o texto do banner descreve o que a
medicao faz (e em 5 idiomas), o "Nada de anuncios" deixou de depender so do
Consent Mode, a Politica de Privacidade cita o Google como medidor, e **ativar a
medicao** ganhou um passo a passo - que e so preencher uma variavel: o
carregamento ja existia desde a F1 (ADR-013).

### Adicionado

- `docs/guides/google-analytics.md` (pasta `docs/guides/`, nova): como criar a
  propriedade GA4, ligar o `ANALYTICS_GA4_ID` no `.env` de producao, conferir no
  devtools e o que **nao** ligar na console (Google Signals, personalizacao de
  anuncios, Enhanced measurement) - mais a rotacao do id e o mapa de onde cada
  peca vive no codigo.
- `docs/adr/ADR-032-medicao-com-cookies.md`: a frase do banner e afirmacao
  factual - GA4 padrao depois do aceite, sinais de anuncio desligados no
  `config`, Google declarado na politica, area da conta medida e admin fora.
- `resources/js/__tests__/consent-layouts.test.ts`: a area da conta mostra o
  banner e injeta a tag so com aceite; o painel administrativo nao injeta nada,
  nem com `consent=granted`.

### Alterado

- `lang/{pt_BR,pt_PT,en,es,fr}/app.php` (`consent.analytics_body`): "paginas
  vistas e cookies de medicao, so depois do seu aceite" no lugar de "sem dado
  pessoal" (as chaves nao mudaram).
- `lang/{pt_BR,pt_PT,en,es,fr}/legal.php`: a secao "Com quem compartilhamos"
  passa a citar o Google tambem como medidor; `config/legal.php` foi para
  `2026-10-15`, a data que aparece no topo das paginas legais.
- `resources/js/lib/analytics.ts`: `gtag('config', id, { allow_google_signals:
  false, allow_ad_personalization_signals: false })` - o "Nada de anuncios" nao
  fica so por conta do Consent Mode.
- `resources/js/layouts/account/AccountLayout.vue`: monta `ConsentBanner` e
  `AnalyticsScript`, como os layouts publico e de acesso. O `AdminLayout.vue`
  segue sem os dois, de proposito.
- `docs/RUNBOOK.md`: `ANALYTICS_GA4_ID` no bloco de `.env` de producao, secao 9
  (medicao) com o link do guia, rotacao do GA4 na secao 7 e uma linha na tabela
  de diagnostico.
- `docs/SPEC.md` (secoes 8, 10 e 11), `docs/glossario.md` (termo **Medicao**) e
  `docs/adr/ADR-013-consentimento.md` (a lista de layouts com banner ficou
  defasada e foi corrigida).

## Deploy - dependencias sob gatilho (composer + npm) - 2026-10-15

O `composer install` deixou de rodar em **todo** deploy: as duas instalacoes
dependem do hash do manifesto, e uma falha no meio do script pode ser refeita
sem commit novo. A mudanca vive no `deploy/deploy.sh` (e nas docs abaixo);
nada muda para quem usa o site. No VPS, porem, o script em uso e uma **copia**
(`deploy-instaled.sh`), trocada a mao - e so depois dessa troca a mudanca vale
em producao (passo a passo em `deploy/deploy-instaled.md`).

### Adicionado

- `deploy/deploy.sh --force`: roda o corpo do deploy mesmo sem commit novo - o
  caminho manual para consertar uma falha no meio (o script normal sai na hora
  quando `HEAD == origin/main`, e era o que o RUNBOOK mandava fazer). Com a
  trava ocupada, o `--force` avisa e sai com codigo 1; o cron nunca passa a
  flag.
- `deploy/deploy-instaled.md`: o passo a passo para trocar a copia instalada do
  script no VPS (o cron executa `deploy-instaled.sh`, nao o arquivo do repo) -
  sinal de que ha versao nova (`diff -q`), backup datado, troca atomica com
  `mv -T`, como conferir com `--force`, rollback e os problemas comuns.

### Alterado

- `deploy/deploy.sh`: `composer install --no-dev --optimize-autoloader` so roda
  quando o `sha256` do `composer.json` difere do guardado em
  `storage/framework/composer-deps.sha256` (ou quando nao ha `vendor/`), e o
  `npm install` ganhou a mesma rede (`[ ! -d node_modules ]`). Quando o install
  **recusa** o lock do servidor (exit 4: o `composer.lock` de la nao acompanha
  o `composer.json`, porque nenhum dos dois e versionado), o script cai para
  `composer update` em vez de morrer no meio. Os dois gates logam a etapa
  pulada.
- `storage/framework/.gitignore`: `*.sha256` - os arquivos de estado do gate
  estavam fora do git por acidente (o `.gitignore` de la lista nomes um a um).
- `.gitignore` (raiz): a copia instalada do script no VPS e os arquivos que a
  troca cria (`/deploy/deploy-instaled.sh`, `.sh.novo` e `.bak-*`) - o
  `git status` do servidor fica limpo.

### Corrigido

- `deploy/deploy.sh`: o `git pull` passava `origin/main` como um **argumento
  unico** (`git pull -q --ff-only "origin/$BRANCH"`), e o git le argumento
  unico como repositorio - o deploy morria logo depois do "Deploy iniciado",
  com `fatal: 'origin/main' does not appear to be a git repository`. Agora e
  `git pull -q --ff-only origin "$BRANCH"` (o `git fetch` da linha de cima ja
  estava na forma certa, e por isso passava). O defeito esta no repositorio
  desde `172aea0`, o commit que criou o script.
- `docs/adr/ADR-014-deploy.md` dizia que, sem lockfile, o `composer install` do
  deploy "resolve a arvore na hora"; o servidor tem o lock do ultimo install, e
  e ele que o install usa - o que existe e a recusa quando o manifesto pede
  pacote novo.
- `docs/RUNBOOK.md` secao 2: o conserto manual documentado ("rodar o script a
  mao") nao funcionava; agora e `deploy-instaled.sh --force`, e a secao descreve
  as dependencias sob gatilho.
- `docs/RUNBOOK.md` secao 2 e o tutorial: o comando manual documentado rodava
  como `www-data`, que nao escreve no `.git` do repositorio (do `root`) - o
  `git fetch` do script morria com `insufficient permission for adding an
  object to repository database`; os dois passam a mandar rodar como o dono do
  repositorio, que e o usuario do cron.
- `docs/RUNBOOK.md`, `docs/SPEC.md` e `docs/adr/ADR-014-deploy.md` diziam que o
  cron executa `deploy/deploy.sh`; ele executa a **copia instalada**
  (`deploy-instaled.sh`). O arquivo do repo chega pelo pull, mas quem roda e a
  copia, trocada a mao.

## F3 - Tools simples (3 a 8 do MVP) - 2026-10-14

A fase da secao 9 do SPEC: **as seis tools simples viram `available` de uma vez**
(`json-to-code`, `sql-formatter`, `base64-url-hash`, `jwt-decoder`,
`timestamp-uuid`, `regex-tester`), todas na mesma pagina e na mesma casca da F2 -
a fase nao mexeu em rota, em `Show.vue` nem na forma do shell. Com elas, o site
sai de uma ferramenta que funciona para sete; o **AI SQL Builder** e o unico
card que continua com o selo "em breve", porque depende de servidor (F5).
Aceite em `docs/acceptance/FASE-3.md`.

### Adicionado

- **Base comum das tools** (o que duas ou mais usam sai de dentro de uma):
  `components/tool/CodeEditor.vue` (editor parametrizado pela extensao de
  linguagem), `FileDropzone.vue`, `lib/clipboard.ts`, `lib/download.ts`,
  `lib/bytes.ts`, `lib/file.ts` (abrir arquivo e arrastar-e-soltar),
  `lib/position.ts` (posicao do erro a partir da mensagem de uma biblioteca),
  `lib/encoding.ts`, `lib/editor/` (CodeMirror por token) e `lib/worker/`
  (cliente e servidor do worker, com queda para a thread principal). O
  `json-viewer` passa a consumir os mesmos, **sem mudanca de comportamento**.
- **JSON para codigo** (`json-to-code`): inferencia de um modelo de tipos a
  partir do documento - campo ausente em parte do array vira opcional, array de
  tipos diferentes vira uniao, `null` vira anulavel, inteiro e decimal se
  distinguem, vazio tem tipo proprio (`lib/infer.ts`) -, plano de declaracoes
  com nome unico por objeto (`lib/plan.ts`) e tres emissores
  (`lib/emit/{typescript,php,pydantic}.ts`): `export interface`, `final class`
  com `declare(strict_types=1)` e propriedades promovidas `public readonly`, e
  `BaseModel` do Pydantic na ordem em que o Python consegue definir. O alvo
  (TypeScript | PHP (DTO) | Pydantic) e o nome do tipo raiz ficam no cabecalho
  da **saida**; a contagem de tipos vai para a faixa de status.
- **SQL Formatter** (`sql-formatter`): quatro dialetos (`mysql`, `pgsql`,
  `sqlsrv`, `sqlite` - o vocabulario do glossario, traduzido para os nomes das
  bibliotecas em `lib/dialects.ts`), caixa das palavras-chave (maiusculas,
  minusculas ou manter), indentacao fixa em 2 espacos e o erro sintatico da
  biblioteca convertido em **linha e coluna** na tela. A formatacao roda em
  worker, com queda para a thread principal.
- **Base64, URL e Hash** (`base64-url-hash`): direcao (codificar | decodificar)
  no cabecalho da entrada e **abas de modo** no painel de saida - Base64 (UTF-8
  de verdade, com a variante URL-safe sem padding), URL (componente e
  formulario, `+` no lugar do espaco) e Hash (MD5, SHA-1, SHA-256 e SHA-512 de
  uma vez, hex minusculo, com copiar por linha). O MD5 e do repositorio
  (`lib/md5.ts`, conferido contra os vetores do RFC 1321) porque a Web Crypto
  nao tem MD5; sem `crypto.subtle` (conexao nao segura) os tres SHA avisam o
  motivo e o MD5 continua funcionando.
- **JWT Decoder** (`jwt-decoder`), que so decodifica: cabecalho e payload em
  JSON colorido, com `Bearer` colado sendo tolerado; `exp`, `iat` e `nbf`
  aparecem como data legivel no idioma e com selo de estado ("expirado" em
  `--destructive`); a assinatura sai so como **comprimento em bytes** e com o
  rotulo de nao verificada, e uma **faixa de aviso** no topo da saida diz que a
  ferramenta nao valida assinatura.
- **Timestamp e UUID** (`timestamp-uuid`): a entrada aceita digitos (a unidade
  sai da quantidade deles), texto de data ISO 8601, ou um UUID v7/ULID colado -
  que nao e instante, mas carrega um, e a tool diz de que tipo ele veio. A saida
  traz seis leituras com copiar (Unix em segundos e milissegundos, ISO em UTC e
  local, data por extenso no idioma da pagina e tempo relativo) e os geradores
  de UUID v4, UUID v7 e ULID, de 1, 5 ou 10 por vez.
- **Regex Tester** (`regex-tester`), com o modelo de seguranca do **ADR-031**:
  a busca roda num Web Worker descartavel e, passando de
  `TOOLS_REGEX_TIMEOUT_MS`, o worker e encerrado e **nenhum resultado** fica na
  tela - nem a lista parcial, nem a leitura anterior. Padrao invalido volta como
  resultado, nao derruba o worker. A saida traz a lista de correspondencias
  (indice, trecho, posicao e grupos numerados e nomeados), o texto com cada
  correspondencia realcada e a previa da substituicao (`$1`, `$<nome>`); acima
  de `TOOLS_REGEX_MAX_MATCHES` a lista para e avisa.
- `config/tools.php` e `.env.example`: `TOOLS_REGEX_TIMEOUT_MS` (padrao 2000) e
  `TOOLS_REGEX_MAX_MATCHES` (padrao 1000), descendo por prop como o teto de
  entrada.
- Dependencias novas: `sql-formatter` (a unica de runtime) e
  `@codemirror/lang-sql`. Ambas entram por glob lazy, no chunk da tool.
- **Imagens OG das seis tools novas** (`public/og/<slug>.png`), geradas pelo
  `seo:og-image` ao virar o `status` e comitadas (ADR-030).
- i18n: os textos das seis tools nos 5 idiomas; os do shell (`tools.ui.*`)
  deixam de falar de JSON e passam a valer para qualquer ferramenta.
- Testes (Vitest): `json-to-code`, `json-to-code-infer`, `json-to-code-emit`,
  `sql-dialects`, `sql-format`, `sql-formatter`, `encoding-base64`,
  `encoding-url`, `base64-url-hash`, `md5`, `hash`, `jwt-decode`,
  `jwt-decoder`, `time`, `ids`, `regex-run`, `regex-worker`, `regex-tester`,
  `position` e `file`.

### Alterado

- Os seis `manifest.json` passam a `status: "available"`: o card da home ganha
  link e perde o selo, a busca passa a abrir a pagina e o sitemap vai de 5 para
  **50 URLs** (10 paginas x 5 idiomas).
- `App\Support\Tools::all()` passa a excluir `hidden`, como o leitor
  TypeScript: era a unica divergencia entre os dois leitores, e o consumidor
  que a expunha e o cartao padrao de compartilhamento, desenhado a partir
  dali. `Tools::find()` le `all()`, entao uma tool `hidden` nao e encontrada -
  e o mesmo `null` do slug desconhecido, que vira o mesmo 404 (ADR-007).
- `ToolButton.vue` ganha `ariaLabel` (os alternadores de flag do Regex Tester
  mostram o simbolo, e nao a frase) e `ToolShell.vue` recebe os controles de
  produto nos slots `#input-header` e `#output-header`, que ja existiam.
- `resources/js/lib/parse.ts` e `types.ts` do `json-viewer` viram
  `lib/json/{parse,types}.ts`, e o editor do `json-viewer` (`editor/JsonEditor.vue`
  e companhia) vira o `CodeEditor.vue` compartilhado.

### Decidido

- **ADR-031** (modelo de seguranca do Regex Tester: worker com prazo, nenhum
  resultado no estouro, teto de correspondencias) e **ADR-029 emendado** (o que
  a F3 confirmou, a conta de cinco ferramentas que eram seis, a excecao do
  Regex Tester que cabe na casca e a base comum que saiu de dentro da primeira
  tool). **ADR-007** registra o alinhamento de `hidden` entre os dois leitores.
- Listas de produto (dialetos, alvos, algoritmos) como constantes tipadas do
  modulo, no `Select` ou `Tabs` do painel que elas governam; os numeros seguem
  em `config/tools.php`.
- "Ultima saida valida" deixa de ser so do JSON Viewer e vira estado da casca
  (glossario), com o `json-to-code` e o `sql-formatter` mostrando a ultima saida
  boa enquanto a entrada nao fecha - e o Regex Tester como excecao, pelo
  ADR-031.

### Corrigido

- `AdminPanelTest`: o teste de busca fixava o termo "ana" e criava o admin com
  e-mail sorteado pelo Faker - um em cada cem e-mails gerados contem o termo
  (`nathanael`, `mariana`, `susana`), e a lista voltava com duas contas. Os
  e-mails do teste passam a ser fixos.

## Deploy - lockfiles fora do git e gatilho de dependencias - 2026-10-14

Bloco que veio antes da F3, para a home voltar a publicar: os **dois lockfiles
saem do git** (`composer.lock` e `package-lock.json`, com o `.gitignore`
cobrindo os dois) e o `deploy.sh` passa a decidir a instalacao de dependencias
de front pelo **hash do `package.json`** (`storage/framework/npm-deps.sha256`),
chamando `npm install --no-audit --no-fund` no lugar de `npm ci`. Nenhum dos
dois lados do build e reproduzivel, e isso e assumido no ADR-014 (atualizado):
faixas `^` no `package.json` significam que dois deploys do mesmo commit podem
instalar versoes diferentes.

A falha no meio do script - que deixa o PHP novo servindo o JS velho e nao
retenta, porque o `git pull` ja moveu o HEAD - fica **mantida de proposito**, com
o diagnostico no `docs/RUNBOOK.md`. A publicacao continua dependendo do merge em
`main`, que e o que o cron observa (commit em `develop_natan` nao publica nada).

## F2 - JSON Viewer, pagina da tool e imagem OG por tool - 2026-10-14

A fase da secao 9 do SPEC, mais o que a F1 deixou explicitamente para ela: a
rota `/{locale}/tools/{slug}` e a imagem OG por tool. O `json-viewer` vira a
**primeira tool `available`** - o card da home passa a linkar, a busca a abrir e
o sitemap a listar -, e a pagina onde ela abre e generica: a F3 acrescenta
ferramentas sem tocar em pagina, shell ou rota. Aceite em
`docs/acceptance/FASE-2.md`.

### Adicionado

- **Rota e pagina da tool** (ADR-029): `GET /{locale}/tools/{slug}`
  (`tools.show`) com `ToolController` - slug desconhecido, tool `soon` e tool
  `hidden` dao o **mesmo 404** -, props `slug` (do registro) e `maxInputBytes`
  (`config/tools.php`, `TOOLS_MAX_INPUT_BYTES`, padrao 5 MB). A pagina
  (`pages/tools/Show.vue`) e generica: breadcrumb, cabecalho com nome,
  descricao e icone do registro, metas via `PageHead` e o componente da tool
  vindo de `resources/js/tools/<slug>/Tool.vue` por **glob lazy**
  (`tools/components.ts`). O SSR entrega a casca e as metas; o editor monta no
  cliente (`ClientOnly`), porque CodeMirror e Web Worker dependem do DOM.
- **Shell da tool** (`components/tool/ToolShell.vue`, `ToolActionBar.vue`,
  `ToolButton.vue`): barra de acoes com o `kbd` do atalho, dois paineis
  (entrada | saida) com divisor arrastavel - `role="separator"`,
  `aria-valuenow`, setas do teclado, duplo clique restaura e a divisao fica no
  `localStorage` por tool - e **abas no mobile** (`Tabs` do reka-ui ja
  vendorizado, sem dependencia nova) com `force-mount`: trocar de aba nao pode
  desmontar o editor.
- **JSON Viewer** (a primeira tool que funciona): editor CodeMirror 6 com
  realce pelos tokens do DESIGN, erro marcado na linha e historico; arvore
  colorida colapsavel com contagem de chaves, itens e tamanho, expandir/recolher
  tudo e copia do **caminho JSONPath** de qualquer no; busca na arvore por chave
  e valor (sem acento e sem caixa) expandindo os ancestrais do que casa;
  formatar, minificar e ordenar chaves (recursivo, arrays intactos); erro de
  sintaxe com **linha e coluna** calculadas aqui, mantendo a ultima saida valida
  na tela; abrir arquivo, arrastar e soltar, exemplo, limpar, copiar e baixar;
  limite de entrada vindo da configuracao, com o tamanho formatado no idioma da
  pagina.
- **Funcoes puras** (`lib/parse`, `tree`, `path`, `transform`, `window`) e
  **virtualizacao propria** das listas grandes - janela sobre linhas de altura
  uniforme, conta numa funcao pura, sem dependencia nova.
- **Parse em Web Worker com queda** (`lib/parser.worker.ts`): o JSON e analisado
  fora da thread principal, e o cliente cai para a thread principal quando nao
  ha `Worker` (SSR, jsdom) ou a criacao falha, descartando resposta velha - a
  digitacao tem debounce.
- **Imagem OG por tool** (ADR-030): `seo:og-image` passa a escrever tambem
  `public/og/<slug>.png` para cada tool `available`, com o nome da ferramenta no
  lugar do wordmark e os termos do proprio manifesto na linha de baixo;
  `config/seo.php` ganhou `image.tools_dir` e `App\Support\Seo` escolhe a
  imagem pela rota, **caindo na padrao** quando o arquivo nao existe. A imagem
  nao tem idioma: o nome traduzido da ferramenta vai no `og:image:alt`.
- i18n `tools.ui.*` (os textos do shell, compartilhados pelas 8 tools) e
  `tools.json-viewer.*` nos 5 idiomas.
- Testes: `ToolPageTest` (Pest) e `SeoMetaTest` estendido (imagem, `alt`
  traduzido e queda para a padrao), mais `json-parse`, `json-tree`, `json-path`,
  `json-transform`, `json-window`, `json-viewer`, `json-editor-theme`,
  `tool-shell` e `tool-page` (Vitest).

### Alterado

- `resources/js/tools/json-viewer/manifest.json` passa a `status: "available"`:
  o card da home ganha link e perde o selo, a busca Cmd+K passa a abrir a
  pagina, e o `SitemapTest` deixa de fixar "nenhuma tool no sitemap" e passa a
  usar a rota de verdade.
- `ToolCard.vue` vira `Link` quando a tool tem caminho; `inertiaApp.ts` mapeia
  `tools/*` para o layout publico.
- `App\Support\Tools::all()` passa a carregar os `keywords` do manifesto (a
  imagem OG usa os termos da propria tool).
- `--json-punct` ganha valor proprio nos dois temas (fecha o item da SPEC
  11.2): a pontuacao do JSON deixa de depender do `--elevated`, que fica so com
  bordas e superficies - o DESIGN passa a citar o valor real.

### Decidido

- ADR-018 revisado (conjunto minimo de pacotes, sem `basicSetup`, tema por
  token e parse no worker com queda) e **ADR-029** (pagina generica da tool) e
  **ADR-030** (imagem OG por tool) novos.

### Corrigido

- Revisao independente da F1 (secao "Revisao independente" do
  `docs/acceptance/FASE-1.md`): **aceitar de novo depois de recusar religa a
  medicao** - `loadAnalytics` reafirma o `consent update granted` em vez de sair
  cedo quando a tag ja esta na pagina (`lib/analytics.ts`), com teste proprio
  para a sequencia aceitar -> recusar -> aceitar.
- O teste de import sob demanda do registro (`tools-registry.test.ts`) ganhou
  **timeout explicito**: o gate `npm run test` ficava vermelho por timeout do
  Vitest sob carga, nao por defeito.
- `docs/SPEC.md`: os itens **3 a 8 do MVP** voltam a secao 5 (o bloco tinha sido
  removido sem substituto); o `Esc` do rodape da palette virou chave de i18n
  (`app.search.key_escape`, "Échap" em frances); o contrato de `hidden` entre os
  dois leitores foi registrado no ADR-007 e na secao 4.
- Verificacao independente da F2 (secao "Revisao de 2026-10-14" do
  `docs/acceptance/FASE-2.md`), que fechou cinco divergencias entre o aceite e o
  codigo - tres delas em coisas que este bloco ja prometia e o codigo nao fazia:
  a **arvore da ultima versao valida continua na tela** quando o texto fica
  invalido ou passa do limite, marcada como defasada (`stale_tree` nos 5
  idiomas) e com opacidade reduzida, como o SPEC secao 5 exige; os **atalhos**
  foram ligados e o `kbd` apareceu na tela - `Mod-Enter` formata (o editor
  registra a tecla **antes** das do CodeMirror, que ja usava `Mod-Enter` para
  inserir linha em branco), `Mod-f` busca e `Esc` limpa a busca, com o `kbd` no
  botao Formatar, no campo de busca e na faixa de atalhos (que passa a listar os
  tres do mockup, no lugar de desfazer/colar); e o **limiar de virtualizacao**
  saiu do modulo para `config/tools.php` (`TOOLS_VIRTUALIZE_FROM`, padrao 2000),
  descendo por prop como o teto de entrada. As outras duas eram de texto: o nome
  do protocolo do worker no aceite passou a ser o real (`{id, text, options}` /
  `{id, outcome}`) e a celula dos commits deixou de afirmar que a fase inteira
  estava no working tree. O DESIGN e o glossario passaram a descrever o que foi
  entregue (24px por linha, 14px de indentacao, as acoes por painel e o termo
  "arvore defasada").

### Fora do escopo (proximas fases)

- As tools simples 3 a 8 do MVP (F3): elas entram pelo **mesmo** shell e pela
  mesma pagina, e uma tool nova custa um `Tool.vue`, o status no manifesto e as
  chaves de traducao.
- Nao entraram no viewer (e nao estavam na linha do SPEC): o painel de busca do
  proprio CodeMirror (rotulos fixos em ingles), a indentacao por Tab e a
  validacao por schema.
- O arrasto real de arquivo, o layout do CodeMirror e o worker de verdade nao
  sao testaveis em jsdom: o que os cerca (funcao pura da janela, caminho de
  queda e smoke manual com SSR) esta coberto.

## F1 - Registro de tools, home, busca e consentimento - 2026-10-14

A fase da secao 9 do SPEC, mais o que a F0 deixou para tras (sitemap e imagens
OG). As 8 tools do MVP entram **registradas** com `status: "soon"` - nenhuma
funciona ainda -, entao o grid, a busca, o sitemap e os testes ja sao reais e
cada fase seguinte so troca o status. Aceite em `docs/acceptance/FASE-1.md`.

### Adicionado

- **Registro de tools** (ADR-007 revisado): um `manifest.json` por tool em
  `resources/js/tools/<slug>/` com `slug`, `status`, `category`, `icon`,
  `order` e `keywords`; `registry.ts` carrega tudo com `import.meta.glob` e
  `App\Support\Tools` le os **mesmos arquivos** no PHP, para o sitemap. Nenhum
  texto visivel mora no manifesto: os 5 idiomas trazem `tools.<slug>.*`. Cinco
  nomes sao de produto e ficam iguais em ingles nos 5 idiomas; os outros tres
  sao descritivos e mudam por idioma (`JSON to code`, `Base64, URL and Hash` e
  `Timestamp and UUID` em ingles).
- **Home real** (`HomeController`): hero com titulo e campo de busca, chips das
  categorias que tem tool, grid de 3 colunas com as 8 tools (card sem link e com
  o selo "Em breve" enquanto a tool nao abre), destaque do AI SQL Builder com
  preview e **secao de precos** lendo a tabela `plans` - os mesmos numeros do
  painel, formatados no idioma da pagina -, com CTA de criar conta no Free e de
  contato por e-mail nos pagos, porque no site nao existe botao de assinar.
- **Busca Cmd+K** (`CommandPalette` + `SearchTrigger`, no header e no hero):
  abre por Cmd/Ctrl+K e por `/` fora de campo de texto, filtra por nome,
  categoria e palavras-chave nos 5 idiomas, navega com as setas e o Enter e
  fecha no Esc; combobox/listbox com `aria-activedescendant` e foco preso.
  Construida sobre o `Dialog` e o `Input` do reka-ui ja vendorizado - **sem
  dependencia nova**.
- **Consentimento LGPD + GA4** (ADR-013 revisado): `config/analytics.php`
  (`ANALYTICS_GA4_ID`, nome e validade do cookie), `App\Support\Consent` com os
  tres estados resolvidos no **servidor** (cookie corrompido volta a nulo e o
  middleware `HandleConsent` apaga o lixo), banner com Necessarios e Medicao, e
  a tag do GA4 injetada **so no cliente**, uma vez, somente com aceite e id
  configurado - `consent default denied` antes de qualquer tag. Id vazio
  desliga a medicao. Sem decisao, nenhuma linha de GA no HTML do servidor nem no
  SSR. Reabrir pelo botao "Cookies" no rodape.
- **Sitemap e robots servidos pela aplicacao** (heranca da F0): `GET
  /sitemap.xml` com home, Privacidade e Termos nos 5 idiomas e as alternates de
  cada pagina (com `x-default`), listando tool **apenas** quando ela for
  `available`; `GET /robots.txt` com o `Disallow` das areas privadas e a linha
  do `Sitemap:`. Os dois ficam fora do prefixo de idioma, porque sao do site
  inteiro.
- **Tags OG** (heranca da F0): `og:*` e `twitter:card` no ramo indexavel do
  `app.blade.php` (tipo, `og:url` igual ao canonical, idioma com os alternates,
  imagem, `site_name`) e `og:title`/`og:description` por pagina via `<Head>`;
  `public/og-default.png` (1200x630) gerada pelo comando de uso unico
  `seo:og-image`, que baixa as fontes para `storage/app/og-fonts/` (fora do git)
  e escreve um PNG que vai comitado.
- `PageHead.vue` centraliza titulo e descricao das paginas indexaveis, e
  `App\Support\Seo` passa a montar tambem as metas de compartilhamento.
- Testes: `ToolsRegistryTest`, `HomeTest`, `ConsentTest`, `SeoMetaTest`,
  `SitemapTest` e `RobotsTest` (Pest) mais `tools-registry.test.ts`,
  `command-palette.test.ts`, `home.test.ts`, `consent-banner.test.ts` e
  `page-head.test.ts` (Vitest).

### Alterado

- Pagina de Privacidade: a secao de cookies descreve a medicao como ela e -
  cookies de medicao opcionais, que so entram depois do aceite e podem ser
  revogados pelo rodape - nos 5 idiomas, com a data de revisao atualizada.
- `Home.vue` deixa de ser esqueleto e o `SearchTrigger` deixa de ser um botao
  desabilitado com "em breve" (o antigo `search_coming_soon` saiu do i18n).
- O guia de estilo sai do indice e do sitemap: e pagina de referencia do DESIGN,
  nao conteudo do site - continua navegavel, com `noindex`.

### Removido

- `public/robots.txt` estatico: arquivo estatico vence a rota, no nginx e no
  `artisan serve`, e amarraria a linha do `Sitemap:` ao ambiente. Ha teste que
  falha se ele voltar.

### Decidido

- ADR-007 revisado (manifesto em JSON, com dois leitores sobre os mesmos
  arquivos) e ADR-013 revisado (banner, cookie e injecao do GA; exclusao e
  exportacao de conta passam a ser so do ADR-028).
- A rota `/{locale}/tools/{slug}` entra na **F2**, junto com a primeira tool
  `available`: nada e `available` na F1, entao nao ha link quebrado.
- A imagem OG por tool fica para a F2/F3; a F1 entrega uma imagem padrao do
  site.

### Fora do escopo (proximas fases)

- A pagina da tool, o CodeMirror e a primeira tool que funciona (F2); as tools
  simples 3 a 8 do MVP (F3).
- AI SQL Builder, cache, fila e o consumo real da cota (F5); gateway, doacao e
  webhooks (F6); historico de uso, SEO final e hardening (F7). Mensagens de
  validacao nos 5 idiomas segue aberto (SPEC secao 11.2).
- Nome traduzivel de plano: `plans.name` e coluna de dado e aparece igual nos 5
  idiomas.

## F0 - Papeis, modelo comercial e paineis - 2026-10-14

Bloco antecipado por decisao do grill: papeis e o painel administrativo (F7), o
modelo comercial com venda manual (F6) e o que faltava da F4 (telas de settings
traduzidas, exportar e excluir conta). Plano em
`docs/plans/master/20261014_papeis_paineis_plansfacts.md`.

### Adicionado

- **Papeis e autorizacao** (ADR-025): `users.role` com o enum `UserRole`
  (`user` | `admin`), Gate `access-admin` no grupo `/admin` e a `UserPolicy`
  como unico lugar da autorizacao; primeiro admin por
  `php artisan devtools:admin {email} [--revoke]`, idempotente e auditado.
- **Auditoria** na tabela `admin_actions` (ator, acao, alvo, `meta`, IP), com a
  tela `/admin/audit` filtravel; nada de schema ou prompt entra ali.
- **Suspensao** (`users.suspended_at` + middleware `active` em toda rota
  autenticada): a conta suspensa nao entra e a sessao aberta cai.
- **Impersonation** (`entrar como`): faixa no topo de toda tela autenticada,
  saida por `DELETE /impersonation` fora do grupo do painel.
- **Modelo comercial** (ADR-027): tabelas `plans`, `subscriptions`, `payments` e
  `usage_ledger`; catalogo inicial (Free, Pro mensal R$ 29,90, Pro anual
  R$ 299,00) carregado por migracao com `insertOrIgnore`; venda manual com
  `GrantPlan`, `GrantQuota`, `RegisterPayment` e `RefundPayment`; cota calculada
  do livro-razao em `App\Support\Quota`, sem coluna de saldo.
- **Idioma da conta** (ADR-026): `users.locale`, escolha do idioma no cadastro
  (5 endonimos, pre-selecionado, opcional) e troca no perfil; sem prefixo na URL
  a ordem e cookie -> conta -> `Accept-Language` -> en.
- **Painel administrativo** (`/admin`, 5 idiomas): Visao geral, Contas,
  Assinantes, Planos, Pagamentos e Auditoria; o detalhe da conta
  (`/admin/users/{id}`) traz as nove acoes (verificar e-mail, reenviar
  verificacao, enviar redefinicao de senha, papel, suspender, reativar, plano,
  cota, excluir) e as secoes de assinatura, consumo, logins sociais, sessoes e
  pagamentos.
- **Painel do usuario**: navegacao unica na area autenticada - Visao geral
  (plano, cota, assinatura e vencimento), Historico, Faturas e Conta -, com
  exportacao dos dados em JSON e exclusao da conta (ADR-028).
- **Exclusao por anonimizacao** (ADR-028): nome, e-mail, senha, vinculos e
  preferencias saem, e `payments`/`subscriptions` ficam pela guarda fiscal (a FK
  `restrictOnDelete` garante no banco); sessoes abertas caem e a conta nao entra
  mais.
- Testes: `AdminRoleTest`, `AdminPanelTest`, `AdminAccountActionsTest`,
  `AdminBillingTest`, `AdminImpersonationTest`, `QuotaTest`, `BillingTest` e
  `AccountPanelTest` (Pest), mais `admin-panel.test.ts` e `account-panel.test.ts`
  (Vitest).

### Corrigido

- Telas de perfil/seguranca/aparencia e a navegacao de settings passaram a ser
  traduzidas nos 5 idiomas (o carry-over dizia que estavam em portugues sem
  i18n; ja estavam majoritariamente traduzidas e faltava fechar).
- `history` e `billing` do painel do usuario nao existiam; agora existem com
  estado vazio explicado, e a visao geral aponta para elas.

### Decidido

- ADR-025 (papeis, autorizacao, auditoria e o primeiro admin por comando - fecha
  a pendencia da SPEC secao 11.2), ADR-026 (idioma da conta x idioma de
  navegacao), ADR-027 (venda manual, com `gateway` pronto para a F6) e ADR-028
  (anonimizacao com guarda fiscal e exportacao em JSON).
- Valores dos planos definidos (Free 5 usos / 8 KB / 500 caracteres; Pro mensal
  e anual com 2.000.000 de tokens, 64 KB e 4.000 caracteres) e gravados na
  tabela `plans`, nunca no codigo.
- Venda manual nao tem botao no painel do usuario: quem concede e quem registra
  o pagamento e o admin; o gateway (Mercado Pago) entra na F6.

### Fora do escopo (proximas fases)

- `output_targets`, `ai_requests`, cache, DeepSeek, polling e o consumo real da
  cota (F5).
- Mercado Pago/interface `PaymentGateway`, doacao e webhooks, com a renovacao da
  franquia por periodo (F6).
- Historico de uso real, SEO final e hardening (F7); mensagens de validacao nos
  5 idiomas segue aberto (SPEC secao 11.2).

## F0 - Autenticacao, login social e newsletter - 2026-10-13

Bloco antecipado da F4, por decisao do grill: o ciclo de conta entrou junto da
F0, antes da home real. Plano em
`docs/plans/master/20261013_auth_login_plansfacts.md`.

### Adicionado

- Telas de acesso reconstruidas no design system (barra de 56px, card com borda
  de 1px, sem sombra): Login, Register, ForgotPassword, ResetPassword,
  VerifyEmail e ConfirmPassword, todas traduzidas nos 5 idiomas e com foco
  inicial, `aria-live`, `aria-invalid` e rotulos ligados aos campos.
- **Auth sob `/{locale}`** (ADR-022): `config/fortify.php` passa o caminho com o
  `{locale}` e o `Route::pattern` global valida o slug; os nomes de rota seguem
  os do Fortify (`route('login')`, `route('password.reset')`), resolvidos pelo
  `URL::defaults` do `ResolveLocale`. Endpoints de maquina ficam fora do
  prefixo: `/auth/{provider}/redirect|callback`.
- **Login social com Google e GitHub** (ADR-023): tabela `social_accounts`,
  vinculo com conta existente so com e-mail confirmado pelo provedor, conta nova
  sem senha (`users.password` anulavel) e botao sempre visivel.
- **Definir a primeira senha** em `POST /{locale}/user/password/set` a partir da
  tela de confirmar senha, com a area sensivel liberada em seguida.
- **Newsletter com duplo opt-in** (ADR-024): inscricao pelo rodape publico e
  pelo cadastro (checkbox desmarcado), confirmacao e descadastro decididos por
  POST com pagina intermediaria, token novo a cada envio, e-mail na fila no
  idioma do inscrito e rodape com o formulario.
- **Paginas de Privacidade e Termos** sob `/{locale}`, nos 5 idiomas,
  indexaveis, com hreflang normal, contato e data de revisao vindos de
  `config/legal.php`.
- E-mails traduzidos: `App\Notifications\ResetPassword` e `VerifyEmail` no lugar
  das notificacoes do framework, e `lang/{locale}/mail.php` para os assuntos e
  corpos.
- Testes: `LocalizedAuthPagesTest`, `SocialAuthTest`, `SetPasswordTest`,
  `NewsletterTest`, `LegalPagesTest` (Pest) e `auth-login.test.ts` +
  `newsletter-form.test.ts` (Vitest), com traducoes marcadoras para denunciar
  texto fixo nas telas.

### Corrigido

- **Ordem de middleware**: o `Authenticate` esta na lista de prioridade do
  framework e por isso rodava **antes** do `ResolveLocale`, que era anexado ao
  grupo `web`. O visitante sem sessao que abria `/dashboard` era redirecionado
  para o login antes de o idioma existir — `UrlGenerationException` no servidor
  (mascarada nos testes pelo `URL::defaults` do `TestCase`). Correcao:
  `prependToPriorityList(before: AuthenticatesRequests::class, ...)`.
- Link de token invalido da newsletter montava a URL da home com o codigo do
  idioma (`pt_BR`) em vez do slug (`pt-br`).
- Reinscricao de quem havia se descadastrado nao limpava `confirmed_at`: a
  pessoa voltava para a lista sem ter confirmado o e-mail novo.
- Data de revisao invalida em `config/legal.php` derrubava as paginas legais;
  agora cai para hoje.
- `SocialProviders::emailIsVerified()` lia o payload cru fora do contrato do
  Socialite (`AbstractUser`), o que quebraria com outro driver.
- **Placeholder de e-mail quebrava a tela inteira no SSR**: o `@` de
  `voce@exemplo.com` e sintaxe de mensagem vinculada do vue-i18n, e a mensagem
  nao compilava — a tela de login deixava de renderizar no servidor. Os 20
  textos afetados (5 idiomas) usam `{'@'}`, e `TranslationsParityTest` passou a
  falhar se aparecer `@` ou `|` cru em qualquer traducao. Achado no smoke de SSR,
  nao na suite: os testes de componente usam traducao marcadora.

### Decidido

- ADR-022 (auth sob o prefixo de idioma + rotas de maquina fora), ADR-023 (login
  social e conta sem senha) e ADR-024 (newsletter com duplo opt-in).
- Verificacao do e-mail pelo provedor: Google usa `email_verified`, GitHub usa a
  existencia do e-mail primario verificado; provedor que nao confirma nao vincula.
- `noindex, follow` + canonical e **sem** hreflang nas telas de acesso e paginas
  de token; as paginas legais seguem indexaveis com hreflang normal.

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
  gravado pela conta (F4) — entregue antecipado no bloco acima; restam as telas
  de settings traduzidas e as mensagens de validacao nos 5 idiomas.
- AI SQL Builder, planos, pagamentos e admin (F5 a F7).
