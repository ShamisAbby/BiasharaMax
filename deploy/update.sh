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

# ---------------------------------------------------------------------
# PHP. Same selection as setup.sh, and it has to be — this script was
# written with `command -v php8.4`, which finds nothing on this host
# because the versioned binaries live at /opt/alt/php84/usr/bin/php and
# are not on PATH. It fell through to bare `php`, the system 8.2 build,
# and composer then refused the install against a lock file requiring
# >= 8.4.1. Setup got fixed and this did not, so the first deploy after
# the fix would have failed in exactly the way the fix was for.
#
# Extensions are verified, not assumed: this server's 8.5 CLI is missing
# intl, zip and gd, so it is a worse choice than 8.4 despite the higher
# number.
# ---------------------------------------------------------------------
PHP_BIN="${PHP_BIN:-}"

if [ -z "$PHP_BIN" ]; then
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
    echo "No usable PHP found. Re-run as: PHP_BIN=/path/to/php bash deploy/update.sh"
    exit 1
fi

echo "  php: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# Through $PHP_BIN, never bare `composer` — see the long note in setup.sh.
# Bare composer runs under the system default PHP, which on shared hosting
# is not the version the site is served with.
if [ -n "${COMPOSER:-}" ]; then
    : # Caller knows better.
elif [ -f "$ROOT/composer.phar" ]; then
    COMPOSER="$PHP_BIN $ROOT/composer.phar"
elif command -v composer >/dev/null 2>&1 && "$PHP_BIN" "$(command -v composer)" --version >/dev/null 2>&1; then
    COMPOSER="$PHP_BIN $(command -v composer)"
else
    # setup.sh leaves a composer.phar behind for exactly this case; if it
    # is not there, say so rather than expanding to "$PHP_BIN " and
    # failing with a confusing "could not open input file".
    echo "No composer found. Run deploy/setup.sh once, or set COMPOSER=..."
    exit 1
fi

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

# ---------------------------------------------------------------------
# The script pulls a new version of itself, so anything it decided
# before this line was decided by the *old* version.
#
# That is not hypothetical. A fix to the PHP selection landed in this
# file, and the deploy that pulled the fix had already picked PHP 8.2
# using the code the fix replaced — composer then failed on the lock
# file, and the error blamed a PHP version nobody had chosen on
# purpose. Reading the diff afterwards was no help either, because the
# file on disk was by then the corrected one.
#
# So: notice when this file changes and start again with the new copy.
# BIASHARAMAX_REEXEC stops that from recursing — the second run pulls
# nothing, so its hash comparison is a no-op anyway, but a deploy script
# that can loop forever is not worth the risk.
# ---------------------------------------------------------------------
self_before="$(md5sum "$ROOT/deploy/update.sh" 2>/dev/null | cut -d' ' -f1 || echo none)"

git pull --ff-only

self_after="$(md5sum "$ROOT/deploy/update.sh" 2>/dev/null | cut -d' ' -f1 || echo none)"

if [ "$self_before" != "$self_after" ] && [ -z "${BIASHARAMAX_REEXEC:-}" ]; then
    echo
    echo "  deploy/update.sh changed in this pull — restarting with the new version."
    echo

    # The site is in maintenance mode and the trap is about to fire on
    # exec. Hand the second run a repository it can pull nothing from
    # and let it manage maintenance mode itself.
    trap - EXIT

    BIASHARAMAX_REEXEC=1 exec bash "$ROOT/deploy/update.sh" "$@"
fi

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
