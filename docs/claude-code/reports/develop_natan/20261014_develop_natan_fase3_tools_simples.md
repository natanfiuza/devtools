# Relatorio - FASE 3 (tools simples) e o bloco 0 do deploy

Branch: `develop_natan`
Data: 2026-10-14
Tarefa: "execute a proxima fase 3"
Plano: `docs/plans/develop_natan/20261014_fase3_tools_simples_plansfacts.md`
Levantamento: `docs/survey/20261014_fase3_tools_simples_surveyfacts.md`
Aceite: `docs/acceptance/FASE-3.md` (a criar no bloco 1)

**Relatorio em duas partes**: a sessao de grill fechou o plano da fase e o bloco 0
(deploy) foi **comecado e esta parado**, esperando cinco linhas de diagnostico do
servidor. Os blocos 1 a 9 da F3 ainda **nao comecaram**. O que esta fechado e o
que esta bloqueado vao separados abaixo.

## 1. O que foi pedido

Executar a F3 da secao 9 do SPEC - "Tools simples (3 a 8 do MVP)": `json-to-code`,
`sql-formatter`, `base64-url-hash`, `jwt-decoder`, `timestamp-uuid` e
`regex-tester`. As seis existem hoje so como `manifest.json` com
`"status": "soon"`; nenhuma tem `Tool.vue`, teste ou texto em `lang/*/tools.php`.

No meio da sessao o usuario relatou um **bug de producao**: depois do deploy, a
home passou a mostrar chave de traducao crua no lugar do texto. Isso virou o
**bloco 0** da fase - a home voltar ao ar nao espera a fase inteira.

## 2. Metodo

- **Levantamento por sub-agentes em paralelo** (registro de tools nos dois
  leitores, o shell e a pagina da tool, o pipeline de deploy, o historico das
  chaves de i18n), com citacao de `arquivo:linha` e instrucao de nao rodar
  suites.
- **Verificacao na fonte pelo orquestrador** de tudo que ia para uma decisao:
  `git log -S`, `git show` do `deploy.sh` em commits antigos, `git status` e
  `git ls-files`, leitura integral do `ToolShell.vue` e do `Show.vue`.
- **Tres rodadas de grill** (Q1 a Q23), com a fronteira recalculada a cada
  resposta. O usuario respondeu todas; a unica que divergiu do recomendado foi a
  Q23.
- Duas perguntas novas depois, ao encostar no `composer.lock` e na fragilidade do
  script (secao 6).

## 3. O bug de producao (bloco 0)

**Sintoma relatado**: a home mostra `app.actions.search_placeholder`,
`home.search_hint`, `home.status_title`, `home.status_body`, `home.ai_title` e
`home.ai_body` no lugar do texto - enquanto "Guia de estilo" (`app.nav.styleguide`)
aparece certo.

**Diagnostico**: a producao serve um **bundle JavaScript anterior a F1**.

- `git log -S` das chaves: nasceram em `172aea0` e foram removidas em `1f00431`,
  o commit da F1. O `Home.vue` atual pede `home.ai.*`, `home.cta.register`,
  `home.pricing.*` e `home.tools.*` (aninhadas) e **nunca** as chaves planas que
  aparecem na tela - nenhum bundle do repositorio hoje as emite.
- `lang/pt_BR/app.php:9` tem `'styleguide' => 'Guia de estilo'`, e essa chave
  sobreviveu a refatoracao inteira, entao continua resolvendo: e exatamente o que
  o usuario ve.
- Ou seja: os arquivos de idioma no servidor sao **os de hoje** e o JavaScript e
  **antigo**.

**Mecanismo**: o `deploy.sh` roda com `set -euo pipefail` e publica os assets
depois do `git pull` e do `composer install`. Uma falha no bloco do meio deixa o
**PHP novo com o JS velho**. Como o `git pull` ja moveu o HEAD para
`origin/main`, a execucao seguinte do cron sai cedo (`HEAD == origin/main`) e
**um deploy que falha nunca e retentado** ate entrar commit novo. Nem `migrate`
nem `queue:restart` rodam nesse caso (verificado: nem `1f00431` nem `edb62b2`
trazem migration, entao nao ha risco de banco).

**Correcao de uma leitura minha**: eu havia afirmado que o mecanismo de release
por symlink entrou em `b9a7f78` e que o primeiro bundle a depender dele era o da
F1. **E falso**: `git show 172aea0:deploy/deploy.sh` mostra o mecanismo inteiro ja
no commit inicial, e o `b9a7f78` so moveu o arquivo do `flock` de
`/tmp/devtools-deploy.lock` para `storage/framework/locks/deploy.lock`. Isso abriu
a hipotese mais provavel de todas: **o cron talvez nunca tenha publicado nada**, e
o `public/build` que a producao serve seja um build **manual** da subida do VPS,
da epoca do `172aea0`. Como o JS manual e do `172aea0` e os idiomas sao lidos do
disco pelo PHP de hoje, a home mostra chave crua - e o deploy da F1 foi apenas
quando isso ficou visivel.

## 4. Hipoteses descartadas por verificacao local

Nenhuma delas virou causa ate aqui; duas cairam por medicao, nao por argumento.

- (a) `vite build --outDir` apontando para uma release fora de `public/`:
  **descartada**. O comando exato do script
  (`npm run build -- --outDir storage/app/builds/localtest/build --emptyOutDir`)
  roda aqui com exit 0, em 8,25s; o `laravel-vite-plugin` nao recusa o `outDir` e
  `storage/app/builds` esta dentro do root do projeto. (O diretorio de teste foi
  removido no fim.)
- (b) `npm ci` falhando por lockfile fora de sincronia ou por falta do binario
  nativo: **descartada**. `npm ci --dry-run` com o `package.json` e o
  `package-lock.json` **do HEAD** instala 422 pacotes sem erro; os dois arquivos
  mudaram juntos em `edb62b2`; e o lock esta completo e multiplataforma - tem
  `@rolldown/binding-linux-x64-gnu@1.2.13` (o Vite 8 usa **Rolldown**, nao
  Rollup; os `@rollup/rollup-*` em 4.9.5 que o `package.json` fixa em
  `optionalDependencies` sao pinos orfaos, ninguem os exige).
- (c) **rede no build** - a candidata que sobrou. O `vite.config.ts` registra o
  plugin `fonts` do `laravel-vite-plugin` com duas familias do Bunny desde o
  commit inicial, e o plugin baixa os arquivos por `fetch` sempre que nao tem
  cache (`DEFAULT_CACHE_DIR = "node_modules/.cache/laravel-vite-plugin/fonts"`).
  Como instalar dependencias **apaga o `node_modules` inteiro**, todo deploy que
  instala zera o cache de fontes e o build seguinte obrigatoriamente vai a rede.
- Outras que so o log separa: `composer install`, o build do SSR
  (`npx vite build --ssr`), a versao do Node no VPS (o `package.json` nao tem
  `engines` e o script nao confere a versao) e a propria troca de symlink.

## 5. O que foi executado nesta sessao (bloco 0, parte)

| Arquivo | Mudanca | Por que |
|---|---|---|
| `deploy/deploy.sh` | o gatilho de dependencias passa de `sha256sum package-lock.json` para `sha256sum package.json`; `npm ci` vira `npm install --no-audit --no-fund`; o arquivo de estado vira `storage/framework/npm-deps.sha256` | Q23: o lockfile sai do git, nao ha lock para comparar |
| `deploy/deploy.sh` | comentario novo acima do `composer install` explicando que, sem lockfile, ele resolve a arvore na hora e escreve um lock novo | a decisao "Deixar composer install" (secao 6) |
| `docs/adr/ADR-014-deploy.md` | a parte de dependencias de front reescrita; dois bullets novos em Consequencias (o build deixa de ser reproduzivel; o build depende de rede por causa das fontes); paragrafo do `composer.lock`; e **bullet novo** sobre a falha no meio do deploy | o ADR descrevia o contrato antigo (`npm ci`, `package-lock`) e passaria a descrever o contrario do que o script faz |
| `docs/RUNBOOK.md` | secao 2 atualizada (`npm install` + hash do `package.json`, e o paragrafo dos lockfiles fora do git); **secao nova "Quando o deploy falha no meio"** com o sintoma, o comando de diagnostico e o conserto; linha nova na tabela de diagnostico da secao 8 (chave de traducao crua); e a correcao de dois caminhos errados - o `flock` da secao 2 e o `fuser` da secao 8 ainda apontavam para `/tmp/devtools-deploy.lock`, que o `b9a7f78` moveu para `storage/framework/locks/deploy.lock` | a decisao "So documentar" (secao 6) e a limpeza do que a mudanca de hoje tornou falso |

Nenhuma linha de codigo da F3 foi escrita ainda.

## 6. Decisoes do usuario

As 23 da fronteira estao na tabela do plano, com o texto completo. As tres que
mudam o rumo:

- **Q23, a divergencia**: eu recomendei **reverter** a mudanca do `.gitignore` e
  manter o `package-lock.json` versionado; o usuario escolheu **parar de
  versionar de verdade** - sai do git e o `deploy.sh` passa a `npm install` com
  gatilho no `package.json`. Consequencia assumida: **o build no servidor deixa
  de ser reproduzivel** (o `package.json` usa faixas `^`, entao dois deploys do
  mesmo commit podem instalar versoes diferentes). O ADR-014 passou a documentar
  isso em vez do contrato antigo.
- **O `composer.lock` tambem esta saindo do git** (o usuario ja tinha feito
  `git rm --cached`). Perguntado como o script deveria tratar: **"Deixar
  `composer install`"** - sem lockfile ele ja resolve sozinho e escreve o lock
  novo; ficou so um comentario no script explicando o comportamento e a
  consequencia registrada no ADR.
- **A fragilidade do deploy**: perguntado se a falha no meio (PHP novo com JS
  velho, sem retentativa) deveria ser corrigida junto: **"So documentar"** -
  comportamento mantido, e o ADR-014 e o RUNBOOK registram o sintoma, o
  diagnostico e o conserto manual.

## 7. Bloqueio atual

O passo 1 do bloco 0 (diagnostico) **nao anda sem o servidor**. Estas cinco
linhas decidem entre a hipotese (c) e a de que o cron nunca publicou nada - e a
segunda elimina o problema inteiro:

```bash
tail -150 /var/www/devtools/storage/logs/deploy.log
ls -l /var/www/devtools/public/build /var/www/devtools/bootstrap/ssr
ls -1dt /var/www/devtools/storage/app/builds/* | head -5
cat /var/www/devtools/storage/framework/npm-lock.sha256
node -v && npm -v
```

`public/build` ser **diretorio de verdade** em vez de symlink ja confirma que a
publicacao nunca aconteceu. O `npm-lock.sha256` e o nome **antigo** do arquivo de
estado (o script novo passou a gravar `npm-deps.sha256`): se o caminho antigo nem
existir, e sinal de que o `npm ci` nunca chegou a rodar no servidor. O mesmo
diagnostico esta na secao nova do `docs/RUNBOOK.md`, para nao depender deste
relatorio.

## 8. O que falta (nao comecado)

- **Bloco 0, passos 2 a 5**: identificar a causa com o log, corrigir, o usuario
  commitar em **branch propria curta** -> PR -> `main` (o cron publica), conferir
  `ls -l public/build` e a home traduzida, e trazer o `main` de volta para a
  `develop_natan`.
- **Blocos 1 a 9 da F3**: `docs/acceptance/FASE-3.md`; extrair os ajudantes
  compartilhados do `json-viewer` (copiar, abrir/arrastar arquivo,
  `downloadText`, `formatBytes`, wrapper de CodeMirror por linguagem);
  generalizar `tools.ui.*` nos 5 idiomas; os dois limites novos do Regex Tester
  em `config/tools.php`; `sql-formatter` e `@codemirror/lang-sql`; as seis tools
  com seus `lib/` puros e suites de Vitest; virar os seis manifestos para
  `available`; os textos nos 5 idiomas; as seis imagens OG; os testes que hoje
  afirmam "so o json-viewer e available"; alinhar `hidden` em `Tools::all()`;
  os cinco gates; SPEC, DESIGN, glossario, ADR-029 (emenda), ADR-031 (novo),
  CHANGELOG e o aceite.
- **Este relatorio** ganha as secoes de verificacao e o veredito da fase quando
  os blocos rodarem.

## 9. Nenhum commit

Regra do CLAUDE.md respeitada: **nada foi commitado**. Tudo esta no working
tree, para o usuario commitar via gitpr.

## 10. Arquivos no working tree

Meus (5): `deploy/deploy.sh`, `docs/adr/ADR-014-deploy.md`, `docs/RUNBOOK.md`,
`docs/plans/develop_natan/20261014_fase3_tools_simples_plansfacts.md` (novo) e
`docs/survey/20261014_fase3_tools_simples_surveyfacts.md` (novo), mais este
relatorio.

**Do usuario, intocados por mim** (2): `.gitignore` modificado (ganhou
`package-lock.json` e `composer.lock` no fim) e o `package-lock.json` apagado do
disco mas ainda rastreado. O `composer.lock` tem a **remocao preparada no indice**
(11.729 linhas) e o arquivo segue no disco (434 KB) - foi `git rm --cached`, nao
`rm`.
