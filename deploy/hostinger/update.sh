#!/usr/bin/env bash
set -euo pipefail

# Run on Hostinger after git sync (manually or via GitHub Actions).
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer2}"

echo "==> Fetching latest main"
git fetch origin main
git reset --hard origin/main

echo "==> Ensuring public/.htaccess rewrite base"
if [ -f deploy/hostinger/public.htaccess ] && [ ! -f public/.htaccess ]; then
    cp deploy/hostinger/public.htaccess public/.htaccess
fi
sed -i 's#RewriteBase /BACS/public/#RewriteBase /#' public/.htaccess

echo "==> Installing PHP dependencies"
# --no-scripts avoids artisan hooks that require proc_open on shared hosting.
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-scripts

echo "==> Running migrations"
$PHP artisan migrate --force

echo "==> Caching config, routes, and views"
$PHP artisan config:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

if grep -rq '/BACS/public/build/' public/build/assets/*.js 2>/dev/null; then
    echo "WARNING: Vite bundle uses local XAMPP asset paths (/BACS/public/build/). Run npm run build in the repo and push public/build."
fi

MAPBOX_LEN="$($PHP artisan tinker --execute="echo strlen(trim((string) \\App\\Support\\MapboxSettings::accessToken()));" 2>/dev/null || echo 0)"
if [ "${MAPBOX_LEN:-0}" = "0" ]; then
    echo "WARNING: Mapbox token not configured — Station Locations map will show \"Map unavailable\"."
    echo "         Set MAPBOX_ACCESS_TOKEN=pk.... in .env on this server, or save the token under Admin -> Settings -> Station location map."
fi

echo "==> Deployed $(git rev-parse --short HEAD)"
