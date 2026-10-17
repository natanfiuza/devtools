# DESIGN - devtools
Referencias: Linear, Vercel, Clerk, SST, opencode. Principios: tipografico, silencioso, denso, sem decoracao.
## Tokens (dark-first)
--bg:#0b0c0f; --surface:#111318; --surface-2:#171a20; --border:rgba(167,165,198,.14); --border-strong:rgba(167,165,198,.28)
--fg:#e8e9ee; --fg-muted:#8797b2; --fg-subtle:#6d8a96; --accent:#66ced6; --accent-2:#a7a5c6; --elevated:#5d707f (so bordas/superficies, NAO texto pequeno)
Light (validado na F0): bg #fafafb; surface #ffffff; surface-2 #f1f2f5; fg #14161a; fg-muted #4c5766; fg-subtle #5c6b76; accent #0f7f8a; accent-2 #4f4b6e; elevated #46545f (so bordas/superficies); destructive #b4232a; bordas rgb(20 22 26 / 12% e 24%).
Fonte da verdade: `resources/css/app.css`. Os nomes do shadcn-vue (--background, --primary, --accent) sao mantidos porque os componentes vendorizados dependem deles; o `--accent` do shadcn e superficie de hover, a marca (ciano) vive em `--primary`/`--brand`.
## Contraste (AA, conferido por teste)
Texto sempre >= 4.5:1 e anel de foco >= 3:1 (WCAG 1.4.11), nos dois temas. Medido em `resources/js/__tests__/design-tokens.test.ts`, que le o `app.css` e falha se cair.
Tema claro: pior par 4.55 (accent sobre bg, que e tambem o anel de foco); foreground 17.4; fg-muted 7.0; fg-subtle 5.5.
Tema escuro: pior par 4.68 (pontuacao JSON sobre surface); foreground 16.1; fg-muted 6.3; fg-subtle 5.1; anel 10.6.
Excecao documentada: no escuro a pontuacao JSON e `--json-punct: #71828e`, e nao o `--elevated #5d707f`, que nao passa como texto pequeno - o `--elevated` fica so com bordas e superficies. A F2 deu a pontuacao token proprio nos **dois** temas (no claro, `--json-punct: #46545f`, o mesmo tom do `--elevated`), e a regra ficou a mesma nos dois (fecha o item da SPEC secao 11.2).
## Tipografia
Inter (UI), JetBrains Mono (codigo) - baixadas do Bunny Fonts no build e servidas pelo proprio dominio (plugin `fonts` do laravel-vite-plugin, com preload). Escala 12/13/14/16/20/32/56. Titulos com tracking -0.02em, peso 500-600.
## Layout
Container 1120px; header 56px sticky com blur: wordmark `devtools_` (cursor em --accent), links Tools/Pricing, seletor de idioma, Cmd+K, Login (a pagina `/styleguide` permanece acessivel pela URL para referencia visual, mas sem link na barra superior).
Home (F1): hero de titulo 56px com subtitulo e um campo de busca que abre a palette; chips de categoria logo abaixo (`JSON`, `SQL`, `Codificacao e hash`, `Seguranca`, `Data e hora`, `Texto` - so as que tem tool); grid de 3 colunas com todas as tools do registro (card de borda 1px sem sombra, icone de linha 20px, nome e uma linha de descricao, clicavel em toda a area do card quando `available`, ou com o selo "Em breve" enquanto a tool nao abre); destaque do AI SQL Builder com preview estatico e CTA de cadastro; footer minimo (idioma, Privacidade, Termos, Cookies, doar). A grade de planos e vantagens Pro vive em pagina publica propria (`/{locale}/pricing`, ADR-034).
```
+--------------------------------------------------------------+
|  devtools_  Tools Precos              [pt] [Buscar] [Login]  |
| ------------------------------------------------------------ |
|                                                              |
|   Ferramentas                                                |
|   Formate, converta e inspecione dados direto no navegador.  |
|   [ Buscar uma ferramenta...                   Cmd K ]       |
|   ---------------------------------------------------------- |
|   [Todas] [JSON] [SQL] [Codificacao] [Seguranca] [...]       |
|   ---------------------------------------------------------- |
|   +-------------+  +-------------+  +-------------+          |
|   | {} JSON V.  |  | <> AI SQL B.|  | </> JSON->C |          |
|   | 1 linha     |  | 1 linha     |  | 1 linha     |          |
|   |             |  |   Em breve  |  |             |          |
|   +-------------+  +-------------+  +-------------+          |
|   ... 8 cards, 3 por linha ...                               |
|   ---------------------------------------------------------- |
|   [ AI SQL Builder - preview estatico + CTA de cadastro ]    |
+--------------------------------------------------------------+
```
O card nao e link enquanto a tool nao abre: sem `href`, sem cursor de mao - o selo "Em breve" e o aviso, e nao ha clique que nao leve a lugar nenhum. Quando a tool esta disponivel (`href` presente), o card inteiro e clicavel via stretched link (`after:inset-0`). Na pagina dedicada de planos (`/{locale}/pricing`), o CTA do Free leva ao cadastro e o dos pagos leva ao formulario de intencao de assinatura (`/subscribe?plan=<slug>`, ADR-034); os numeros vem da tabela `plans` (R$ 8,90/mes e R$ 89,00/ano), formatados no idioma da pagina.
Pagina de tool (F2): mesma largura de container da home, com breadcrumb de uma linha acima do cabecalho (icone 24px, nome em 32px e a descricao em fg-muted). Abaixo, a casca que toda ferramenta compartilha: barra de acoes (botao outline + o `kbd` do atalho ao lado), dois paineis lado a lado - `Entrada` | `Saida`, titulo em 11px maiusculo com fg-subtle, borda 1px, raio 12px, fundo --surface, altura minima de 380px - separados por um divisor de 12px que se arrasta (duplo clique restaura; a divisao fica no localStorage, por tool); embaixo, a faixa de status (chaves, itens, tamanho em JetBrains Mono) e a de atalhos em 12px fg-subtle, com tres itens - formatar, buscar na arvore e limpar a busca - cada um com a tecla num `kbd`. Abaixo de 768px os mesmos dois paineis viram abas (`Entrada` | `Saida`) e as acoes de cada painel sobem para o topo da aba - trocar de aba **nao** desmonta o editor.
```
+--------------------------------------------------------------+
|   Ferramentas > JSON Viewer                                  |
| ------------------------------------------------------------ |
|   {} JSON Viewer                                             |
|   Formate, valide e inspecione JSON sem sair do navegador.   |
| ------------------------------------------------------------ |
|   [Exemplo] [Abrir] [Limpar]           [Copiar] [Baixar]     |
|  +------------------------+ |  +----------------------------+|
|  | ENTRADA                | |  | SAIDA    [Buscar] [Tudo]   ||
|  | [Formatar Cmd+Enter]   | |  | -------------------------- ||
|  | [Minificar] [Ordenar]  | |  | v raiz           3 chaves  ||
|  | ---------------------- | |  |     nome: "devtools"       ||
|  | {                      | |  | v tags            2 itens  ||
|  |   "nome": "devtools",  | |  |       0: "json"            ||
|  |   "tags": [            | |  |       1: "viewer"          ||
|  |     "json",            | |  | v meta                     ||
|  |     "viewer"           | |  |     tema: 1  > chaves 19 KB||
|  |   ],                   | |  |                            ||
|  |   "meta": { "tema": 1 }| |  |                            ||
|  | }                      | |  |                            ||
|  |                        | |  |                            ||
|  |                        | |  |                            ||
|  +------------------------+ |  +----------------------------+|
| ------------------------------------------------------------ |
|   3 chaves  4 itens  0,2 KB                                  |
|   Cmd+Enter formata · Cmd+F busca · Esc limpa a busca        |
+--------------------------------------------------------------+
```
As acoes se dividem por onde agem: na barra de cima ficam **exemplo, abrir arquivo e limpar** (a esquerda) e **copiar e baixar** (a direita) - as duas ultimas no documento inteiro; no cabecalho da **entrada** ficam as transformacoes, que reescrevem o texto - **formatar** (com o `kbd` do atalho ao lado), **minificar** e **ordenar chaves**; no cabecalho da **saida** fica o que e da arvore - buscar, o contador de resultados, limpar a busca (com o `kbd` do `Esc`) e expandir/recolher tudo. O caminho JSONPath de um no aparece ao copiar, no proprio no - nao ha coluna extra para ele. A arvore sobe uma linha por nivel, com 24px por linha e indentacao de 14px; o erro de sintaxe sai em --destructive com a linha e a coluna, e nunca em Dialog. Enquanto o texto esta invalido, a arvore da ultima versao valida **continua na tela** com um aviso na saida e opacidade reduzida (SPEC secao 5): quem esta corrigindo o erro nao perde a referencia do documento.
Pagina de tool (F3): a forma nao mudou - as seis ferramentas novas entram na mesma casca, com as **listas de produto no cabecalho do painel que elas governam**: o alvo (TypeScript | PHP (DTO) | Pydantic) e o campo do nome da raiz no cabecalho da **saida** do JSON para codigo; o dialeto e a caixa das palavras-chave (dois `Select`) no da saida do SQL Formatter; a direcao (Codificar | Decodificar) no da **entrada** do Base64, porque ela muda o que a entrada significa; e as **abas de modo** (Base64 | URL | Hash) dentro do painel de saida do Base64 - as unicas abas dentro de um painel do site, porque ali a entrada e uma so e as leituras sao tres. O cabecalho do painel e o mesmo da F2 - titulo em 11px maiusculo fg-subtle a esquerda -, e os controles entram alinhados a direita (`ml-auto`), em `Select` de 28px (`h-7`) com rotulo acessivel.
A **excecao do Regex Tester**: o campo do padrao mora na **barra de acoes**, em linha inteira, lido como um literal de expressao - as barras em fg-subtle em volta e as flags ligadas logo apos a ultima (`/…/gim`) -, com `i`, `m`, `s` e `u` como alternadores (`ToolButton` com `aria-pressed`) ao lado. E o unico campo de entrada que sai do painel de entrada, e o motivo e o mesmo do ADR-031: o campo que se ajusta **olhando o resultado** fica ao lado do resultado. O `g` nao aparece porque esta sempre ligado, e o `y` nao existe na barra. A saida tem tres partes: a lista de correspondencias (indice, trecho, posicao e os grupos numerados e nomeados), o texto de teste com **cada correspondencia realcada** (`bg-accent-2/30`, raio de 2px, sem sublinhado) e, abaixo, o campo de substituicao (`$1`, `$<nome>`) com o resultado da troca em bloco proprio. No estouro do prazo as tres ficam vazias e so a faixa de status fala (ADR-031) - nao ha meio resultado.
O JWT Decoder abre a saida com uma **faixa de aviso** (borda --accent/40, fundo --accent/5, icone de 16px) dizendo que a ferramenta decodifica e nao valida a assinatura: e a mesma caixa do aviso de sucesso das telas de acesso, porque aviso nao e erro, e o que ela pede e atencao, e nao correcao. As claims de tempo ganham um selo ao lado do valor (expirado em --destructive), e a assinatura aparece so como comprimento em bytes e com o rotulo de nao verificada. No Timestamp/UUID as seis leituras sao linhas de valor em JetBrains Mono com copiar em cada uma, e os ids gerados saem em **lista no painel de saida** (com "copiar todos" no cabecalho dela), nunca em Dialog: gerar e ler ficam no mesmo lugar.
Tela de acesso (login, criar conta, senha): moldura propria em 3 faixas - barra 56px so com wordmark, idioma e tema; coluna centrada de 384px; rodape de uma linha (copyright, Privacidade, Termos). Cabecalho (titulo 20px + descricao fg-muted) e card de borda 1px sem sombra, padding 24px, raio 12px.
Login e Register comecam pelos botoes sociais (outline, largura cheia, marca monocromatica 16px) e uma divisoria 'ou'. Erro em --destructive com role=alert e aria-invalid no campo; envio com Spinner dentro do botao. Aviso de sucesso em caixa de borda --accent/40 e fundo --accent/5.
Newsletter: no rodape publico (campo + botao outline) e, em Register, como checkbox; a resposta sai como toast (flash), nunca inline.
## Componentes
Button (primary --accent texto escuro; ghost; outline), Input, Select, Tabs, Dialog, Command palette, Toast, Badge, Card sem sombra (borda 1px, hover border-strong), Tooltip, Skeleton, ConsentBanner (canto inferior, nao bloqueia).
Command palette (F1): Dialog + Input do reka-ui ja vendorizado - nao entrou dependencia nova, e nao existe primitivo `command` no projeto. Lista com `role="listbox"`/`option`, `aria-activedescendant` e foco preso no campo; abre por Cmd/Ctrl+K e por `/` fora de campo de texto, fecha no Esc. Cada linha: icone, nome, categoria e o selo "Em breve" quando for o caso; tool que ainda nao abre aparece e nao navega.
ConsentBanner (F1): faixa no canto inferior esquerdo, largura maxima de 420px, borda 1px, fundo --surface, sem sombra e sem overlay - a pagina continua utilizavel e rolavel. Titulo "Cookies", uma linha de texto, os dois botoes (Aceitar em primary, Recusar em outline) e "Detalhes", que abre as categorias Necessarios (fixa) e Medicao (interruptor) com o link da Politica de Privacidade. Decidido, o banner some e volta so pelo botao "Cookies" no rodape.
## Tabela densa (AdminTable)
Primeira tabela do projeto: ela vive no painel administrativo, nao no site. Regras: borda 1px entre linhas (nunca sombra), altura de linha 40px, cabecalho em 12px maiusculo com fg-subtle, celula numerica alinhada a direita em JetBrains Mono, coluna de acao a direita. Sem zebra. Ordenacao e filtro na barra acima da tabela, nunca dentro do cabecalho. Estados: vazio (titulo + uma linha de ajuda, sem ilustracao), carregando (Skeleton de 5 linhas) e erro (AlertError). Paginacao como contagem + anterior/proxima, porque o painel nao usa scroll infinito.
## Painel do usuario (area autenticada)
Uma navegacao so, na sidebar existente: grupo `Area da conta` com Visao geral (/dashboard), Historico (/dashboard/history) e Faturas (/dashboard/billing), e, abaixo, o grupo `Configuracoes` com Perfil, Seguranca e Aparencia (URLs de settings continuam as mesmas). A exportacao dos dados e a exclusao da conta ficam na tela de Perfil (ADR-028).
```
+--------------------------------------------------------------+
| Visao geral                                                   |
| ------------------------------------------------------------ |
| [ Plano ]     [ Cota ]        [ Assinatura ]   [ Vencimento ] |
| Pro          118.400 tokens   Manual           12/11/2026     |
|              limite 2.000.000 Ativa 01/10-31/10               |
|              usados 118.400                                   |
|              Ate 31/10/2026                                   |
| ------------------------------------------------------------ |
| 3 pagamentos              [ Faturas ]  [ Historico ]          |
+--------------------------------------------------------------+
```
Os quatro cards respondem, na ordem: em que plano estou, quanto ainda cabe, como estou sendo cobrado e quando vence. A cota mostra a unidade do plano (`usos` no Free, `tokens` nos pagos), a franquia, o consumo e a janela contada - e essa terceira linha vira o aviso quando a cota acabou. Numeros grandes em JetBrains Mono; nenhuma barra de progresso (o numero e o dado). Estado vazio (conta no Free, sem uso): a cota aparece em `usos` com janela vitalicia, o vencimento vira "Sem vencimento" e a assinatura, "Sem assinatura ativa". Faturas e Historico vazios usam o mesmo padrao da tabela densa (titulo + uma linha), nunca um card decorativo; o vazio das faturas explica que a mudanca de plano passa pelo contato, porque a cobranca pelo site ainda nao existe.
## Painel administrativo
Layout proprio (`AdminLayout`), sem o header publico: sidebar de 6 secoes fixas (Visao geral, Contas, Assinantes, Planos, Pagamentos, Auditoria) e barra superior com o nome do painel, `voltar ao site` e o menu do usuario. Fundo --bg, conteudo em container de 1280px (o painel e mais largo que o site).
```
+-------------------+------------------------------------------+
| devtools admin    | Contas                     voltar ao site |
| ----------------- | ---------------------------------------- |
| Visao geral       | [ busca q ] [ papel ] [ situacao ]  [csv]|
| Contas            | ---------------------------------------- |
| Assinantes        | conta | papel | situacao | criada | acoes |
| Planos            | ---------------------------------------- |
| Pagamentos        | ...   | user  | ativa    | 12/10  | ...   |
| Auditoria         |                                          |
|                   | 24 contas            1-20   [<] [>]      |
+-------------------+------------------------------------------+
```
`Visao geral` = 4 numeros (contas, assinantes ativos, receita do mes, contas suspensas) em cards de borda 1px, sem grafico, mais a lista dos cadastros mais recentes. O detalhe da conta abre em pagina propria (`/admin/users/{id}`) com as secoes em ordem: identidade, situacao, acoes, assinatura, consumo, logins sociais, sessoes abertas e pagamentos - e, abaixo, os formularios de conceder plano, conceder cota e excluir conta. Acoes destrutivas (excluir) pedem o e-mail digitado num Dialog; as demais sao botoes outline com confirmacao simples (o papel e a suspensao pedem um "sim" antes de aplicar). Enquanto o admin impersona, uma faixa em --accent/10 com borda --accent/40 ocupa o topo de toda tela autenticada e nao pode ser dispensada sem sair.
## Cores sintaxe JSON
Uma cor por token, e a mesma no editor (CodeMirror) e na arvore - nao ha paleta paralela em JavaScript (ADR-018). Claro: chaves `--json-key` = --accent-2 #4f4b6e; strings `--json-string` = --brand #0f7f8a; numeros `--json-number` = --fg-muted #4c5766; boolean/null `--json-literal` = --fg-subtle #5c6b76; pontuacao `--json-punct` #46545f. Escuro: chaves #a7a5c6; strings #66ced6; numeros #8797b2; boolean/null #6d8a96; pontuacao `--json-punct` #71828e (a excecao da secao de contraste). O erro de sintaxe nao e uma cor de token: sai em --destructive.
## Motion
150ms ease-out; respeitar prefers-reduced-motion. Sem animacao decorativa.
## Acessibilidade
Contraste AA, foco visivel 2px --accent, navegacao por teclado, aria-labels, i18n com textos expansivos (fr/es ~30% maiores).
## Entregaveis de design
1) tokens.css + tailwind preset 2) pagina /styleguide com todos os componentes 3) wordmark SVG 4) favicon 5) OG image por tool (gerada) 6) mockups textuais das telas Home, Tool, AI Builder, Pricing, Admin.
Estado na F2: 1 a 6 prontos. A **imagem OG padrao do site** (`public/og-default.png`, 1200x1200 quadrada, com o desenho na faixa central de 628px que o corte 1.91:1 preserva e a URL versionada por `?v=` - ADR-033) entrou na F1 - fundo --bg, wordmark `devtools_` com o cursor em --accent e os nomes das tools em --fg-muted -, gerada por um comando artisan de uso unico (`seo:og-image`) que baixa as fontes para `storage/app/og-fonts/` (fora do git) e escreve o PNG, que **vai comitado**: em producao o comando nunca roda e o VPS nao precisa de GD nem de rede. A **imagem por tool** (5) entrou na F2 no mesmo comando: uma por tool que funciona, em `public/og/<slug>.png`, com o nome da ferramenta no lugar do wordmark grande e os termos do proprio manifesto na linha de baixo, **sem idioma nenhum** - o nome traduzido vai no `og:image:alt`, e a pagina cai na imagem padrao sem o arquivo (ADR-030). Os mockups (6) cobrem Home, Tool, AI Builder, Pricing e Admin; o do Pricing e o da secao de precos da home, e o da Tool e o desta secao.
Fora do design, mas decidido junto: `sitemap.xml` e `robots.txt` sao servidos pela **aplicacao** (`/sitemap.xml`, `/robots.txt`, fora do prefixo de idioma) e o `public/robots.txt` estatico saiu do repositorio - arquivo estatico vence a rota, no nginx e no `artisan serve`, e amarraria a linha do `Sitemap:` a um ambiente.
Estado na F3: 1 a 6 continuam prontos, sem entrega nova de design - a fase nao desenhou tela, e sim as seis variacoes da mesma casca (as listas no cabecalho do painel, a excecao do Regex Tester e a faixa de aviso do JWT Decoder, nesta secao). A **imagem OG por tool** (5) passou de uma para sete: o `seo:og-image` escreve um PNG por tool que funciona, e as seis novas nasceram ao virar o `status` delas (ADR-030); todos os cards seguem o canvas quadrado de 1200x1200 com o desenho na faixa central (ADR-033). Os mockups (6) seguem os mesmos cinco; o da Tool e o desta secao, e a F3 nao precisou de um segundo.
