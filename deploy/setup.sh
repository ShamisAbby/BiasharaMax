#!/usr/bin/env bash
#
# BiasharaMax — first-run setup on Hostinger shared hosting.
#
# Run this ONCE, on the server, after cloning the repository. It is safe to
# re-run: every step either does nothing or does the same thing again.
#
#   ssh -p 65002 u713307449@77.37.37.66
#   cd ~/biasharamax && bash deploy/setup.sh
#
# It deliberately does NOT: create the database, write secrets, set the
# document root, or install the cron entries. Those need either hPanel or a
# password, and a script that asks for a password is a script that ends up
# with the password in someone's shell history.

set -euo pipefail

# `set -e` alone is not enough. Without this trap a failure part-way leaves
# the app half-migrated with no indication which step stopped, and the next
# person re-runs from the top and hits a different error.
trap 'echo; echo "FAILED at line $LINENO. Nothing after this point ran."; exit 1' ERR

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
APP="$ROOT/backend"

echo "BiasharaMax setup"
echo "  repository: $ROOT"
echo

# ---------------------------------------------------------------------
# PHP. Hostinger's default `php` on the command line is often an older
# build than the one serving the site, and Laravel 12 needs 8.2+. Picking
# the wrong one fails deep inside composer with an error about a syntax
# feature, which points nowhere useful.
# ---------------------------------------------------------------------
PHP_BIN="${PHP_BIN:-}"

if [ -z "$PHP_BIN" ]; then
    for candidate in /usr/bin/php8.3 /usr/bin/php8.2 /opt/alt/php83/usr/bin/php /opt/alt/php82/usr/bin/php php; do
        if command -v "$candidate" >/dev/null 2>&1; then
            version="$("$candidate" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo 0)"
            if [ "$(printf '%s\n8.2\n' "$version" | sort -V | head -1)" = "8.2" ]; then
                PHP_BIN="$candidate"
                break
            fi
        fi
    done
fi

if [ -z "$PHP_BIN" ]; then
    echo "No PHP 8.2 or newer found on PATH."
    echo "Set the PHP version in hPanel, or re-run as: PHP_BIN=/path/to/php bash deploy/setup.sh"
    exit 1
fi

echo "  php:        $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# ---------------------------------------------------------------------
# Composer. Hostinger usually ships it; falling back to a local phar keeps
# this working on plans that do not.
# ---------------------------------------------------------------------
if command -v composer >/dev/null 2>&1; then
    COMPOSER="composer"
elif [ -f "$ROOT/composer.phar" ]; then
    COMPOSER="$PHP_BIN $ROOT/composer.phar"
else
    echo "  composer:   not found, downloading composer.phar"
    curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir="$ROOT" --filename=composer.phar
    COMPOSER="$PHP_BIN $ROOT/composer.phar"
fi

echo "  composer:   $COMPOSER"
echo

cd "$APP"

# ---------------------------------------------------------------------
# The .env has to exist before anything else. Every command below reads
# it, and Laravel's failure without one is a 500 with no message.
# ---------------------------------------------------------------------
if [ ! -f .env ]; then
    cp "$ROOT/deploy/.env.production.example" .env
    echo "Created backend/.env from the template."
    echo
    echo "STOP. Fill in the blanks before continuing:"
    echo "  nano $APP/.env"
    echo
    echo "You need: DB_DATABASE, DB_USERNAME, DB_PASSWORD, MAIL_PASSWORD,"
    echo "SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD, PLATFORM_ALERT_RECIPIENTS."
    echo "Then run this script again."
    exit 0
fi

# Refuse to go further on a half-filled file rather than failing later with
# "could not connect", which sends people looking at the database instead.
missing=""
for key in DB_DATABASE DB_USERNAME DB_PASSWORD; do
    value="$(grep -E "^${key}=" .env | head -1 | cut -d= -f2- | tr -d '"' | xargs || true)"
    [ -z "$value" ] && missing="$missing $key"
done

if [ -n "$missing" ]; then
    echo "backend/.env is missing values for:$missing"
    echo "Fill them in and run this script again."
    exit 1
fi

echo "==> Installing PHP dependencies"
# --no-dev matters: dev packages include debugging tools that should not
# exist on a public server at all.
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo
echo "==> Application key"
if ! grep -qE '^APP_KEY=base64:' .env; then
    "$PHP_BIN" artisan key:generate --force
    echo "    generated"
else
    echo "    already set, left alone"
fi

echo
echo "==> Storage symlink"
"$PHP_BIN" artisan storage:link || echo "    already linked"

echo
echo "==> Permissions"
chmod -R 775 storage bootstrap/cache

echo
echo "==> Database migrations"
# --force because artisan refuses to migrate in production without it.
# That prompt exists for a reason; this script is the deliberate answer.
"$PHP_BIN" artisan migrate --force

echo
echo "==> Reference data"
# Each of these is idempotent (updateOrCreate / firstOrCreate), so a
# re-run refreshes them without duplicating or clobbering live edits.
for seeder in PermissionSeeder BusinessTypeSeeder CurrencySeeder CountrySeeder NotificationChannelSeeder NotificationTemplateSeeder PlatformRoleSeeder PlatformUserSeeder; do
    echo "    $seeder"
    "$PHP_BIN" artisan db:seed --class="$seeder" --force
done

echo
echo "==> Filament assets"
"$PHP_BIN" artisan filament:assets

echo
echo "==> Caching config, routes and views"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo
echo "==> Checking the built front-end is present"
if [ ! -f public/build/manifest.json ]; then
    echo
    echo "  public/build/manifest.json is missing."
    echo "  Every page will fail to load its CSS and JavaScript."
    echo
    echo "  On your Mac:  cd backend && npm run build"
    echo "  then commit public/build and pull again here."
    exit 1
fi
echo "    found"

echo
echo "Setup complete."
echo
echo "Still to do by hand — see deploy/DEPLOYMENT.md:"
echo "  1. Point the domain's document root at $APP/public (hPanel)"
echo "  2. Add the two cron entries (scheduler and queue)"
echo "  3. Enable HTTPS for biasharamax.com"
