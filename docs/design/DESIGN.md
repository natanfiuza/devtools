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
Container 1120px; header 56px sticky com blur: wordmark `devtools_` (cursor em --accent), links Tools/Pricing/Docs, seletor de idioma, Cmd+K, Login.
Home (F1): hero de titulo 56px com subtitulo e um campo de busca que abre a palette; chips de categoria logo abaixo (`JSON`, `SQL`, `Codificacao e hash`, `Seguranca`, `Data e hora`, `Texto` - so as que tem tool); grid de 3 colunas com todas as tools do registro (card de borda 1px sem sombra, icone de linha 20px, nome e uma linha de descricao, com o selo "Em breve" enquanto a tool nao abre); destaque do AI SQL Builder com preview estatico e CTA de cadastro; secao de precos; footer minimo (idioma, Privacidade, Termos, Cookies, doar).
```
+--------------------------------------------------------------+
|  devtools_  Tools Precos Styleguide [pt] [Buscar] [Login]    |
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
|   |   Em breve  |  |   Em breve  |  |   Em breve  |          |
|   +-------------+  +-------------+  +-------------+          |
|   ... 8 cards, 3 por linha ...                               |
|   ---------------------------------------------------------- |
|   [ AI SQL Builder - preview estatico + CTA de cadastro ]    |
|   ---------------------------------------------------------- |
|   Planos                                                     |
|   Free (Gratis)    Pro (R$ 29,90/mes)    Pro (R$ 299/ano)    |
|   5 usos vitalicios 2.000.000 tokens     2.000.000 tokens    |
|   [Criar conta]    [Falar por e-mail]    [Falar por e-mail]  |
+--------------------------------------------------------------+
```
O card nao e link enquanto a tool nao abre: sem `href`, sem cursor de mao - o selo "Em breve" e o aviso, e nao ha clique que nao leve a lugar nenhum. Na secao de precos o CTA do Free e criar conta e o dos pagos e o contato por e-mail, porque no site nao existe botao de assinar (a venda e manual, ADR-027); os numeros vem da tabela `plans`, formatados no idioma da pagina.
Pagina de tool (F2): mesma largura de container da home, com breadcrumb de uma linha acima do cabecalho (icone 24px, nome em 32px e a descricao em fg-muted). Abaixo, a casca que toda ferramenta compartilha: barra de acoes (botao outline + o `kbd` do atalho ao lado), dois paineis lado a lado - `Entrada` | `Saida`, titulo em 11px maiusculo com fg-subtle, borda 1px, raio 12px, fundo --surface, altura minima de 380px - separados por um divisor de 12px que se arrasta (duplo clique restaura; a divisao fica no localStorage, por tool); embaixo, a faixa de status (chaves, itens, tamanho em JetBrains Mono) e a de atalhos em 12px fg-subtle. Abaixo de 768px os mesmos dois paineis viram abas (`Entrada` | `Saida`) e as acoes de cada painel sobem para o topo da aba - trocar de aba **nao** desmonta o editor.
```
+--------------------------------------------------------------+
|   Ferramentas > JSON Viewer                                  |
| ------------------------------------------------------------ |
|   {} JSON Viewer                                             |
|   Formate, valide e inspecione JSON sem sair do navegador.   |
| ------------------------------------------------------------ |
|   [Formatar  Cmd+Shift+F] [Minificar] [Ordenar] [Exemplo]    |
|  +------------------------+ |  +----------------------------+|
|  | ENTRADA        [Abrir] | |  | SAIDA    [Buscar] [Tudo]   ||
|  | ---------------------- | |  | -------------------------- ||
|  | {                      | |  | v raiz           3 chaves  ||
|  |   "nome": "devtools",  | |  |     nome: "devtools"       ||
|  |   "tags": [            | |  | v tags            2 itens  ||
|  |     "json",            | |  |       0: "json"            ||
|  |     "viewer"           | |  |       1: "viewer"          ||
|  |   ],                   | |  | v meta                     ||
|  |   "meta": { "tema": 1 }| |  |     tema: 1  > chaves 19 KB||
|  | }                      | |  |                            ||
|  +------------------------+ |  +----------------------------+|
| ------------------------------------------------------------ |
|   3 chaves  4 itens  0,2 KB                                  |
|   Cmd+Enter formata · Cmd+F busca · Esc limpa a busca        |
+--------------------------------------------------------------+
```
Cada painel tem a acao no proprio cabecalho: na entrada, abrir arquivo e limpar; na saida, buscar na arvore e expandir/recolher tudo. Copiar e baixar ficam na barra de cima, junto das transformacoes (formatar, minificar, ordenar chaves), porque agem no documento inteiro. O caminho JSONPath de um no aparece ao copiar, no proprio no - nao ha coluna extra para ele. A arvore sobe uma linha por nivel, com 20px por linha e indentacao de 16px; o erro de sintaxe sai em --destructive com a linha e a coluna, e nunca em Dialog.
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
Estado na F2: 1 a 6 prontos. A **imagem OG padrao do site** (`public/og-default.png`, 1200x630) entrou na F1 - fundo --bg, wordmark `devtools_` com o cursor em --accent e os nomes das tools em --fg-muted -, gerada por um comando artisan de uso unico (`seo:og-image`) que baixa as fontes para `storage/app/og-fonts/` (fora do git) e escreve o PNG, que **vai comitado**: em producao o comando nunca roda e o VPS nao precisa de GD nem de rede. A **imagem por tool** (5) entrou na F2 no mesmo comando: uma por tool que funciona, em `public/og/<slug>.png`, com o nome da ferramenta no lugar do wordmark grande e os termos do proprio manifesto na linha de baixo, **sem idioma nenhum** - o nome traduzido vai no `og:image:alt`, e a pagina cai na imagem padrao sem o arquivo (ADR-030). Os mockups (6) cobrem Home, Tool, AI Builder, Pricing e Admin; o do Pricing e o da secao de precos da home, e o da Tool e o desta secao.
Fora do design, mas decidido junto: `sitemap.xml` e `robots.txt` sao servidos pela **aplicacao** (`/sitemap.xml`, `/robots.txt`, fora do prefixo de idioma) e o `public/robots.txt` estatico saiu do repositorio - arquivo estatico vence a rota, no nginx e no `artisan serve`, e amarraria a linha do `Sitemap:` a um ambiente.
