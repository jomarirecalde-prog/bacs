#!/usr/bin/env bash
set -euo pipefail

# Run on Hostinger over SSH after cloning the repo into public_html (or your domain folder).
# Example:
#   cd ~/domains/your-domain.com/public_html
#   git clone git@github.com:jomarirecalde-prog/bacs.git .
#   bash deploy/hostinger/setup.sh

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"

echo "==> BACS Hostinger setup in ${ROOT}"

if ! command -v "$PHP" >/dev/null 2>&1; then
    echo "PHP not found. Try: PHP_BIN=/usr/bin/php bash deploy/hostinger/setup.sh"
    exit 1
fi

if ! command -v "$COMPOSER" >/dev/null 2>&1; then
    if [ -f composer.phar ]; then
        COMPOSER="$PHP composer.phar"
    else
        echo "Composer not found. Install it or set COMPOSER_BIN."
        exit 1
    fi
fi

echo "==> Installing shared-hosting .htaccess files"
cp deploy/hostinger/root.htaccess .htaccess
cp deploy/hostinger/public.htaccess public/.htaccess

echo "==> Installing PHP dependencies"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

if [ ! -f .env ]; then
    echo "==> Creating .env from deploy/hostinger/env.example"
    cp deploy/hostinger/env.example .env
    echo "    Edit .env with your Hostinger MySQL credentials and APP_URL before continuing."
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    echo "==> Generating APP_KEY"
    $PHP artisan key:generate --force
fi

echo "==> Linking public storage"
$PHP artisan storage:link --force 2>/dev/null || true

echo "==> Running migrations"
$PHP artisan migrate --force

echo "==> Caching config/routes/views"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "==> Setting writable directories"
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

echo
echo "Done."
echo "Next:"
echo "  1. Set PHP 8.2+ in hPanel -> Advanced -> PHP Configuration"
echo "  2. Fill MYSQL_* and APP_URL in .env"
echo "  3. Open https://your-domain.com/login"
