#!/usr/bin/env bash
#
# BiasharaMax — deploy an update.
#
# Run on the server for every deploy after the first:
#
#   ssh -p 65002 u713307449@77.37.37.66
#   cd ~/biasharamax && bash deploy/update.sh
#
# Order matters here and is the whole point of the script. Maintenance mode
# goes up before the code changes and comes down after the caches are
# rebuilt, so nobody is served a page whose assets no longer exist or whose
# routes point at code that has not arrived yet.

set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
APP="$ROOT/backend"

PHP_BIN="${PHP_BIN:-$(command -v php8.3 || command -v php8.2 || command -v php)}"
COMPOSER="${COMPOSER:-$(command -v composer || echo "$PHP_BIN $ROOT/composer.phar")}"

cd "$APP"

# Bring the site back up whatever happens. Without this, a failed deploy
# leaves customers looking at a maintenance page until someone notices —
# and the person who would notice is the one whose deploy just failed.
cleanup() {
    "$PHP_BIN" artisan up >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "==> Maintenance mode"
# The secret lets you check the site yourself while it is down for others.
"$PHP_BIN" artisan down --render="errors::503" --secret="deploy-preview" || true

echo
echo "==> Pulling changes"
cd "$ROOT"
git pull --ff-only
cd "$APP"

echo
echo "==> Dependencies"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo
echo "==> Migrations"
"$PHP_BIN" artisan migrate --force

echo
echo "==> Rebuilding caches"
# Cleared before being rebuilt: a cached config file still holds the values
# from before the pull, and `config:cache` on top of a stale cache keeps
# them.
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo
echo "==> Restarting the queue"
# Workers hold the old code in memory. Without this they keep running it
# until they happen to exit, which produces failures that do not match the
# code you are looking at.
"$PHP_BIN" artisan queue:restart

if [ ! -f public/build/manifest.json ]; then
    echo
    echo "WARNING: public/build/manifest.json is missing — the site will load unstyled."
    echo "Run 'npm run build' locally, commit public/build, and pull again."
fi

echo
echo "==> Bringing the site back up"
"$PHP_BIN" artisan up

echo
echo "Deploy complete."
