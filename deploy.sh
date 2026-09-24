#!/usr/bin/env bash
# Deploy auf srv.iksf.de als Benutzer leawernli.ch
set -euo pipefail
cd /var/www/vhosts/leawernli.ch/app.leawernli.ch
PHP=/opt/plesk/php/8.4/bin/php
COMPOSER="$PHP /opt/psa/var/modules/composer/composer.phar"

git pull --ff-only
$COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$PHP artisan migrate --force
[ -x bin/build-css ] && bin/build-css
$PHP artisan filament:assets
$PHP artisan optimize:clear
$PHP artisan optimize
$PHP artisan filament:optimize || true
echo "Deploy fertig: $(git log -1 --oneline)"
