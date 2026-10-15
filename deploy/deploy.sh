#!/usr/bin/env bash
#
# Deploy do devtools.natanfiuza.dev.br.
#
# Roda pelo cron a cada minuto. Sai na hora quando o HEAD nao mudou, e usa
# flock para nunca rodar duas vezes ao mesmo tempo.
#
# Decisoes que estao neste arquivo (ver docs/adr/ADR-014-deploy.md):
#   - os assets sao construidos aqui no servidor (public/build nao vai para o git);
#   - o build vai para uma release propria e a troca e um rename de symlink:
#     nunca existe um instante com HTML novo e assets ausentes, e o HTML que
#     ja esta no navegador de alguem continua encontrando seus arquivos;
#   - composer install e npm install so rodam quando o manifesto muda (o npm
#     install leva minutos);
#   - `--force` refaz o deploy inteiro sem commit novo (uso manual).
#
# Nao usar: este script e para o servidor. Em dev use npm run dev.

set -euo pipefail

# O cron nao herda o PATH do shell de login: sem isto, php/node/composer
# simplesmente nao existem para o script.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

APP_DIR=/var/www/devtools
BRANCH=main
RELEASES_DIR="$APP_DIR/storage/app/builds"
KEEP_RELEASES=3

# `--force` e o caminho manual: depois de uma falha no meio do deploy o HEAD ja
# avancou e uma execucao normal sairia na hora. O cron nunca passa a flag.
FORCE="${1:-}"

cd "$APP_DIR"

log() { printf '%s %s\n' "$(date -Is)" "$*"; }

# Uma execução por vez, tanto manualmente quanto pelo cron.
LOCK_DIR="$APP_DIR/storage/framework/locks"
LOCK_FILE="$LOCK_DIR/deploy.lock"

mkdir -p "$LOCK_DIR"

exec 9>"$LOCK_FILE"

# Sem a trava o script sai calado (e o certo para o cron); quando alguem pediu
# --force, o silencio esconderia que nada rodou.
if ! flock -n 9; then
    if [ "$FORCE" = "--force" ]; then
        log "Outra execucao em andamento: o --force nao rodou."
        exit 1
    fi

    exit 0
fi

for bin in php composer node npm git; do
    if ! command -v "$bin" >/dev/null 2>&1; then
        log "ERRO: '$bin' nao esta no PATH ($PATH). Deploy abortado."
        exit 1
    fi
done

git fetch -q origin "$BRANCH"

if [ "$FORCE" != "--force" ] && [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$BRANCH")" ]; then
    exit 0
fi

log "Deploy iniciado: $(git rev-parse --short "origin/$BRANCH")"

git pull -q --ff-only "origin/$BRANCH"

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
# O package-lock.json nao e versionado (ADR-014): o gatilho e o package.json,
# e quem resolve a arvore de dependencias e o npm install. A contrapartida
# assumida e que o build no servidor deixa de ser reproduzivel.
DEPS_HASH="$(sha256sum package.json | cut -d' ' -f1)"
STORED_HASH="$(cat storage/framework/npm-deps.sha256 2>/dev/null || true)"

if [ "$DEPS_HASH" != "$STORED_HASH" ] || [ ! -d node_modules ]; then
    log "package.json mudou: npm install"
    npm install --no-audit --no-fund
    printf '%s\n' "$DEPS_HASH" > storage/framework/npm-deps.sha256
else
    log "package.json sem mudanca: npm install pulado"
fi

# --- build + troca atomica -------------------------------------------------
RELEASE="$RELEASES_DIR/$(date -u +%Y%m%d%H%M%S)"
mkdir -p "$RELEASE"

log "Build do cliente e do SSR em $RELEASE"
npm run build -- --outDir "$RELEASE/build" --emptyOutDir
npx vite build --ssr --outDir "$RELEASE/ssr" --emptyOutDir

# public/build e bootstrap/ssr passam a apontar para a release nova. O rename
# de um symlink e atomico: quem chegar no meio ve a versao antiga inteira.
# (Na primeira execucao os dois sao diretorios de verdade e saem da frente.)
[ -L public/build ] || rm -rf public/build
[ -L bootstrap/ssr ] || rm -rf bootstrap/ssr

ln -sfn "$RELEASE/build" public/build.tmp
mv -T public/build.tmp public/build

ln -sfn "$RELEASE/ssr" bootstrap/ssr.tmp
mv -T bootstrap/ssr.tmp bootstrap/ssr

# Releases antigas so saem depois da troca (quem tem o HTML antigo ainda usa).
ls -1dt "$RELEASES_DIR"/* 2>/dev/null | tail -n "+$((KEEP_RELEASES + 1))" | xargs -r rm -rf

# --- banco e caches --------------------------------------------------------
php artisan migrate --force
php artisan optimize

# --- processos -------------------------------------------------------------
php artisan queue:restart

# O SSR le o bundle no boot: reinicia para pegar a release nova. O programa
# do supervisor tem autorestart, entao basta encerrar o processo.
if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl restart devtools-ssr >/dev/null || log "AVISO: falha ao reiniciar o SSR"
else
    pkill -f 'inertia:start-ssr' || log "AVISO: processo do SSR nao encontrado"
fi

log "Deploy concluido: $(git rev-parse --short HEAD)"
