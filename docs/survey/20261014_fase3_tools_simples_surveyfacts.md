# Levantamento - FASE 3 (tools simples) e o bloco 0 do deploy

Data: 2026-10-14
Branch: `develop_natan`
Pedido original: "execute a proxima fase 3"
Plano: `docs/plans/develop_natan/20261014_fase3_tools_simples_plansfacts.md`
Relatorio: `docs/claude-code/reports/develop_natan/20261014_develop_natan_fase3_tools_simples.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

- HEAD = `1d556ee` ("feat: adiciona arvore defasada, atalhos e limiar na config"),
  o commit da revisao da F2. Abaixo dele: `edb62b2` (F2), `1f00431` (F1 na home),
  `b9a7f78` (deploy.sh) e `c23bcef` (painel admin). Todos do usuario; o assistente
  nunca commitou.
- Working tree **sujo, com mudancas do usuario nao commitadas** - e duas delas
  sao lockfiles saindo do git:
  - `.gitignore` modificado (sem commit), ganhando **duas** linhas no fim:
    `package-lock.json` e `composer.lock`;
  - `package-lock.json` apagado do disco e **ainda rastreado no indice** (a
    remocao nao esta preparada);
  - `composer.lock` com a **remocao ja preparada no indice** (`git diff
    --cached`: 11.729 linhas) e o arquivo **presente no disco** (434 KB) - foi
    `git rm --cached`, nao `rm`.
- A F3 tem seis diretorios em `resources/js/tools/`, cada um com **apenas** o
  `manifest.json`, todos com `"status": "soon"`:
  `json-to-code` (json / file-code / 30), `sql-formatter` (sql / database / 40),
  `base64-url-hash` (encode / binary / 50), `jwt-decoder` (security / key-round /
  60), `timestamp-uuid` (time / clock / 70) e `regex-tester` (text / regex / 80).
- Nenhum `Tool.vue`, nenhum teste e nenhuma entrada em `lang/*/tools.php` para
  essas seis: a fase comeca do zero em cada uma.

## 2. Metodo

- Levantamento por sub-agentes em paralelo (registro de tools nos dois leitores,
  o shell e a pagina da tool, o pipeline de deploy e o historico das chaves de
  i18n), sempre com citacao de `arquivo:linha` e com a instrucao de **nao**
  rodar suites.
- Verificacao na fonte, pelo orquestrador, de tudo que iria para uma decisao:
  `git log -S` para datar as chaves de traducao, `git status`/`git ls-files` para
  o estado do lockfile, leitura integral do `deploy.sh`, do `ToolShell.vue` e do
  `Show.vue`.
- **Tres rodadas de grill** (Q1 a Q23), com a fronteira recalculada a cada
  resposta; o usuario respondeu todas, e a unica que divergiu do recomendado foi
  a Q23.

## 3. O bug de producao (bloco 0)

**Sintoma relatado pelo usuario**: depois do deploy, a home mostra chave de
traducao crua no lugar de texto - `app.actions.search_placeholder`,
`home.search_hint`, `home.status_title`, `home.status_body`, `home.ai_title`,
`home.ai_body` - e "Guia de estilo" (que e `app.nav.styleguide`) aparece certo.

**Diagnostico**: a producao serve um **bundle JavaScript anterior a F1**.

- `git log -S` das chaves: nasceram em `172aea0` e foram removidas em `1f00431`,
  o commit da F1. O `Home.vue` atual pede `home.ai.*`, `home.cta.register`,
  `home.pricing.*` e `home.tools.*` (aninhadas) e **nunca** as chaves planas que
  aparecem na tela - nenhum bundle do repositorio hoje as emite.
- `lang/pt_BR/app.php:9` tem `'styleguide' => 'Guia de estilo'` e a chave
  sobreviveu a refatoracao inteira, entao continua resolvendo: e exatamente o
  que o usuario ve.
- Ou seja: os arquivos de idioma no servidor sao **os de hoje** e o JavaScript
  e **antigo**.

**Mecanismo**: `deploy/deploy.sh` roda com `set -euo pipefail`; os assets sao
publicados depois do `git pull` e do `composer install`, mas antes de `migrate`,
`optimize`, `queue:restart` e do reinicio do SSR. Uma falha no bloco do meio
deixa o **PHP novo com o JS velho**. Como o `git pull` ja moveu o HEAD para
`origin/main`, a execucao seguinte do cron sai cedo (`HEAD == origin/main`) e
**um deploy que falha nunca e retentado** ate entrar commit novo. Nem `migrate`
nem `queue:restart` rodam nesse caso (verificado: nem `1f00431` nem `edb62b2`
trazem migration, entao nao ha risco de banco).

**Correcao de uma leitura anterior**: o mecanismo de release por symlink **nao**
entrou em `b9a7f78` - ele existe desde `172aea0`, o commit inicial (verificado em
`git show 172aea0:deploy/deploy.sh`); o `b9a7f78` so moveu o arquivo do `flock`
de `/tmp/devtools-deploy.lock` para `storage/framework/locks/deploy.lock`. Isso
abre uma hipotese mais provavel do que a de que "a F1 quebrou o deploy": a de que
o **cron nunca publicou nada** e o `public/build` que a producao serve e um build
**manual** feito na subida do VPS, na epoca do `172aea0`. Como o JS manual e do
`172aea0` e os arquivos de idioma (lidos do disco pelo PHP atualizado) sao de
hoje, a home mostra chave crua - e o deploy da F1 foi apenas quando isso ficou
visivel.

**Hipoteses, ja com o que foi descartado do lado de ca:**

- (a) `vite build --outDir` apontando para uma release fora de `public/`:
  **descartada**. O comando exato do script
  (`npm run build -- --outDir storage/app/builds/localtest/build --emptyOutDir`)
  roda aqui e termina com exit 0; o `laravel-vite-plugin` nao recusa o `outDir`,
  e `storage/app/builds` esta dentro do root do projeto.
- (b) `npm ci` falhando: **descartada**. `npm ci --dry-run` com o `package.json` e
  o `package-lock.json` **do HEAD** instala 422 pacotes sem erro; os dois arquivos
  mudaram juntos em `edb62b2`; e o lock esta completo e multiplataforma - tem
  `@rolldown/binding-linux-x64-gnu@1.2.13` (o Vite 8 usa **Rolldown**, nao
  Rollup; os `@rollup/rollup-*` em 4.9.5 que o `package.json` fixa em
  `optionalDependencies` sao pinos orfaos, ninguem os exige).
- (c) **rede no build** - a candidata que sobrou. `vite.config.ts` registra o
  plugin `fonts` do `laravel-vite-plugin` com duas familias do Bunny desde o
  commit inicial, e o plugin baixa os arquivos com `fetch` quando nao tem cache
  (`node_modules/laravel-vite-plugin/dist/index.js`, `DEFAULT_CACHE_DIR =
  "node_modules/.cache/laravel-vite-plugin/fonts"`). Como o `npm ci` **apaga o
  `node_modules` inteiro**, todo `npm ci` zera o cache de fontes e o build
  seguinte obrigatoriamente vai a rede.
- Outras que so o log separa: `composer install` (linha 58), o build do SSR
  (`npx vite build --ssr`, linha 76), a versao do Node no VPS (o
  `package.json` nao tem `engines` e o script nao confere a versao) e a propria
  troca de symlink.

**Estado**: **nao resolvido** - (a) e (b) cairam por verificacao local, e o
`deploy.log` do servidor e o que separa as candidatas restantes. Nenhuma delas
foi tratada como causa ate aqui.

## 4. Fatos do registro de tools e do shell (base da F3)

- **Um manifesto, dois leitores**: `resources/js/tools/<slug>/manifest.json`
  (slug, status, category, icon, order, keywords), lido pelo TypeScript
  (`registry.ts`, via `import.meta.glob('./*/manifest.json', {eager:true})`) e
  pelo PHP (`App\Support\Tools`, com `glob` sobre `config('tools.path')`). O
  manifesto **nao tem texto visivel**: o texto vem de `tools.<slug>.*` nos
  arquivos de idioma, por convencao.
- **Divergencia conhecida**: o leitor TypeScript descarta `status !== 'hidden'`,
  e o `Tools::all()` do PHP **nao** - alinhado na F3 (Q6).
- **Pagina generica** (ADR-029): rota `/{locale}/tools/{slug}` (nome
  `tools.show`); o servidor decide a existencia e slug desconhecido, `soon` e
  `hidden` dao **o mesmo 404**; o slug e lido por nome (`$request->route('slug')`)
  porque o Laravel preenche parametro escalar **por posicao** e a URL comeca com
  `{locale}`; as props sao exatamente `slug`, `maxInputBytes` e `virtualizeFrom`;
  `pages/tools/Show.vue` resolve o componente por glob lazy
  (`tools/components.ts`) dentro de um `<ClientOnly>`; o SSR entrega a casca e as
  metas.
- **Contrato do `ToolShell.vue`**: uma prop (`splitKey`) e os slots `actions`,
  `input-header`, `input`, `output-header`, `output`, `status` e `shortcuts`;
  dois paineis com divisor arrastavel (`role="separator"`, setas, duplo clique
  restaura, divisao em `tool-split:<splitKey>` no `localStorage`); abaixo de
  768px os mesmos paineis viram abas com `force-mount`, e trocar de aba **nao**
  desmonta o editor. Quem usa o shell e o `Tool.vue` da tool, nao a pagina.
- **Companheiros**: `ToolActionBar.vue` (slot `default` a esquerda, `end` a
  direita com `ml-auto`) e `ToolButton.vue` (label, shortcut, disabled, pressed,
  variant).
- **Cores de sintaxe**: uma familia so, `--json-*` (key, string, number, literal,
  punct), mapeada em `@theme inline` para `--color-json-*`; **nao existe paleta
  paralela em JavaScript** (ADR-018) - as seis tools herdam isso.
- **i18n**: o `HandleInertiaRequests` embarca **so o idioma ativo**, caminhando
  `config('locales.namespaces')` (app, admin, account, home, tools, settings,
  styleguide, locales, auth, legal, newsletter). O `TranslationsParityTest`
  falha por chave **faltando ou sobrando**, por string vazia e proibe `@` e `|`
  crus.
- **Limites**: `config/tools.php` traz `path`, `limits.input_bytes`
  (`TOOLS_MAX_INPUT_BYTES`, 5 MB) e `limits.virtualize_from`
  (`TOOLS_VIRTUALIZE_FROM`, 2000). Os dois limites novos do Regex Tester (Q20)
  seguem esse mesmo caminho.

## 5. Decisoes das rodadas

Tabela completa em `docs/plans/develop_natan/20261014_fase3_tools_simples_plansfacts.md`
(secao "Decisoes confirmadas pelo usuario"). Resumo do que a fronteira fechou:

- **Escopo e forma**: as seis tools numa fase, com uma parada para revisao (Q1);
  todas no shell, com listas no cabecalho do painel e abas de modo so no
  `base64-url-hash` (Q2).
- **Dependencias**: `sql-formatter` e `@codemirror/lang-sql` entram; MD5 e
  implementado no repositorio, porque o Web Crypto nao tem MD5 (Q3).
- **Seguranca**: worker com encerramento por tempo limite so no Regex Tester
  (Q4, Q20), com ADR proprio (Q22).
- **Configuracao x codigo**: listas de produto (dialetos, alvos, algoritmos) sao
  constantes tipadas do modulo; **limites** vao para `config/tools.php` (Q5),
  seguindo a regra do CLAUDE.md.
- **Base compartilhada**: ajudantes usados por duas ou mais tools saem do
  `json-viewer` (Q8) e as strings do shell deixam de falar de JSON (Q7).
- **Comportamento comum**: entrada invalida mantem a ultima saida valida (Q10).
- **Processo**: o deploy e o bloco 0, em branch propria curta (Q9, Q21), e a
  F3 produz emenda no ADR-029 mais um ADR-031 novo (Q22).
- **Q23, a unica divergencia do recomendado**: o usuario escolheu **parar de
  versionar o `package-lock.json`** de verdade (sai do git e o `deploy.sh` passa
  a `npm install` com gatilho no hash do `package.json`), em vez de reverter a
  mudanca do `.gitignore` e manter o lockfile versionado, que era o
  recomendado. A consequencia assumida: o build no servidor deixa de ser
  reproduzivel. O ADR-014, que documenta o contrato antigo, passa a descrever o
  novo.
- **Duas decisoes fora das rodadas**, tomadas ao encostar no `composer.lock` e na
  fragilidade do script (2026-10-14): **"Deixar `composer install`"** (sem
  lockfile ele resolve a arvore sozinho e escreve o lock novo - fica um
  comentario no script e a consequencia no ADR-014) e **"So documentar"** para a
  falha no meio do deploy (o comportamento nao muda; o sintoma do PHP novo com
  JS velho, o diagnostico e o conserto manual vao para o ADR-014 e para o
  RUNBOOK).

## 6. Domain modeling

- **"Linguagem de destino"** (ou "Alvo de codigo") e o termo para as escolhas do
  `json-to-code` (TypeScript / PHP DTO / Pydantic). O glossario ja tem "Output
  target" para o formato cadastrado na tabela `output_targets` do AI SQL Builder
  - **sao coisas diferentes** e o termo antigo fica reservado para o builder.
- Entradas novas a escrever no glossario: o **limite de tempo do regex** (com o
  encerramento do worker), o **teto de resultados** (com o aviso de corte) e
  **"Ultima saida valida"**, generalizando o termo "Arvore defasada" (que hoje
  fala so da arvore do JSON Viewer) para qualquer saida das tools.
- **Nenhum termo novo de ADR** alem do ADR-031: o modelo de seguranca do Regex
  Tester e o unico do bloco que passa no teste do `grilling` (dificil de
  reverter, surpreendente sem contexto e fruto de um trade-off real).

## 7. Pendencias

- **O `deploy.log` do servidor** e as quatro linhas de diagnostico da secao 3 -
  sem eles o passo 3 do bloco 0 continua em hipotese.
- Os passos 2 a 5 do bloco 0 e os nove blocos da F3, na ordem do plano.
- Nenhum commit pelo assistente: cada bloco para e o usuario comita pelo gitpr.
