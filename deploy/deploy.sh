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
#   - npm ci so roda quando o package-lock.json muda (leva minutos).
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

cd "$APP_DIR"

# Uma execução por vez, tanto manualmente quanto pelo cron.
LOCK_DIR="$APP_DIR/storage/framework/locks"
LOCK_FILE="$LOCK_DIR/deploy.lock"

mkdir -p "$LOCK_DIR"

exec 9>"$LOCK_FILE"
flock -n 9 || exit 0

log() { printf '%s %s\n' "$(date -Is)" "$*"; }

for bin in php composer node npm git; do
    if ! command -v "$bin" >/dev/null 2>&1; then
        log "ERRO: '$bin' nao esta no PATH ($PATH). Deploy abortado."
        exit 1
    fi
done

git fetch -q origin "$BRANCH"

if [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$BRANCH")" ]; then
    exit 0
fi

log "Deploy iniciado: $(git rev-parse --short "origin/$BRANCH")"

git pull -q --ff-only "origin/$BRANCH"

composer install --no-dev --optimize-autoloader -q

# --- dependencias de front -------------------------------------------------
LOCK_HASH="$(sha256sum package-lock.json | cut -d' ' -f1)"
STORED_HASH="$(cat storage/framework/npm-lock.sha256 2>/dev/null || true)"

if [ "$LOCK_HASH" != "$STORED_HASH" ] || [ ! -d node_modules ]; then
    log "package-lock.json mudou: npm ci"
    npm ci --no-audit --no-fund
    printf '%s\n' "$LOCK_HASH" > storage/framework/npm-lock.sha256
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
