# ADR-033-metas-de-pagina-no-servidor-e-og-quadrada
Status: Aceito
Data: 2026-10-17

## Decisao
**O titulo e a descricao de cada pagina sao resolvidos no servidor
(`App\Support\Seo::pageMeta()`) e escritos pelo Blade; as imagens de
compartilhamento passam a ser quadradas (1200x1200), com o desenho na faixa
central que o corte 1.91:1 preserva, e a URL da imagem leva `?v=` de cache.**

- **Fonte unica das metas de pagina no PHP**: `app.blade.php` escreve
  `<title>`, `meta description`, `og:title` e `og:description` a partir de
  `Seo::pageMeta()`. O `<Head>` do Inertia (`PageHead.vue`) emite so o
  `<title>` da navegacao do cliente, a partir das shared props (`seo.meta`);
  nenhuma meta e escrita duas vezes.
- **`og:title` e o titulo puro**, sem sufixo de marca; o `<title>` usa
  `Seo::documentTitle()`, que reproduz o `appTitle()` do frontend
  (`"Pagina - devtools"`, ou so `devtools` quando o titulo e vazio ou igual ao
  nome do app).
- **Fallbacks por rota** em `pageMeta()`: home usa `app.nav.tools` e
  `home.subtitle`; `tools.show` usa `tools.<slug>.name` e `.description`
  (slug conferido no registro); privacidade e termos usam `legal.*.title` e
  `.intro` com o `{app}` interpolado no servidor; o resto cai no nome do app e
  no `app.tagline`. Telas `noindex` voltam com os dois campos vazios.
- **Canvas quadrado 1200x1200** (`config/seo.php`) e uma faixa central de
  628px (`CROP_BAND_HEIGHT` em `GenerateOgImage`): o WhatsApp exibe o preview
  numa area quadrada, e as redes que ainda cortam 1.91:1 (X, Facebook) cortam
  so as bordas. O wordmark, o nome da tool e a lista de termos ficam dentro da
  faixa.
- **A versao de cache entra na URL** (`?v=2`, `config('seo.image.version')`),
  nao no arquivo: o `is_file()` continua sendo feito no caminho em disco, sem
  query. Incrementar a cada redesenho dos PNGs.
- **`twitter:card` continua `summary_large_image`**: a imagem e quadrada, mas e
  o card grande que carrega titulo e descricao ao lado no X.

## Por que
O preview do WhatsApp mostrava so "devtools" e o dominio: o HTML ao vivo
respondia com `<title>devtools</title>` e **sem** `og:title`, `og:description`
e `meta description`, mesmo com User-Agent de navegador. As metas viviam apenas
no `<Head>` do Inertia, e o SSR nao estava entregando o head em producao.
Robo de preview nao executa JavaScript, entao depender do SSR para uma meta
que so importa para robos e o mesmo que nao ter a meta.

A imagem horizontal (1200x630) tambem era exibida pequena e cortada no card
quadrado do WhatsApp. Como a faixa central da area quadrada ainda e a mesma
1.91:1 que as outras redes cortam, um desenho unico que cabe nas duas resolve
os dois casos sem imagens por rede.

## Consequencias
- `App\Support\Seo` passa a ser a unica fonte do titulo e da descricao de
  pagina; `HandleInertiaRequests` compartilha `seo.meta` para o `<title>` da
  navegacao do cliente e para o SSR.
- `PageHead.vue` perdeu as props e as tres metas que escrevia; as paginas
  passaram a usar `<PageHead />` sem argumentos. As metas de uma pagina mudam
  quando a pagina e carregada do servidor - correto para crawler, que busca a
  URL direto; na navegacao SPA o `<title>` acompanha as shared props.
- `config/seo.php` ganhou `image.version`; a largura e a altura sao 1200x1200.
  O teste de dimensoes compara a config com o PNG real (`getimagesize`), e o
  teste da versao troca a config e cobra a query na URL.
- Os 8 PNGs (`og-default.png` e `og/<slug>.png` das tools `available`) foram
  redesenhados e seguem comitados; em producao o comando nunca roda (o VPS nao
  tem GD nem rede, ADR-014).
- O preview da **pagina** pode continuar em cache por URL no WhatsApp mesmo
  com `?v=` na imagem; para forcar um novo scrape, usar o Facebook Sharing
  Debugger ou uma query temporaria na URL da pagina.

## Alternativas descartadas
- **Depender do SSR para as metas** - era o estado que falhou em producao:
  sem head no HTML, o robo nao ve titulo nem descricao.
- **Duplicar as metas no Blade e no `<Head>`** - duas fontes do mesmo valor,
  que divergem em silencio (e `meta` repetida e contraditoria no SSR).
- **Imagens por idioma** - cinco vezes mais arquivos para repetir o mesmo
  desenho; o nome traduzido ja vai no `og:image:alt` (ADR-030).
- **`og:title` com o sufixo de marca** - o card ja mostra o dominio; o titulo
  curto e o que identifica a pagina.
- **Manter 1200x630 e confiar no corte da rede** - e exatamente o que deixava
  a imagem pequena e cortada no WhatsApp.
- **Gerar ou redimensionar a imagem em runtime** - o VPS nao tem GD nem rede
  (ADR-014); um card que depende de requisicao quebra o compartilhamento.
- **Trocar para `twitter:card=summary`** (card quadrado pequeno) - o card
  grande carrega titulo e descricao ao lado, que e o objetivo da mudanca.
