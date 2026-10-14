# Verificacao da FASE 2 e fechamento das divergencias

Sessao de grill (skill `grill-with-docs`), pedido: "verifique se a fase 2 foi
toda executada docs/acceptance/FASE-2.md". Branch `develop_natan`. O plano foi
aprovado pelo usuario (duas rodadas de perguntas, todas "Recomendado") antes da
execucao.

## Context

A F2 (JSON Viewer + pagina generica da tool + imagem OG por tool) esta aceita e
commitada pelo usuario em `edb62b2`. O pedido e uma **verificacao independente**
do auto-relato do aceite - e ela achou **cinco divergencias reais** entre o que
`docs/acceptance/FASE-2.md` (e o SPEC secao 5, e o DESIGN) afirmam e o que o
codigo faz. Dos 19 criterios, **14 se confirmaram integralmente**.

| Onde | Documento afirma | Realidade verificada |
|---|---|---|
| Crit. 10 | "a ultima saida valida continua na tela" | `outcome.value = result` sobrescreve sempre (`Tool.vue:178` em `edb62b2`); a arvore **sai da tela** e o teste afirma a ausencia (`json-viewer.test.ts:404`) |
| Crit. 12 | evidencia: "`json-viewer.test.ts` (a janela entra acima do limiar)" | **Evidencia falsa** (zero referencias a virtualizacao no arquivo) e o limiar era constante de modulo (`window.ts:15`), nao config |
| Crit. 13 | protocolo `{id, task, payload}` | **Nome errado**: o real e `{id, text, options}` / `{id, outcome}` (`parser.protocol.ts:14-23`) |
| Crit. 5 / SPEC §5 | barra de acoes "com o `kbd` do atalho ao lado" | `ToolButton.vue` tem a prop `shortcut` e o `<kbd>` com docblock citando o DESIGN - e **nenhum botao passa `shortcut`** |
| Crit. 19 | "`git log` continua no `1f00431`" | Ja falso: `edb62b2` e o commit do usuario, posterior ao aceite |

Menores: o DESIGN descreve 20px por linha e 16px de indentacao (o codigo usa
24px e 14px) e poe copiar/baixar/transformacoes todos na barra de cima (o codigo
poe as transformacoes no cabecalho da Entrada); o **survey da F2 nao existe** em
`docs/survey/`; o aceite chama a pagina da tool de "entregavel 5", que no DESIGN
e a imagem OG por tool.

**Resultado esperado**: as cinco divergencias fechadas - as tres de codigo com
testes, as duas de texto nos documentos -, a documentacao descrevendo o que foi
entregue, os cinco gates verdes e o smoke de SSR refeito, sem commit (regra do
CLAUDE.md). Levantamento completo em
`docs/survey/20261014_verificacao_fase2_surveyfacts.md`.

## Decisoes confirmadas pelo usuario (todas "Recomendado")

| # | Decisao |
|---|---|
| 1 | Crit. 10: **implementar no codigo** o comportamento do SPEC secao 5 (a arvore da ultima versao valida continua na tela) |
| 2 | Crit. 5: **ligar os atalhos e mostrar o `kbd`** |
| 3 | Crit. 12: **mover o limiar para `config/tools.php`** (`TOOLS_VIRTUALIZE_FROM`, padrao 2000) |
| 4 | DESIGN x codigo: **atualizar o DESIGN** para descrever o entregue, nao o contrario |
| 5 | Arvore defasada: **aviso na faixa de estado + sinal visual** (opacidade reduzida) |
| 6 | Quais atalhos: **`Mod-Enter`** formata e **`Esc`** limpa a busca |
| 7 | Gates: **rodar agora como baseline e de novo no fim** |

## Blocos de execucao

| # | Bloco | Estado |
|---|---|---|
| 0 | Baseline dos cinco gates no commit `edb62b2` | concluido |
| A | Limiar de virtualizacao em `config/tools.php` (crit. 12) | concluido |
| B | A ultima arvore valida continua na tela, marcada como defasada (crit. 10) | concluido |
| C | Atalhos (`Mod-Enter` formata, `Esc` limpa a busca) e o `kbd` na tela (crit. 5) | concluido |
| D1 | DESIGN, SPEC e FASE-2; glossario e CHANGELOG | concluido |
| D2 | Survey, plano da sessao e relatorio | concluido |
| E | Gates finais e smoke de SSR | concluido |

## Bloco A - limiar de virtualizacao em config

O caminho ja existia inteiro para o teto de entrada; o limiar segue o mesmo:
config -> controller -> prop -> componente. `config/tools.php`
(`limits.virtualize_from`), `.env.example` (`TOOLS_VIRTUALIZE_FROM`),
`ToolController` (prop `virtualizeFrom`), `pages/tools/Show.vue`,
`tools/json-viewer/Tool.vue`, `JsonTree.vue` (compara com a prop),
`lib/window.ts` (sai a constante; ficam `ROW_HEIGHT` e `OVERSCAN`, que sao
geometria). Testes: `ToolPageTest` afirma as duas props com valores trocados na
configuracao; `json-window.test.ts` perde a assercao da constante;
`json-viewer.test.ts` ganha **o teste que o aceite alegava existir** (a janela
entra acima do limiar, com dois limiares na mesma lista).

## Bloco B - a ultima arvore valida continua na tela

`lastValid` guarda o ultimo resultado com sucesso; `nodes` cai nele quando a
analise atual nao tem sucesso, e `stale` marca o estado - vale para o texto
invalido **e** para o texto acima do limite; o documento vazio continua zerando
tudo. Sinal: rotulo novo (`tools.ui.states.stale_tree`, nos 5 idiomas) na saida e
a arvore em opacidade reduzida, com o erro da versao atual no rodape. O teste que
afirmava `exists()).toBe(false)` virou a prova do comportamento novo.

## Bloco C - atalhos visiveis e o `kbd`

`createEditor.ts` ganha `onFormat` no `Mod-Enter`; a ordem importa - as teclas da
tela vao **antes** das do motor, porque o `defaultKeymap` ja usa `Mod-Enter` para
inserir linha em branco e a primeira ligacao registrada vence. `Tool.vue`:
`:shortcut` no botao Formatar, `kbd` do `Esc` no campo de busca, listener de
`Escape` na janela (o foco costuma estar no editor) e a faixa de atalhos com os
tres do mockup. i18n: `tools.json-viewer.shortcuts.{format,clear}` nos 5 idiomas,
saem `{undo,paste}`. Testes: formatar pelo atalho, limpar a busca no `Esc`, os
tres `kbd` da faixa, o `kbd` no botao e no campo - e `json-editor-keys.test.ts`,
o unico teste que **constroi CodeMirror de verdade**, provando que o
`Mod-Enter` da tela ganha do motor (e que, sem `onFormat`, o motor segue
inserindo a linha em branco).

## Bloco D - documentacao e artefatos da sessao

- `docs/design/DESIGN.md`, secao "Pagina de tool (F2)": 24px por linha e 14px de
  indentacao, as acoes divididas por painel, a faixa de atalhos com os tres itens
  e a arvore defasada; o mockup ASCII foi regerado por script (64 colunas).
- `docs/SPEC.md` secao 5: o limiar de virtualizacao passa a ser descrito como
  vindo de `config/tools.php`.
- `docs/acceptance/FASE-2.md`: celulas 10, 12, 13 e 19 corrigidas, a fonte com o
  entregavel certo e a secao datada "Revisao de 2026-10-14", sem reescrever o
  historico do aceite.
- `docs/glossario.md`: termo "Arvore defasada" e o limiar de configuracao na
  "Virtualizacao de lista". `CHANGELOG.md`: nota da revisao no bloco da F2.
- `docs/plans/develop_natan/20261014_verificacao_fase2_plansfacts.md`,
  `docs/survey/20261014_verificacao_fase2_surveyfacts.md` e o relatorio em
  `docs/claude-code/reports/develop_natan/20261014_develop_natan_verificacao_fase2.md`.

## Arquivos criticos

- `resources/js/tools/json-viewer/Tool.vue` - arvore defasada, atalhos, `kbd`.
- `resources/js/tools/json-viewer/editor/{createEditor.ts,JsonEditor.vue}` -
  `onFormat` com a ordem das teclas.
- `resources/js/tools/json-viewer/{JsonTree.vue,lib/window.ts}` - limiar por
  prop, geometria no modulo.
- `config/tools.php`, `.env.example`, `app/Http/Controllers/ToolController.php`,
  `resources/js/pages/tools/Show.vue` - o caminho da configuracao.
- `lang/*/tools.php` (5 idiomas) - `shortcuts.{format,clear}` e `stale_tree`.
- Testes: `json-viewer.test.ts`, `json-window.test.ts`, `tool-page.test.ts`,
  `ToolPageTest.php` e o novo `json-editor-keys.test.ts`.
- Docs: `docs/design/DESIGN.md`, `docs/SPEC.md`, `docs/acceptance/FASE-2.md`,
  `docs/glossario.md`, `CHANGELOG.md`.

## Riscos / pontos de atencao

- **O `Mod-Enter` tinha dono**: sem a ordem certa no keymap, o "formatar" nunca
  rodaria - provado com a inversao temporaria da ordem (o teste falha) e
  revertido; o comentario no codigo explica o porque.
- **A arvore defasada muda um teste que existia**: a assercao de ausencia era o
  defeito escrito em forma de teste; ela foi reescrita, nao removida.
- **O SSR continua sem a arvore**: o editor e a arvore montam no cliente
  (`ClientOnly`); o smoke confere as metas e a casca, nao o workspace.
- **`npm run build:ssr` invalida um SSR em execucao**: um `node
  bootstrap/ssr/ssr.js` antigo aponta para um bundle apagado e devolve a pagina
  vazia sem erro visivel; o processo precisa ser reiniciado depois de cada build.
- **Nenhum commit**: o usuario commita via gitpr. A tarefa termina no working
  tree.
