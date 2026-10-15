# Criterios de aceite — FASE 3 (tools simples: 3 a 8 do MVP)

Gerado em 2026-10-14, no inicio da fase (regra do `docs/SPEC.md` secao 9).
Fonte: `docs/SPEC.md` secao 9 (F3) + secao 4 (registro de tools e a pagina
generica) + secao 5 (as seis ferramentas) + `docs/design/DESIGN.md` (secao
"Pagina de tool") + as decisoes do plano de execucao da sessao
(`docs/plans/develop_natan/20261014_fase3_tools_simples_plansfacts.md`, Q1 a
Q23).

## Escopo da fase

As seis ferramentas simples do MVP - `json-to-code`, `sql-formatter`,
`base64-url-hash`, `jwt-decoder`, `timestamp-uuid` e `regex-tester` -, que
existiam desde a F1 como `manifest.json` com `status: "soon"`. A fase troca o
status das seis para `available`, escreve o `Tool.vue` e o `lib/` de cada uma e
**extrai para fora do `json-viewer`** o que duas ou mais ferramentas usam - o
molde do shell ja existe (F2, ADR-029) e nao muda.

Tudo roda no navegador: nenhuma das seis faz requisicao ao servidor
(CLAUDE.md). O unico trabalho novo do servidor e entregar os dois limites do
Regex Tester como prop, do mesmo jeito que o teto de entrada ja e entregue.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | As seis tools respondem `available` no registro (home, busca e sitemap), e a pagina de cada uma responde 200 nos 5 idiomas | `tools-registry.test.ts` + `home.test.ts` + `ToolPageTest` |
| 2 | A pagina de cada uma das 6 e a mesma pagina generica da F2 - nenhuma pagina, rota ou componente de shell novo | `git diff` das rotas + `tool-page.test.ts` |
| 3 | Toda tool usa o `ToolShell`, com `splitKey` igual ao slug (a divisao fica lembrada por ferramenta) | `tool-shell.test.ts` (a lembranca por tool) + `split-key` lido nos sete `Tool.vue` |
| 4 | O que duas ou mais tools usam saiu do `json-viewer` para `lib`/`composables`/`components/tool` (copiar, abrir arquivo e arrastar-e-soltar, baixar, tamanho em bytes, editor de codigo parametrizado por linguagem), e o `json-viewer` passou a consumir os mesmos | `json-viewer.test.ts` (inalterado no comportamento) + os imports dos novos `Tool.vue` |
| 5 | Os textos da casca (`tools.ui.*`) deixaram de falar de JSON nos 5 idiomas; os limites de caractere do texto por tool (o erro de linha/coluna e o que aceita `.json`) continuam com quem os produz | `TranslationsParityTest` + testes de componente |
| 6 | `json-to-code`: JSON -> **TypeScript**, **PHP DTO** e **Pydantic**, com o alvo escolhido no cabecalho da saida e um nome de raiz literal (padrao `Root`), igual nas tres saidas | `json-to-code.test.ts` + `json-to-code-emit.test.ts` |
| 7 | `json-to-code`: inferencia completa - campo ausente em parte do array vira opcional, array misto vira uniao, `null` vira anulavel, inteiro e decimal se distinguem, objeto e array vazios tem tipo proprio | `json-to-code-infer.test.ts` |
| 8 | `json-to-code`: o PHP sai `final class` com `declare(strict_types=1)` e propriedades promovidas `public readonly` | `json-to-code-emit.test.ts` |
| 9 | Entrada invalida **mantem a ultima saida valida na tela** no `json-to-code` e no `sql-formatter` | `json-to-code.test.ts` + `sql-formatter.test.ts` |
| 10 | `sql-formatter`: os 4 dialetos do SPEC (`mysql`, `pgsql`, `sqlsrv`, `sqlite`), com a caixa das palavras-chave como unica opcao (padrao maiusculas) e indentacao fixa | `sql-dialects.test.ts` + `sql-format.test.ts` |
| 11 | `sql-formatter`: o erro da biblioteca vira **linha e coluna** na tela, e o dialeto padrao `mysql` nao deixa dialeto nenhum "lembrado" de uma visita anterior | `sql-format.test.ts` + `sql-formatter.test.ts` |
| 12 | `base64-url-hash`: Base64 (UTF-8 de verdade, nao `btoa` cru) e a variante URL-safe sem padding, com a **direcao** (codificar/decodificar) no cabecalho da entrada | `encoding-base64.test.ts` + `base64-url-hash.test.ts` |
| 13 | `base64-url-hash`: componente de URL (`encodeURIComponent`) e formulario (`+` no lugar do espaco) nas duas direcoes | `encoding-url.test.ts` |
| 14 | `base64-url-hash`: os quatro hashes (MD5, SHA-1, SHA-256, SHA-512) na mesma tela, hex minusculo, com copiar; o MD5 e implementado no repositorio (a Web Crypto nao tem MD5), conferido contra os vetores do RFC 1321 | `md5.test.ts` + `hash.test.ts` |
| 15 | `jwt-decoder`: le cabecalho e payload de um token colado, tolerando o prefixo `Bearer`; `exp`/`iat`/`nbf` aparecem como data legivel no idioma, com "expirado" no token de erro | `jwt-decode.test.ts` + `jwt-decoder.test.ts` |
| 16 | `jwt-decoder`: a assinatura aparece **so como comprimento em bytes**, com a faixa fixa de aviso de que a ferramenta decodifica e **nao valida** | `jwt-decoder.test.ts` |
| 17 | `timestamp-uuid`: aceita digitos (unidade pela quantidade) e texto de data, e sai com Unix em segundos, Unix em ms, ISO em UTC, ISO local, data por extenso no idioma e tempo relativo | `time.test.ts` + `timestamp-uuid.test.ts` |
| 18 | `timestamp-uuid`: gera UUID v4, UUID v7 e ULID, em 1, 5 ou 10 de uma vez, com "copiar todos"; reconhece UUID v7 e ULID colados e mostra o instante embutido | `ids.test.ts` + `timestamp-uuid.test.ts` |
| 19 | `regex-tester`: o padrao fica na barra de acoes, em linha inteira, com as flags (`i`, `m`, `s`, `u`) como alternadores e `g` sempre ligado | `regex-tester.test.ts` |
| 20 | `regex-tester`: lista de matches (indice, trecho, posicao e grupos numerados e nomeados), o texto de teste com o match realcado **no painel de saida** e o campo de substituicao com previa (`$1`, `$<nome>`) | `regex-tester.test.ts` + `regex-run.test.ts` |
| 21 | `regex-tester`: a execucao roda em Web Worker e **estoura por tempo** acima do limite de config, terminando o worker; no estouro a tela nao mostra resultado nenhum, so o aviso | `regex-worker.test.ts` + `regex-tester.test.ts` |
| 22 | `regex-tester`: a lista de matches para no teto de config, com aviso de corte | `regex-run.test.ts` + `regex-tester.test.ts` |
| 23 | Os dois limites novos do Regex Tester (tempo e teto de matches) vem de `config/tools.php` com entrada no `.env.example`, e chegam ao componente por prop | `ToolPageTest` |
| 24 | Nenhuma das seis tools faz requisicao ao servidor (nem `fetch`, nem `axios`, nem `XMLHttpRequest`) | `grep` nos modulos das tools + revisao |
| 25 | Zero string hardcoded nas telas novas, com paridade nos 5 idiomas e sem plural com `\|` | `TranslationsParityTest` + testes de componente |
| 26 | Toda tool `available` tem imagem OG em `public/og/<slug>.png` (as 6 novas), com o `alt` traduzido por idioma | `SeoMetaTest` + o comando `seo:og-image` |
| 27 | `App\Support\Tools::all()` passa a excluir `hidden`, ficando igual ao leitor TypeScript | `ToolsRegistryTest` |
| 28 | Os testes que afirmavam "so o `json-viewer` e available" foram atualizados para as 7, sem perder o que afirmavam | `ToolsRegistryTest`, `SitemapTest`, `SeoMetaTest`, `tools-registry.test.ts`, `home.test.ts` |
| 29 | Gates: `composer test`, `npm run test`, `npm run types:check`, `npm run build`, `npm run build:ssr` | os cinco comandos |
| 30 | O SSR entrega casca e metas das 7 paginas de tool nos 5 idiomas | smoke com SSR |
| 31 | Documentacao atualizada: SPEC (secoes 4, 5, 9 e 11.1), DESIGN (a excecao do Regex Tester e as listas no cabecalho do painel), ADR-029 emendado, ADR-031 (modelo de seguranca do Regex Tester), glossario e CHANGELOG | leitura dos documentos |
| 32 | Nenhum commit feito pelo assistente | `git log` |

## Fora do escopo da F3

- **Qualquer coisa que precise do servidor**: as seis tools rodam no navegador;
  upload, historico de uso e cota ficam para depois (F4/F7).
- **AI SQL Builder** (F5), gateway e doacao (F6), SEO final e hardening (F7).
- HMAC no `base64-url-hash`, verificacao de assinatura no `jwt-decoder`
  (chave publica, JWKS ou HS256), e o `regex-tester` com `y` (sticky) ou
  substituicao aplicada ao texto - a previa e previa.
- As ferramentas 9+ do catalogo e a pagina de indice `/tools` (nao existem no
  SPEC).
- O conserto do deploy (bloco 0 da sessao) - veio **antes** das tools, no commit
  `ae47779` (lockfiles fora do git, gatilho do `npm install` no hash do
  `package.json`, falha no meio do script mantida de proposito e documentada no
  ADR-014/RUNBOOK). Nao faz parte do aceite desta fase, e a publicacao depende do
  merge em `main` - o cron so observa o `main`, e ele segue atras.

## Resultado

**Fase concluida em 2026-10-14.** Os 32 criterios foram verificados; o que cada
um pediu e onde a prova esta:

| Verificacao | Resultado |
|---|---|
| `composer test` (Pint + PHPStan nivel 7 + Pest) | verde: 268 testes, 7166 asserts |
| `npm run test` (Vitest) | verde: 46 arquivos, 659 testes |
| `npm run types:check` | exit 0 |
| `npm run build` | exit 0 (CodeMirror e `sql-formatter` em chunk lazy, fora do chunk da home) |
| `npm run build:ssr` | exit 0 |
| Smoke com SSR e banco de verdade (7 tools x 5 idiomas + 3 404 + 15 paginas de conteudo + sitemap) | **541 conferencias, 0 falhas** |
| Sitemap | 200 com **50 `<loc>`** (10 paginas x 5 idiomas) e sem `ai-sql-builder` |
| Requisicao ao servidor nos modulos das tools (criterio 24) | `grep` por `fetch`/`axios`/`XMLHttpRequest` em `resources/js/tools/`, `lib/` e `composables/`: nenhuma ocorrencia |
| `git log` (criterio 32) | nenhum commit do assistente |

O que o smoke conferiu em cada uma das 35 paginas: status 200, `title` com o
nome traduzido, `description` traduzida, canonical, `og:image` da propria tool e
`og:image:alt` com o nome do idioma, exatamente 6 `hreflang`, a pagina do Inertia
e o `h1` no HTML do servidor - e, no sentido contrario, **sem** editor montado
(`cm-editor` ausente, porque o `ClientOnly` e o unico caminho) e **sem** chave de
traducao crua. Os tres 404 sao os do contrato: a tool que ainda nao funciona
(`ai-sql-builder`) e um slug que nao existe, em dois idiomas.

### Nota sobre o gate do Vitest (carga da maquina)

A rodada que fechou a fase (46 arquivos, 659 testes) passou com a maquina calma.
Nas reconferencias feitas depois, com a maquina entre 65% e 99% de CPU - o VS
Code e o navegador de quem revisa abertos, em 8 CPUs -, a mesma suite falhou de
tres jeitos, todos por tempo e nenhum do codigo:

1. `Failed to start forks worker`: o Vitest abre um fork por CPU e o pool
   desiste antes de o worker responder (uma das rodadas conferiu 35 dos 46
   arquivos).
2. Testes de componente estourando os **5s padrao** do Vitest, com `setTimeout`
   faminto: o `command-palette.test.ts` chega a estourar dentro do `montar()`, e
   o `json-viewer.test.ts` le o editor antes de o `FileReader` responder.
3. Um teste que estoura o prazo deixa a continuacao pendente, e ela cai no teste
   seguinte: o duble de `navigator.clipboard` e redefinido a cada teste, entao a
   copia atrasada do teste anterior entra na conta do seguinte - foi assim que o
   teste dos quatro hashes recebeu as linhas da aba URL.

O que prova que o problema e a carga: os **sete** arquivos que falharam em alguma
rodada passam sozinhos (132 testes verdes em duas rodadas de isolamento), e a
suite inteira passa com menos paralelismo - `npm run test -- --maxWorkers=2` deu
46 arquivos e 658 de 659 testes (o unico vermelho foi o `command-palette` por
prazo, e ele passa sozinho). Nada foi mexido no `vitest.config.ts`; se o
incomodo voltar, `maxWorkers` menor e/ou um `testTimeout` maior resolvem.

### Desvios do plano (o que saiu diferente do que foi combinado)

1. **Worker tambem no SQL Formatter.** A Q4 dizia worker so no Regex Tester; a
   formatacao entrou em worker (`lib/format.worker.ts` + host) com queda para a
   thread principal, como no JSON Viewer. Ali ele e fluidez, e nao protecao - a
   distincao esta no ADR-031.
2. **`useCodeEditor` nao existe.** O plano citava um composable ao lado do
   `CodeEditor.vue`; ficou so o componente, sobre `lib/editor/`.
3. **`lib/json/{parse,types}.ts`**: o plano dizia mover para `lib/`; foram para
   um diretorio proprio, porque `lib/` ja tem `bytes.ts` e `file.ts` e o par
   parse+tipos e uma unidade.
4. **`lib/position.ts` e novo** (nao estava no plano): a conversao de mensagem
   de erro em linha/coluna e usada pelo SQL Formatter e pelo JSON Viewer.
5. **`base64-url-hash`**: os `lib/base64.ts` e `lib/url.ts` do plano viraram
   `lib/encoding.ts` **compartilhado** (o JWT Decoder le base64url) mais
   `lib/convert.ts` (a composicao da tela), `lib/hash.ts` e `lib/md5.ts`.
6. **O worker do Regex Tester chama-se `lib/run.worker.ts`** (o plano dizia
   `lib/regex.worker.ts`), com `run.ts` (funcao pura), `run.workerHost.ts` e
   `runner.ts` (o unico lugar que encerra worker).
7. **O realce do casamento ficou no painel de saida** (o texto de teste pintado,
   `bg-accent-2/30`), e nao dentro do editor da entrada: a lista, o realce e a
   previa da substituicao sao a mesma leitura, e ler as tres coisas no mesmo
   painel e o que a tela pede.
8. **`ToolButton` ganhou a prop `ariaLabel`** (nao estava no plano): os
   alternadores de flag mostram `i`, `m`, `s`, `u`, e o simbolo nao e rotulo.
9. **Nomes dos testes**: `json-to-code-emit.test.ts` e `json-to-code-infer.test.ts`
   (o plano dizia `json-emit`/`json-infer`); nao existe `code-editor.test.ts` - o
   editor e coberto por `json-editor-keys.test.ts` e `json-editor-theme.test.ts`,
   e a casca por `tool-shell.test.ts`.
10. **Pintura do JSON reaproveitada**: o JWT Decoder usa os tokens `--json-*` do
    DESIGN (nome herdado do viewer) em vez de tokens proprios.
11. **O namespace `model_*` do Pydantic nao e tratado**: um campo `model_id` sai
    como `model_id`, sem renomear nem avisar. O Pydantic v2 trata `model_` como
    prefixo protegido (e reclama disso ao importar o modelo gerado); ficou fora
    do escopo desta fase.
12. **`npm run format:check` nao e gate da fase** e nao foi usado como criterio
    (a regra do CLAUDE.md pede `composer test` e `npm run test`); o estilo dos
    arquivos novos segue o dos vizinhos.

### Achados incidentais do gate

- **`AdminPanelTest` tinha um teste instavel** (nao e da F3): o teste de busca
  fixava o termo "ana" e criava o admin com e-mail sorteado pelo Faker - um em
  cada cem e-mails (`nathanael`, `mariana`, `susana`) contem o termo, e a lista
  voltava com duas contas. Corrigido fixando os e-mails do teste.
- **A aritmetica do plano estava errada**: o sitemap nao tem 45 URLs (9 paginas x
  5 idiomas) e sim **50** (10 x 5) - a conta do plano esqueceu a propria pagina
  da tool, que entrou na F2. Conferido por contagem no XML do servidor.

### O que ficou para conferencia manual

O jsdom nao cobre CodeMirror, arrasto de ponteiro nem worker, entao um caso
feliz e um caso de erro de cada uma das seis tools **no navegador** continuam
sendo conferencia de quem revisa - em especial o Regex Tester com um padrao
catastrofico (`(a+)+$`) sobre um texto longo, que e onde o prazo do ADR-031
aparece. O resto da fase esta coberto por teste ou por smoke.
