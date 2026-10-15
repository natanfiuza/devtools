# ADR-014-deploy
Status: Aceito
Data: 2026-10-05
Atualizado: 2026-10-13 (estrategia de assets definida; decisao deixa de estar pendente)
Atualizado: 2026-10-14 (os lockfiles saem do git e a falha no meio do deploy fica registrada de proposito)
Atualizado: 2026-10-15 (as duas instalacoes passam a depender do hash do manifesto e o deploy manual ganha `--force`)

## Decisao
Deploy continuo por cron: a cada minuto o `deploy/deploy.sh` roda, pega um
`flock` (uma execucao por vez), faz `git fetch` e **sai imediatamente se o HEAD
nao mudou**. Quando mudou: `git pull --ff-only`, as dependencias **sob gatilho**
(abaixo), build e `migrate --force`; no fim `queue:restart` e reinicio do SSR.
Com `--force` (uso manual) o corpo roda mesmo sem commit novo.

**Assets sao construidos no servidor.** `public/build` e `bootstrap/ssr` nao
vao para o git (o kit ja os ignora): sao artefatos de build, e versionar saida
de bundler enche o historico de ruido e conflito.

O build vai para uma **release timestamped** (`storage/app/builds/<AAAAMMDDHHMMSS>`)
e a publicacao e a troca de um symlink:

```bash
ln -sfn <release>/build public/build.tmp && mv -T public/build.tmp public/build
```

O `mv -T` sobre um symlink e atomico. Sem isso existe uma janela em que o HTML
novo ja esta no ar e o JS antigo ja foi apagado — quem carregou a pagina um
segundo antes quebra. Com as releases, o HTML que ja esta no navegador de
alguem continua encontrando os arquivos dele; mantemos as **3 ultimas** e as
antigas so saem depois da troca.

Nem o `package-lock.json` nem o `composer.lock` sao versionados (os dois estao
no `.gitignore`): o servidor tem os locks do ultimo install, e nenhum deles
acompanha o manifesto. Cada instalacao so roda quando o **hash do manifesto**
difere do guardado em `storage/framework/<nome>-deps.sha256` - instalar leva
minutos e quase todo deploy nao mexe em dependencia. O hash so e gravado
**depois** de uma instalacao que terminou bem: um deploy que morreu no meio
deixa o hash para tras, e o proximo deploy refaz o que faltou. (O gatilho nao e
um `git diff` contra o commit anterior: depois do pull o HEAD ja mudou, e o
commit seguinte que nao toca o manifesto nao apareceria no diff.)

Quando o `composer.json` muda, o install roda primeiro, contra o lock que o
servidor tem; se o manifesto pede um pacote que esse lock nao tem, o install
**recusa o lock** (exit 4) sem instalar nada, e o script cai para `composer
update`, que resolve a arvore de novo e reescreve o lock. Nenhum dos dois lados
do build e reproduzivel.

Migrations rodam nesse mesmo deploy, com o site no ar, entao precisam ser
**retrocompativeis**: adicionar coluna com default/nullable, renomear em dois
passos, nunca dropar no mesmo deploy que para de usar.

## Consequencias
- O VPS precisa de Node/npm alem do PHP; sem isso nao ha deploy (o script
  verifica `php`, `composer`, `node`, `npm` e `git` no PATH e aborta com o
  nome do que faltou — o cron nao herda o PATH do shell de login).
- Rollback de assets e trocar o symlink de volta para a release anterior
  (paliativo: o proximo deploy republica). Rollback de codigo e `git revert`.
- O bundle de SSR e carregado no boot do processo: o deploy reinicia o
  programa do supervisor para o node enxergar a release nova.
- O build do servidor **nao e reproduzivel**: sem lockfile versionado e com
  faixas (`^`) no `package.json`, dois deploys do mesmo commit podem instalar
  versoes diferentes de dependencia. E o preco de deixar o lock fora do git; o
  caminho de volta, se um dia for preciso, e recomitar o arquivo e voltar ao
  `npm ci`.
- O build **depende de rede**: o plugin `fonts` do `laravel-vite-plugin` baixa
  Inter e JetBrains Mono do Bunny por `fetch` sempre que nao tem cache, e o
  cache mora em `node_modules/.cache/laravel-vite-plugin/fonts` — que a
  instalacao de dependencias apaga junto com o `node_modules`.
- **Uma falha no meio do script deixa o PHP novo com o JS velho** — e ela e
  definitiva: o `set -euo pipefail` aborta na primeira falha, e como o
  `git pull` ja moveu o HEAD para `origin/main`, a execucao seguinte do cron
  sai cedo e **nao retenta nada**. Os assets sao publicados depois do pull e do
  `composer install`, entao uma falha entre esses passos e o build (rede, Node,
  disco) deixa a aplicacao com codigo e traducao novos servindo o bundle
  antigo — foi assim que a home ficou mostrando chave de traducao crua em
  2026-10-14. `migrate`, `optimize`, `queue:restart` e o reinicio do SSR tambem
  nao rodam nesse caso. O conserto e `deploy-instaled.sh --force` no servidor
  (o corpo inteiro roda de novo, e o gatilho por hash refaz as instalacoes que
  nao terminaram) ou entrar commit novo, que dispara o cron. **Mantido de proposito** (decisao de 2026-10-14):
  publicar os assets antes do pull nao resolve — o bundle novo precisa das
  dependencias novas do `composer install` — e as alternativas (retomar do
  ponto de falha, reverter o pull automaticamente) custam mais do que o
  problema que evitam. Diagnostico em `docs/RUNBOOK.md`.
- Nada aqui exige acesso ao servidor para publicar: commit no `main` e o cron
  leva. Por isso migration quebrada e o unico erro que nao da para desfazer
  rapido — a regra de retrocompatibilidade nao e estilo, e o que compra isso.
- Mudanca **no proprio script de deploy** e a excecao: o VPS executa uma copia
  (`deploy/deploy-instaled.sh`), trocada a mao quando o `deploy/deploy.sh`
  muda - sem a troca, o pull traz o arquivo novo mas quem roda continua sendo o
  antigo. Passo a passo em
  [deploy/deploy-instaled.md](../../deploy/deploy-instaled.md).
- Passo a passo de operacao (instalacao, supervisor, backup, rotacao de
  chaves, diagnostico) em [docs/RUNBOOK.md](../RUNBOOK.md).

## Alternativas descartadas
- **Versionar `public/build`**: historico poluido, conflito a cada merge e
  build local diferente do build do servidor.
- **Build em `public/build` direto**: e a janela de HTML novo com asset
  ausente descrita acima.
- **Publicar por rsync para um diretorio unico**: mesma janela, mais partes
  moveis.
