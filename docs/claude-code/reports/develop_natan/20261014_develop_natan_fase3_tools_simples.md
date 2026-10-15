# Relatorio - FASE 3 (tools simples) e o bloco 0 do deploy

Branch: `develop_natan`
Data: 2026-10-14
Tarefa: "execute a proxima fase 3"
Plano: `docs/plans/develop_natan/20261014_fase3_tools_simples_plansfacts.md`
Levantamento: `docs/survey/20261014_fase3_tools_simples_surveyfacts.md`
Aceite: `docs/acceptance/FASE-3.md`

**Relatorio em duas partes**: o **bloco 0** (deploy) foi o primeiro a rodar e ja
esta **commitado** (`ae47779`, pelo usuario); a **F3** (blocos 1 a 9) esta no
working tree, verde nos cinco gates e conferida por smoke com SSR. As duas partes
estao abaixo, com o que ficou pendente no fim.

## 1. O que foi pedido

Executar a F3 da secao 9 do SPEC - "Tools simples (3 a 8 do MVP)": `json-to-code`,
`sql-formatter`, `base64-url-hash`, `jwt-decoder`, `timestamp-uuid` e
`regex-tester`. As seis existiam so como `manifest.json` com
`"status": "soon"`; nenhuma tinha `Tool.vue`, teste ou texto em `lang/*/tools.php`.

No meio da sessao o usuario relatou um **bug de producao**: depois do deploy, a
home passou a mostrar chave de traducao crua no lugar do texto. Isso virou o
**bloco 0** da fase - a home voltar ao ar nao esperava a fase inteira.

## 2. Metodo

- **Levantamento por sub-agentes em paralelo** (registro de tools nos dois
  leitores, o shell e a pagina da tool, o pipeline de deploy, o historico das
  chaves de i18n), com citacao de `arquivo:linha` e instrucao de nao rodar
  suites.
- **Verificacao na fonte pelo orquestrador** de tudo que ia para uma decisao:
  `git log -S`, `git show` do `deploy.sh` em commits antigos, `git status` e
  `git ls-files`, leitura integral do `ToolShell.vue` e do `Show.vue`.
- **Tres rodadas de grill** (Q1 a Q23), com a fronteira recalculada a cada
  resposta. O usuario respondeu todas; a unica que divergiu do recomendado foi a
  Q23.
- Duas perguntas novas depois, ao encostar no `composer.lock` e na fragilidade do
  script (secao 6).
- **Execucao em blocos**, cada um com os testes do que ele mexia antes de
  seguir - a extracao dos ajudantes (bloco 1b) foi a que mais risco tinha, por
  mexer em codigo ja commitado, e a suite do `json-viewer` foi a rede.

## 3. O bug de producao (bloco 0)

**Sintoma relatado**: a home mostra `app.actions.search_placeholder`,
`home.search_hint`, `home.status_title`, `home.status_body`, `home.ai_title` e
`home.ai_body` no lugar do texto - enquanto "Guia de estilo"
(`app.nav.styleguide`) aparece certo.

**Diagnostico**: a producao serve um **bundle JavaScript anterior a F1**.

- `git log -S` das chaves: nasceram em `172aea0` e foram removidas em `1f00431`,
  o commit da F1. O `Home.vue` atual pede `home.ai.*`, `home.cta.register`,
  `home.pricing.*` e `home.tools.*` (aninhadas) e **nunca** as chaves planas que
  aparecem na tela - nenhum bundle do repositorio hoje as emite.
- `lang/pt_BR/app.php` tem `'styleguide' => 'Guia de estilo'`, e essa chave
  sobreviveu a refatoracao inteira, entao continua resolvendo: e exatamente o que
  o usuario ve.
- Ou seja: os arquivos de idioma no servidor sao **os de hoje** e o JavaScript e
  **antigo**.

**Mecanismo**: o `deploy.sh` roda com `set -euo pipefail` e publica os assets
depois do `git pull` e do `composer install`. Uma falha no bloco do meio deixa o
**PHP novo com o JS velho**. Como o `git pull` ja moveu o HEAD para
`origin/main`, a execucao seguinte do cron sai cedo e **um deploy que falha nunca
e retentado** ate entrar commit novo. Nem `migrate` nem `queue:restart` rodam
nesse caso (verificado: nem `1f00431` nem `edb62b2` trazem migration, entao nao
havia risco de banco).

**Correcao de uma leitura minha**: eu havia afirmado que o mecanismo de release
por symlink entrou em `b9a7f78` e que o primeiro bundle a depender dele era o da
F1. **E falso**: `git show 172aea0:deploy/deploy.sh` mostra o mecanismo inteiro ja
no commit inicial, e o `b9a7f78` so moveu o arquivo do `flock`. Isso abriu a
hipotese mais provavel de todas: **o cron talvez nunca tenha publicado nada**, e o
`public/build` que a producao serve seja um build **manual** da subida do VPS.

## 4. Hipoteses descartadas por verificacao local

Duas cairam por medicao, nao por argumento.

- (a) `vite build --outDir` apontando para uma release fora de `public/`:
  **descartada**. O comando exato do script roda aqui com exit 0, em 8,25s; o
  `laravel-vite-plugin` nao recusa o `outDir`.
- (b) `npm ci` falhando por lockfile fora de sincronia ou por falta do binario
  nativo: **descartada**. `npm ci --dry-run` com os arquivos do HEAD instala 422
  pacotes sem erro; o lock esta completo e multiplataforma (o Vite 8 usa
  **Rolldown**; os `@rollup/rollup-*` que o `package.json` fixa em
  `optionalDependencies` sao pinos orfaos).
- (c) **rede no build** - a candidata que sobrou e a que ficou registrada no
  ADR-014: o plugin `fonts` do `laravel-vite-plugin` baixa Inter e JetBrains Mono
  do Bunny por `fetch` sempre que nao tem cache, e o cache mora em
  `node_modules/.cache/`, que toda instalacao de dependencias apaga.

O diagnostico final dependia do log do servidor (cinco comandos, no fim deste
relatorio) - e ele nao veio, porque o usuario decidiu encerrar o bloco 0 pelo
outro caminho: **so documentar** a fragilidade e seguir (secao 6).

## 5. O que o bloco 0 entregou (commit `ae47779`)

| Arquivo | Mudanca | Por que |
|---|---|---|
| `deploy/deploy.sh` | o gatilho de dependencias passa de `sha256sum package-lock.json` para `sha256sum package.json`; `npm ci` vira `npm install --no-audit --no-fund`; o arquivo de estado vira `storage/framework/npm-deps.sha256` | Q23: o lockfile sai do git, nao ha lock para comparar |
| `deploy/deploy.sh` | comentario acima do `composer install` explicando que, sem lockfile, ele resolve a arvore na hora e escreve um lock novo | a decisao "Deixar `composer install`" (secao 6) |
| `.gitignore` | `package-lock.json` e `composer.lock` entram na lista | os dois saem do versionamento |
| `docs/adr/ADR-014-deploy.md` | a parte de dependencias de front reescrita; bullets novos em Consequencias (o build deixa de ser reproduzivel; o build depende de rede; a falha no meio do deploy **mantida de proposito**) | o ADR descrevia o contrato antigo (`npm ci`, `package-lock`) e passaria a descrever o contrario do que o script faz |
| `docs/RUNBOOK.md` | secao 2 atualizada (`npm install` + hash do `package.json`, lockfiles fora do git), **secao nova "Quando o deploy falha no meio"** com sintoma, diagnostico e conserto, e a correcao de dois caminhos errados (o `flock` e o `fuser` ainda apontavam para `/tmp`) | a decisao "So documentar" e a limpeza do que a mudanca tornou falso |
| `CHANGELOG.md` | bloco proprio, antes do da F3 | a mudanca e operacional, e nao da fase |

**O que o usuario fez do lado dele**: commitou o bloco em `ae47779`, na
`develop_natan` (e nao numa branch curta propria, como o plano previa) e empurrou
para `origin/develop_natan`. **A producao nao mudou**: o cron observa o `main`, e
o `main` segue em `abfbb0c` (`first commit`, 14 commits atras). Enquanto o merge
nao acontecer, a home em producao continua com o bundle antigo - nem a F2 nem a
F3 estao publicadas.

## 6. Decisoes do usuario

As 23 da fronteira estao na tabela do plano, com o texto completo. As que mudam
o rumo:

- **Q23, a divergencia**: eu recomendei **reverter** a mudanca do `.gitignore` e
  manter o `package-lock.json` versionado; o usuario escolheu **parar de
  versionar de verdade** - sai do git e o `deploy.sh` passa a `npm install` com
  gatilho no `package.json`. Consequencia assumida: **o build no servidor deixa
  de ser reproduzivel**.
- **O `composer.lock` tambem saiu do git** (o usuario ja tinha feito
  `git rm --cached`). Perguntado como o script deveria tratar: **"Deixar
  `composer install`"** - sem lockfile ele resolve sozinho e escreve o lock novo.
- **A fragilidade do deploy**: perguntado se a falha no meio (PHP novo com JS
  velho, sem retentativa) deveria ser corrigida junto: **"So documentar"**.
- **Q22**: emendar o ADR-029 e criar o ADR-031 (modelo de seguranca do Regex
  Tester), em vez de um ADR novo para cada assunto.
- **Q6**: alinhar `hidden` em `Tools::all()` **agora**, com teste, e nao quando a
  primeira tool escondida existir.

## 7. A execucao da F3 (blocos 1 a 8)

**Bloco 1 - chao da fase.** `docs/acceptance/FASE-3.md` com os 32 criterios; os
ajudantes que duas ou mais tools usam sairam de dentro do `json-viewer` para
`components/tool/` (`CodeEditor.vue`, `FileDropzone.vue`), `lib/`
(`clipboard`, `download`, `bytes`, `file`, `position`, `encoding`) e
`composables/` (`useCopyFeedback`, `useFileError`); `tools.ui.*` deixou de falar
de JSON nos 5 idiomas (as chaves orfas foram removidas - o
`TranslationsParityTest` cobra os dois lados); `config/tools.php` e `.env.example`
ganharam os dois limites do Regex Tester; `sql-formatter` e
`@codemirror/lang-sql` entraram no `package.json`.

**Bloco 2 - `json-to-code`.** `lib/parse` e `lib/types` do viewer viraram
`lib/json/`, e a tool nova trouxe `lib/names.ts` (identificador por linguagem, a
regra de aspas do TypeScript e o `_` no lugar do caractere proibido), `lib/infer.ts`
(modelo de tipos), `lib/plan.ts` (nome unico por objeto) e
`lib/emit/{typescript,php,pydantic}.ts`. O caso que mais deu trabalho foi o
Pydantic: o Python le a classe no momento em que a define, entao os modelos saem
**do mais fundo para o mais raso**.

**Bloco 3 - `sql-formatter`.** `lib/dialects.ts` traduz os quatro slugs do
glossario para os nomes das bibliotecas (e para os objetos de linguagem do
CodeMirror), `lib/format.ts` envolve o `sql-formatter` com resultado proprio, e o
erro da biblioteca vira linha e coluna por `lib/position.ts`. Foi aqui que
nasceu o cliente de worker generico (`lib/worker/`), que o viewer passou a usar.

**Bloco 4 - `base64-url-hash`.** Base64 UTF-8 e URL-safe saem de `lib/encoding.ts`
(compartilhado com o JWT Decoder, que le base64url), o MD5 e do repositorio -
conferido contra os vetores do RFC 1321 - e os tres SHA saem da Web Crypto, com a
razao escrita quando `crypto.subtle` nao existe.

**Bloco 5 - `jwt-decoder`.** Decodifica e nao valida: a assinatura sai so como
comprimento em bytes, a faixa de aviso fica fixa no topo da saida, e as claims de
tempo ganham data legivel no idioma e selo de estado.

**Bloco 6 - `timestamp-uuid`.** A unidade sai da quantidade de digitos, o texto
de data e aceito, e um UUID v7 ou ULID colado tem o instante embutido lido de
volta. Os geradores (v4, v7, ULID) sao proprios, menos o v4 (`crypto.randomUUID`).

**Bloco 7 - `regex-tester`.** `lib/run.ts` (funcao pura), `lib/run.worker.ts`
(worker descartavel), `lib/run.workerHost.ts` e `lib/runner.ts` - o unico lugar
da F3 que chama `terminate()`. O ADR-031 registra o modelo: estourou o prazo, a
tela nao mostra resultado nenhum; padrao invalido volta como resultado; sem
worker (SSR, jsdom, CSP) a busca roda na thread principal e **sem prazo**.

**Bloco 8 - virada de status e SEO.** Os seis manifestos viram `available`, o
`seo:og-image` escreve os seis PNGs novos em `public/og/` (comitados, ADR-030), e
`Tools::all()` passa a excluir `hidden`, como o leitor TypeScript. O consumidor
que motivou o alinhamento estava fora da conta do ADR-007:
`GenerateOgImage` desenha o cartao padrao a partir de `all()`, e uma tool
escondida apareceria no nome dos cards. Os testes que afirmavam "so o
`json-viewer` e available" foram atualizados para as sete.

## 8. Verificacao

| Comando | Resultado |
|---|---|
| `composer test` (Pint + PHPStan nivel 7 + Pest) | verde: **268 testes, 7166 asserts** |
| `npm run test` (Vitest) | verde: **46 arquivos, 659 testes** |
| `npm run types:check` | exit 0 |
| `npm run build` | exit 0 - CodeMirror e `sql-formatter` em chunk lazy, fora do chunk da home |
| `npm run build:ssr` | exit 0 |
| **Smoke com SSR** | **541 conferencias, 0 falhas** |

O gate do Vitest e **sensivel a carga da maquina**: a rodada da tabela passou
com a maquina calma, e as reconferencias feitas depois, com a maquina entre 65%
e 99% de CPU, flaquearam por tempo - o relato esta na secao 10 e em
`docs/acceptance/FASE-3.md`.

O smoke rodou contra servidor de verdade (`php artisan serve` com
`INERTIA_SSR_ENABLED=true` **e** o processo do SSR no ar, sobre um
`storage/smoke.sqlite` migrado - o MySQL local nao tem a tabela `plans`) e
conferiu, nas **7 tools x 5 idiomas**: status 200, `title` com o nome traduzido,
`description` traduzida, canonical, `og:image` da propria tool, `og:image:alt`
com o nome, `og:title`, os 6 `hreflang`, a pagina do Inertia, o `h1` no HTML do
servidor, a introducao traduzida - e, no sentido contrario, **sem** `cm-editor`
(o editor nao monta no servidor, por desenho) e **sem** chave de traducao crua.
Mais os **3 404** do contrato (a tool que nao funciona e um slug que nao existe,
em dois idiomas), as **15 paginas de conteudo** (home, Privacidade e Termos nos 5
idiomas) e o **sitemap**: 200, **50 `<loc>`** (10 paginas x 5 idiomas), as 35 URLs
de tool e nenhuma do `ai-sql-builder`.

Duas coisas que o smoke ensinou, e que ficaram escritas no proprio script:

- **Apostrofo**: o SSR do Vue escreve `&#39;` e o `e()` do PHP escreve `&#039;`,
  entao o HTML buscado e decodificado (`html_entity_decode`) antes de comparar com
  o texto do `trans()`. Sem isso, tres conferencias do frances caiam por
  diferenca de entidade, e nao por defeito.
- **`inertia:start-ssr` recusa rodar** quando `inertia.ssr.enabled` e falso: o
  comando precisa do `INERTIA_SSR_ENABLED=true` no ambiente, mesmo com a
  variavel ligada no `.env` do processo que serve a pagina.

O criterio 24 (nada de requisicao ao servidor) foi conferido por `grep`: nenhuma
ocorrencia de `fetch`, `axios` ou `XMLHttpRequest` em `resources/js/tools/`,
`resources/js/lib/` ou `resources/js/composables/`.

## 9. Desvios do plano

Estao detalhados no `docs/acceptance/FASE-3.md` (secao Desvios), e os que mais
importam para quem for ler o codigo:

1. **Worker tambem no SQL Formatter** (a Q4 dizia worker so no Regex Tester).
   Ali ele e fluidez, e nao protecao - a distincao esta no ADR-031.
2. **`useCodeEditor` nao existe**: o componente `CodeEditor.vue` sobre
   `lib/editor/` bastou.
3. **`lib/json/{parse,types}.ts`** num diretorio proprio (o plano dizia `lib/`).
4. **`lib/position.ts` e novo** - nasceu no SQL Formatter e o viewer passou a
   usar.
5. **`lib/encoding.ts` compartilhado** no lugar de `lib/base64.ts` + `lib/url.ts`
   dentro da tool; o que fica no diretorio dela e `convert.ts`, `hash.ts` e
   `md5.ts`.
6. **O worker do Regex Tester e `lib/run.worker.ts`** (o plano dizia
   `lib/regex.worker.ts`).
7. **O realce do casamento ficou no painel de saida** (o texto de teste pintado),
   e nao dentro do editor da entrada.
8. **`ToolButton` ganhou `ariaLabel`** (os alternadores de flag mostram o
   simbolo).
9. **Nomes dos testes**: `json-to-code-emit.test.ts` e
   `json-to-code-infer.test.ts`; nao existe `code-editor.test.ts` - o editor e
   coberto por `json-editor-keys.test.ts` e `json-editor-theme.test.ts`.
10. **O namespace `model_*` do Pydantic nao e tratado** (prefixo protegido da
    biblioteca; ficou fora do escopo).
11. **`npm run format:check` nao e gate da fase** e nao foi usado como criterio.

## 10. Achados incidentais do gate

- **`AdminPanelTest` tinha um teste instavel** (nao e da F3): o teste de busca
  fixava o termo "ana" e criava o admin com e-mail sorteado pelo Faker. Medido:
  **28 de 3000** e-mails gerados pelo Faker contem "ana" (`nathanael`, `mariana`,
  `susana`), e a busca casa nome **ou** e-mail - a lista voltava com duas contas
  em cerca de 1% das execucoes. Ele passava sozinho e caiu na primeira rodada da
  suite inteira. Corrigido fixando os e-mails do teste.
- **A aritmetica do plano estava errada**: o sitemap nao tem 45 URLs (9 paginas x
  5 idiomas) e sim **50** (10 x 5) - a conta do plano esqueceu a propria pagina
  da tool, que entrou na F2. Conferido por contagem no XML do servidor.
- **`resources/js/node_modules/`**, um diretorio com so um cache `.vite` dentro,
  apareceu no working tree (nao esta no `.gitignore`, que so cobre
  `/node_modules`) e foi removido na limpeza - se tivesse ido junto de um
  `git add -A`, entraria no commit.
- **A suite do Vitest e sensivel a carga da maquina** (achado das
  reconferencias, nao e da F3 e nao e defeito do codigo): com a maquina em 65% a
  99% de CPU - o VS Code e o navegador de quem revisa abertos, 8 CPUs aqui - a
  mesma suite que fechou a fase verde falhou de tres jeitos, todos por tempo:

  1. `Failed to start forks worker`: o Vitest abre um fork por CPU e o pool
     desiste antes de o worker responder (rodada com 35 de 46 arquivos);
  2. testes de componente estourando os **5s padrao** do Vitest com `setTimeout`
     faminto - o `command-palette.test.ts` chega a estourar dentro do `montar()`,
     e o `json-viewer.test.ts` le o editor antes de o `FileReader` responder
     (a assercao ve `''`);
  3. um teste que estoura o prazo deixa a continuacao pendente, e ela cai no
     teste **seguinte**: o duble de `navigator.clipboard` e redefinido a cada
     teste, entao a copia atrasada do teste anterior entra na conta do seguinte
     (foi assim que o teste dos quatro hashes recebeu as linhas da aba URL).

  As provas de que o problema e a carga: os sete arquivos que falharam em alguma
  rodada passam **sozinhos** (132 testes verdes em duas rodadas de isolamento), e
  a suite inteira passa com menos paralelismo - `npm run test -- --maxWorkers=2`
  deu **46 arquivos, 658 de 659** (o unico vermelho foi o `command-palette` por
  prazo, e ele passa sozinho), enquanto a rodada de fechamento, com a maquina
  calma, tinha dado **659 de 659**. Nada foi aplicado no `vitest.config.ts`: se o
  incomodo voltar, `maxWorkers` menor e/ou um `testTimeout` maior resolvem, e a
  decisao e de quem mantem o gate.

## 11. O que ficou pendente

- **Conferencia manual no navegador**: o jsdom nao cobre CodeMirror, arrasto de
  ponteiro nem worker, entao um caso feliz e um caso de erro de cada uma das seis
  tools no navegador continuam sendo de quem revisa - em especial o Regex Tester
  com um padrao catastrofico (`(a+)+$`) sobre um texto longo, que e onde o prazo
  do ADR-031 aparece.
- **A publicacao em producao**: depende do merge em `main`. O cron so observa o
  `main`, e ele segue em `abfbb0c`. Vale rodar o diagnostico do bloco 0
  (`docs/RUNBOOK.md`, secao "Quando o deploy falha no meio") **depois** desse
  merge, para conferir se o deploy novo publicou: `ls -l public/build` deve
  mostrar symlink, e `tail storage/logs/deploy.log` deve terminar com o deploy
  concluido.
- **Documentacao**: nada pendente - SPEC (secoes 4, 5, 9 e 11.1), DESIGN,
  glossario, ADR-007 (o `hidden`), ADR-029 (emenda da F3), ADR-031, ADR-014,
  RUNBOOK, CHANGELOG e o aceite estao atualizados.

## 12. Nenhum commit

Regra do CLAUDE.md respeitada: **nada foi commitado por mim** nesta fase. O
`ae47779` (bloco 0) e do usuario. Todo o trabalho da F3 esta no working tree,
para o usuario commitar via gitpr.

Um detalhe do indice: **este relatorio ja existe no `ae47779`**, numa versao de
195 linhas escrita no meio da sessao (quando o bloco 0 ainda estava parado e os
blocos 1 a 9 nao tinham comecado). A versao do working tree - a que esta sendo
lida - substitui aquela, e o proprio `git status` mostra o arquivo como
modificado.

## 13. Arquivos no working tree

Conferido no fim, depois da limpeza (o `storage/smoke.sqlite` e o
`resources/js/node_modules/` ja nao estao mais). Contagem por
`git status --porcelain`:

**Modificados (47)**: os seis `manifest.json`, `app/Support/Tools.php`,
`app/Http/Controllers/ToolController.php`, `config/tools.php`, `.env.example`,
`package.json`, os cinco `lang/*/tools.php`, `resources/js/pages/tools/Show.vue`,
`resources/js/components/tool/{ToolButton,ToolShell}.vue`, o `json-viewer`
(`Tool.vue`, `TreeRow.vue`, `lib/analyze.ts`, `lib/transform.ts`, `lib/tree.ts`),
os testes (`tests/Feature/{AdminPanelTest,SeoMetaTest,SitemapTest}.php`,
`tests/Feature/Tools/{ToolPageTest,ToolsRegistryTest}.php` e 11 arquivos de
`resources/js/__tests__/`), e as docs (`SPEC`, `DESIGN`, `glossario`, `CHANGELOG`,
`ADR-007`, `ADR-029` e este relatorio).

**Removidos (5)**: os arquivos do `json-viewer` que viraram ajudante
compartilhado - `editor/JsonEditor.vue`, `editor/createEditor.ts`,
`editor/theme.ts`, `lib/bytes.ts` e `lib/download.ts` (os tres primeiros vivem
agora em `components/tool/CodeEditor.vue` e `lib/editor/`).

**Movidos com edicao por cima (2)**: `lib/json/parse.ts` e `lib/json/types.ts`
saem de `tools/json-viewer/lib/`; os dois estao **no index como renomeacao**
(estado intermediario de quem os moveu) com a edicao da extracao ainda por
estagiar - um `git add -A` antes do commit resolve, e nada se perde.

**Novos (78)**: as seis tools (`Tool.vue`, `lib/`, `sample.*`), os ajudantes
compartilhados (`components/tool/{CodeEditor,FileDropzone}.vue`, `lib/editor/`,
`lib/json/`, `lib/worker/`, `lib/{bytes,clipboard,download,encoding,file,position}.ts`,
`composables/{useCopyFeedback,useFileError}.ts`), 20 arquivos de teste novos de
Vitest, as seis imagens OG, `docs/acceptance/FASE-3.md` e o
`docs/adr/ADR-031-seguranca-do-regex-tester.md`.
