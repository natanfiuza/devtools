# Criterios de aceite — FASE 2 (JSON Viewer, rota da tool e imagem OG por tool)

Gerado em 2026-10-14, no inicio da fase (regra do `docs/SPEC.md` secao 9).
Fonte: `docs/SPEC.md` secao 9 (F2) + secao 4 (rota `/{locale}/tools/{slug}`) +
secao 5 (JSON Viewer) + `docs/design/DESIGN.md` (secao "Pagina de tool" e os
entregaveis 5 e 6) + decisoes do plano de execucao da sessao.

## Escopo da fase

A primeira tool `available`: o **JSON Viewer** (arvore colorida colapsavel,
busca, copiar caminho em JSONPath, minify/pretty, validacao com linha e coluna
do erro, ordenar chaves, limite de 5 MB, virtualizacao das listas grandes e
parse em Web Worker, com CodeMirror 6). Junto com ela nascem as duas coisas que
a F1 deixou explicitamente para ca: a **rota generica `/{locale}/tools/{slug}`**
com a pagina da tool (2 paineis redimensionaveis, barra de acoes, atalhos
visiveis, mobile empilhado em abas) e a **imagem OG por tool**.

A pagina da tool nasce **generica**: ela e o molde onde as tools 3 a 8 da F3 se
encaixam sem tocar no shell.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | `/{locale}/tools/{slug}` responde o componente `tools/Show` nos 5 idiomas para tool `available`, e **404** para slug desconhecido, para tool `soon` e para tool `hidden` | `ToolPageTest` |
| 2 | A pagina da tool e indexavel (sem `noindex`), com canonical, hreflang e as 5 URLs no sitemap | `ToolPageTest` + `SitemapTest` |
| 3 | O card da home linka so a tool `available`; as outras 7 continuam com selo e sem link, e o Cmd+K navega para a mesma | `home.test.ts` + `tools-registry.test.ts` |
| 4 | A pagina e um shell: 2 paineis redimensionaveis no desktop (arrasto, setas, `role="separator"`) e abas no mobile, com a divisao preservada | `tool-shell.test.ts` |
| 5 | Barra de acoes com copiar, baixar, limpar, exemplo, abrir arquivo e arrastar-e-soltar; nada sai do navegador | `json-viewer.test.ts` |
| 6 | Arvore colorida colapsavel, com colapso por no, expandir/recolher tudo e contagem | `json-tree.test.ts` + `json-viewer.test.ts` |
| 7 | Busca na arvore por **chave e valor** (a busca por caminho JSONPath ficou de fora), com auto-expansao dos ancestrais, contador de resultados e sem acento/caixa | `json-tree.test.ts` + `json-viewer.test.ts` |
| 8 | Copiar caminho (JSONPath) de qualquer no, com a forma `.chave` quando o nome e identificador e `['chave']` quando nao e | `json-path.test.ts` |
| 9 | Formatar, minificar e ordenar chaves (arrays preservam a ordem; ordenacao determinista) agindo no texto | `json-transform.test.ts` + `json-viewer.test.ts` |
| 10 | JSON invalido mostra o erro **com linha e coluna**, e a ultima saida valida continua na tela | `json-parse.test.ts` + `json-viewer.test.ts` |
| 11 | O limite de entrada vem de `config/tools.php` (env, padrao 5 MB), bloqueia a analise — nunca a digitacao — e aparece com o tamanho formatado no idioma | `ToolPageTest` + `json-viewer.test.ts` |
| 12 | As listas grandes sao virtualizadas acima de um limiar de config, com a conta de janela numa funcao pura | `json-window.test.ts` |
| 13 | O parse roda em Web Worker, com queda para a thread principal quando nao ha `Worker` (o caminho que o jsdom exercita) | `json-worker.test.ts` |
| 14 | O tema do editor sai **so** dos tokens `--json-*` (nenhuma cor literal), e o `--json-punct` ganha token proprio nos dois temas (contraste medido contra `--surface`) | `json-editor-theme.test.ts` + `design-tokens.test.ts` |
| 15 | O SSR entrega a casca e as metas da pagina da tool (title, description, canonical, `og:image` da tool) no HTML do servidor | smoke com SSR |
| 16 | A imagem OG da tool existe em `public/og/<slug>.png` para toda tool `available`, com as dimensoes configuradas, caindo para a padrao quando o arquivo nao existe | `SeoMetaTest` |
| 17 | Zero string hardcoded nas telas novas, com paridade nos 5 idiomas e sem plural com `\|` (o teste de paridade rejeita) | `TranslationsParityTest` + testes de componente |
| 18 | Gates: `composer test`, `npm run test`, `npm run types:check`, `npm run build`, `npm run build:ssr` | os cinco comandos |
| 19 | Nenhum commit feito pelo assistente | `git log` |

## Fora do escopo da F2

- As tools 3 a 8 do MVP (F3) — o shell nasce pronto para elas, mas nenhuma
  outra vira `available` aqui.
- Schema/inferencia, diff de JSON, jq, conversao para outros formatos e
  historico de documentos: nao estao na linha do SPEC para o JSON Viewer.
- Pagina de indice `/tools`: nao existe no SPEC; o caminho de volta e o header.
- AI SQL Builder, cache, fila e cota (F5); gateway e doacao (F6); SEO final e
  hardening (F7).
- Imagem OG por idioma: a imagem e uma por tool e **nao tem frase em idioma
  nenhum** - o nome da ferramenta (nome de produto, igual nos 5 idiomas) e os
  termos tecnicos do manifesto; quem e traduzido e o `og:image:alt`. Registrado
  no ADR-030.

## Resultado (a preencher no fim da fase)

Preenchido em 2026-10-14, ao fim da fase.

| # | Criterio | Resultado |
|---|---|---|
| 1 | `/{locale}/tools/{slug}` | **Atende.** `ToolPageTest` (4 testes): componente `tools/Show` nos 5 idiomas com `slug` e `maxInputBytes`; 404 para slug desconhecido, para tool `soon` (fixture) e para tool `hidden` (fixture), com uma tool `available` da mesma fixture como controle. Achei e corrigi no caminho um bug de causa obscura: `__invoke(string $slug)` recebia o **idioma**, porque o Laravel preenche parametro escalar **por posicao** e a rota e `{locale}/tools/{slug}` - o slug passou a ser lido pelo nome (`$request->route('slug')`), com teste proprio (`nao monta caminho a partir do slug da URL`). |
| 2 | Indexavel, canonical, hreflang, sitemap | **Atende.** `ToolPageTest` (`seo.noindex` falso) + `SitemapTest` (a tool nos 5 idiomas, com alternates e `x-default`) + smoke: o sitemap sai com 20 URLs e as 5 da tool. |
| 3 | Card da home e Cmd+K | **Atende.** `home.test.ts`: 1 link (`/pt-br/tools/json-viewer`) e 7 selos; `tools-registry.test.ts`: `toolPath` so devolve caminho para a tool que funciona e `toolComponentLoader` tem componente para cada `availableTools`. |
| 4 | Shell: 2 paineis e abas | **Atende.** `tool-shell.test.ts` (arrasto, setas, duplo clique restaura, divisao por tool no `localStorage`, abas abaixo de 768px) e o divisor com `role="separator"` + `aria-valuenow` no `ToolShell.vue`. O arrasto real do ponteiro nao roda em jsdom (coberto pelo smoke manual). |
| 5 | Barra de acoes | **Atende.** `json-viewer.test.ts`: copiar, baixar, limpar, exemplo, abrir arquivo e o `drop` de arquivo, com as acoes de cada painel na casca. Nada sai do navegador: a tool nao faz requisicao nenhuma (o arquivo e lido com `FileReader`). O arrasto real nao existe em jsdom. |
| 6 | Arvore colapsavel | **Atende.** `json-tree.test.ts` (achatamento, profundidade, colapso por caminho, linhas visiveis) + `json-viewer.test.ts` (contagem de chaves, itens e tamanho, expandir/recolher tudo). |
| 7 | Busca na arvore | **Atende com correcao do criterio** (feita acima): a busca e por **chave e valor**, sem acento e sem caixa, expandindo os ancestrais do que casa, com contador e aviso de corte; a busca por caminho JSONPath nao entrou. |
| 8 | Caminho JSONPath | **Atende.** `json-path.test.ts`: `.chave` para identificador, `['chave']` para o resto, com escape de `'` e `\`, e o caminho de array indexado. |
| 9 | Formatar, minificar, ordenar | **Atende.** `json-transform.test.ts` (recursivo, arrays intactos, comparacao por code unit - sem `localeCompare`) + `json-viewer.test.ts` (a acao age no texto e entra no historico do editor, entao o Ctrl+Z desfaz). |
| 10 | Erro com linha e coluna | **Atende.** `json-parse.test.ts` (posicao -> linha/coluna, erro sem posicao, detalhe do motor) + `json-viewer.test.ts`: `mostra o erro com linha e coluna` (o erro na faixa de estado e a linha marcada no editor) e **`mantem a ultima arvore valida na tela, marcada como defasada`**, que e o comportamento que o SPEC secao 5 exige. Esta celula afirmava o mesmo no aceite original, mas o codigo nao fazia isso: a analise invalida zerava a saida e a arvore saia da tela - ver a nota de revisao no fim. |
| 11 | Limite de `config/tools.php` | **Atende.** `ToolPageTest` (a prop vem da configuracao: `config(['tools.limits.input_bytes' => 1234])`) e `json-viewer.test.ts` (passar do limite bloqueia a analise e nao a digitacao, com o tamanho formatado no idioma da pagina). |
| 12 | Virtualizacao | **Atende.** `json-window.test.ts` (topo, meio, fundo, folga, lista menor que a janela) e `json-viewer.test.ts` (`renderiza so a janela quando a lista passa do limiar da configuracao`, com dois limiares na mesma lista). O limiar vem de `config/tools.php` (`TOOLS_VIRTUALIZE_FROM`) por `ToolPageTest`; no aceite original ele era a constante `VIRTUALIZE_FROM` no modulo e o teste que esta celula citava nao existia - ver a nota de revisao no fim. |
| 13 | Web Worker com queda | **Atende.** `json-worker.test.ts` exercita o **caminho de queda** (o jsdom nao tem `Worker`), o descarte de resposta velha e o protocolo `{id, text, options}` (pedido) / `{id, outcome}` (resposta); o worker de verdade so roda no navegador - conferido no `npm run build` (chunk `parser.worker-*.js`) e no smoke manual. O aceite original nomeava o protocolo `{id, task, payload}`, que nunca existiu - ver a nota de revisao no fim. |
| 14 | Tema por token | **Atende.** `json-editor-theme.test.ts` (so `var(--json-*)`, nenhuma cor literal, os 5 tokens) + `design-tokens.test.ts` (contraste de `--json-punct` contra `--surface` nos dois temas). |
| 15 | SSR entrega casca e metas | **Atende.** Smoke com `INERTIA_SSR_ENABLED=true` nos 5 idiomas: title, description, `og:*` (imagem da tool e `alt` traduzido), canonical, 6 hreflang, `<h1>` e breadcrumb no HTML do servidor; o editor nao esta la (por desenho - `ClientOnly`). |
| 16 | Imagem OG por tool | **Atende.** `SeoMetaTest`: `og:image` = `/og/json-viewer.png` na pagina da tool, dimensoes conferidas no PNG de verdade (`getimagesize`), `alt` traduzido nos 5 idiomas e queda para a imagem padrao quando a tool nao tem PNG (fixture). A imagem padrao da F1 saiu **byte a byte igual** da refatoracao. |
| 17 | i18n | **Atende.** `TranslationsParityTest` verde com as chaves novas (`tools.ui.*` e `tools.json-viewer.*`) nos 5 idiomas; os testes de componente afirmam texto traduzido, nao literal. |
| 18 | Gates | **Atende.** `composer test` (pint + phpstan nivel 7 sem erro + **266 testes, 6375 assercoes**), `npm run test` (**299 testes em 23 arquivos**), `npm run types:check`, `npm run build` (CodeMirror em chunk proprio) e `npm run build:ssr` - os cinco verdes. |
| 19 | Nenhum commit | **Atende.** No momento do aceite o `git log` parava no `1f00431` (commit da F1) e a fase inteira estava no working tree. Depois do aceite o usuario commitou a fase (`edb62b2`, `feat: adiciona pagina de tool e o JSON Viewer funcional`, 13:35) - o trabalho continua sendo commitado so por ele, pelo gitpr; o assistente nao commita. |

## Revisao de 2026-10-14 (verificacao independente)

A pedido, os 19 criterios foram conferidos um a um contra o repositorio (a
leitura do codigo, dos testes e do `git log` no commit `edb62b2`). **14 dos 19
se confirmaram integralmente** e cinco nao descreviam o que o codigo fazia:

1. **Criterio 10 (arvore defasada).** O codigo zerava a saida a cada analise
   invalida, e a arvore saia da tela - o oposto do que o SPEC secao 5 promete e
   do que esta celula afirmava; um teste chegava a afirmar a ausencia
   (`exists()).toBe(false)`). **Corrigido no codigo**: o ultimo `AnalyzeOutcome`
   com sucesso fica guardado, a arvore dele permanece na tela com aviso
   ("Arvore da ultima versao valida", chave nova em `tools.ui.states` nos 5
   idiomas) e opacidade reduzida, e o erro com linha e coluna segue no rodape.
   Vale tambem para o texto acima do limite; o documento vazio continua zerando
   tudo.
2. **Criterio 12 (virtualizacao).** A evidencia citada - "`json-viewer.test.ts`
   (a janela entra acima do limiar)" - nao existia (zero referencias a
   virtualizacao nesse arquivo), e o limiar era a constante `VIRTUALIZE_FROM` no
   modulo, nao configuracao. **Corrigido no codigo**: o limiar passou a
   `config/tools.php` (`TOOLS_VIRTUALIZE_FROM`, padrao 2000, com entrada no
   `.env.example`), descendo por prop como o teto de entrada (`ToolController` ->
   `tools/Show` -> `Tool.vue` -> `JsonTree.vue`), e o teste que a celula alegava
   existe agora de verdade.
3. **Criterio 13 (protocolo do worker).** O nome `{id, task, payload}` nunca
   existiu: o protocolo e `{id, text, options}` / `{id, outcome}`.
   **Corrigido aqui**, no texto da celula.
4. **Criterio 19 (commits).** A afirmacao de que o `git log` continuava no
   `1f00431` ja era falsa quando a revisao olhou: o usuario havia commitado a
   fase as 13:35 (`edb62b2`). **Corrigido aqui**, no texto da celula.
5. **Criterio 5 + SPEC secao 5 / DESIGN (o `kbd` do atalho).** O criterio pedia a
   barra de acoes, mas o SPEC e o DESIGN pedem "o `kbd` do atalho ao lado de cada
   botao" - e nenhum botao recebia atalho. **Corrigido no codigo**: `Mod-Enter`
   formata (novo `onFormat` no editor, registrado **antes** das teclas do motor,
   que ja usavam `Mod-Enter` para inserir linha em branco), `Esc` limpa a busca
   da arvore, o botao Formatar mostra `Ctrl/⌘ Enter`, o campo de busca mostra
   `Esc`, e a faixa de atalhos passou a listar os tres do mockup (formatar,
   buscar e limpar a busca) no lugar de desfazer/colar.

Divergencias menores, resolvidas na documentacao: o DESIGN descrevia a arvore
com 20px por linha e 16px de indentacao (o entregue sao 24px e 14px) e punha as
transformacoes na barra de cima (o entregue as poe no cabecalho da Entrada, e
copiar/baixar na barra de cima) - a secao "Pagina de tool" foi reescrita para
descrever o que existe; a fonte deste documento citava o "entregavel 5" do
DESIGN como se fosse a pagina de tool (o 5 e a imagem OG por tool, o 6 e o
mockup da Tool), agora corrigido; a contagem de assercoes do criterio 18 no
aceite (6.375) nao bate com a medida no commit (6.380) - numeros de relatorio,
sem efeito no resultado.

**Gates depois da revisao** (no working tree, sem commit): `composer test` -
Pint limpo, PHPStan nivel 7 com 0 erro, **266 testes e 6.392 assercoes** verdes;
`npm run test` - **310 testes em 24 arquivos**; `npm run types:check`,
`npm run build` e `npm run build:ssr` verdes; smoke com `INERTIA_SSR_ENABLED=true`
nos 5 idiomas (58-62 KB, title/description/canonical/6 hreflang/`og:image` com
`alt` no HTML do servidor), 404 para slug desconhecido e para tool `soon`, e o
sitemap com 20 URLs. Nenhum commit: a revisao termina no working tree, para o
usuario commitar pelo gitpr.

O levantamento completo e o relatorio da tarefa estao em
`docs/survey/20261014_verificacao_fase2_surveyfacts.md`,
`docs/plans/develop_natan/20261014_verificacao_fase2_plansfacts.md` e
`docs/claude-code/reports/develop_natan/20261014_develop_natan_verificacao_fase2.md`.
