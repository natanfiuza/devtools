# Criterios de aceite — FASE 1 (Registro de tools, home, busca, LGPD/GA)

Gerado em 2026-10-14, no inicio da fase (regra do `docs/SPEC.md` secao 9).
Fonte: `docs/SPEC.md` secao 9 (F1) + `docs/design/DESIGN.md` (home, componentes,
consentimento) + decisoes do plano de execucao da sessao.

## Escopo da fase

Registro de tools (fonte unica em JSON, lida pelo TypeScript e pelo PHP), home
real (hero, chips de categoria, grid de cards, destaque do AI SQL Builder e
secao de precos vinda da tabela `plans`), busca Cmd+K, banner de consentimento
LGPD com GA4 carregado so apos o aceite, sitemap/robots servidos pela aplicacao
e tags OG com uma imagem padrao do site.

As 8 tools do MVP entram **registradas** com `status: "soon"` (nenhuma funciona
ainda): o grid, a busca, o sitemap e os testes ja sao reais, e cada fase
seguinte so troca o status.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | As 8 tools do MVP estao registradas e o grid da home mostra todas com o selo "em breve" e **sem** link | `HomeTest` + `home.test.ts` |
| 2 | Registro e traducao batem: todo slug tem `name`/`description`/`keywords` nos 5 idiomas, toda categoria tem rotulo e o icone de cada manifest existe no mapa | `ToolsRegistryTest` + `tools-registry.test.ts` |
| 3 | A busca Cmd+K abre pelo teclado, filtra por nome/categoria/palavras-chave e **so navega** para tool disponivel; acessivel (combobox/listbox, `aria-activedescendant`) | `command-palette.test.ts` |
| 4 | A home mostra hero, chips de categoria, grid, destaque do AI SQL Builder e a secao de precos com os 3 planos vindos da **tabela**, sem preco no codigo | `HomeTest` + `home.test.ts` |
| 5 | O banner de consentimento aparece para quem ainda nao decidiu e a decisao vem resolvida do **servidor** (sem piscar) | `ConsentTest` |
| 6 | Sem aceite, **nenhuma** tag do Google e emitida; com aceite e id configurado, a tag entra e o `consent default denied` vem antes de qualquer tag | `ConsentTest` |
| 7 | Dá para reabrir o banner pelo rodape depois de decidir, e recusar nao carrega nada | `consent-banner.test.ts` |
| 8 | `/sitemap.xml` responde com home, Privacidade e Termos nos 5 idiomas, com alternates e `x-default`, sem paginas `noindex` e sem tool "soon" | `SitemapTest` |
| 9 | `/robots.txt` e servido pela aplicacao (`Disallow` das areas privadas + `Sitemap:`) e o `public/robots.txt` estatico nao existe mais | `RobotsTest` |
| 10 | Paginas publicas indexaveis emitem `og:*`/`twitter:*` (com a imagem padrao) e as `noindex` nao emitem | `SeoMetaTest` |
| 11 | `styleguide` sai do indice (`noindex` e fora do sitemap) | `SeoMetaTest` + `SitemapTest` |
| 12 | Zero string hardcoded nas telas novas (5 idiomas, paridade verde) | `TranslationsParityTest` + testes de componente com traducao marcadora |
| 13 | Gates: `composer test`, `npm run test`, `npm run types:check`, `npm run build`, `npm run build:ssr` | os cinco comandos |
| 14 | Smoke com SSR ligado das telas novas (home nos 5 idiomas, sitemap, robots, `noindex`) | smoke manual registrado no relatorio |
| 15 | Nenhum commit feito pelo assistente | `git log` |

## Fora do escopo da F1

- A rota `/{locale}/tools/{slug}` e a pagina da tool entram na F2, junto com a
  primeira tool disponivel (nada e `available` nesta fase).
- A geracao de imagem OG por tool (DESIGN, entregavel 5) fica para a F2/F3.
- AI SQL Builder, cache, fila e consumo da cota (F5); gateway e doacao (F6);
  historico real, SEO final e hardening (F7).
- Nome traduzivel de plano: `plans.name` e coluna de dado e aparece igual nos 5
  idiomas (traduzir exigiria colunas novas).

## Resultado (a preencher no fim da fase)

Preenchido em 2026-10-14, ao fim da fase.

| # | Criterio (do plano) | Resultado |
|---|---|---|
| 1 | As 8 tools registradas, com selo "em breve" e sem link | OK — `HomeTest` (8 cards, `status: soon`, sem `href`) + `home.test.ts` |
| 2 | Registro e traducao batem (nome/descricao/keywords nos 5 idiomas, categoria com rotulo, icone no mapa) | OK — `ToolsRegistryTest` (8 testes: campos obrigatorios, slug unico, icone existente, fixture com manifesto quebrado) + `tools-registry.test.ts` |
| 3 | Busca Cmd+K abre pelo teclado, filtra e so navega para tool disponivel | OK — `command-palette.test.ts` (atalho, `/` fora de campo, filtro por nome/categoria/keywords, setas/Enter/Esc, `aria-activedescendant`, `soon` nao navega) |
| 4 | Home com hero, chips, grid, destaque e precos da **tabela** | OK — `HomeTest` (3 planos do catalogo, preco formatado por idioma, contato por e-mail, 5 idiomas) + `home.test.ts` (traducao marcadora, chips so das categorias com tool) |
| 5 | Banner aparece para quem nao decidiu e a decisao vem do servidor | OK — `ConsentTest` (prop nula sem cookie, `granted`/`denied` com cookie, cookie corrompido volta a nula e e apagado) |
| 6 | Sem aceite nenhuma tag do Google e emitida; com aceite e id, a tag entra com `consent default denied` antes | OK — `ConsentTest` (nenhuma ocorrencia de `googletagmanager`/`gtag` no HTML em nenhum dos estados; `ga4Id` so com id configurado) + `consent-banner.test.ts` |
| 7 | Reabrir pelo rodape depois de decidir; recusar nao carrega nada | OK — `consent-banner.test.ts` (aceitar grava o cookie e injeta a tag **uma vez**, recusar nao injeta, Necessarios nao desliga, reabrir pelo botao do rodape) |
| 8 | `/sitemap.xml` com as paginas de conteudo nos 5 idiomas, alternates e `x-default`, sem `noindex` e sem tool "soon" | OK — `SitemapTest` (15 URLs, alternates completas por bloco, exclusoes, fixture `available` entra e `soon` nao) |
| 9 | `/robots.txt` servido pela aplicacao e `public/robots.txt` fora do repositorio | OK — `RobotsTest` (text/plain, as 4 linhas de `Disallow`, a linha do `Sitemap:`, e o teste que falha se o arquivo estatico voltar) |
| 10 | Paginas indexaveis emitem `og:*`/`twitter:*` e as `noindex` nao emitem | OK — `SeoMetaTest` (tipo, site_name, locale + alternates, url = canonical, imagem com dimensoes e alt traduzido, `twitter:card`) + `page-head.test.ts` |
| 11 | `styleguide` fora do indice | OK — `LocaleRoutingTest` (noindex + sem hreflang) + `SitemapTest` (fora do arquivo) |
| 12 | Zero string hardcoded nas telas novas | OK — `TranslationsParityTest` verde nos 5 idiomas; `home.test.ts`, `command-palette.test.ts` e `consent-banner.test.ts` usam traducao marcadora |
| 13 | Gates: `composer test`, `npm run test`, `npm run types:check`, `npm run build`, `npm run build:ssr` | OK — 256 testes e 6.011 assercoes (Pint + PHPStan nivel 7 sem erro + Pest 4); 154 testes em 12 arquivos (Vitest 5); `vue-tsc` limpo; os dois builds sem erro |
| 14 | Smoke com SSR ligado | OK — tabela abaixo |
| 15 | Nenhum commit feito pelo assistente | OK — as mudancas ficam na arvore, para o usuario commitar |

Smoke de SSR: banco SQLite descartavel em diretorio temporario (removido depois),
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
| `/sitemap.xml` | 200 | — | 9.6 KB (`application/xml`) |
| `/robots.txt` | 200 | — | 131 B (`text/plain`) |

O que foi conferido no HTML **do servidor** (nao no do navegador): os 8 nomes de
tool e os 6 chips de categoria nas 5 URLs; os precos da tabela formatados no
idioma da pagina (`R$ 29,90` no pt-BR, com o espaco inquebravel que a
formatacao de moeda usa); `og:locale`/`og:locale:alternate`/`og:url` iguais ao
canonical, com o proprio idioma nunca na lista de alternates; `og:title` e
`og:description` traduzidos por pagina (so aparecem com SSR); `noindex` + sem
hreflang no styleguide e na tela de acesso; as 4 linhas de `Disallow` e a linha
do `Sitemap:` no robots; e **nenhuma** ocorrencia de `googletagmanager`/`gtag(`
em pagina nenhuma.

Conferencia manual pendente (usuario): abrir a palette no teclado, aceitar e
recusar no banner e ver a tag do GA entrar e sair do `<head>` no devtools com um
id real - em teste e em desenvolvimento nao ha id, entao a medicao fica desligada
de proposito.

## Revisao independente (2026-10-14)

Auditoria pedida depois da fase fechada, para **conferir** o auto-relato acima em
vez de reassumi-lo: quatro leituras independentes dos criterios nas fontes, os
cinco gates executados de verdade (com o exit code capturado, nao com o do
`tail` do pipe) e o smoke de SSR refeito do zero, com banco descartavel. Nada
acima foi reescrito - a tabela original fica como registro do que foi aceito na
F1; o que segue e o que a revisao encontrou.

Os criterios 3, 4, 5, 7, 9, 11 e 15 se confirmaram como estavam. O **6** e o
**13** precisaram de correcao; o **14** ganhou evidencia nova; o **12** tinha uma
excecao; o **1** e parte do **10** foram **superados pela F2 por desenho** (o
`json-viewer` ficou `available`, o card ganhou link e a imagem OG por tool
chegou); e a tabela acima saiu com imprecisoes de atribuicao e de contagem, que
este adendo corrige.

| Ponto | O que a revisao achou | O que foi feito |
|---|---|---|
| 6 | **Parcial, com defeito real**: o teste de servidor cobria **um** estado (granted com id), nao "nenhum dos estados" como a tabela diz; e a sequencia aceitar -> recusar -> aceitar **nao religava a medicao** - `loadAnalytics` saia cedo quando a tag ja existia, entao o `consent update granted` nunca era reenviado: cookie e tela diziam "granted" e o Consent Mode ficava em `denied`. O teste que executava a sequencia so contava a tag e nao pegava. Falhava para o lado seguro (nao media), mas contrariava o **ADR-013** | `lib/analytics.ts` reafirma o `granted` quando a tag ja esta na pagina, com teste proprio em `consent-banner.test.ts` |
| 13 | **Nao reproduzivel**: `composer test`, `types:check` e os dois builds verdes, mas `npm run test` vermelho em **3 de 4** passadas completas, sempre o import sob demanda do registro estourando o timeout de 5s do Vitest sob carga (isolado passa em ~3s) - nenhum defeito do codigo testado | Timeout explicito no teste; **3 de 3** passadas verdes (300 testes, 23 arquivos) |
| 14 | O smoke so existia como registro. Refazer mostrou que ele se sustenta (tabela abaixo), agora com banco SQLite descartavel e uma conferencia a mais: quem **ja decidiu** nao ve o banner no HTML do servidor | Zero erro no log do SSR; **zero** `googletagmanager`/`gtag(` nos 5 idiomas |
| 12 | "Zero string hardcoded" tinha **uma excecao**: o `<kbd>Esc</kbd>` literal no rodape da palette | Virou `app.search.key_escape` nos 5 idiomas ("Échap" em frances) |
| 5 | "Sem piscar" e **estrutural** (a decisao e resolvida no servidor a cada carregamento) e nunca teve teste dedicado; a revisao conferiu no HTML do servidor: com `Cookie: consent=granted` **ou** `consent=denied`, o banner nao vem | Evidencia nova, sem teste novo |
| 8 | "15 URLs" era verdade na F1 e ficou defasado pela F2 (json-viewer x5): hoje sao **20**. O teste deriva a lista do registro, nao fixa numero | So a contagem, corrigida aqui |
| 2 | "8 testes" do `ToolsRegistryTest` virou **9** (a fixture de manifesto quebrado entrou depois) | So a contagem, corrigida aqui |
| 1 e 4 | A atribuicao ao `HomeTest` de checagens que so existem em `home.test.ts` (linhas 55 e 58 desta tabela); os chips de categoria sao do `tools-registry.test.ts`, nao do `home.test.ts` | So a atribuicao, corrigida aqui |

Smoke de SSR da revisao: SQLite descartavel, `php artisan serve` com
`INERTIA_SSR_ENABLED=true` e `node bootstrap/ssr/ssr.js` no 13714, tudo removido
no fim.

| URL | HTTP | O que foi conferido no HTML **do servidor** |
|---|---|---|
| `/en`, `/pt-br`, `/pt-pt`, `/fr`, `/es` | 200 | os 8 nomes de tool em cada idioma (cinco sao nome de produto e ficam em ingles nos 5; tres sao descritivos e sao traduzidos) e os 6 chips de categoria mais o "todas"; precos da tabela formatados no idioma (`R$ 29,90` no pt-BR, `29,90 BRL` no es); os `data-test` dos cartoes e do banner, que so existem no HTML renderizado |
| `/pt-br/styleguide`, `/pt-br/login` | 200 | `noindex` sem `og:*` e sem hreflang |
| `/sitemap.xml` | 200 | 20 `loc` e 20 `x-default`, sem `styleguide` e sem tool `soon` |
| `/robots.txt` | 200 | as 4 linhas de `Disallow` e a linha do `Sitemap:` |
| as 5 homes com `Cookie: consent=granted` e com `consent=denied` | 200 | banner **ausente** nos dois casos: a decisao chega resolvida do servidor |

Gates depois das correcoes: `composer test` verde (266 testes, 6.380 assercoes);
`npm run test` **3 de 3** verde (300 testes em 23 arquivos, era 1 de 4);
`types:check`, `build` e `build:ssr` exit 0.

Resta a conferencia manual de sempre (usuario): palette no teclado,
aceitar/recusar no banner e ver a tag do GA entrar e sair do `<head>` no devtools
com um id real no `.env` local - incluindo **aceitar de novo depois de recusar**,
a sequencia corrigida nesta revisao. Sem id configurado, a medicao fica desligada
de proposito.
