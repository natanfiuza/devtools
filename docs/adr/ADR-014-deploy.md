# ADR-014-deploy
Status: Aceito
Data: 2026-10-05
Atualizado: 2026-10-13 (estrategia de assets definida; decisao deixa de estar pendente)

## Decisao
Deploy continuo por cron: a cada minuto o `deploy/deploy.sh` roda, pega um
`flock` (uma execucao por vez), faz `git fetch` e **sai imediatamente se o HEAD
nao mudou**. Quando mudou: `git pull --ff-only`, `composer install --no-dev`,
build e `migrate --force`; no fim `queue:restart` e reinicio do SSR.

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

`npm ci` so roda quando o hash do `package-lock.json` muda (leva minutos e
quase todo deploy nao mexe em dependencia); o hash fica em
`storage/framework/npm-lock.sha256`.

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
- Nada aqui exige acesso ao servidor para publicar: commit no `main` e o cron
  leva. Por isso migration quebrada e o unico erro que nao da para desfazer
  rapido — a regra de retrocompatibilidade nao e estilo, e o que compra isso.
- Passo a passo de operacao (instalacao, supervisor, backup, rotacao de
  chaves, diagnostico) em [docs/RUNBOOK.md](../RUNBOOK.md).

## Alternativas descartadas
- **Versionar `public/build`**: historico poluido, conflito a cada merge e
  build local diferente do build do servidor.
- **Build em `public/build` direto**: e a janela de HTML novo com asset
  ausente descrita acima.
- **Publicar por rsync para um diretorio unico**: mesma janela, mais partes
  moveis.
