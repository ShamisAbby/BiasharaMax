#!/usr/bin/env bash
# Seeds (or exports) demo data for local dev / sales demos.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR/backend"

if [ "${1:-}" = "--export" ]; then
    echo "==> Exporting current data as demo seed data to sql/seed.sql"
    "$ROOT_DIR/scripts/backup-db.sh"
    LATEST="$(ls -t "$ROOT_DIR"/backups/*.sql | head -1)"
    cp "$LATEST" "$ROOT_DIR/sql/seed.sql"
    echo "==> Wrote sql/seed.sql from $LATEST"
    exit 0
fi

echo "==> Running database seeders (php artisan db:seed)"
php artisan db:seed

echo "==> Done. Demo business/users are whatever DatabaseSeeder wires up —"
echo "    see backend/database/seeders/DatabaseSeeder.php."
