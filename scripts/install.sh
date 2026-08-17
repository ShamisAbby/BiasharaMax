#!/usr/bin/env bash
# Sets up backend/ for local development. Wraps the steps in backend/README.md.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"

cd "$BACKEND_DIR"

echo "==> Installing PHP dependencies"
composer install

echo "==> Installing JS dependencies"
npm install

if [ ! -f .env ]; then
    echo "==> Creating .env from .env.example"
    cp .env.example .env
    php artisan key:generate
else
    echo "==> .env already exists, skipping key:generate"
fi

echo "==> Running migrations (with seed data)"
php artisan migrate --seed

echo "==> Building frontend assets"
npm run build

cat <<'EOF'

Done. Start the app with:
    cd backend && composer run dev
(runs the web server, queue listener, log tailer and Vite dev server together)

Or individually:
    php artisan serve
    npm run dev
EOF
