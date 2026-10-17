# Survey — OG images quadradas e metas de página no servidor

Data: 2026-10-17
Branch: develop_natan
Task: og_quadrada_metas_no_servidor

## Contexto da tarefa

Ao compartilhar `https://devtools.natanfiuza.dev.br/pt-br/tools/json-viewer` no
WhatsApp, o preview mostrava apenas "devtools" e o domínio, sem título nem
descrição, e a imagem OG 1200×630 aparecia pequena/cortada. Pedido: card mais
quadrado e exibição do título e da descrição da página.

## Fatos levantados

### Estado do código

- `php artisan seo:og-image` (`app/Console/Commands/GenerateOgImage.php`) desenha
  PNGs com GD: `public/og-default.png` e `public/og/<slug>.png` para cada tool
  `available`. Dimensões vêm de `config('seo.image.width/height')` (1200×630).
- O desenho atual: card padrão com wordmark grande + lista de tools; card de tool
  com wordmark pequeno, nome da tool e `keywords` do manifest (em inglês). Sem
  idioma nenhum, por decisão da ADR-030.
- `App\Support\Seo::openGraph()` monta apenas dados do site (tipo, nome, idioma,
  alternates e imagem). Título e descrição por página vinham do `<Head>` do
  Inertia em `resources/js/components/PageHead.vue`.
- `og:title`, `og:description` e `meta description` só existiam no `<Head>` do
  Inertia; sem SSR eles não chegam ao HTML do servidor.
- `App\Support\Seo::image()` escolhe `/og/<slug>.png` para a rota `tools.show`
  com slug `available` e PNG em disco; senão `og-default.png`.

### Diagnóstico em produção

Requisição ao site ao vivo (User-Agent de WhatsApp e de navegador) retornou:

- `<title>devtools</title>` (fallback do slot do Inertia);
- sem `og:title`, sem `og:description`, sem `meta description`;
- `og:image` = `https://devtools.natanfiuza.dev.br/og/json-viewer.png`
  (1200×630).

Ou seja: o SSR não estava entregando o head do Inertia — causa do preview sem
título/descrição. Robôs de preview não executam JS.

### Comportamento do WhatsApp

- O WhatsApp exibe o card em área quadrada e corta imagens horizontais; guias de
  2026 recomendam 1:1 (800×800 a 1200×1200) para evitar o corte.
- X/Facebook/LinkedIn seguem 1.91:1 e cortam o topo/base de uma imagem quadrada;
  o conteúdo essencial deve ficar na faixa central (1200×628).
- O preview é cacheado por URL; a imagem precisa de URL nova/versionada para
  forçar o recache.

## Rodadas do grill

### Rodada 1

1. Onde gerar título/descrição? **No servidor** (Blade + `App\Support\Seo`), com
   PageHead emitindo só o `<title>`.
2. Formato da imagem? **1200×1200** (quadrada).
3. Conteúdo do card de tool? **Título + keywords** (uma imagem por tool, sem
   idioma; ADR-030 mantida).
4. Cache-bust? **Versão na URL** (`?v=2`, configurável).

### Rodada 2

5. Formato do `og:title`? **Título puro**, sem sufixo de marca.
6. Card padrão? **Wordmark + lista de tools**.
7. `twitter:card`? **Manter `summary_large_image`**.

## Consequências

- Metas passam a funcionar com SSR ligado ou desligado.
- Nenhuma meta duplicada: PageHead não escreve mais `og:title`,
  `og:description` nem `meta description`.
- Imagens regeneradas e comitadas manualmente pelo usuário.
- ADR-033 registra a decisão; ADR-030 recebe emenda.
