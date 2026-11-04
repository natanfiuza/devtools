# ADR-082: Documentação pública do BadgeForge e i18n

Status: Aceito
Data: 2026-11-03
Relacionados: ADR-002 (processamento local), ADR-057 (documentação pública da API), ADR-068 (acesso a providers), ADR-071 (documentação pública), ADR-080 (camada de providers), ADR-081 (badges pessoais e PAT)

## Contexto

Até a F3, o BadgeForge só se explicava na própria tool e nos tutoriais de
token em `docs/tutorial/`. A F4 introduz três assuntos que o README de quem
integra precisa entender sem abrir o builder:

1. **URLs de provider** — quatro providers, nove métricas e placeholders de
   path diferentes (`owner/repo`, `package`, `vendor/package`,
   `namespace/repository`).
2. **Badge pessoal e PAT** — quem pode criar, o que é público, o que fica
   cifrado e como revogar.
3. **Cache e falha** — TTL, `ETag`, stale, fallback e o que o leitor deve
   esperar quando o provider cai.

O SPEC §14 já exigia `/docs/badgeforge` pública e “página por provider a
partir da F4”, mas não dizia como a página se relaciona com o catálogo do
banco, com os cinco idiomas, com o SEO/sitemap nem com o conteúdo da imagem.

A pergunta de idioma também precisava ser fechada: o label e a message de um
badge de provider vêm do provider (`stars`, `v`, `pulls`) e do número. Traduzir
o conteúdo da imagem por idioma exigiria uma variante de URL por idioma e
multiplicaria o cache — e o README de quem usa não muda de idioma junto.

## Decisão

### 1. Página pública em `/{locale}/docs/badgeforge`

- Rota no grupo de idioma de `routes/web.php`, logo depois de `api-docs`:
  `GET {locale}/docs/badgeforge` → `BadgeForgeDocsController`, nome
  `badgeforge-docs`, componente `BadgeForgeDocs`.
- Entra em `resolveLayout()` como página pública, no `useRoutes()`
  (`badgeforgeDocs`) e no rodapé (`app.footer.badgeforge_docs`).
- SEO: `Seo::pageMeta()` ganha o caso `'badgeforge-docs'` lendo
  `docs.badgeforge.meta.title|description`, no servidor (o robô de preview não
  executa JS); `Sitemap::PAGES` ganha a página; `hreflang` sai do grupo
  genérico de cinco idiomas. `Seo::noindex()` não a lista — é indexável.

### 2. O catálogo é a fonte; a página não repete a tabela

As props são públicas e não incluem credencial nem asset de conta:
`providers` (o mesmo `ProviderCatalog::forDocs()` que alimenta o builder),
`staticEndpoint`, `providerEndpoint`, `personalEndpoint`, `cacheTtl` e
`origin`. A tabela de cada provider sai do banco; os exemplos copiáveis são
montados no cliente a partir de `examplePath` e dos endpoints. Adicionar uma
definição no catálogo passa a aparecer na documentação sem editar o Vue.

Os nomes de métrica são chaves (`tools.badgeforge.provider.metrics.<provider>_<slug>`),
traduzidas; o `name` do banco é só fallback. Placeholders de parâmetro também
vêm de i18n (`tools.badgeforge.provider.params.<key>`), nunca do banco.

### 3. Namespace `docs` nos cinco idiomas

`docs` entra em `config/locales.php` (`namespaces`), com
`lang/{en,pt_BR,pt_PT,fr,es}/docs.php`. A paridade de traduções
(`TranslationsParityTest` e `i18n-messages.test.ts`) passa a cobri-lo
automaticamente. Toda string visível da página é i18n; o conteúdo vem em
`docs.badgeforge.{meta,hero,endpoints,quick_start,static,providers,personal,cache,errors,security}`.

### 4. Exceção documentada: conteúdo da imagem é neutro de idioma

O label (`stars`, `forks`, `v`, `pulls`...) e a mensagem (`1.2k`, `v3.4.5`,
`unavailable`) da imagem **não** são traduzidos por idioma da requisição. A
imagem é um recurso público e cacheável por URL; uma variante por idioma
multiplicaria cache, ETag e analytics sem servir ao caso de uso (README é do
projeto, não do visitante). A interface e a documentação são traduzidas; a
imagem não. Esta é a exceção explícita à regra “toda string visível via i18n”,
limitada ao conteúdo da imagem.

### 5. Conteúdo mínimo da página

- Quick start com a URL estática e o snippet Markdown.
- Seção por provider com métrica, path de exemplo, acesso público/privado,
  descrição e link para a documentação oficial.
- Badge pessoal: formato da URL, quem cria, PAT por provider (GitHub, npm,
  Docker com username; Packagist e npm `dm` sempre públicos), segurança e
  refresh por POST.
- Cache: TTL interpolado de `cacheTtl`, stale, `?refresh` ignorado na URL
  pública.
- Erros: o que é 404, o que vira fallback 200 e por que a imagem nunca 500.
- Segurança: anonimato do item público, cifra do PAT, ausência em log/URL.

Os tutoriais de `docs/tutorial/{github,npm,docker}-token.md` apontam para a
página e para o fluxo real (criar badge privada, informar PAT, excluir a badge
para revogar — não existe “remover credencial” sem recriar).

## Consequências

### Positivas

- Quem integra encontra a URL certa sem abrir o builder, nos cinco idiomas.
- O catálogo do banco e a documentação não divergem: a tabela é gerada do
  mesmo `forDocs()` usado pela tool.
- A página é indexável, com meta de servidor e sitemap, como as demais
  páginas de conteúdo (ADR-057).
- A exceção de idioma da imagem é explícita, e o teste não tenta “consertar”
  o que é decisão.

### Negativas / Trade-offs

- A documentação depende do catálogo: se o banco não tiver a migração do
  seeder, a página mostra a lista vazia (não 500) — `ProviderCatalog` captura
  `QueryException`, o mesmo cuidado da tool durante o deploy.
- Uma definição nova exige chave de tradução de métrica nos cinco idiomas;
  sem ela, a UI cai no `name` do banco (fallback), nunca em chave crua.
- A página não tem versão em markdown nem API de docs; é uma página Inertia
  como as outras.

## Alternativas descartadas

- **Gerar a documentação de provider só no cliente, sem catálogo**: duplicaria
  as 9 definições em JSON/TS e divergiria da tool. O catálogo é a fonte.
- **Traduzir label/message da imagem por idioma**: multiplica cache/ETag e não
  serve ao README; recusado e documentado como exceção.
- **Página fora do prefixo de idioma (`/docs/badgeforge`)**: quebraria a regra
  de SEO/hreflang do site (SPEC §3) e a navegação por idioma.
- **Documentação só em inglês**: contraria a regra dos cinco idiomas do
  `CLAUDE.md` e a paridade já cobrada.
