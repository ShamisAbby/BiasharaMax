#!/usr/bin/env bash
# Restores a dump produced by scripts/backup-db.sh. Destructive — asks for
# confirmation before touching the target database.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/backend/.env"

DUMP_FILE="${1:-}"
if [ -z "$DUMP_FILE" ] || [ ! -f "$DUMP_FILE" ]; then
    echo "Usage: $0 <path-to-dump.sql>"
    echo "Available backups:"
    ls -1t "$ROOT_DIR"/backups/*.sql 2>/dev/null || echo "  (none in backups/)"
    exit 1
fi

get_env() { grep -E "^${1}=" "$ENV_FILE" | tail -1 | cut -d '=' -f2-; }

DB_CONNECTION="$(get_env DB_CONNECTION)"
DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_DATABASE="$(get_env DB_DATABASE)"
DB_USERNAME="$(get_env DB_USERNAME)"
DB_PASSWORD="$(get_env DB_PASSWORD)"

echo "This will overwrite database '$DB_DATABASE' on $DB_HOST:$DB_PORT with $DUMP_FILE"
read -r -p "Continue? [y/N] " CONFIRM
[ "$CONFIRM" = "y" ] || [ "$CONFIRM" = "Y" ] || { echo "Aborted."; exit 1; }

case "$DB_CONNECTION" in
    pgsql)
        PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" \
            -d "$DB_DATABASE" -f "$DUMP_FILE"
        ;;
    mysql)
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
            "$DB_DATABASE" < "$DUMP_FILE"
        ;;
    *)
        echo "Unsupported DB_CONNECTION '$DB_CONNECTION' (expected pgsql or mysql)"
        exit 1
        ;;
esac

echo "==> Restored $DUMP_FILE into $DB_DATABASE"
