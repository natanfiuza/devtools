# Levantamento - gate de dependencias no deploy (composer + npm)

Data: 2026-10-15
Branch: `develop_natan`
Pedido original: "Altere o arquivo deploy\deploy.sh e prepare ele igual ao script
C:\Users\nataniel\projetos\cultivve\Cultivve-Consult_v2\.server\script_deploy.sh
que verifica se tem alguma alteração para executar o composer install e npm
install"
Plano: `docs/plans/develop_natan/20261015_deploy_gate_dependencias_plansfacts.md`
Relatorio: `docs/claude-code/reports/develop_natan/20261015_develop_natan_deploy_gate_dependencias.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

- HEAD = `9dee711` ("feat: adiciona seis tools simples e infraestrutura
  compartilhada", o commit da F3, do usuario); branch `develop_natan`; working
  tree com um unico item, nao rastreado: a descricao de PR do `/gitpr`
  (`.gitpr/reports/pr_desc/develop_natan_20261015060910_PR_DESC.md`).
- `deploy/deploy.sh` com 114 linhas, na versao de `ae47779` (bloco 0).
- Historico do arquivo: `abfbb0c` (first commit) -> `172aea0` (o script nasce) ->
  `b9a7f78` -> `ae47779` (bloco 0). **O gate por hash ja era o idioma do repo**:
  em `172aea0`/`b9a7f78` era `sha256sum package-lock.json` contra
  `storage/framework/npm-lock.sha256`, com `npm ci`; o bloco 0 trocou o
  manifesto e o comando (`package.json`, `npm install`, `npm-deps.sha256`). O
  `composer install --no-dev --optimize-autoloader -q` foi **incondicional em
  todas as versoes**.
- `storage/framework/.gitignore` lista nomes um a um (`compiled.php`,
  `config.php`, `down`, `events.scanned.php`, `lsp-*.php`, `maintenance.php`,
  `routes.php`, `routes.scanned.php`, `schedule-*`, `services.json`) - sem
  curinga. `git check-ignore -v` nos dois `*.sha256` sai **1** (nao ignorados):
  o arquivo de estado do npm esta fora do git por acidente.

## 2. O script de referencia

`C:\Users\nataniel\projetos\cultivve\Cultivve-Consult_v2\.server\script_deploy.sh`
(100 linhas). Mecanismo:

- `git fetch origin main`; `LOCAL_HASH`/`REMOTE_HASH` por `git rev-parse`; sai se
  iguais.
- `COMPOSER_CHANGED=$(git diff --name-only HEAD origin/main | grep
  "composer.json")` e `NODE_CHANGED=$(... | grep -E
  "package.json|package-lock.json")` - calculados **antes** do pull.
- `git pull origin main`; depois `if [ ! -z "$COMPOSER_CHANGED" ]` ->
  `composer install --no-interaction --no-dev --prefer-dist
  --optimize-autoloader`, senao loga "Pulando etapa"; o mesmo par para
  `npm install`.
- `npm run build` sempre; `migrate --force`; `route:clear`, `cache:clear`,
  `config:clear`, `optimize:clear`, `optimize`.
- Sem rede de seguranca: `[ ! -d vendor ]` / `[ ! -d node_modules ]` nao existem
  la.
- O `.gitignore` do projeto de referencia tambem deixa os dois lockfiles fora do
  git (linhas 21, 25, 26, 27, 30) - o `composer install` de la tem a mesma
  exposicao do item 3.1.

## 3. Fatos que mudaram a decisao

### 3.1 O `composer install` recusa o lock quando o manifesto pede pacote novo (comprovado)

Probe em `/tmp/composer-probe` (diretorio descartavel, fora do repo): copias do
`composer.json`/`composer.lock` do projeto, com um pacote a mais no manifesto
(`symfony/dom-crawler`, que o lock nao tem):

```
COMPOSER_EXIT=4
Required package "symfony/dom-crawler" is not present in the lock file.
```

A recusa e imediata - o composer valida o lock antes de instalar qualquer coisa
- e acontece **depois** do `git pull`, que ja moveu o HEAD. Ou seja: rodar
`composer install` no gatilho "mudou" e justamente o caso em que ele quebra, com
o HEAD ja avancado (o padrao do incidente de 2026-10-14). O `composer update`
no mesmo cenario resolve e reescreve o lock.

### 3.2 O `git diff` fica cego depois de um deploy que falhou no meio

O diff do script de referencia e calculado antes do pull. Num deploy que morreu
no meio, o pull ja moveu o HEAD: o commit seguinte pode nao tocar o manifesto, o
diff nao mostra nada, e a instalacao que faltou **nao roda nunca** - com o
deploy terminando "com sucesso", que e pior do que falhar. O hash guardado so e
escrito **depois** de uma instalacao que terminou bem; um deploy interrompido
deixa o hash para tras e o proximo refaz o que faltou (auto-conserto).

### 3.3 A receita de deploy manual do RUNBOOK nao funciona como esta escrita

- `docs/RUNBOOK.md:131-133`: "O conserto e rodar o script a mao depois de
  resolver a causa, ou entrar commit novo" - mas o script sai na hora quando
  `HEAD == origin/main` (`deploy.sh:50`), e depois de uma falha pos-pull o HEAD
  ja e igual. O mesmo vale para `ADR-014:70-71` ("ou rodar o script a mao no
  servidor").
- `docs/RUNBOOK.md:82` documenta `sudo -u www-data
  /var/www/devtools/deploy/deploy.sh   # manual, se precisar` - que nao faz nada
  nesse caso.

### 3.4 O `ADR-014` descreve o contrario do que acontece

`ADR-014:36-39` diz que "sem lockfile, o `composer install` do deploy **nao
instala versoes do lock** - ele resolve a arvore na hora e escreve um lock
novo". Nao e o que o servidor faz: o `composer install` da instalacao
(`docs/RUNBOOK.md:26`) deixou um `composer.lock` no servidor, e e **esse** lock
que o install usa a cada deploy - ele apenas nao acompanha o `composer.json`.
Vale para o npm tambem: o `npm install` reconcilia o lock quando ele esta
defasado, enquanto o `npm ci` recusava.

### 3.5 O primeiro deploy depois desta mudanca roda as duas instalacoes

No servidor o arquivo de estado e o antigo, `storage/framework/npm-lock.sha256`
(versao `172aea0`/`b9a7f78`); `npm-deps.sha256` e `composer-deps.sha256` nao
existem. Sem os dois arquivos, os dois gates abrem - que e o desejado: e o
deploy que publica a F3 inteira de uma vez.

## 4. Metodo

- Leitura integral do `deploy.sh` atual e do script de referencia; evolucao do
  arquivo por `git show <commit>:deploy/deploy.sh` nos tres commits que o
  tocaram.
- Probe de composer em diretorio descartavel fora do repo, com o `composer.json`
  e o `composer.lock` reais; o cenario decisivo e o do item 3.1.
- Verificacao na fonte do estado do repo (`git status`, `git log`), das regras
  de ignore (`git check-ignore -v`) e das citacoes de ADR/RUNBOOK por linha.
- **Duas rodadas de grill** (Q1 a Q4): a primeira com tres perguntas (mecanismo
  do gatilho, comando do composer, deploy manual), a segunda com o desenho final
  do script (os blocos de dependencia e da trava, e o `--force`) para conferencia
  antes de escrever, mais a Q4 (onde o commit viaja). Nenhuma resposta divergiu
  do recomendado.

## 5. Decisoes das rodadas

| # | Pergunta | Resposta |
|---|---|---|
| Q1 | Como o deploy decide que as dependencias mudaram | Hash guardado do manifesto (`sha256` de `composer.json`/`package.json` contra `storage/framework/{composer,npm}-deps.sha256`), escrito so depois de instalacao concluida |
| Q2 | O que o composer roda quando o manifesto mudou | `composer install`; se ele recusar o lock do servidor, `composer update` |
| Q3 | O deploy manual entra nesta tarefa | Sim: `--force` no script (pula a checagem de HEAD; o cron nao passa a flag) |
| Q4 | Onde o commit viaja | Na propria `develop_natan`, junto da F3 |
