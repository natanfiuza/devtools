# DESIGN - devtools
Referencias: Linear, Vercel, Clerk, SST, opencode. Principios: tipografico, silencioso, denso, sem decoracao.
## Tokens (dark-first)
--bg:#0b0c0f; --surface:#111318; --surface-2:#171a20; --border:rgba(167,165,198,.14); --border-strong:rgba(167,165,198,.28)
--fg:#e8e9ee; --fg-muted:#8797b2; --fg-subtle:#6d8a96; --accent:#66ced6; --accent-2:#a7a5c6; --elevated:#5d707f (so bordas/superficies, NAO texto pequeno)
Light (validado na F0): bg #fafafb; surface #ffffff; surface-2 #f1f2f5; fg #14161a; fg-muted #4c5766; fg-subtle #5c6b76; accent #0f7f8a; accent-2 #4f4b6e; elevated (e pontuacao JSON) #46545f; destructive #b4232a; bordas rgb(20 22 26 / 12% e 24%).
Fonte da verdade: `resources/css/app.css`. Os nomes do shadcn-vue (--background, --primary, --accent) sao mantidos porque os componentes vendorizados dependem deles; o `--accent` do shadcn e superficie de hover, a marca (ciano) vive em `--primary`/`--brand`.
## Contraste (AA, conferido por teste)
Texto sempre >= 4.5:1 e anel de foco >= 3:1 (WCAG 1.4.11), nos dois temas. Medido em `resources/js/__tests__/design-tokens.test.ts`, que le o `app.css` e falha se cair.
Tema claro: pior par 4.55 (accent sobre bg, que e tambem o anel de foco); foreground 17.4; fg-muted 7.0; fg-subtle 5.5.
Tema escuro: pior par 4.68 (pontuacao JSON sobre surface); foreground 16.1; fg-muted 6.3; fg-subtle 5.1; anel 10.6.
Excecao documentada: no escuro a pontuacao JSON e #71828e, e nao o --elevated #5d707f do DESIGN, que nao passa como texto (ver SPEC secao 11.2).
## Tipografia
Inter (UI), JetBrains Mono (codigo) - baixadas do Bunny Fonts no build e servidas pelo proprio dominio (plugin `fonts` do laravel-vite-plugin, com preload). Escala 12/13/14/16/20/32/56. Titulos com tracking -0.02em, peso 500-600.
## Layout
Container 1120px; header 56px sticky com blur: wordmark `devtools_` (cursor em --accent), links Tools/Pricing/Docs, seletor de idioma, Cmd+K, Login.
Home: hero (titulo 56px + subtitulo + campo de busca Cmd+K), grid 3 colunas de cards (icone de linha 20px, nome, 1 linha), categorias como chips, secao 'AI SQL Builder' em destaque com preview, footer minimo (idioma, privacidade, termos, doar).
Pagina de tool: 2 paineis (entrada | saida) redimensionaveis, barra de acoes (copiar, baixar, limpar, exemplo), atalhos visiveis, mobile empilhado em abas.
Tela de acesso (login, criar conta, senha): moldura propria em 3 faixas - barra 56px so com wordmark, idioma e tema; coluna centrada de 384px; rodape de uma linha (copyright, Privacidade, Termos). Cabecalho (titulo 20px + descricao fg-muted) e card de borda 1px sem sombra, padding 24px, raio 12px.
Login e Register comecam pelos botoes sociais (outline, largura cheia, marca monocromatica 16px) e uma divisoria 'ou'. Erro em --destructive com role=alert e aria-invalid no campo; envio com Spinner dentro do botao. Aviso de sucesso em caixa de borda --accent/40 e fundo --accent/5.
Newsletter: no rodape publico (campo + botao outline) e, em Register, como checkbox; a resposta sai como toast (flash), nunca inline.
## Componentes
Button (primary --accent texto escuro; ghost; outline), Input, Select, Tabs, Dialog, Command palette, Toast, Badge, Card sem sombra (borda 1px, hover border-strong), Tooltip, Skeleton, ConsentBanner (canto inferior, nao bloqueia).
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
chaves #a7a5c6; strings #66ced6; numeros #8797b2; boolean/null #6d8a96; pontuacao #5d707f.
## Motion
150ms ease-out; respeitar prefers-reduced-motion. Sem animacao decorativa.
## Acessibilidade
Contraste AA, foco visivel 2px --accent, navegacao por teclado, aria-labels, i18n com textos expansivos (fr/es ~30% maiores).
## Entregaveis de design
1) tokens.css + tailwind preset 2) pagina /styleguide com todos os componentes 3) wordmark SVG 4) favicon 5) OG image por tool (gerada) 6) mockups textuais das telas Home, Tool, AI Builder, Pricing, Admin.
