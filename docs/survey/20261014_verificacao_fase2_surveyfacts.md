# Levantamento - verificacao independente da FASE 2

Data: 2026-10-14
Branch: `develop_natan`
Pedido original: "verifique se a fase 2 foi toda executada docs/acceptance/FASE-2.md"
Plano: `docs/plans/develop_natan/20261014_verificacao_fase2_plansfacts.md`
Relatorio: `docs/claude-code/reports/develop_natan/20261014_develop_natan_verificacao_fase2.md`
Aceite revisado: `docs/acceptance/FASE-2.md` (secao "Revisao de 2026-10-14")

## 1. Estado do repositorio no inicio (levantado, nao assumido)

- HEAD = `edb62b2` ("feat: adiciona pagina de tool e o JSON Viewer funcional"),
  o commit da F2, feito pelo **usuario** via gitpr as 13:35. Os cinco commits do
  repositorio sao todos do usuario; o assistente nunca commitou.
- A **F2 inteira esta commitada** - ao contrario da verificacao da F1, nao havia
  arvore de trabalho para auditar: o que vale e o que esta no commit.
- `docs/acceptance/FASE-2.md` tinha os 19 criterios com a tabela "Resultado"
  preenchida, 18 deles "Atende" e o criterio 7 com correcao do proprio criterio.
  O pedido e **desconfiar** desse auto-relato.
- `docs/survey/` **nao tem o levantamento da F2**: tem
  `20261013_auth_login`, `20261013_executar_spec`, `20261014_auditoria_fase1` e
  `20261014_papeis_paineis`. O plano da F2
  (`docs/plans/develop_natan/20261014_fase2_json_viewer_plansfacts.md`) e o
  relatorio da F2 existem e estao completos - metade da regra da skill
  `grill-with-docs` foi cumprida na fase.

## 2. Metodo

- **Tres levantamentos de leitura em paralelo**, com instrucao explicita de nao
  rodar suites e de citar `arquivo:linha`: (a) criterios de cliente - shell,
  editor, arvore, busca, virtualizacao, worker, i18n; (b) criterios de servidor -
  rota, 404, SEO, sitemap, OG por tool, SSR; (c) documentacao, processo e escopo
  - o que o aceite, o SPEC, o DESIGN, os ADRs, o glossario e o git registram.
- **Verificacao na fonte** de cada achado, pelo orquestrador, no commit
  `edb62b2`: `git show` dos arquivos citados, `grep` das evidencias que o aceite
  alegava e `git log` para o criterio dos commits.
- **Gates executados de verdade** no estado commitado (bloco 0 do plano), antes
  de qualquer mudanca: os numeros do relatorio da F2 nunca tinham sido
  reproduzidos fora dela.
- As divergencias achadas foram levadas a duas rodadas de grill (secao 5) antes
  de qualquer linha de codigo mudar.

## 3. Fatos por criterio (veredito independente)

| # | Fatos |
|---|---|
| 1 | Confirmado. `ToolPageTest` entrega `tools/Show` nos 5 idiomas e da 404 para slug desconhecido, `soon` e `hidden`; o bug de causa obscura do parametro escalar preenchido **por posicao** (`__invoke(string $slug)` recebia o idioma) ja estava corrigido com teste proprio |
| 2 | Confirmado. Sem `noindex`, canonical, hreflang e as 5 URLs no sitemap (`SitemapTest` deriva a lista, nao fixa numero) |
| 3 | Confirmado. `home.test.ts` (1 link e 7 selos) e `tools-registry.test.ts` (`toolPath` so para `available`, loader com componente para cada uma) |
| 4 | Confirmado. `tool-shell.test.ts` cobre arrasto, setas, duplo clique, `localStorage` por tool e as abas abaixo de 768px; `role="separator"` + `aria-valuenow` no `ToolShell.vue` |
| 5 | **Divergente.** A barra de acoes existe inteira (copiar, baixar, limpar, exemplo, abrir, arrastar-e-soltar) e nada sai do navegador - mas o **`kbd` do atalho ao lado do botao**, que o SPEC secao 5 e o DESIGN pedem, nao existia: `ToolButton.vue:16,38-41` tem a prop `shortcut` e renderiza o `<kbd>` com o docblock citando o DESIGN, e **nenhum chamador passava a prop**; dos atalhos so o `Mod-f` estava ligado |
| 6 | Confirmado. `json-tree.test.ts` (achatamento, profundidade, colapso por caminho) e o workspace (contagem de chaves, itens, tamanho, expandir/recolher tudo) |
| 7 | Confirmado com a correcao do proprio criterio (a busca e por chave e valor; a busca por JSONPath nao entrou), registrada no aceite |
| 8 | Confirmado. `json-path.test.ts`: `.chave` para identificador, `['chave']` para o resto, com escape de `'` e `\` |
| 9 | Confirmado. `json-transform.test.ts` (recursivo, arrays intactos, comparacao por code unit) e a acao entrando no historico do editor |
| 10 | **Divergente.** O aceite e o SPEC secao 5 prometem que "a ultima saida valida continua na tela"; o codigo fazia o oposto - `outcome.value = result` (`Tool.vue:178` em `edb62b2`) sobrescrevia com a falha, `hasTree` virava falso e o `v-if` **removia a arvore**. Pior: o teste afirmava a ausencia (`json-viewer.test.ts:404` em `edb62b2`: `expect(...exists()).toBe(false)`) |
| 11 | Confirmado. O teto de entrada vem de `config/tools.php` por `ToolPageTest` (`config(['tools.limits.input_bytes' => 1234])`), bloqueia a analise e nao a digitacao, com o tamanho formatado no idioma |
| 12 | **Divergente em dois pontos.** A evidencia citada ("`json-viewer.test.ts` - a janela entra acima do limiar") **nao existia**: zero referencias a virtualizacao no arquivo. E o limiar era constante de modulo (`window.ts:15` em `edb62b2`: `export const VIRTUALIZE_FROM = 2000;`), nao configuracao - contra a regra "limite nao mora no codigo" do CLAUDE.md, que a F2 tinha acabado de seguir no teto de entrada |
| 13 | **Divergente no documento.** O aceite nomeava o protocolo do worker `{id, task, payload}`; o real e `{id, text, options}` (pedido) e `{id, outcome}` (resposta), em `parser.protocol.ts:14-23`. O codigo e os testes estavam certos; o texto do criterio e que nunca descreveu o que existe |
| 14 | Confirmado. `json-editor-theme.test.ts` (so `var(--json-*)`, nenhuma cor literal) e `design-tokens.test.ts` (contraste de `--json-punct` contra `--surface` nos dois temas) |
| 15 | Confirmado, com o smoke reverificado nesta sessao (secao 7) |
| 16 | Confirmado. `SeoMetaTest` confere `/og/json-viewer.png`, as dimensoes no PNG de verdade (`getimagesize`), o `alt` nos 5 idiomas e a queda para a imagem padrao |
| 17 | Confirmado. `TranslationsParityTest` verde com `tools.ui.*` e `tools.json-viewer.*` nos 5 idiomas |
| 18 | Confirmado. Os cinco comandos verdes no estado commitado, com os numeros do relatorio conferidos |
| 19 | **Divergente no documento.** A celula afirmava que o `git log` continuava no `1f00431` com a fase inteira no working tree - ja era falso quando a revisao olhou: o usuario tinha commitado a fase (`edb62b2`, 13:35) |

**Resultado: 14 dos 19 criterios se confirmaram integralmente e 5 nao
descreviam o que o codigo faz.**

## 4. As cinco divergencias e o que cada uma exigia

| # | Criterio | Natureza | Decisao do usuario |
|---|---|---|---|
| 1 | 10 - arvore defasada | **Defeito de codigo** contra o SPEC secao 5, com teste afirmando o comportamento errado | Implementar no codigo |
| 2 | 12 - virtualizacao | **Evidencia falsa** no aceite + limite fixado no modulo | Mover o limiar para `config/tools.php` |
| 3 | 13 - protocolo do worker | Nome errado no texto do criterio (o codigo esta certo) | Corrigir o documento |
| 4 | 19 - commits | Afirmacao ja falsa quando a revisao olhou (commit do usuario) | Corrigir o documento |
| 5 | 5 + SPEC secao 5 / DESIGN | **Requisito nao cumprido**: o `kbd` do atalho, com a prop pronta e ninguem passando | Ligar os atalhos e mostrar o `kbd` |

Divergencias menores, so de documentacao: o DESIGN descrevia a arvore com
**20px por linha e 16px de indentacao** (o entregue sao 24px e 14px -
`window.ts` `ROW_HEIGHT = 24` e `TreeRow.vue:60`, `${depth * 14 + 4}px`) e punha
as transformacoes na barra de cima (o entregue as poe no cabecalho da Entrada,
com copiar/baixar na barra); a fonte do aceite citava o "entregavel 5" do DESIGN
como se fosse a pagina da tool (**o 5 e a imagem OG por tool**, o 6 e o mockup);
e a contagem de assercoes do criterio 18 no aceite (**6.375**) nao batia com a
medida no commit (**6.380**) - numeros de relatorio, sem efeito no resultado.

## 5. Decisoes confirmadas pelo usuario (duas rodadas de grill)

| # | Rodada | Decisao |
|---|---|---|
| 1 | 1 | Arvore defasada (crit. 10): **implementar no codigo** o comportamento do SPEC, em vez de afrouxar o documento |
| 2 | 1 | Atalhos (crit. 5): **ligar os atalhos e mostrar o `kbd`** |
| 3 | 1 | Limiar de virtualizacao (crit. 12): **mover para `config/tools.php`**, seguindo o caminho do teto de entrada |
| 4 | 1 | DESIGN x codigo: **atualizar o DESIGN** para descrever o que foi entregue, nao o contrario |
| 5 | 2 | Arvore defasada: **aviso na faixa de estado + sinal visual** (nao so um dos dois) |
| 6 | 2 | Quais atalhos: **`Mod-Enter`** (formata) e **`Esc`** (limpa a busca) |
| 7 | 2 | Gates: **rodar agora como baseline e de novo no fim** |

Decisoes de mecanismo tomadas junto (registradas no relatorio):

- O `Mod-Enter` da tela vai **antes** das teclas do motor no keymap: o
  `defaultKeymap` do CodeMirror ja usa `Mod-Enter` para inserir linha em branco e
  a primeira ligacao registrada vence.
- O `Esc` e ouvido na **janela**, nao no campo: a busca e um `Input` e o foco
  costuma estar no editor quando a pessoa quer limpar a busca.
- A faixa de atalhos passa a listar os **tres do mockup** (formatar, buscar,
  limpar a busca) no lugar de desfazer/colar, que sao genericos do CodeMirror.
- A arvore defasada vale tambem para o texto **acima do limite**; o documento
  vazio continua zerando tudo.
- **Nenhum ADR**: a arvore defasada ja esta decidida no SPEC secao 5, o limite em
  config e a regra que ja vale no projeto, e a ordem das teclas e reversivel e
  esta comentada no proprio codigo - nenhuma das tres e "dificil de reverter +
  surpreendente + fruto de um trade-off real", o teste do `grilling`.

## 6. Fatos de processo e documentacao

- Tres decisoes da revisao entraram na documentacao com data, sem reescrever o
  historico do aceite: a secao "Revisao de 2026-10-14" do `FASE-2.md` registra
  as cinco divergencias e o que foi feito com cada uma.
- O `docs/glossario.md` ganhou o termo **"Arvore defasada"** e a entrada de
  **"Virtualizacao de lista"** passou a citar o limiar de configuracao: os dois
  conceitos existiam no DESIGN e no SPEC e nao tinham nome no glossario.
- O `docs/design/DESIGN.md` foi reescrito na parte da pagina da tool (24px por
  linha, 14px de indentacao, as acoes divididas por painel, tres itens na faixa
  de atalhos, a arvore defasada) e o mockup ASCII foi **regerado por script**
  para todas as linhas terem 64 colunas - a edicao a mao tinha desalinhado.
- O `CHANGELOG.md` ganhou, no bloco da F2, a nota da revisao: o bloco ja
  prometia a arvore defasada e o `kbd` no `ToolButton` desde `edb62b2`, e sao
  essas promessas que a revisao tornou verdadeiras.

## 7. Estado final (execucao desta sessao)

- **Crit. 10**: `lastValid` guarda o ultimo resultado com sucesso; a arvore dele
  fica na tela com o rotulo "arvore da ultima versao valida" (`stale_tree`, nos 5
  idiomas) e opacidade reduzida, com o erro da versao atual no rodape. O teste
  que afirmava a ausencia virou a prova do comportamento.
- **Crit. 12**: `TOOLS_VIRTUALIZE_FROM` em `config/tools.php` (padrao 2000, com
  entrada no `.env.example`), descendo `ToolController` -> `tools/Show` ->
  `Tool.vue` -> `JsonTree.vue`; a constante saiu de `window.ts`, que fica so com
  a geometria (`ROW_HEIGHT`, `OVERSCAN`).
- **Crit. 5 / SPEC secao 5**: `Mod-Enter` formata (novo `onFormat` no editor,
  registrado antes das teclas do motor), `Esc` limpa a busca, o botao Formatar
  mostra `Ctrl/⌘ Enter`, o campo de busca mostra `Esc` e a faixa lista os tres.
- **Crit. 13 e 19**: corrigidos no texto das celulas, com a nota de revisao.
- Gates depois da revisao: `composer test` com Pint limpo, PHPStan nivel 7 sem
  erro e **266 testes / 6.392 assercoes**; `npm run test` com **310 testes em 24
  arquivos** (era 299 em 23); `types:check`, `build` e `build:ssr` verdes; smoke
  com `INERTIA_SSR_ENABLED=true` nos 5 idiomas (58-62 KB por pagina, title,
  description, canonical, 6 hreflang, `og:image` com `alt` no HTML do servidor),
  404 para slug desconhecido e para tool `soon` e sitemap com 20 URLs.
- **Nenhum commit**: a tarefa termina com as mudancas no working tree, para o
  usuario commitar pelo gitpr.
