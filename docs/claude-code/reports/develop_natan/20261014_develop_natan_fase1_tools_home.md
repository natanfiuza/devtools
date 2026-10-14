# Relatorio - F1: registro de tools, home real, busca Cmd+K e consentimento LGPD/GA

Branch: `develop_natan`
Data: 2026-10-14
Tarefa: "De continuidade as proximas fases do projeto" (F1 da secao 9 do SPEC)
Plano: `docs/plans/develop_natan/20261014_fase1_tools_home_plansfacts.md`
Aceite: `docs/acceptance/FASE-1.md`

## 1. O que foi entregue

A F1 da secao 9 do SPEC — registro de tools, home real, busca Cmd+K, banner
LGPD e GA — mais as duas heranças que a F0 tinha deixado explicitamente para
ca: **sitemap/robots** e **imagens OG**. Oito blocos, todos concluidos.

| # | Bloco | Estado |
|---|---|---|
| 0 | `docs/acceptance/FASE-1.md` com os criterios da fase | concluido |
| 1 | Registro de tools: 8 manifestos JSON, `registry.ts`, `categories.ts`, `icons.ts`, `App\Support\Tools`, `lang/*/tools.php` | concluido |
| 2 | Busca Cmd+K: `CommandPalette`, `useCommandPalette`, `SearchTrigger` real, i18n `app.search` | concluido |
| 3 | Home real: `HomeController`, `ToolCard`/`ToolGrid`/`PricingSection`, chips, destaque, precos da tabela | concluido |
| 4 | Consentimento LGPD + GA4: `config/analytics.php`, `Consent`/`Analytics`, `HandleConsent`, banner, tag no cliente | concluido |
| 5 | Meta/OG: `Seo::openGraph`, `app.blade.php`, `PageHead`, comando `seo:og-image` e `public/og-default.png` | concluido |
| 6 | Sitemap e robots servidos pela aplicacao; `public/robots.txt` apagado | concluido |
| 7 | Gates, smoke com SSR, docs (SPEC, ADR-007, ADR-013, DESIGN, CHANGELOG), aceite e relatorio | concluido |

As 8 tools do MVP entram **registradas com `status: "soon"`** — nenhuma funciona
ainda. Isso e deliberado: o grid, os chips, a busca, o sitemap, as metas e os
testes ja sao definitivos, e a F2/F3 so troca o status de cada manifesto.

## 2. Decisoes novas (ADRs)

Nao entrou ADR novo: as duas decisoes da fase sao **revisoes** de ADRs que
existiam como esboco de duas linhas.

- **ADR-007 - registro de tools (revisado)**: a fonte do registro e o
  `manifest.json` de cada tool, com **dois leitores sobre os mesmos arquivos** —
  o TypeScript (`import.meta.glob`) monta home, chips e busca, e o PHP
  (`App\Support\Tools`) monta o sitemap. O manifesto guarda so identidade e
  ciclo de vida: nenhum texto visivel mora nele (vem de `tools.<slug>.*`, por
  convencao a partir do slug). `status` e o que separa o que existe do que ainda
  nao existe: `available` navega e entra no sitemap, `soon` aparece com selo e
  nao navega, `hidden` nao aparece. Alternativas descartadas: `manifest.ts` (o
  PHP nao le), registro num arquivo unico (a tool deixa de ser um modulo), texto
  visivel no manifesto (traducao presa no build).
- **ADR-013 - consentimento (revisado)**: a decisao tem **tres estados**
  resolvidos no **servidor** — sem cookie (ou com cookie corrompido) a decisao e
  nula, o banner aparece e **nenhuma** tag do Google e emitida. O id vem de
  `config/analytics.php` e vazio desliga a medicao; a tag entra so no cliente,
  uma vez, com `consent default denied` antes. A parte de exclusao e exportacao
  de conta saiu deste ADR (e do ADR-028). Alternativa descartada que vale
  registrar: **carregar o GA sempre com Consent Mode `denied`** — e o modo
  recomendado pelo Google, mas ainda manda requisicao, e recusar tem que
  significar nenhuma requisicao.

Decisoes menores, tomadas na execucao e registradas na SPEC secao 11.1:
`robots.txt`/`sitemap.xml` servidos pela aplicacao (com o arquivo estatico
removido), guia de estilo fora do indice, e a imagem OG padrao **comitada**,
gerada por um comando que nunca roda em producao.

## 3. Banco de dados

**Nenhuma migration nesta fase.** O deploy da F1 e so codigo + um PNG — o que
importa para o cron do `deploy.sh`, que roda sem downtime: nao ha alteracao de
schema para o `migrate --force` aplicar. Os precos da home saem da tabela
`plans`, que ja existe desde 2026-10-14.

## 4. Rotas

**Novas** (fora do prefixo de idioma, porque sao do site inteiro):
`GET /sitemap.xml` (`sitemap`) e `GET /robots.txt` (`robots`).

**Inalteradas**: as paginas publicas sob `/{locale}` (`home`, `privacy`,
`terms`, `styleguide`), a area autenticada sem prefixo e as rotas de maquina. A
rota `/{locale}/tools/{slug}` **nao** entrou: ela nasce na F2, junto com a
primeira tool `available` — em F1 nao existe link para ela em lugar nenhum. O
`Sitemap::urls()` pergunta por `Route::has('tools.show')` antes de montar essas
URLs, entao o arquivo sai correto hoje e no dia em que a rota existir.

## 5. Telas e componentes

- **Home** (`Home.vue` + `HomeController`): hero com titulo, subtitulo e campo de
  busca; chips das categorias que tem tool; grid de 3 colunas com as 8 tools
  (`ToolCard`: icone 20px, nome, uma linha, selo "Em breve" e **sem link**);
  destaque do AI SQL Builder com preview; `PricingSection` lendo `plans`.
- **Palette** (`CommandPalette.vue` + `useCommandPalette.ts`): construida sobre
  `Dialog` e `Input` do reka-ui ja vendorizado — **nenhuma dependencia nova**,
  porque nao existe primitivo `command` no projeto. Abre por Cmd/Ctrl+K e por
  `/` fora de campo de texto; `role="listbox"/"option"` com
  `aria-activedescendant` e foco preso; setas, Enter, Esc; tool `soon` aparece
  com selo e nao navega.
- **Consentimento** (`ConsentBanner.vue`, `AnalyticsScript.vue`,
  `useConsent.ts`, `lib/analytics.ts`): faixa no canto inferior que nao bloqueia
  a pagina, com Necessarios (fixa) e Medicao (interruptor), nos dois layouts
  publicos. Reabre pelo botao "Cookies" no rodape.
- **`PageHead.vue`** centraliza titulo e descricao das paginas indexaveis
  (`Home`, `Privacy`, `Terms`), para as cinco URLs nao repetirem a mesma
  duplicacao de metas.

## 6. O registro de tools, em numeros

8 manifestos (`json-viewer`, `ai-sql-builder`, `json-to-code`, `sql-formatter`,
`base64-url-hash`, `jwt-decoder`, `timestamp-uuid`, `regex-tester`), 6
categorias (`json`, `sql`, `encode`, `security`, `time`, `text`), 41 chaves de
traducao em cada um dos 5 idiomas em `lang/*/tools.php`, e 30 chaves em cada
`home.php` reescrito. O `ToolsRegistryTest` cobra campos obrigatorios, slug
unico, icone existente no mapa e o que acontece com um manifesto quebrado (ha
uma fixture `sem-slug` para isso).

## 7. Bugs e descobertas

1. **`public/robots.txt` vencia a rota** (comportamento, nao bug de codigo):
   arquivo estatico tem precedencia sobre rota, no nginx e no `artisan serve`.
   Com ele no lugar, a aplicacao nunca serviria o `robots.txt` e a linha do
   `Sitemap:` ficaria presa a um ambiente. O arquivo saiu do repositorio e
   `RobotsTest` falha se ele voltar.
2. **A imagem OG saiu com chaves de traducao cruas** (`tools.items.json-viewer.name`
   no PNG): a convencao e `tools.<slug>.name`, derivada do slug. Corrigido em
   `GenerateOgImage::toolNames()`.
3. **SimpleXML: `$link['hreflang']` devolve string vazia** para filhos obtidos
   por `children($ns)`. O teste do sitemap lia as alternates como `''` e
   passava a comparar nada com nada; passou a usar `attributes()`, que e o
   caminho correto.
4. **Rota registrada no meio do teste nao existe para `Route::has()`**: o indice
   de nomes so e refeito quando o framework sobe. O teste da tool `available`
   precisou de `Route::getRoutes()->refreshNameLookups()` — e o mesmo detalhe
   que faria o sitemap ignorar a rota em producao se ela fosse registrada fora
   do boot normal (nao e o caso).
5. **PHPStan nivel 7 contra a GD**: `imagecreatetruecolor` exige `int<1, max>` e
   `imagecolorallocate` exige `int<0, 255>`. Resolvido com uma guarda explicita
   de dimensoes positivas (com mensagem de erro de verdade) e com os tipos nos
   `@param`, sem cast solto, sem baseline e sem ignore.
6. **Pint (`integer_literal_case`) maiusculiza hexadecimal** (`0x0b` -> `0x0B`) e
   `imagedestroy()` esta deprecado no PHP 8.5 (os objetos da GD sao coletados
   sozinhos): os dois ajustes entraram no comando da imagem.

Descoberta que vale para o proximo que mexer no `<Head>`: o `addTitleElement` do
Inertia **nao** adiciona a prop `title` quando ja existe um `<title>` no slot,
entao passar `:title` e metas no slot e seguro e nao duplica o elemento. E o
`og:locale` quer `idioma_TERRITORIO`, entao `pt-BR` tem que virar `pt_BR` — a
conversao sai de `Locales::html()`, sem tabela nova.

## 8. Desvios do plano (conscientes)

- **6 categorias, e nao as 4 do plano** (JSON, SQL, Texto, Dados): as 8 tools do
  MVP nao cabem em 4 sem "Dados" virar saco de tudo. O catalogo ficou em
  `json`, `sql`, `encode`, `security`, `time`, `text`, com os chips mostrando
  **so** as categorias que tem tool.
- **`order` e nao `sort`** no manifesto, e o `keywords` do manifesto guarda so o
  termo tecnico que nao se traduz (`jsonpath`, `jwt`): o que a pessoa le —
  inclusive as palavras-chave de busca — vem do i18n, nos 5 idiomas.
- **Titulo e descricao das paginas nao sao montados no servidor**: saem do
  `<Head>` do Inertia (`PageHead.vue`), como o `<title>` ja saia. Com SSR os
  tres aparecem no HTML do servidor (conferido no smoke); **sem** SSR, os
  `og:*` por pagina entram pelo cliente. Foi aceito em troca de uma fonte unica
  para as cinco URLs.
- **Sem `lastmod` no sitemap**: nao ha data honesta para a home. Adicionar
  depois nao quebra nada.
- **A imagem OG e neutra de idioma** (wordmark + os 8 nomes de produto, que sao
  ingleses nos 5 idiomas) porque o site tem 5 idiomas e a imagem e uma; o
  `og:image:alt` e que e traduzido por pagina, com a marca e a frase do site.
- **`config/legal.php` teve a data de revisao atualizada** (`2026-10-13` ->
  `2026-10-14`): o texto da secao de cookies mudou nos 5 idiomas porque agora
  existe medicao de verdade para descrever.
- **`Format.php` foi tocado** (formatacao dos precos no servidor) e o linter do
  gitpr lembrou dos DocBlocks: os dois estao atualizados.

## 9. Verificacao executada

| Comando | Resultado |
|---|---|
| `composer test` (config:clear + Pint + PHPStan nivel 7 + Pest) | Pint limpo, **PHPStan 0 erros**, Pest **256 testes / 6.011 assercoes** |
| `npm run test` (Vitest 5) | **154 testes em 12 arquivos** |
| `npm run types:check` (wayfinder + `vue-tsc --noEmit`) | limpo, TS estrito |
| `npm run build` | exit 0 |
| `npm run build:ssr` | exit 0 |

Contra o fim da F0 (220 testes / 4.660 assercoes e 105 testes em 7 arquivos):
**+36 testes de Pest e +49 de Vitest, em 5 arquivos novos**. Os novos:
`ToolsRegistryTest`, `HomeTest`, `ConsentTest`, `SeoMetaTest`, `SitemapTest` e
`RobotsTest` (Pest); `tools-registry.test.ts`, `command-palette.test.ts`,
`home.test.ts`, `consent-banner.test.ts` e `page-head.test.ts` (Vitest).

## 10. Smoke de SSR

Banco SQLite descartavel em diretorio temporario (removido depois),
`php artisan migrate --force`, `php artisan serve` com `INERTIA_SSR_ENABLED=true`
e `node bootstrap/ssr/ssr.js` no 13714. **Zero erro no log do SSR.**

| URL | HTTP | SSR | Tamanho |
|---|---|---|---|
| `/en` | 200 | sim | 81.6 KB |
| `/pt-br` | 200 | sim | 82.7 KB |
| `/pt-pt` | 200 | sim | 83.5 KB |
| `/fr` | 200 | sim | 85.4 KB |
| `/es` | 200 | sim | 83.3 KB |
| `/pt-br/styleguide` | 200 | sim | 77.7 KB |
| `/pt-br/privacy` | 200 | sim | 58.9 KB |
| `/pt-br/login` | 200 | sim | 53.9 KB |
| `/sitemap.xml` | 200 | — | 9.6 KB (`application/xml; charset=UTF-8`) |
| `/robots.txt` | 200 | — | 131 B (`text/plain; charset=UTF-8`) |

Conferido **no HTML do servidor** (nao no DOM do navegador): os 8 nomes de tool
e os 6 chips nas 5 URLs; os precos da tabela formatados no idioma da pagina
(`R$ 29,90` no pt-BR, com o espaco inquebravel da formatacao de moeda);
`og:locale`/`og:locale:alternate`/`og:url` iguais ao canonical, com o proprio
idioma nunca na lista de alternates; `og:title`/`og:description` traduzidos por
pagina (so existem com SSR); `noindex` e **sem** hreflang no styleguide e na
tela de acesso; as 4 linhas de `Disallow` e a linha do `Sitemap:` no robots; 15
URLs no sitemap (3 paginas x 5 idiomas), cada uma com as 5 alternates e o
`x-default`; e **nenhuma** ocorrencia de `googletagmanager`/`gtag(` em pagina
nenhuma.

## 11. Nao verificado daqui

- **GA4 com id real**: sem `ANALYTICS_GA4_ID` a medicao fica desligada de
  proposito, entao o que da para provar daqui e o que os testes provam (nenhuma
  tag sem aceite; tag uma unica vez com aceite e id; `consent default denied`
  antes de qualquer tag). O id real entra no `.env` e a conferencia e no devtools
  do navegador — esta na lista de conferencia manual do aceite.
- **Visual e teclado de verdade**: a palette, o banner e a home foram exercitados
  em jsdom e no HTML do SSR; abrir a palette com o teclado, ver o banner nos
  dois temas e conferir o espacamento da home e da revisao do usuario.
- **Cartao social de verdade**: o PNG existe, tem mime e dimensoes conferidos por
  teste, mas nenhum crawler leu a pagina ainda.
- **SSR em producao**: depende do processo do supervisor no VPS (RUNBOOK); aqui o
  bundle foi exercitado local.

## 12. Carry-overs

- **F2**: rota `/{locale}/tools/{slug}` e a pagina da tool (CodeMirror 6), a
  primeira tool `available` (JSON Viewer) e a **imagem OG por tool**.
- **F3**: as tools simples 3 a 8 do MVP.
- **F5/F6/F7**: como ja estava na SPEC (AI SQL Builder, gateway/doacao,
  historico e SEO final).
- **Aberto (SPEC 11.2)**: mensagens de validacao nos 5 idiomas e o `--elevated`
  como cor de pontuacao do JSON no DESIGN.
- **Baseline**: o eslint continua fora de controle, como registrado na rodada
  anterior.

## 13. Docs atualizados

`docs/SPEC.md` (secoes 3, 4, 7, 9 e 11.1), `ADR-007` e `ADR-013` reescritos no
formato dos ADRs recentes, `docs/design/DESIGN.md` (home com mockup, palette,
banner, imagem OG e a nota de sitemap/robots), `CHANGELOG.md` (secao da F1),
`docs/acceptance/FASE-1.md` (tabela preenchida) e o plano em
`docs/plans/develop_natan/`.

**Nenhum commit foi feito** (regra do `CLAUDE.md`): as mudancas ficam na arvore
de trabalho, para o usuario revisar e commitar pelo gitpr.
