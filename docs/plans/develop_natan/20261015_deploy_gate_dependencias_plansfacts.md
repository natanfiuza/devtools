# Gate de dependencias no deploy (composer.json + package.json)

Sessao de grill (skill `grill-with-docs`), pedido: "Altere o arquivo
deploy\deploy.sh e prepare ele igual ao script
C:\Users\nataniel\projetos\cultivve\Cultivve-Consult_v2\.server\script_deploy.sh
que verifica se tem alguma alteração para executar o composer install e npm
install". Branch `develop_natan`. Duas rodadas de perguntas (**Q1 a Q4**), todas
respondidas pelo usuario, com o desenho do script conferido por ele antes de ser
escrito. Fatos em
`docs/survey/20261015_deploy_gate_dependencias_surveyfacts.md`.

## Context

O `deploy.sh` roda `composer install` em **todo** deploy e o `npm install` so
quando o hash do `package.json` muda. O pedido e levar o mesmo gate para o
composer, como no projeto de referencia - com uma diferenca de mecanismo
decidida na Q1: o de la decide por `git diff HEAD origin/main`, que fica cego
depois de um deploy que falhou no meio; aqui o gatilho e o hash guardado, que o
repo ja usa no npm (o gate nasceu em `172aea0` sobre o `package-lock.json`) e
que se auto-conserta. Duas pendencias de documentacao entram junto, porque este
trabalho as toca: o ADR-014 descreve o contrario do que acontece (a frase do
lock) e a receita de deploy manual do RUNBOOK nao funciona como esta escrita
(o script sai na hora quando `HEAD == origin/main`).

## Decisoes (duas rodadas)

| # | Decisao |
|---|---|
| Q1 | Gatilho dos dois manifestos = `sha256` guardado em `storage/framework/{composer,npm}-deps.sha256`, escrito so depois de instalacao concluida |
| Q2 | `composer.json` mudou -> `composer install --no-dev --optimize-autoloader -q`; se ele recusar o lock do servidor (exit 4, comprovado no probe), cai para `composer update --no-dev --optimize-autoloader -q` |
| Q3 | `--force` (primeiro argumento): pula a checagem de HEAD e refaz o corpo inteiro; o cron nao passa a flag; com a trava ocupada, o `--force` loga e sai 1 |
| Q4 | O commit viaja na propria `develop_natan`, junto da F3 |

Miudezas decididas pelo assistente (visiveis na rodada 2, nenhuma vetada):
`composer-deps.sha256` (simetrico ao `npm-deps.sha256`); `*.sha256` no
`storage/framework/.gitignore`; log explicito quando a etapa e pulada (espelha o
"Pulando etapa" da referencia); o lado npm **nao** ganha queda para `update` (o
`npm install` reconcilia o lock em vez de recusar); rede de seguranca
`|| [ ! -d vendor ]` / `|| [ ! -d node_modules ]`; o `log()` sobe para antes do
`flock` (a trava ocupada precisa poder falar); comentario de cabecalho do script
com a linha do `--force`.

## O script depois da mudanca

Argumento e trava:

```bash
FORCE="${1:-}"          # --force: refaz o deploy inteiro sem commit novo

log() { printf '%s %s\n' "$(date -Is)" "$*"; }   # sobe para antes do flock

exec 9>"$LOCK_FILE"

# A trava e do cron: duas execucoes ao mesmo tempo brigariam pelos symlinks.
if ! flock -n 9; then
    if [ "$FORCE" = "--force" ]; then
        log "Outra execucao em andamento: o --force nao rodou."
        exit 1
    fi

    exit 0
fi
```

```bash
if [ "$FORCE" != "--force" ] && [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$BRANCH")" ]; then
    exit 0
fi
```

Dependencias (substitui o `composer install` incondicional e o gate do npm):

```bash
# --- dependencias ----------------------------------------------------------
# O gatilho dos dois manifestos e o hash guardado, escrito so depois de uma
# instalacao que terminou bem (ADR-014): um deploy que morreu no meio deixa o
# hash para tras, e o proximo deploy refaz o que faltou. O `git diff` contra o
# commit anterior nao serve - depois do pull o HEAD ja mudou, e o commit
# seguinte que nao toca o manifesto nao apareceria no diff.
COMPOSER_HASH="$(sha256sum composer.json | cut -d' ' -f1)"
STORED_COMPOSER_HASH="$(cat storage/framework/composer-deps.sha256 2>/dev/null || true)"

if [ "$COMPOSER_HASH" != "$STORED_COMPOSER_HASH" ] || [ ! -d vendor ]; then
    log "composer.json mudou: composer install"

    # O composer.lock do servidor nao acompanha o composer.json (nenhum dos
    # dois e versionado): quando o manifesto pede um pacote que o lock nao
    # tem, o install recusa o lock (exit 4) antes de instalar, e a arvore
    # precisa ser resolvida de novo.
    if ! composer install --no-dev --optimize-autoloader -q; then
        log "AVISO: o install recusou o lock do servidor; caindo para composer update"
        composer update --no-dev --optimize-autoloader -q
    fi

    printf '%s\n' "$COMPOSER_HASH" > storage/framework/composer-deps.sha256
else
    log "composer.json sem mudanca: composer install pulado"
fi

# --- dependencias de front -------------------------------------------------
DEPS_HASH="$(sha256sum package.json | cut -d' ' -f1)"
STORED_HASH="$(cat storage/framework/npm-deps.sha256 2>/dev/null || true)"

if [ "$DEPS_HASH" != "$STORED_HASH" ] || [ ! -d node_modules ]; then
    log "package.json mudou: npm install"
    npm install --no-audit --no-fund
    printf '%s\n' "$DEPS_HASH" > storage/framework/npm-deps.sha256
else
    log "package.json sem mudanca: npm install pulado"
fi
```

## Arquivos

| Arquivo | Mudanca |
|---|---|
| `deploy/deploy.sh` | argumento `--force`, `log()` antes da trava, checagem de HEAD condicionada, gate do composer (install -> update) e log de etapa pulada nos dois gates |
| `storage/framework/.gitignore` | `*.sha256` (o arquivo de estado do npm esta fora do git por acidente hoje) |
| `docs/adr/ADR-014-deploy.md` | emenda: a frase do lock, o gatilho por hash dos dois manifestos, o `--force` e a consequencia da falha no meio |
| `docs/RUNBOOK.md` | secao 2: comandos de dependencia na lista do que o script faz, o passo manual com `--force`, e "Quando o deploy falha no meio" com a receita que funciona |
| `CHANGELOG.md` | secao nova do deploy |
| `docs/claude-code/reports/develop_natan/20261015_develop_natan_deploy_gate_dependencias.md` | relatorio da tarefa |

## Verificacao

1. `bash -n deploy/deploy.sh` (sintaxe).
2. Harness descartavel em `/tmp` (fora do repo): copia do script com `APP_DIR` e
   o `PATH` reapontados, binarios stubados (`git`, `composer`, `npm`, `npx`,
   `php`, `flock`) e os caminhos exercitados: nada mudou; manifesto mudou e o
   install passou; install recusou e o update rodou; `--force` com
   `HEAD == origin`; trava ocupada com e sem `--force`.
3. A prova final e o `deploy.log` do servidor depois do merge - e o primeiro
   deploy depois desta mudanca roda as duas instalacoes (os arquivos de estado
   ainda nao existem la) e publica a F3 inteira.

## Riscos

- O `composer update` da queda re-resolve a arvore inteira **apenas quando o
  install recusa** (o caso do pacote novo); quando o lock ainda serve, a arvore
  travada do servidor fica onde esta.
- O script so roda no servidor: o harness prova a logica, nao o ambiente
  (flock, symlinks, supervisor).
- O gate do composer deixa de rodar `composer install` em todo deploy - a
  contrapartida de um `vendor/` que nao se atualiza sozinho esta coberta pelo
  hash (so existe apos instalacao concluida) e pela rede de seguranca
  `[ ! -d vendor ]`.

## Pendencias

- O commit e o PR sao do usuario (nada e commitado pelo assistente). O merge em
  `main` e o primeiro deploy tambem: conferir `tail
  storage/logs/deploy.log` com "Deploy concluido" e a home traduzida.
