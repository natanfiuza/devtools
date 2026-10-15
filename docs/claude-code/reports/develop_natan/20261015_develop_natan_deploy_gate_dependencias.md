# Relatorio - deploy com dependencias sob gatilho (composer + npm) e `--force`

Branch: `develop_natan`
Data: 2026-10-15
Tarefa: "Altere o arquivo deploy\deploy.sh e prepare ele igual ao script
`.server/script_deploy.sh` (Cultivve-Consult_v2) que verifica se tem alguma
alteracao para executar o composer install e npm install"
Plano: `docs/plans/develop_natan/20261015_deploy_gate_dependencias_plansfacts.md`
Levantamento: `docs/survey/20261015_deploy_gate_dependencias_surveyfacts.md`

**Nada foi commitado pelo assistente.** As alteracoes estao no working tree;
o commit e o PR ficam com o usuario, pelo gitpr (CLAUDE.md).

## 1. O que foi pedido

O `deploy/deploy.sh` rodava `composer install` em **todo** deploy (com o
`npm install` ja sob um gate de hash, desde `172aea0`). O pedido: usar o mesmo
padrao do script de referencia do projeto Cultivve, que so instala dependencia
quando o manifesto mudou - e fazer isso para as duas instalacoes.

O script de referencia foi lido inteiro. O mecanismo dele e
`git diff --name-only HEAD~1 HEAD | grep composer.json` - um diff **contra o
commit anterior**. Aqui isso nao serve: o script da `git pull --ff-only` antes
de decidir, entao quando o teste rodasse o `HEAD` ja teria avancado e o diff
viria vazio. A ideia do gate foi adotada; o mecanismo nao.

## 2. Metodo

- **Duas rodadas de grill** (Q1 a Q4), com a fronteira recalculada depois das
  respostas da primeira. Facts levantados por mim antes de cada pergunta
  (leitura do script de referencia, do script atual, do ADR-014, do RUNBOOK,
  historico do `deploy.sh` por `git log`/`git show`).
- **Probe do comportamento do composer** em `/tmp/composer-probe`: um
  `composer.json` com pacote novo + um `composer.lock` velho -> `composer
  install` sai com **exit 4** (`Required package "symfony/dom-crawler" is not
  present in the lock file.`) **sem instalar nada**, e `composer update`
  resolve e reescreve o lock. Esse fato e o que sustenta a queda para o
  `update` no script.
- **Decisao do usuario (Q1)**: o gatilho e **hash guardado do manifesto**, nao
  `git diff` - o diff contra o commit anterior e cego depois do pull, e o
  commit seguinte que nao toca o manifesto tambem nao apareceria.
- **Q2**: quando o `composer.json` muda -> `composer install` primeiro e, se
  ele recusar o lock, `composer update`.
- **Q3**: o deploy manual entra na tarefa -> flag `--force`.
- **Q4** (confirmada com "ok"): o commit viaja na `develop_natan`.
- Implementacao em quatro edits no `deploy.sh`, um no `storage/framework/.gitignore`,
  e depois as docs. Verificacao por harness descartavel com binarios stubados
  (secao 5).

## 3. O que o script passou a fazer

### 3.1 Dependencias sob gatilho (o pedido)

```bash
COMPOSER_HASH="$(sha256sum composer.json | cut -d' ' -f1)"
STORED_COMPOSER_HASH="$(cat storage/framework/composer-deps.sha256 2>/dev/null || true)"

if [ "$COMPOSER_HASH" != "$STORED_COMPOSER_HASH" ] || [ ! -d vendor ]; then
    log "composer.json mudou: composer install"

    if ! composer install --no-dev --optimize-autoloader -q; then
        log "AVISO: o install recusou o lock do servidor; caindo para composer update"
        composer update --no-dev --optimize-autoloader -q
    fi

    printf '%s\n' "$COMPOSER_HASH" > storage/framework/composer-deps.sha256
else
    log "composer.json sem mudanca: composer install pulado"
fi
```

O `npm install` ja tinha o mesmo desenho (`storage/framework/npm-deps.sha256`);
nesta tarefa ele ganhou o `|| [ ! -d node_modules ]` e o log da etapa pulada.

Tres propriedades que valem registrar:

- **O hash so e gravado depois de uma instalacao que terminou bem.** Se o
  deploy morrer no meio, o hash nao avanca e a proxima execucao **refaz** o que
  faltou. Isso e o que torna o `--force` (abaixo) um conserto de verdade.
- **`vendor`/`node_modules` ausentes reabrem o gate** mesmo com o hash batendo
  (servidor novo, diretorio apagado na mao).
- **O install contra o lock do servidor** e o caso normal, mas nao confiavel:
  nem o `composer.lock` nem o `package-lock.json` sao versionados, entao o lock
  do servidor e o do ultimo install e pode nao conter o que o manifesto novo
  pede - dai a queda para o `update`.

### 3.2 `--force` (o deploy manual)

O script sai na hora quando `HEAD == origin/main` - e e por isso que uma falha
no meio do deploy era definitiva: o `pull` ja moveu o HEAD e o cron nunca
retenta. O RUNBOOK mandava "rodar o script a mao depois de resolver a causa",
receita que **nao funcionava** (a execucao manual batia no mesmo early exit).
Com a flag:

```bash
FORCE="${1:-}"
...
if [ "$FORCE" != "--force" ] && [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$BRANCH")" ]; then
    exit 0
fi
```

Com a trava ocupada e `--force`, o script **avisa e sai com codigo 1** (o cron
segue saindo calado com 0, que e o certo para ele). O cron nunca passa a flag.

### 3.3 `.gitignore`

`storage/framework/.gitignore` lista os nomes um a um (sem curinga), entao os
dois `*-deps.sha256` apareciam como untracked-no-git-mas-nao-ignorados
(`git check-ignore` exit 1 nos dois). Foi acrescentado `*.sha256`.

## 4. Decisoes (tabela)

| # | Pergunta | Resposta |
|---|---|---|
| Q1 | Como o deploy decide que as dependencias mudaram? | **Hash guardado do manifesto** (`storage/framework/<nome>-deps.sha256`) |
| Q2 | Quando o `composer.json` muda, o que roda? | `composer install` e, se ele recusar o lock, `composer update` |
| Q3 | O deploy manual entra na tarefa? | Sim: flag `--force` |
| Q4 | Onde o commit viaja? | Na `develop_natan` (confirmado com "ok") |

## 5. Verificacao

### 5.1 Sintaxe

`bash -n deploy/deploy.sh` -> OK (e tambem na copia reapontada do harness).

### 5.2 Harness descartavel (50 asseracoes, todas PASS)

`deploy/deploy.sh` nao pode rodar de verdade aqui (git pull, build, migrate,
supervisor). O harness vive em `%TEMP%\dh-harness.sh` - **fora do repo, nao
versionado**, feito para jogar fora - e roda o script real com `APP_DIR` e
`PATH` reapontados por `sed` para stubs de `git`, `composer`, `npm`, `npx`,
`php`, `node`, `pkill` e `flock`, com os exit codes controlados por arquivo.

| Cenario | O que prova |
|---|---|
| S1 | Nada mudou: sai na hora, nao chama binario nenhum |
| S2 | Os dois manifestos mudam: install + npm install rodam, os dois hashes sao gravados |
| S3 | Commit sem tocar manifesto: os dois logam "pulado", o build roda assim mesmo |
| S4 | Install recusa o lock (exit 4): `AVISO... caindo para composer update`, update roda e o hash e gravado |
| S5a/S5b | Sem `--force` sai na hora; com `--force` o corpo roda com `HEAD == origin` |
| S6/S7 | Trava ocupada: cron sai calado com 0; `--force` avisa e sai com 1 |
| S8 | Install **e** update falham: exit 1, sem build, **hash nao escrito** |
| S9 | `vendor/` ausente reabre o gate mesmo com o hash batendo |

### 5.3 Achado do harness (artifact do Windows, nao bug do script)

As primeiras execucoes quebravam em `ln -sfn <release>/build public/build.tmp`
com `No such file or directory`. Causa: o **Git Bash/MSYS emula symlink
copiando o alvo**, e o stub do npm nao criava a pasta de saida - no Linux o
`ln -s` aceita alvo pendente e o build real sempre cria o diretorio. Corrigido
no harness (os stubs de `npm`/`npx` fazem `mkdir -p` do `--outDir`), nao no
script.

### 5.4 O que NAO foi verificado aqui

O caminho real no VPS (composer/npm de verdade, supervisor, SSR) so roda no
proximo deploy. O primeiro deploy depois deste commit vai rodar **as duas
instalacoes**, porque os arquivos de estado nao existem no servidor.

## 6. Docs atualizadas

- **`docs/adr/ADR-014-deploy.md`** (4 emendas): linha de "Atualizado" de
  2026-10-15; a Decisao passa a falar em dependencias "sob gatilho" (e do
  `--force`); o paragrafo de dependencias foi reescrito (hash por manifesto,
  hash so depois do sucesso, exit 4 -> `update`); e a consequencia da falha no
  meio agora aponta o conserto certo (`deploy.sh --force` ou commit novo) no
  lugar do "unico conserto e entrar commit novo". O texto antigo dizia que,
  sem lockfile, o `composer install` "resolve a arvore na hora" - o servidor
  tem o lock do ultimo install, entao isso descrevia o contrario do que
  acontece.
- **`docs/RUNBOOK.md`** secao 2 (5 pontos): bloco de comandos com `--force`;
  "O que ele faz" com os dois gates, o `*-deps.sha256` e a regra do hash;
  explicacao de por que o gatilho nao e o lockfile; a trava com o aviso do
  `--force`; a receita de conserto da falha no meio (a antiga **nao
  funcionava**); e a tabela de sintomas ("o cron **nao retenta sozinho** -
  `deploy.sh --force` refaz").
- **`CHANGELOG.md`**: secao nova `## Deploy - dependencias sob gatilho
  (composer + npm) - 2026-10-15` (Adicionado / Alterado / Corrigido).
- **`docs/plans/.../20261015_deploy_gate_dependencias_plansfacts.md`** e
  **`docs/survey/20261015_deploy_gate_dependencias_surveyfacts.md`** (salvos
  pelo grill-with-docs ao fechar o entendimento).

## 7. Arquivos no working tree

```
 M CHANGELOG.md
 M deploy/deploy.sh
 M docs/RUNBOOK.md
 M docs/adr/ADR-014-deploy.md
 M storage/framework/.gitignore
?? docs/plans/develop_natan/20261015_deploy_gate_dependencias_plansfacts.md
?? docs/survey/20261015_deploy_gate_dependencias_surveyfacts.md
```

(`.gitpr/reports/pr_desc/develop_natan_20261015060910_PR_DESC.md` tambem esta
untracked, mas e da tarefa anterior - a F3 - e nao faz parte desta.)

## 8. Notas de percurso

- A quarta emenda do ADR-014 falhou na primeira tentativa: o `old_string` usava
  hifens onde o arquivo tem **travessao (—)**. Refeita com o trecho exato.
- O harness inicial se apagava no proprio `rm -rf "$D"` (morava dentro de `$D`);
  a versao que roda ficou **fora** de `$D`, com um comentario explicando.
- As unicas mensagens do IDE sobre o `deploy.sh` sao do spell-checker marcando
  palavras em portugues (severity "Information") - ignoradas.
- Os avisos `MD024/no-duplicate-heading` no `CHANGELOG.md` sao do formato ja
  usado em todas as secoes do arquivo (toda entrega repete `### Adicionado`),
  nao da secao nova.

## 9. Pendente

- **O commit e o PR sao do usuario** (gitpr). O assistente nao commita.
- Conferir no proximo deploy do VPS: `deploy.log` com "composer.json mudou" +
  "package.json mudou" (os dois, na primeira vez) e "Deploy concluido"; nas
  execucoes seguintes, "sem mudanca: ... pulado".
