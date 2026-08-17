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
# build than the one serving the site.
#
# Newest first, and 8.4 is the real floor despite composer.json saying
# ^8.2. The floor is set by composer.lock, not composer.json: the lock was
# resolved on a machine running 8.4, so it pins symfony/clock, /string,
# /translation and nine others at versions requiring >= 8.4.1. An 8.2 or
# 8.3 install fails with a page of conflicts that never mentions the lock
# file, which is the actual cause.
# ---------------------------------------------------------------------
PHP_BIN="${PHP_BIN:-}"

if [ -z "$PHP_BIN" ]; then
    # 8.4 first, not the newest available.
    #
    # Two reasons, learned the hard way on this host. Laravel 12 and
    # Filament are not tested against 8.5 yet. And a newer PHP build on
    # shared hosting often has *fewer* extensions compiled in — this
    # server's 8.5 CLI is missing intl, zip and gd, so composer refuses to
    # install filament, openspout, phpspreadsheet and laravel-backup. The
    # newest interpreter is not the most capable one.
    #
    # Extensions are checked, not assumed: a candidate that cannot load
    # what the packages need is skipped rather than selected and then
    # failing several steps later with an error about a lock file.
    for candidate in /opt/alt/php84/usr/bin/php /usr/bin/php8.4 /opt/alt/php85/usr/bin/php /usr/bin/php8.5 /opt/alt/php83/usr/bin/php /usr/bin/php8.3 /opt/alt/php82/usr/bin/php /usr/bin/php8.2 php; do
        command -v "$candidate" >/dev/null 2>&1 || continue

        version="$("$candidate" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo 0)"
        [ "$(printf '%s\n8.2\n' "$version" | sort -V | head -1)" = "8.2" ] || continue

        missing="$("$candidate" -r 'echo implode(" ", array_filter(["intl","zip","gd"], fn($e) => ! extension_loaded($e)));' 2>/dev/null || echo "?")"

        if [ -n "$missing" ]; then
            echo "  skipping $candidate ($version) — missing extensions: $missing"
            continue
        fi

        PHP_BIN="$candidate"
        break
    done
fi

if [ -z "$PHP_BIN" ]; then
    echo "No PHP 8.2 or newer found on PATH."
    echo "Set the PHP version in hPanel, or re-run as: PHP_BIN=/path/to/php bash deploy/setup.sh"
    exit 1
fi

# Warn, do not stop. Some lock files resolve fine below 8.4, and refusing
# to try would be guessing on the pessimistic side.
PHP_MINOR="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
if [ "$(printf '%s\n8.4\n' "$PHP_MINOR" | sort -V | head -1)" != "8.4" ]; then
    echo
    echo "  NOTE: composer.lock contains packages requiring PHP >= 8.4.1"
    echo "  (symfony/clock, symfony/string and others). This is $PHP_MINOR."
    echo "  If the install below fails on version conflicts, that is why —"
    echo "  set PHP 8.4 in hPanel, or see DEPLOYMENT.md for pinning the lock"
    echo "  to the server's version instead."
    echo
fi

echo "  php:        $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# ---------------------------------------------------------------------
# Composer, run through the PHP we just chose.
#
# This is not a detail. Invoking bare `composer` runs it under whatever
# PHP is first on PATH — on Hostinger that is the older system build, not
# the one selected in hPanel. The first version of this script detected
# 8.3, printed "php: 8.3.30", and then handed the install to composer
# running under 8.2, which failed with a wall of version conflicts naming
# a PHP version the script had already rejected. Detecting the right tool
# and then not using it is worse than not detecting it at all.
# ---------------------------------------------------------------------
COMPOSER=""

if [ -f "$ROOT/composer.phar" ]; then
    COMPOSER="$PHP_BIN $ROOT/composer.phar"
elif command -v composer >/dev/null 2>&1; then
    COMPOSER_PATH="$(command -v composer)"

    # Only usable this way if it is a PHP script or phar. Some hosts ship
    # a shell wrapper, which PHP cannot execute.
    if "$PHP_BIN" "$COMPOSER_PATH" --version >/dev/null 2>&1; then
        COMPOSER="$PHP_BIN $COMPOSER_PATH"
    fi
fi

if [ -z "$COMPOSER" ]; then
    echo "  composer:   downloading composer.phar (so it runs under $PHP_BIN)"
    curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir="$ROOT" --filename=composer.phar
    COMPOSER="$PHP_BIN $ROOT/composer.phar"
fi

echo "  composer:   $COMPOSER"
echo "              (running under $("$PHP_BIN" -r 'echo PHP_VERSION;'))"
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
echo "==> Clearing cached config before seeding"
# Not housekeeping — a correctness requirement.
#
# Laravel skips loading .env entirely when a config cache exists, so
# `env()` outside config files returns null. PlatformUserSeeder reads
# SUPERADMIN_PASSWORD through env(), and on a re-run (when the previous
# run left a config cache behind) it reads null, prints one line, and
# creates nothing. The site comes up with no way to log in and no error
# that says why.
"$PHP_BIN" artisan config:clear

echo
echo "==> Reference data"
# DatabaseSeeder, not a hand-written list. The first version of this
# script listed eight seeders by name and silently omitted seven of them,
# including SubscriptionPlanSeeder — so the site launched with no plans,
# no role templates, no website templates and no payment gateways, and
# "Start free trial" returned 503. Duplicating an ordering that already
# exists in DatabaseSeeder was the mistake; that class also encodes which
# seeder must run before which, which a flat list here cannot.
#
# Every seeder in it is idempotent (updateOrCreate / firstOrCreate), so a
# re-run refreshes reference data without duplicating or clobbering it.
"$PHP_BIN" artisan db:seed --force

# Not in DatabaseSeeder, because adding it there would change what the
# module-gating tests see. Production needs it: without the module rows
# the vendor dashboard has nothing to gate and sections do not appear.
echo "    DashboardModuleSeeder"
"$PHP_BIN" artisan db:seed --class=DashboardModuleSeeder --force

echo
echo "==> SuperAdmin account"
# Checked here rather than left to the seeder. The seeder reports a
# missing password and returns without an error status, which inside a
# `set -e` script scrolls past as one line among forty and leaves an
# unreachable admin panel.
superadmin_password="$(grep -E '^SUPERADMIN_PASSWORD=' .env | head -1 | cut -d= -f2- | tr -d '"' | xargs || true)"

if [ -z "$superadmin_password" ]; then
    echo
    echo "  SUPERADMIN_PASSWORD is blank in backend/.env."
    echo "  Nothing can log in to /platform until it is set."
    echo
    echo "  Set SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD, then re-run"
    echo "  this script. Use a password you have not used elsewhere."
    exit 1
fi
unset superadmin_password

"$PHP_BIN" artisan db:seed --class=PlatformRoleSeeder --force
"$PHP_BIN" artisan db:seed --class=PlatformUserSeeder --force

# The seeder returns quietly on several paths, so confirm the row exists
# rather than trusting that it ran.
platform_users="$("$PHP_BIN" artisan tinker --execute='echo \App\Domain\Authentication\Models\PlatformUser::query()->count();' 2>/dev/null | tr -cd '0-9' || echo 0)"

if [ "${platform_users:-0}" -lt 1 ]; then
    echo
    echo "  No platform user exists after seeding."
    echo "  Check backend/storage/logs for the reason, then re-run."
    exit 1
fi
echo "    $platform_users platform user(s) present"

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
