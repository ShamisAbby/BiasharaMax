#!/usr/bin/env bash
# Deploys backend/ to a production/staging server. Run this ON the target
# server (or over ssh), from the repo root, after a `git pull`.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR/backend"

echo "==> Installing PHP dependencies (production, optimized autoloader)"
composer install --no-dev --optimize-autoloader

echo "==> Installing JS dependencies and building assets"
npm ci
npm run build

echo "==> Running migrations"
php artisan migrate --force

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Restarting queue workers and Horizon"
php artisan queue:restart
php artisan horizon:terminate || true

echo "==> Done."
