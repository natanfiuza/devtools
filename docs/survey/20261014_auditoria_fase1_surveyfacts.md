# Levantamento - verificacao independente da FASE 1

Data: 2026-10-14
Branch: `develop_natan`
Pedido original: "verifique se a fase 1 foi toda executada docs/acceptance/FASE-1.md"
Plano: `docs/plans/develop_natan/20261014_auditoria_fase1_plansfacts.md`
Relatorio: `docs/claude-code/reports/develop_natan/20261014_develop_natan_auditoria_fase1.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

- HEAD = `1f00431` ("feat: adiciona home com busca, precos e consentimento de
  cookies") - o commit da F1, feito pelo **usuario** via gitpr. O assistente
  nunca commitou (criterio 15 confere).
- A **F2 inteira esta na arvore de trabalho, nao commitada**: 31 arquivos
  modificados + ~35 untracked (pagina da tool, CodeMirror, worker, OG por tool,
  ADR-029/030, `docs/acceptance/FASE-2.md`).
- `docs/acceptance/FASE-1.md` ja estava com a tabela "Resultado" preenchida so
  com "OK" - o pedido e **desconfiar** dela, nao reler.
- Gates do estado inicial, executados de verdade: `composer test` **verde**
  (Pint + PHPStan nivel 7 com 0 erros + Pest **266 testes / 6375 assercoes**);
  `npm run test` **vermelho em 3 de 4 passadas completas**; `npm run
  types:check`, `npm run build` e `npm run build:ssr` exit 0.

## 2. Metodo

- **Quatro auditorias de leitura em paralelo** (criterios 1/2/4/12; 3/5/6/7;
  8/9/10/11; docs, processo e escopo), com instrucao explicita de nao rodar
  suites e de citar `arquivo:linha`.
- **Gates executados pelo orquestrador**, com o exit code capturado (o primeiro
  `npm run test` tinha mascara de exit code por causa de um `| tail` no pipe -
  a suite estava vermelha e parecia verde; corrigido e remedido).
- **Verificacao na fonte dos achados principais** pelo orquestrador (leitura de
  `lib/analytics.ts`, `composables/useConsent.ts`, `AnalyticsScript.vue`,
  `consent-banner.test.ts`, `git show`/`git diff` da SPEC, `git ls-tree` das
  fixtures).
- Medicao de flakiness: 4 passadas completas do Vitest em 3 condicoes de carga.

## 3. Fatos por criterio (veredito independente)

| # | Fatos |
|---|---|
| 1 | No commit da F1: 8 manifests todos `soon`, `ToolCard.vue` sem `Link`/`<a>`, `home.test.ts` com 8 cartoes, 8 selos e `findAll('a')` vazio. Hoje: `json-viewer/manifest.json` e `available` e o card linka (`ToolCard.vue:28,45-53`) - **superado pela F2 por desenho** |
| 2 | 8 slugs na ordem do MVP; `name`/`description`/`keywords` nos 5 idiomas; 6 categorias com rotulo; os 8 icones existem em `icons.ts:24-35`; paridade verde. **Nomes**: cinco sao nome de produto e ficam em ingles nos 5 idiomas; tres sao descritivos e sao **traduzidos** (`JSON to code`/`JSON para codigo`/`JSON a codigo`/`JSON vers code`; `Base64, URL and Hash`/`e Hash`/`y Hash`/`et Hash`; `Timestamp and UUID`/`e UUID`/`y UUID`/`et UUID`) |
| 3 | Palette com atalho, `/` fora de campo, filtro por nome/keyword, setas, Enter/Esc, `role=combobox`/`listbox`, `aria-activedescendant` de verdade; `soon` nao navega (`CommandPalette.vue:76-94`). Lacunas de teste: **categoria nunca isolada** e **foco sem assercao** |
| 4 | Hero, chips, grid, destaque e precos lidos de `Plan::query()` (`HomeController.php:43-63`); `PricingSection.vue` usa `plan.price`; nenhum preco em template. No HTML do servidor o preco sai **formatado no idioma** (`R$ 29,90` no pt-BR, `29,90 BRL` no es - a formatacao de moeda muda, o primeiro grep por `R$` no `/es` foi falso alarme) |
| 5 | Decisao vem do servidor (`HandleInertiaRequests.php:64-69` + `Consent.php:52-59`), lida no setup do composable (`useConsent.ts:42-50`); cookie corrompido volta a nula e e apagado. "Sem piscar" e **estrutural** - sem teste dedicado |
| 6 | **Parcial**: o teste de servidor cobre **um** estado (granted+id, `ConsentTest.php:86-99`), nao "nenhum dos estados" como o doc diz. A precedencia do `consent default` e provada dentro do `dataLayer` (`consent-banner.test.ts:211-225`), nao "antes do elemento script" |
| 7 | Reabrir pelo rodape (`PublicLayout.vue:247-254`), recusar nao injeta, "Necessarios" nao desliga; `AuthLayout` tem banner sem botao de reabrir (proposital) |
| 8 | `/sitemap.xml` com home/privacy/terms nos 5 idiomas, alternates completas e `x-default` = `en`; tools so `available`; **20 URLs hoje** (15 + json-viewer x5) - o "15 URLs" do doc era verdade na F1 e ficou defasado na F2; o teste deriva a lista, nao fixa numero |
| 9 | Robots servido pela aplicacao (4 `Disallow` + `Sitemap:`) e `public/robots.txt` ausente na arvore **e no HEAD** (deletado em `1f00431`); o guard `RobotsTest.php:26-30` (`is_file`) e quem falha se o estatico voltar - o teste HTTP sozinho nao pegaria |
| 10 | `og:*`/`twitter:card` so em pagina indexavel (`app.blade.php:15,25,64-90`); imagem padrao 1200x630 real com alt traduzido; imagem por tool chegou na F2 (`Seo.php:113-124`), com queda para a padrao. So ha **um** tag twitter (`twitter:card`) |
| 11 | `styleguide` em `NOINDEX` (`Seo.php:30-32`) e fora do sitemap; testes nos dois lados |
| 12 | Paridade verde e traducao marcadora nos testes de componente; **excecao encontrada**: `<kbd>Esc</kbd>` literal (`CommandPalette.vue:274`) |
| 13 | `composer test`/`types:check`/builds verdes; **`npm run test` nao deterministico**: em 4 passadas completas, 3 vermelhas, sempre `tools-registry.test.ts:117` estourando o timeout de 5s (isolado passa em ~3,2s) |
| 14 | Smoke SSR so existia como **registro** no relatorio da F1 (`/en`..`/es`, styleguide, privacy, login, sitemap, robots) - nao reverificado nesta rodada de levantamento; **reverificado na execucao** (secao 8) |
| 15 | Todos os commits sao do usuario (com `Co-Authored-By: Gitpr-cli`); nenhum do assistente |

## 4. Defeito de codigo encontrado (criterio 6/7)

**Aceitar -> recusar -> aceitar nao religa a medicao.** Confirmado na fonte:

- `resources/js/lib/analytics.ts:62-64`: `loadAnalytics` sai cedo quando
  `script[data-analytics]` ja existe, entao o `gtag('consent','update',
  {analytics_storage:'granted'})` (linha 67) nunca e reenviado.
- Resultado: cookie e UI dizem "granted" (`useConsent.ts:52-60`,
  `ConsentBanner.vue`), mas o `analytics_storage` continua `denied` depois da
  recusa. Falha "para o lado seguro" (nao mede), mas **contradiz o ADR-013**
  ("mudar a resposta") e o proprio interruptor da tela.
- O teste `consent-banner.test.ts:230-236` executa exatamente essa sequencia e
  so asserta `tags() === 1` - o defeito mora dentro do teste sem ser pego.

## 5. Fatos de processo e documentacao

- **Survey da F1 nao existe**: `docs/survey/` tem so `20261013_auth_login`,
  `20261013_executar_spec` e `20261014_papeis_paineis`. O `plansfacts` da F1 foi
  salvo (`docs/plans/develop_natan/20261014_fase1_tools_home_plansfacts.md`):
  metade da regra da skill foi cumprida.
- Relatorio da F1 existe e esta completo
  (`docs/claude-code/reports/develop_natan/20261014_develop_natan_fase1_tools_home.md`,
  commitado em `1f00431`).
- CHANGELOG, `docs/SPEC.md` (secao 9 com a F1 concluida), ADR-007 e ADR-013
  (revisados) e DESIGN entraram no commit da F1. **`docs/glossario.md` nao**:
  nenhum termo novo da F1 foi adicionado (os termos ja existiam desde `c23bcef`).
- **Tres decisoes da F1 nao viraram ADR**: sitemap/robots servidos pela
  aplicacao com o estatico removido; imagem OG padrao comitada por comando
  artisan de uso unico; `styleguide` fora do indice. Ficaram na SPEC 11.1.
- Escopo: tudo o que o aceite declara esta no commit. Creep reconhecido:
  `lang/*/legal.php` + `config/legal.php` (data de revisao), banner no
  `AuthLayout` e os artefatos `.gitpr/`.
- Imprecisoes do `docs/acceptance/FASE-1.md`: atribui ao `HomeTest` checagens
  que so existem em `home.test.ts` (linhas 55 e 58); diz "8 testes" do
  `ToolsRegistryTest` (hoje 9); "15 URLs" (hoje 20); "em nenhum dos estados"
  (o teste cobre um); "zero string hardcoded" (ha o `Esc`).
- Imprecisao do `CHANGELOG.md` (bloco da F1): "o nome da tool em
  ingles em todos (nome de produto)" - o smoke do SSR mostrou que **tres nomes
  sao descritivos e traduzidos** por idioma (o `en` tem `JSON to code`,
  `Base64, URL and Hash` e `Timestamp and UUID`; o pt-BR tem as formas em
  portugues). Corrigido na execucao (secao 8).
- Contrato "fonte unica, dois leitores" **nao vale para `hidden`**:
  `app/Support/Tools.php:27-67` inclui, `resources/js/tools/registry.ts:16-18`
  exclui. Nenhuma tool `hidden` em producao hoje.
- `tests/Fixtures/tools/hidden-tool/` esta **untracked** e e exigida por
  `ToolsRegistryTest.php:94`: se o commit da F2 nao a incluir, a suite quebra.
- O `hidden-tool` nao substitui nada: as fixtures da F1 (`alpha-tool`,
  `sem-slug`, `zeta-tool`) estao no HEAD.

## 6. Fatos sobre a arvore da F2 (nao commitada)

- **Perda acidental**: `docs/SPEC.md` secao 5 perdeu os itens **3 a 8 do MVP**
  no diff da F2 (`git diff docs/SPEC.md`), enquanto `SPEC.md:101`
  ("F3 Tools simples (3 a 8 do MVP)"), `FASE-2.md` e o CHANGELOG continuam
  citando "3 a 8". Nao ha substituto para o bloco.
- A remocao do item `--elevated` da SPEC 11.2 **e intencional**: a F2 deu valor
  proprio ao `--json-punct` (`resources/css/app.css`, DESIGN) e o CHANGELOG F2
  registra o fechamento do item.
- O resto do diff da F2 e feature nova (CodeMirror, pagina da tool, worker, OG
  por tool) ou extensao planejada de artefato da F1 (`Tools::keywords`,
  `Seo::openGraph(Request)`, `ToolCard` virando `Link`, testes da F1 reescritos
  para o mundo com uma tool `available`).

## 7. Decisoes confirmadas pelo usuario (todas "Recomendado")

| # | Decisao |
|---|---|
| 1 | Corrigir o defeito de re-aceitacao agora, com teste proprio |
| 2 | Consertar o gate `npm run test`: timeout explicito no teste de import pesado; reavaliar `pool: 'vmThreads'` depois |
| 3 | Restaurar os itens 3 a 8 da SPEC secao 5 |
| 4 | Adendo datado "Revisao independente" no `FASE-1.md`, sem reescrever a tabela historica |
| 5 | Conferencia manual do GA com id real no `.env` local (usuario executa, com roteiro preparado) |
| 6 | Reverificar o smoke SSR de forma independente |
| 7 | Menores: (a) `Esc` vira chave i18n nos 5 idiomas; (b) contrato de `hidden` documentado, sem mudanca de codigo; (c) sem ADR retroativo; (d) sem backfill de survey |

## 8. Estado final (execucao desta sessao)

- Defeito do criterio 6 corrigido em `lib/analytics.ts` (reafirma o `granted`
  quando a tag ja existe) com teste novo em `consent-banner.test.ts`.
- `tools-registry.test.ts`: timeout explicito de 20s no teste de import
  sob demanda, com o motivo no comentario.
- SPEC secao 5 restaurada; contrato de `hidden` no ADR-007 e na SPEC secao 4;
  `app.search.key_escape` nos 5 idiomas (`Échap` em frances) usado pela palette.
- Gates depois das correcoes: `composer test` verde (266 testes / 6.380
  assercoes - 6.375 antes da chave nova de i18n nos 5 idiomas);
  `npm run test` **3 de 3 verdes** (antes: 1 de 4); `types:check`, `build` e
  `build:ssr` exit 0.
- Smoke SSR reverificado com banco SQLite descartavel: as 5 home, styleguide,
  privacy, login, sitemap e robots em 200, OG/canonical/noindex conferidos,
  **zero** `googletagmanager`/`gtag(`, e o banner ausente no HTML do servidor
  para quem ja decidiu - conferido com `Cookie: consent=granted` **e** com
  `consent=denied` (fecha a lacuna do criterio 5, que nao tinha teste).
- `CHANGELOG.md`: secao "Corrigido" no bloco (nao commitado) da F2 e a frase do
  bloco da F1 sobre o nome das tools, que o smoke desmentiu (secao 5).
- `docs/acceptance/FASE-1.md`: adendo datado "Revisao independente (2026-10-14)",
  sem reescrever a tabela historica.
- Nenhum commit: tudo na arvore, para o usuario commitar via gitpr (lembrar da
  fixture `hidden-tool` untracked).
- Fica pendente do usuario: a conferencia manual do GA no devtools (item 5) -
  roteiro no relatorio da sessao.
