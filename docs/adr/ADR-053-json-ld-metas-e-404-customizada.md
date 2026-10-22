# ADR-053: JSON-LD, metas sociais finais e 404 customizada

Status: Aceito
Data: 2026-10-22
Relacionados: ADR-013 (medicao e consentimento), ADR-019 (SEO, sitemap e
hreflang), ADR-029 (pagina generica de tool), ADR-030 (imagem OG), ADR-033
(card quadrado), ADR-052 (URLs em HTTPS)

## Contexto

A F7 e a ultima do SPEC e o SEO ficou para ela. Nesta sessao o site tinha:

- `noindex` cobrindo as telas de acesso, mas **nao** as de configuracoes, o
  desafio 2FA e a pagina de tokens de API;
- nenhum dado estruturado: buscador so tinha titulo, descricao, canonical e
  hreflang;
- `twitter:card`, mas sem `twitter:title`, `twitter:description`,
  `twitter:image` e `twitter:image:alt` explicitos;
- nenhuma pagina 404 propria: a resposta padrao nao tinha layout, nem idioma,
  nem `noindex`;
- canonical, OG e sitemap poderiam sair em `http`/host errado atras do proxy
  (resolvido no ADR-052).

Era preciso escolher o que declarar em JSON-LD sem prometer o que o site nao
tem. As alternativas faceis - `FAQPage`, `Offer` e `lastmod` - exigiriam
conteudo ou datas que nao existem, e dados inventados em dado estruturado sao
risco de penalidade, nao ganho.

## Decisao

### 1. JSON-LD do servidor, so em pagina indexavel

`StructuredData::forRequest()` emite blocos gerados no Blade, com
`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT |
JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, um `<script
type="application/ld+json">` por entidade, com o nonce da CSP:

| Pagina | Tipos |
| --- | --- |
| Home | `Organization` (nome, URL, logo) e `WebSite` (nome, URL, `inLanguage`) |
| `tools.show` | `SoftwareApplication` (nome, descricao, `applicationCategory: DeveloperApplication`, `operatingSystem: Any`, `isAccessibleForFree: true`, URL, `inLanguage`) e `BreadcrumbList` (Inicio -> ferramenta) |
| Demais | Nada |

`Seo::noindex($request)` e a unica porta: pagina noindex (settings, 2FA,
tokens, autenticacao, status de doacao, 404) nao emite JSON-LD, nem OG, nem
`hreflang`. A home usa a URL do idioma da pagina, que e o canonical dela.

### 2. Metas sociais completas

`app.blade.php` emite `twitter:card=summary_large_image` com
`twitter:title`, `twitter:description`, `twitter:image` e
`twitter:image:alt`, alem de `og:*`. Em pagina noindex o card inteiro nao sai:
nao ha o que compartilhar numa tela privada.

### 3. 404 customizada com o componente `Error`

- Rotas inexistentes e `abort(404)` de controller chegam ao componente
  `Error` do Inertia, com `status`, layout publico, textos do namespace
  `errors` nos cinco idiomas e CTA para a home.
- O Blade forca `noindex` quando o componente e `Error`, entao a 404 nunca
  entra em indice, sitemap ou card.
- A API continua respondendo JSON; o handler so monta a pagina quando o
  cliente pede HTML.

### 4. O que deliberadamente nao entrou

- **`lastmod` no sitemap**: nao ha data confiavel por pagina. A data de build
  ou do git mudaria a cada deploy sem o conteudo mudar.
- **`FAQPage`**: o site nao tem FAQ propria; marcar perguntas comercialmente
  repetidas como FAQ e o tipo de dado estruturado que o buscador ignora ou
  penaliza.
- **`Offer`/`AggregateRating`**: planos sao assinatura, o preco vem de tabela e
  nao ha avaliacao de usuarios; declarar oferta ou nota seria inventar dado.
- **JSON-LD no cliente**: robo de busca nao executa JavaScript; o bloco sai do
  servidor como o resto das metas.

## Consequencias

### Positivas

- Home e tools passam a ter identidade de entidade e trilha de navegacao para
  o buscador, sem duplicar titulo/descricao.
- A regra de noindex ficou num lugar so (`Seo`), e o Blade a reaproveita para
  meta, card e JSON-LD - impossivel emitir dado estruturado numa tela privada.
- A 404 fala o idioma da URL, tem layout e nao vira pagina indexavel.
- Nada de dado inventado: o contrato do JSON-LD so declara o que existe.

### Negativas / trade-offs

- `StructuredData` repete nome/descricao da tool a partir do i18n; mudar o
  registro exige conferir os campos declarados.
- JSON-LD nao garante rich result: e sinal, nao promessa.
- Sem `lastmod`, o sitemap nao comunica mudanca; o buscador recrawleia pelo
  proprio ritmo.
- A 404 depende do componente `Error` e do namespace `errors`; apagar qualquer
  um dos dois quebra o fluxo de erro (coberto por teste).

## Alternativas descartadas

- **JSON-LD montado no Vue**: invisivel para quem nao executa JavaScript.
- **`FAQPage`**: sem FAQ real e sem ganho, com risco de spam.
- **`Offer` nos planos**: preco e condicao mudam por tabela; declarar oferta
  estatica ficaria desatualizado e enganoso.
- **`lastmod` de git/build**: data falsa em todo deploy.
- **Manter a 404 padrao**: sem idioma, sem layout e sem controle de indexacao.
- **`noindex` so nas telas de acesso**: deixaria configuracoes, 2FA e tokens
  no indice.
