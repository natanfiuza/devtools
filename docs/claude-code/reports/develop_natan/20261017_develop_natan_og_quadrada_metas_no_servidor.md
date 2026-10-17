# Relatorio - OG quadrada e metas de pagina no servidor

Branch: `develop_natan`
Data: 2026-10-17
Tarefa: card de compartilhamento quadrado e titulo/descricao da pagina no preview
Plano: `docs/plans/develop_natan/20261017_og_quadrada_metas_no_servidor_plansfacts.md`
Survey: `docs/survey/20261017_og_quadrada_metas_no_servidor_surveyfacts.md`

## 1. O que foi entregue

Dois problemas do preview de link, confirmados no HTML de producao:

1. O card so mostrava "devtools" e o dominio porque o HTML do servidor saia
   com `<title>devtools</title>` e **sem** `og:title`, `og:description` e
   `meta description` - mesmo com User-Agent de navegador. As metas viviam so
   no `<Head>` do Inertia e o SSR nao estava entregando o head em producao;
   robo de preview nao executa JS.
2. A imagem era 1200x630 e o WhatsApp exibe o card numa area quadrada: a
   imagem aparecia pequena e cortada.

| # | Bloco | Estado |
|---|---|---|
| 1 | `App\Support\Seo::pageMeta()` como fonte unica do titulo e da descricao das paginas | concluido |
| 2 | `app.blade.php` escrevendo `<title>`, `meta description`, `og:title` e `og:description` | concluido |
| 3 | Shared prop `seo.meta` e `PageHead.vue` emitindo so o `<title>` | concluido |
| 4 | Imagens OG redesenhadas em 1200x1200, com o conteudo na faixa central | concluido |
| 5 | `config/seo.php`: 1200x1200 e `image.version` (cache `?v=`) | concluido |
| 6 | Testes backend e frontend atualizados | concluido |
| 7 | ADR-033, emenda na ADR-030, SPEC, DESIGN, glossario e CHANGELOG | concluido |

## 2. Decisoes (ADR-033, com emenda na ADR-030)

- **Metas de pagina no servidor**: `Seo::pageMeta()` resolve titulo e
  descricao por rota (home, tool, privacidade, termos, fallback do site; vazio
  em `noindex`), e o Blade escreve as quatro metas. A dependencia do SSR para
  uma meta que so importa para robos foi o que falhou em producao.
- **`og:title` puro, `<title>` com sufixo**: o card ja mostra o dominio; o
  `<title>` continua "Pagina - devtools" via `Seo::documentTitle()`, no mesmo
  formato do `appTitle()` do frontend.
- **Uma fonte por meta**: `PageHead.vue` nao escreve mais meta nenhuma; ele
  so aplica o `<title>` vindo da shared prop `seo.meta`.
- **Card quadrado 1200x1200 com faixa central de 628px**: o WhatsApp usa area
  quadrada e as redes que cortam 1.91:1 (X, Facebook) cortam so as bordas. O
  desenho continua sem idioma (ADR-030): nome da tool e `keywords` do
  manifesto, com o nome traduzido no `og:image:alt`.
- **Cache da imagem por `?v=`** em `config/seo.php` (`version => 2`), sem
  tocar no nome do arquivo; o `is_file()` continua no caminho em disco, sem a
  query.
- **`twitter:card` segue `summary_large_image`** - o card grande carrega
  titulo e descricao ao lado.

## 3. Arquivos

Backend:

- `app/Support/Seo.php`: `pageMeta()`, `documentTitle()`, `toolMeta()`,
  `legalIntro()` (interpola `{app}` no servidor) e `imageUrl()` (versao de
  cache aplicada so na URL).
- `app/Http/Middleware/HandleInertiaRequests.php`: shared prop
  `seo.meta.title/description`.
- `resources/views/app.blade.php`: metas de pagina no bloco das paginas
  indexaveis e `<title>` pelo `documentTitle()`.
- `config/seo.php`: `width/height = 1200`, `version = 2`.
- `app/Console/Commands/GenerateOgImage.php`: `CROP_BAND_HEIGHT = 628`,
  `MAIN_BASELINE_OFFSET = 48`, `LINES_OFFSET = 96`, `mainBaseline()`, wordmark
  pequeno no alto da faixa; docblock atualizado.

Frontend:

- `resources/js/components/PageHead.vue`: so `<title>`.
- `resources/js/pages/Home.vue`, `tools/Show.vue`, `Privacy.vue`,
  `Terms.vue`: `<PageHead />` sem props.
- `resources/js/types/global.d.ts`: tipo de `seo.meta`.

Imagens (comitam junto):

- `public/og-default.png` e `public/og/{json-viewer,json-to-code,sql-formatter,base64-url-hash,jwt-decoder,timestamp-uuid,regex-tester}.png`,
  todos 1200x1200, `image/png`, 38-48 KB.

Documentacao:

- `docs/adr/ADR-033-metas-de-pagina-no-servidor-e-og-quadrada.md` (novo).
- `docs/adr/ADR-030-imagem-og-por-tool.md`: emenda apontando para a ADR-033.
- `docs/SPEC.md` (secao 4 e 11.1), `docs/design/DESIGN.md`,
  `docs/glossario.md` e `CHANGELOG.md`.

## 4. Testes

- `tests/Feature/SeoMetaTest.php`: titulo e descricao no HTML do servidor nas
  quatro paginas publicas e nos cinco idiomas; `{app}` interpolado nas metas
  legais; shared props (`assertInertia`) e `noindex` com os dois campos
  vazios; card quadrado; versao da config na URL (`config(['seo.image.version'
  => 7])` -> `?v=7`); dimensoes conferidas nos 8 PNGs.
- `resources/js/__tests__/page-head.test.ts`: `usePage` com `seo.meta`, sem
  props e sem meta nenhuma.
- `home.test.ts` e `tool-page.test.ts`: mock de `seo.meta`, assert do titulo e
  zero `<meta>` escritas pelo componente.

Gates:

```
composer test   -> pint ok, phpstan 0 erros, pest 273 testes / 7313 asserts
npm run test    -> 47 arquivos, 663 testes
npm run types:check -> sem erros
```

## 5. Verificacao das imagens

`php artisan seo:og-image` escreveu os 8 PNGs em 1200x1200 (`getimagesize`:
`image/png`). Conferencia visual:

- padrao: wordmark `devtools_` no baseline 552 e a lista de tools em
  648/700/752 - dentro da faixa central (286..914);
- tool: wordmark pequeno no alto da faixa (baseline 354), nome no 552 e os
  `keywords` em 648/700 - tambem dentro da faixa.

## 6. Depois do deploy (fora desta tarefa)

- **Commitar os PNGs** junto com o codigo: em producao o comando nunca roda.
- O `?v=2` forca o recache da **imagem**; o preview da **pagina** pode
  continuar em cache por URL no WhatsApp. Para um scrape novo, usar o Facebook
  Sharing Debugger ou colar uma query temporaria na URL da pagina.
- Conferir um card real em `/pt-br/tools/json-viewer` (titulo, descricao e
  imagem quadrada).

## 7. Observacoes

- A navegacao SPA atualiza o `<title>` pelas shared props; `description` e
  Open Graph ficam os do carregamento inicial - correto para crawler, que
  busca a URL diretamente.
- `APP_NAME` (servidor) e `VITE_APP_NAME` (cliente) precisam continuar em
  sincronia: o sufixo do `<title>` sai dos dois.
- Nenhum commit ou push foi feito; as mudancas e os PNGs ficam para o usuario
  revisar e comitar.
