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
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "==> Deployed $(git rev-parse --short HEAD)"
