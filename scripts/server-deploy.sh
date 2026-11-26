#!/usr/bin/env bash
# Runs ON THE SERVER — started by scripts/deploy.ps1 after it has pulled the new code
# and put the site in maintenance mode. Don't run it by hand unless you know why.
#
#   bash scripts/server-deploy.sh /tmp/fashionstore-build.tgz <expected-commit>
set -euo pipefail

APP=/var/www/fashionstore
BUILD_TGZ=${1:?path to the uploaded build archive}
EXPECTED=${2:-}

cd "$APP"
step() { printf '\n--> %s\n' "$*"; }

if [ -n "$EXPECTED" ] && [ "$(git rev-parse HEAD)" != "$EXPECTED" ]; then
    echo "Server is at $(git rev-parse --short HEAD) but the build was made from ${EXPECTED:0:7}." >&2
    exit 1
fi

step "PHP packages"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress

step "CSS/JS built on the PC"
rm -rf public/build.new
mkdir public/build.new
tar -xzf "$BUILD_TGZ" -C public/build.new --strip-components=1
[ -f public/build.new/manifest.json ] || { echo "Uploaded build has no manifest.json" >&2; exit 1; }
rm -rf public/build.old
[ -d public/build ] && mv public/build public/build.old
mv public/build.new public/build
rm -rf public/build.old "$BUILD_TGZ"

step "Database"
php artisan migrate --force

step "Caches"
php artisan optimize
php artisan filament:optimize

step "Permissions + reload PHP (opcache does not watch files)"
chown -R www-data:www-data storage bootstrap/cache
systemctl reload php8.4-fpm

echo
echo "Deployed $(git log --oneline -1)"
