# Relatorio - Verificacao independente da F2 e fechamento das divergencias

Branch: `develop_natan`
Data: 2026-10-14
Tarefa: "verifique se a fase 2 foi toda executada docs/acceptance/FASE-2.md"
Plano: `docs/plans/develop_natan/20261014_verificacao_fase2_plansfacts.md`
Levantamento: `docs/survey/20261014_verificacao_fase2_surveyfacts.md`
Aceite revisado: `docs/acceptance/FASE-2.md` (secao "Revisao de 2026-10-14")

## 1. O que foi pedido e o veredito

Verificacao **independente** da F2, para conferir o auto-relato do aceite em vez
de reassumi-lo. A fase esta commitada pelo usuario (`edb62b2`), entao o que valia
era o commit - nao havia arvore de trabalho para auditar.

Veredito: **14 dos 19 criterios se confirmaram integralmente** e **cinco nao
descreviam o que o codigo faz** - tres deles coisas que o proprio aceite, o SPEC
e o DESIGN prometiam e o codigo nao fazia. As cinco foram fechadas nesta sessao:
as tres de codigo com testes, as duas de texto nos documentos.

| # | Criterio | Veredito independente |
|---|---|---|
| 1 | Rota `/{locale}/tools/{slug}` e o 404 unico | Confirmado (o bug do parametro escalar preenchido por posicao ja estava corrigido, com teste) |
| 2 | Indexavel, canonical, hreflang, sitemap | Confirmado |
| 3 | Card da home e Cmd+K | Confirmado |
| 4 | Shell com 2 paineis e abas | Confirmado |
| 5 | Barra de acoes com o `kbd` do atalho | **Parcial** - a barra existe completa, o `kbd` e os atalhos nao; corrigido (secao 5) |
| 6 | Arvore colapsavel | Confirmado |
| 7 | Busca na arvore | Confirmado (com a correcao do proprio criterio, ja registrada no aceite) |
| 8 | Caminho JSONPath | Confirmado |
| 9 | Formatar, minificar, ordenar | Confirmado |
| 10 | Erro com linha e coluna **e a ultima saida valida na tela** | **Divergente** - a arvore saia da tela; corrigido (secao 3) |
| 11 | Limite de entrada de `config/tools.php` | Confirmado |
| 12 | Virtualizacao acima de um limiar **de config** | **Divergente** - evidencia inexistente e limiar no modulo; corrigido (secao 4) |
| 13 | Web Worker com queda | Codigo confirmado; o **nome do protocolo no aceite** estava errado (secao 6) |
| 14 | Tema por token | Confirmado |
| 15 | SSR entrega casca e metas | Confirmado (smoke refeito, secao 10) |
| 16 | Imagem OG por tool | Confirmado |
| 17 | i18n nos 5 idiomas | Confirmado |
| 18 | Os cinco gates | Confirmado |
| 19 | Nenhum commit do assistente | Confirmado - mas a celula afirmava que o `git log` continuava no `1f00431`, o que ja era falso (secao 6) |

## 2. Metodo

- **Tres leituras independentes em paralelo** (criterios de cliente; criterios de
  servidor/SEO/SSR/i18n; documentacao, processo e escopo), com instrucao de nao
  rodar suites e de citar `arquivo:linha`.
- **Verificacao na fonte** de cada achado pelo orquestrador, no commit `edb62b2`
  (`git show` dos arquivos citados, `grep` das evidencias que o aceite alegava e
  `git log`).
- **Gates executados de verdade no baseline** (bloco 0), antes de qualquer
  mudanca, para conferir o criterio 18 no estado commitado.
- Achadas as divergencias, **duas rodadas de grill** antes de mexer em qualquer
  linha de codigo (as sete decisoes estao na secao 8).

## 3. Divergencia 1 - criterio 10: a arvore defasada nao existia

O aceite dizia "a ultima saida valida continua na tela" e o SPEC secao 5 promete
o mesmo. O codigo fazia o contrario:

- `Tool.vue:178` (em `edb62b2`): `outcome.value = result` - cada analise,
  inclusive a que falha, sobrescrevia a saida; `hasTree` virava falso e o
  `v-if="hasTree"` **removia a arvore** da tela.
- `json-viewer.test.ts:404` (em `edb62b2`): o teste **afirmava a ausencia** -
  `expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(false)`. O
  defeito estava escrito em forma de teste.

**Correcao**: um `lastValid` guarda o ultimo resultado com sucesso; `nodes` cai
nele quando a analise atual nao tem sucesso e `stale` marca o estado. Vale para o
texto invalido **e** para o texto acima do limite (nos dois a ultima arvore
valida ainda e referencia); o documento vazio continua zerando tudo. Na tela: o
rotulo "arvore da ultima versao valida" (chave nova `tools.ui.states.stale_tree`
nos 5 idiomas) na saida e a arvore em opacidade reduzida, com o erro da versao
atual seguindo no rodape. Busca, colapso e copiar caminho continuam agindo sobre
a arvore que esta na tela.

O teste que afirmava a ausencia virou a prova do comportamento novo
(`mantem a ultima arvore valida na tela, marcada como defasada`, com as 7 linhas
da arvore anterior no DOM depois de quebrar o texto), mais um teste de que a
arvore defasada **sai** quando o documento e limpo.

## 4. Divergencia 2 - criterio 12: evidencia inexistente e limite no codigo

Dois problemas num criterio so:

- A evidencia citada - "`json-viewer.test.ts` (a janela entra acima do limiar)" -
  **nao existia**: zero referencias a virtualizacao naquele arquivo.
- O limiar era constante de modulo (`window.ts:15`: `export const VIRTUALIZE_FROM
  = 2000;`), enquanto o CLAUDE.md manda que limite nao more no codigo - regra que
  a propria F2 tinha acabado de seguir no teto de entrada.

**Correcao**: `TOOLS_VIRTUALIZE_FROM` em `config/tools.php` (padrao 2000, com
entrada no `.env.example`), descendo `ToolController` -> `pages/tools/Show.vue` ->
`Tool.vue` -> `JsonTree.vue` como o teto de entrada. O `lib/window.ts` fica so
com a geometria (`ROW_HEIGHT`, `OVERSCAN`). O `ToolPageTest` passou a afirmar as
**duas** props com valores trocados na configuracao (`1234`/`77`), e o teste que
o aceite alegava existir agora existe de verdade: a janela entra acima do limiar,
provado com **dois limiares na mesma lista** (com um, ela virtualiza; com outro
maior, nao).

## 5. Divergencia 3 - criterio 5 / SPEC secao 5: o `kbd` do atalho

O SPEC e o DESIGN pedem "o `kbd` do atalho ao lado de cada botao". O
`ToolButton.vue` tinha a prop `shortcut` e renderizava o `<kbd>`, com o docblock
citando o DESIGN - e **nenhum chamador passava a prop**. Dos atalhos, so o
`Mod-f` estava ligado.

**Correcao**: `Mod-Enter` formata e `Esc` limpa a busca, com o `kbd` visivel nos
tres lugares que o mockup mostra.

- **Codigo**: `createEditor.ts` ganhou a opcao `onFormat` com o keymap
  `Mod-Enter`; `JsonEditor.vue` emite `format`; `Tool.vue` liga
  `@format="format"`, ouve `Escape` na janela (o foco costuma estar no editor
  quando a pessoa quer limpar a busca) e passa `:shortcut` ao botao Formatar.
- **Um detalhe que decidia o resultado**: as teclas da tela vao **antes** das do
  motor no vetor de keymaps. O `defaultKeymap` do CodeMirror ja usa `Mod-Enter`
  para inserir linha em branco e, na ordem invertida, o "formatar" **nunca
  rodaria**. A ordem foi provada com a inversao temporaria (o teste falha, e com
  a ordem certa passa) e o porque esta comentado no codigo.
- **Na tela**: o botao Formatar mostra `Ctrl/⌘ Enter`, o campo de busca mostra
  `Esc` e a faixa de atalhos lista os tres do mockup - formatar, buscar na arvore
  e limpar a busca - no lugar de desfazer/colar, que sao genericos do CodeMirror
  e nao precisam de anuncio.
- **i18n**: `tools.json-viewer.shortcuts.{format,clear}` nos 5 idiomas, com
  `{undo,paste}` removidos (a paridade e testada, entao saiu dos cinco juntos).
- **Testes**: formatar pelo atalho, limpar a busca no `Esc`, os tres `kbd` da
  faixa na ordem, o `kbd` no botao e no campo - e o novo
  `json-editor-keys.test.ts`, o **unico teste que constroi CodeMirror de
  verdade**, que prova que o `Mod-Enter` da tela ganha do motor (o documento
  continua com uma linha, sem a linha em branco) e que, **sem** `onFormat`, o
  motor segue inserindo a linha em branco (`lines === 2`) - o comportamento
  antigo provado por contraste. O jsdom precisou de `getClientRects`/
  `getBoundingClientRect` no `Range` para o CodeMirror medir sem estourar.

## 6. Divergencias de texto - criterios 13 e 19

Nenhuma mudanca de codigo; o que estava errado era o documento.

- **Criterio 13**: o aceite nomeava o protocolo do worker `{id, task, payload}`,
  que **nunca existiu**. O real e `{id, text, options}` (pedido) e `{id,
  outcome}` (resposta), em `parser.protocol.ts:14-23`. O codigo e os testes
  estavam certos - a celula passou a dizer o nome real.
- **Criterio 19**: a celula afirmava que o `git log` continuava no `1f00431` com
  a fase inteira no working tree. Ja era falso quando a revisao olhou: o usuario
  havia commitado a fase as 13:35 (`edb62b2`) - o texto passou a registrar isso.

## 7. Divergencias menores de documentacao

- O DESIGN descrevia a arvore com **20px por linha e 16px de indentacao**; o
  entregue sao **24px e 14px** (`window.ts`, `ROW_HEIGHT = 24`; `TreeRow.vue:60`,
  `depth * 14 + 4`).
- O DESIGN punha as transformacoes na barra de cima; o entregue as poe no
  cabecalho da **Entrada**, com copiar/baixar na barra - a divisao por onde cada
  acao age.
- A fonte do aceite citava o "entregavel 5" do DESIGN como se fosse a pagina da
  tool; o 5 e a **imagem OG por tool** e o 6 e o mockup da Tool.
- A contagem de assercoes do criterio 18 no aceite (**6.375**) nao batia com a
  medida no commit (**6.380**) - numero de relatorio, sem efeito no resultado.
- O **survey da F2 nao existia**: metade da regra da skill `grill-with-docs`
  tinha ficado para tras na fase. O arquivo foi criado nesta sessao.
- O mockup ASCII da pagina da Tool estava com linhas de 65 e 64 colunas e um
  `v raiz` duplicado depois de uma edicao a mao; foi **regerado por script** com
  `padEnd` e conferido linha a linha (todas com 64).

## 8. Decisoes do usuario e o que foi executado

As sete perguntas das duas rodadas da fronteira foram respondidas "Recomendado".

| # | Decisao | Execucao |
|---|---|---|
| 1 | Arvore defasada: implementar no codigo | `Tool.vue` (`lastValid`/`stale`), `stale_tree` nos 5 idiomas, dois testes novos (secao 3) |
| 2 | Ligar os atalhos e mostrar o `kbd` | `createEditor.ts`, `JsonEditor.vue`, `Tool.vue`, i18n e cinco testes (secao 5) |
| 3 | Limiar de virtualizacao em config | `config/tools.php`, `.env.example`, controller, `Show.vue`, `Tool.vue`, `JsonTree.vue`, `window.ts` (secao 4) |
| 4 | Atualizar o DESIGN para descrever o entregue | `docs/design/DESIGN.md`, secao "Pagina de tool (F2)" |
| 5 | Arvore defasada com aviso **e** sinal visual | Faixa de estado na saida + opacidade reduzida |
| 6 | `Mod-Enter` formata e `Esc` limpa a busca | Keymap na frente do motor, listener na janela |
| 7 | Rodar os gates agora e de novo no fim | Bloco 0 e secao 9 |

**Sem ADR**: nenhuma das tres decisoes de mecanismo passa no teste do `grilling`
(dificil de reverter + surpreendente + trade-off real). A arvore defasada ja esta
decidida no SPEC secao 5, o limite em configuracao e a regra que ja vale no
projeto, e a ordem das teclas e reversivel e esta comentada no proprio codigo.

## 9. Verificacao executada

| Comando | Resultado |
|---|---|
| `composer test` | **verde** - `config:clear`, Pint passed, PHPStan nivel 7 com 0 erro, Pest **266 testes / 6.392 assercoes** (6.380 antes das chaves novas de i18n nos 5 idiomas) |
| `npm run test` | **verde** - **310 testes em 24 arquivos** (eram 299 em 23 antes desta revisao), 26s |
| `npm run types:check` | exit 0 (`wayfinder:generate` + `vue-tsc --noEmit`) |
| `npm run build` | exit 0 (CodeMirror em chunk proprio, `parser.worker-*.js` com o worker) |
| `npm run build:ssr` | exit 0 |

O `types:check` pegou um erro real no caminho: `lastValid` tipado como
`AnalyzeOutcome` deixava `last.nodes` invalido quando o resultado era uma falha -
o tipo passou a ser o resultado com sucesso (`AnalyzeSuccess`).

## 10. Smoke de SSR refeito

Banco SQLite descartavel em diretorio temporario (`migrate --force`), `php artisan
serve` com `INERTIA_SSR_ENABLED=true` e `node bootstrap/ssr/ssr.js` na 13714;
tudo removido no fim.

| URL | HTTP | Conferido no HTML **do servidor** |
|---|---|---|
| `/en/tools/json-viewer`, `/pt-br`, `/pt-pt`, `/fr`, `/es` | 200 | 58-62 KB por pagina, com `<title>JSON Viewer - devtools</title>`, `<h1>JSON Viewer</h1>`, description, canonical, **6 hreflang**, breadcrumb e `og:image` com o `alt` **traduzido por idioma** |
| slug desconhecido e slug de tool `soon` | 404 | o mesmo 404, sem casca de tool |
| `/sitemap.xml` | 200 | 20 URLs, com as 5 da tool |
| editor e arvore | - | **ausentes** do HTML do servidor (por desenho, `ClientOnly`) |

Log do SSR sem erro.

**Achado operacional**: o primeiro smoke devolveu `<div id="app">` vazio, sem
title nem `h1`. A causa nao era o codigo: havia um `node bootstrap/ssr/ssr.js`
**ainda rodando de um build anterior** (PID 26544), apontando para um bundle que
o `build:ssr` seguinte apagou. Morto o processo e subido o SSR novo, o smoke saiu
correto. **Regra pratica**: depois de cada `npm run build:ssr`, reiniciar o
processo de SSR - um SSR velho nao falha, ele serve a pagina vazia.

## 11. Nao verificado daqui: conferencia manual no navegador (usuario)

O que os testes de jsdom nao alcancam fica para a conferencia no navegador:

1. `npm run dev` e abrir `/{locale}/tools/json-viewer`.
2. Colar um JSON valido (**exemplo**), conferir a arvore e o rodape.
3. Quebrar o texto (apagar uma `}`): a arvore **continua na tela**, com o aviso
   de ultima versao valida e opacidade reduzida, e o erro com linha e coluna no
   rodape. Corrigir e a arvore volta ao normal.
4. Com o foco no editor, `Ctrl/Cmd+Enter`: o documento formata (e **nao** entra
   uma linha em branco). O botao Formatar mostra o `kbd` ao lado.
5. Buscar algo na arvore: os resultados contam, e o `Esc` (com o foco em
   qualquer lugar da pagina) **limpa** o campo de busca.
6. Passar do limite de 5 MB (colar um arquivo grande): a analise para, a arvore
   anterior fica como referencia.
7. Arrastar o divisor entre os paineis e conferir as abas em < 768px.

## 12. Nenhum commit

Regra do CLAUDE.md respeitada: **nada foi commitado**. Tudo esta no working tree,
para o usuario commitar via gitpr. A revisao **nao** toca o commit `edb62b2`
(que segue sendo o registro da F2 como ela foi entregue); o que esta na arvore e
um commit novo, de correcao.

## 13. Docs atualizados nesta sessao

- `docs/acceptance/FASE-2.md` - celulas 10, 12, 13 e 19 corrigidas, o entregavel
  certo na fonte e a secao datada "Revisao de 2026-10-14" (o historico do aceite
  nao foi reescrito).
- `docs/design/DESIGN.md` - secao "Pagina de tool (F2)" com o que foi entregue,
  mockup regerado com 64 colunas.
- `docs/SPEC.md` - secao 5: o limiar de virtualizacao vem de `config/tools.php`.
- `docs/glossario.md` - termo "Arvore defasada" e a "Virtualizacao de lista" com
  o limiar de configuracao.
- `CHANGELOG.md` - nota da revisao no bloco da F2 (o bloco ja prometia a arvore
  defasada e o `kbd`; sao essas promessas que a revisao tornou verdadeiras).
- `docs/plans/develop_natan/20261014_verificacao_fase2_plansfacts.md`,
  `docs/survey/20261014_verificacao_fase2_surveyfacts.md` e este relatorio.

## 14. Arquivos no working tree

Codigo (12): `config/tools.php`, `.env.example`,
`app/Http/Controllers/ToolController.php`, `resources/js/pages/tools/Show.vue`,
`resources/js/tools/json-viewer/{Tool.vue,JsonTree.vue,lib/window.ts,
editor/JsonEditor.vue,editor/createEditor.ts}`, `lang/{en,es,fr,pt_BR,pt_PT}/
tools.php`.

Testes (5): `resources/js/__tests__/{json-viewer,json-window,tool-page}.test.ts`,
`resources/js/__tests__/json-editor-keys.test.ts` (**novo**),
`tests/Feature/Tools/ToolPageTest.php`.

Docs (6 + 3 novos): `docs/SPEC.md`, `docs/acceptance/FASE-2.md`,
`docs/design/DESIGN.md`, `docs/glossario.md`, `CHANGELOG.md`, mais o survey, o
plano da sessao e este relatorio.
