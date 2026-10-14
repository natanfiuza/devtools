# ADR-030-imagem-og-por-tool
Status: Aceito
Data: 2026-10-14

## Decisao
**Cada tool que funciona tem a propria imagem de compartilhamento, desenhada
pelo mesmo comando que desenha a imagem padrao, e sem idioma nenhum: o nome
traduzido da ferramenta vai no `og:image:alt`. Sem o arquivo, a pagina cai na
imagem padrao.**

- **Uma imagem por tool `available`**, em `public/og/<slug>.png`, desenhada por
  `php artisan seo:og-image` junto com a padrao (`og-default.png`). Tool que nao
  funciona nao tem pagina e nao precisa de card.
- **O mesmo desenho, com outro texto**: fundo, wordmark, margens e tipografia
  sao os da imagem padrao; muda o nome da ferramenta no lugar do wordmark
  grande (que encolhe se o nome for longo, em vez de vazar a margem) e a lista
  de baixo, que passa a trazer os `keywords` do proprio manifesto.
- **Comando de uso unico, PNGs comitados**: em producao ele nunca roda - o VPS
  nao tem GD nem rede para baixar as fontes. Mexeu no registro, roda o comando
  de novo e comita.
- **Sem idioma na imagem**: o site tem cinco e a imagem e uma so. A frase que
  acompanha o card (`og:image:alt`) sai traduzida no idioma da pagina, com o
  nome da ferramenta **daquele** idioma; a marca e a frase do site nos outros
  casos.
- **A escolha da imagem e do servidor** (`App\Support\Seo::openGraph()`): so a
  rota `tools.show` com slug que existe no registro **e** com o PNG em disco usa
  a imagem da tool. Sem o arquivo, cai na padrao - um `og:image` apontando para
  404 estraga o card mais do que uma imagem generica.
- **As dimensoes sao as mesmas** nas duas imagens (1200x630, `config/seo.php`),
  entao `og:image:width/height` nao mentem em nenhum dos casos, e o teste confere
  o PNG de verdade com `getimagesize`.

## Por que
Quem compartilha a pagina de uma ferramenta quer o card dizendo **qual**
ferramenta e; um card generico do site serve para a home, nao para
`/pt-br/tools/json-viewer`. Como a imagem nao tem texto traduzido, ela nao
precisa ser uma por idioma - o que multiplicaria cinco vezes o numero de
arquivos comitados e nao mudaria o que a pessoa ve.

A decisao no servidor (e nao no `<Head>` do Inertia) e para haver **uma** fonte
das metas `og:image*`: o blade ja escreve tipo, site, idioma, url e imagem, e o
`<Head>` escreve title e description. Duas fontes para a mesma meta e como se
duplicam meta tags contraditorias.

## Consequencias
- `config/seo.php` ganhou `image.tools_dir` (`og`); `Seo::openGraph()` passou a
  receber a request para decidir. Ha teste para o caminho da tool, para o `alt`
  traduzido nos cinco idiomas, para o PNG existir com as dimensoes prometidas e
  para a queda na padrao (fixture sem imagem propria).
- `GenerateOgImage` passou a ler o registro (`Tools::available()` e os
  `keywords` de cada manifesto) - e a terceira coisa a depender do registro, ao
  lado da home e do sitemap. Registrar uma tool nova e rodar o comando de novo
  antes de comitar.
- A imagem padrao da F1 **nao mudou um byte** com a refatoracao (o desenho dela
  foi so extraido para `drawDefault()`).
- Publicar uma tool sem imagem e um estado valido: a pagina abre com o card
  padrao, e o teste registra esse caminho.

## Alternativas descartadas
- **Uma imagem por tool e por idioma** - 40 arquivos em vez de 8, para repetir o
  mesmo desenho; se o nome mudasse de idioma, a imagem comitada ficaria presa ao
  texto de uma versao.
- **Gerar a imagem em runtime** (intervencao de imagem, `?text=`) - o VPS nao
  tem GD nem rede (ADR-014), e um card que depende de requisicao quebra o
  compartilhamento quando o serviço cai.
- **SVG como `og:image`** - nenhuma rede social relevante renderiza SVG em card.
- **Deixar a escolha para o `<Head>` do Inertia** - segunda fonte das mesmas
  metas, e sem SSR o robo nao veria nenhuma das duas.
- **Escrever a imagem da tool no lugar da padrao** (sobrescrever
  `og-default.png` durante a geracao) - a home e as paginas legais perderiam o
  card do site.
