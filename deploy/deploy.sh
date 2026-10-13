#!/usr/bin/env bash
set -euo pipefail
cd /var/www/devtools
exec 9>/tmp/devtools-deploy.lock; flock -n 9 || exit 0
git fetch -q origin main
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] && exit 0
git pull -q --ff-only origin main
composer install --no-dev --optimize-autoloader -q
php artisan migrate --force
php artisan optimize
php artisan queue:restart
