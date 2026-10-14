# Relatorio - Auditoria independente da F1 e fechamento das pendencias

Branch: `develop_natan`
Data: 2026-10-14
Tarefa: "verifique se a fase 1 foi toda executada docs/acceptance/FASE-1.md"
Plano: `docs/plans/develop_natan/20261014_auditoria_fase1_plansfacts.md`
Levantamento: `docs/survey/20261014_auditoria_fase1_surveyfacts.md`
Aceite revisado: `docs/acceptance/FASE-1.md` (secao "Revisao independente")

## 1. O que foi pedido e o veredito

Verificacao **independente** da F1, para conferir o auto-relato do aceite em vez
de reassumi-lo (a tabela "Resultado" ja estava preenchida so com "OK"). Veredito
depois da auditoria e das correcoes: **a F1 foi executada e se sustenta**, com
dois pontos que nao se sustentavam e foram consertados - um **defeito real** de
consentimento (criterio 6) e o gate do Vitest **nao deterministico** (criterio
13) - mais uma perda acidental de documentacao na arvore da F2, uma excecao de
i18n e imprecisoes no proprio aceite.

| # | Criterio | Veredito independente |
|---|---|---|
| 1 | 8 tools registradas, selo "em breve", sem link | Confirmado **na F1** (commit `1f00431`); hoje superado pela F2 por desenho (o card do `json-viewer` linka) |
| 2 | Registro e traducao batem | Confirmado (o aceite diz "8 testes"; sao 9) |
| 3 | Busca Cmd+K por teclado, so navega `available` | Confirmado; lacunas de teste registradas: categoria nunca isolada, foco sem assercao |
| 4 | Home com hero, chips, grid, destaque e precos da tabela | Confirmado; a atribuicao de duas checagens ao `HomeTest` e que estava errada (sao do `home.test.ts`) |
| 5 | Banner para quem nao decidiu; decisao resolvida no servidor | Confirmado; "sem piscar" e estrutural e nao tinha teste - ganhou evidencia nova no smoke |
| 6 | Sem aceite nenhuma tag; com aceite, `consent default denied` antes | **Parcial e com defeito real** - corrigido (secao 3) |
| 7 | Reabrir pelo rodape; recusar nao carrega nada | Confirmado |
| 8 | Sitemap com alternates, `x-default`, sem `noindex` | Confirmado (o aceite diz "15 URLs"; na F1 era verdade, hoje sao 20) |
| 9 | Robots servido pela aplicacao, estatico removido | Confirmado, inclusive o guard que falha se o estatico voltar |
| 10 | `og:*`/`twitter:*` so em pagina indexavel | Confirmado; a imagem OG por tool chegou na F2 |
| 11 | `styleguide` fora do indice | Confirmado |
| 12 | Zero string hardcoded | Confirmado com **uma excecao** - o `<kbd>Esc</kbd>` literal, corrigido |
| 13 | Os cinco gates | `composer test`, `types:check` e os dois builds verdes; **`npm run test` vermelho em 3 de 4 passadas** - corrigido (secao 4) |
| 14 | Smoke com SSR | Existia so como registro; **refeito do zero** (secao 5) |
| 15 | Nenhum commit do assistente | Confirmado (`git log`: todos os commits sao do usuario, via gitpr) |

## 2. Metodo

- **Quatro leituras independentes em paralelo** (criterios 1/2/4/12; 3/5/6/7;
  8/9/10/11; docs/processo/escopo), com instrucao de nao rodar suites e de citar
  `arquivo:linha`.
- **Gates executados de verdade**, com o exit code capturado. O primeiro
  `npm run test 2>&1 | tail -60` mascarou o exit code (o do pipe e o do `tail`):
  a suite estava vermelha e parecia verde. Corrigido o metodo e remedido - foi
  assim que o criterio 13 caiu.
- **Verificacao na fonte** dos achados principais, pelo orquestrador, lendo o
  codigo (`analytics.ts`, `useConsent.ts`, `AnalyticsScript.vue`,
  `consent-banner.test.ts`) e o git (`git show`/`git diff` da SPEC, `git ls-tree`
  das fixtures).
- **Smoke de SSR refeito** com banco descartavel (secao 5).

## 3. O defeito real: aceitar -> recusar -> aceitar nao religava a medicao

- `resources/js/lib/analytics.ts`: `loadAnalytics` saia cedo quando a tag ja
  estava na pagina, entao o `gtag('consent', 'update', { analytics_storage:
  'granted' })` nunca era reenviado depois de uma recusa.
- Efeito: cookie e tela diziam "granted", mas o Consent Mode continuava
  `denied` - a medicao ficava desligada para sempre naquele carregamento.
  Falhava "para o lado seguro" (nao media), mas **contrariava o ADR-013** e o
  proprio interruptor do banner.
- O teste `consent-banner.test.ts` **executava exatamente a sequencia** e
  passava, porque so contava a quantidade de tags.
- Correcao: o caminho da tag existente passa a reafirmar o `granted` no Consent
  Mode (a injecao continua unica), com teste proprio da sequencia exigindo o
  ultimo comando do `dataLayer`.

## 4. O gate nao deterministico: `npm run test`

- Sintoma: em **4 passadas completas, 3 vermelhas**, sempre o mesmo teste
  (`tools-registry.test.ts`, "carrega o componente da tool sob demanda"),
  estourando o timeout padrao de 5s do Vitest. Isolado, passa em ~3s: e o
  import do CodeMirror + worker sob a suite inteira em paralelo, **nao** um
  defeito do codigo testado.
- Correcao: timeout explicito no teste, com o motivo no comentario - sem
  afrouxar o resto da suite.
- Resultado: **3 de 3** passadas verdes depois (300 testes, 23 arquivos).

## 5. Smoke de SSR reverificado

Banco SQLite descartavel em diretorio temporario (`migrate --force`), `php
artisan serve` com `INERTIA_SSR_ENABLED=true` e `node bootstrap/ssr/ssr.js` no
13714; tudo removido no fim (processos pelo `trap`, diretorio por `rm`).

| URL | HTTP | Conferido no HTML **do servidor** |
|---|---|---|
| `/en`, `/pt-br`, `/pt-pt`, `/fr`, `/es` | 200 | 8 nomes de tool por idioma e os 6 chips de categoria + "todas"; precos formatados no idioma (`R$ 29,90` no pt-BR, `29,90 BRL` no es); `data-test` de cartoes e banner (so existem com SSR) |
| `/pt-br/styleguide`, `/pt-br/login` | 200 | `noindex` sem `og:*` e sem hreflang |
| `/sitemap.xml` | 200 | 20 `loc`, 20 `x-default`, sem `styleguide`, sem tool `soon` |
| `/robots.txt` | 200 | 4 `Disallow` + `Sitemap:` |
| as 5 homes com `Cookie: consent=granted` **e** com `consent=denied` | 200 | banner **ausente** nos dois casos |

- **Zero** ocorrencia de `googletagmanager`/`gtag(` em pagina nenhuma.
- Log do SSR com as duas linhas de subida e nenhum erro.
- Achado de idioma: tres nomes de tool sao descritivos e **traduzidos**
  (`JSON to code`, `Base64, URL and Hash`, `Timestamp and UUID` mudam por
  idioma); cinco sao nome de produto e ficam em ingles nos 5. A frase do
  CHANGELOG da F1 dizia "o nome da tool em ingles em todos" - corrigida.
- Achado de preco: a formatacao de moeda muda por idioma (`R$ 29,90` no pt-BR,
  `29,90 BRL` no es) - o primeiro grep por `R$` no `/es` deu falso alarme.

## 6. Perda acidental na arvore da F2 (nao commitada)

`git diff docs/SPEC.md` mostrou a secao 5 perdendo os **itens 3 a 8 do MVP** sem
substituto, enquanto `SPEC.md:101` ("F3 Tools simples (3 a 8 do MVP)"),
`docs/acceptance/FASE-2.md` e o CHANGELOG continuam citando "3 a 8". Restaurado
com o texto exato do HEAD, preservando o detalhamento novo do item 1 vindo da
F2. A remocao do item `--elevated` da secao 11.2, essa sim, e intencional (a F2
deu valor proprio ao `--json-punct`).

## 7. Imprecisoes de documentacao corrigidas

- `docs/acceptance/FASE-1.md`: atribuicao de checagens ao `HomeTest` (sao do
  `home.test.ts`), "8 testes" do `ToolsRegistryTest` (sao 9), "15 URLs" do
  sitemap (20 desde a F2), "em nenhum dos estados" do GA (o teste cobre um),
  "zero string hardcoded" (havia o `Esc`). **A tabela historica nao foi
  reescrita**: o adendo datado registra o que a revisao achou.
- `CHANGELOG.md` (bloco da F1): a frase sobre o nome das tools em ingles nos 5
  idiomas nao descrevia o i18n real; reescrita.
- `docs/SPEC.md` secao 4 e `docs/adr/ADR-007`: o contrato de `hidden` entre os
  dois leitores ficou explicito - o TypeScript exclui, o `Tools::all()` do PHP
  ainda devolve (nada publico consome `all()`), e a F3 alinha quando a primeira
  tool `hidden` existir. Sem mudanca de codigo.

## 8. Decisoes do usuario e o que foi executado

As sete perguntas da fronteira foram respondidas "Recomendado" (Q1 a Q7).

| # | Decisao | Execucao |
|---|---|---|
| 1 | Corrigir a re-aceitacao agora, com teste proprio | `lib/analytics.ts` + teste novo em `consent-banner.test.ts` |
| 2 | Timeout explicito no teste de import pesado | `tools-registry.test.ts` (20s, com o motivo no comentario) |
| 3 | Restaurar os itens 3 a 8 do MVP na SPEC | `docs/SPEC.md` secao 5 |
| 4 | Adendo datado no aceite, sem reescrever a tabela | `docs/acceptance/FASE-1.md`, secao "Revisao independente" |
| 5 | Conferencia manual do GA com id real (usuario) | Roteiro na secao 10 |
| 6 | Reverificar o smoke SSR | Secao 5 |
| 7 | Menores: `Esc` via i18n; contrato de `hidden` documentado; sem ADR retroativo; sem backfill de survey | `CommandPalette.vue` + `app.search.key_escape` nos 5 idiomas; ADR-007 + SPEC secao 4 |

## 9. Verificacao executada

| Comando | Resultado |
|---|---|
| `composer test` | **verde** - config:clear, Pint, PHPStan nivel 7 sem erro, Pest **266 testes / 6.380 assercoes** (6.375 antes da chave nova de i18n nos 5 idiomas) |
| `npm run test` (3 passadas) | **3 de 3 verdes** - 300 testes em 23 arquivos (antes: 3 de 4 vermelhas) |
| `npm run types:check` | exit 0 |
| `npm run build` / `npm run build:ssr` | exit 0 |
| Smoke SSR | secao 5 |

## 10. Nao verificado daqui: conferencia manual do GA (usuario)

Em teste e em desenvolvimento **nao ha id**, entao a medicao fica desligada de
proposito - por isso esta parte e manual:

1. No `.env` local (nao versionado), definir `ANALYTICS_GA4_ID=G-...` com um id
   real e limpar a configuracao (`php artisan config:clear`).
2. `npm run dev`, abrir a home em uma janela anonima.
3. **Palette:** `Ctrl+K` (e `/` com o foco fora de campo) abre; setas navegam;
   `Enter` abre a tool `available`; `Esc` fecha. Conferir tambem o rotulo da
   tecla no rodape da palette (no frances, "Échap").
4. **Aceitar** no banner: a tag do GA aparece no `<head>` (devtools) e o
   `consent default denied` vem antes dela.
5. **Recusar pelo rodape** ("Cookies" -> Necessarios): a medicao para
   (`consent update denied`), sem tag nova.
6. **Aceitar de novo**: a medicao **volta** - e a sequencia corrigida nesta
   revisao; sem a correcao, aqui e onde ela falhava.
7. Ao fim, remover o id do `.env` e `config:clear` de novo.

## 11. Nenhum commit

Regra do CLAUDE.md respeitada: **nada foi commitado**. Tudo esta na arvore, para
o usuario commitar via gitpr - e este trabalho entra junto com o commit da F2,
que ja estava na arvore. Duas atencoes para esse commit:

- `tests/Fixtures/tools/hidden-tool/` esta **untracked** e e exigida por
  `ToolsRegistryTest.php`: sem ela no commit, a suite quebra.
- O bloco da F1 no `CHANGELOG.md` foi corrigido (frase sobre o nome das tools);
  se preferir manter o texto antigo, e so reverter essa linha.

## 12. Docs atualizados nesta sessao

- `docs/acceptance/FASE-1.md` - adendo "Revisao independente (2026-10-14)".
- `CHANGELOG.md` - secao "Corrigido" no bloco (ainda nao commitado) da F2 e a
  correcao da frase no bloco da F1.
- `docs/SPEC.md` - secao 5 restaurada; contrato de `hidden` na secao 4.
- `docs/adr/ADR-007-registro-de-tools.md` - contrato de `hidden`.
- `lang/{en,es,fr,pt_BR,pt_PT}/app.php` + `CommandPalette.vue` - `key_escape`.
- `docs/plans/develop_natan/20261014_auditoria_fase1_plansfacts.md` e
  `docs/survey/20261014_auditoria_fase1_surveyfacts.md` - a sessao de grill.
- Este relatorio.
