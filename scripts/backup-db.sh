#!/usr/bin/env bash
# Dumps the backend database to backups/ (gitignored), reading connection
# details from backend/.env. Supports both PostgreSQL (current) and MySQL
# (target, per docs/ADR/0001-consolidation.md) so this keeps working through
# the engine migration.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/backend/.env"
BACKUP_DIR="$ROOT_DIR/backups"
SCHEMA_ONLY=false

for arg in "$@"; do
    [ "$arg" = "--schema-only" ] && SCHEMA_ONLY=true
done

if [ ! -f "$ENV_FILE" ]; then
    echo "backend/.env not found — run scripts/install.sh first."
    exit 1
fi

get_env() { grep -E "^${1}=" "$ENV_FILE" | tail -1 | cut -d '=' -f2-; }

DB_CONNECTION="$(get_env DB_CONNECTION)"
DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_DATABASE="$(get_env DB_DATABASE)"
DB_USERNAME="$(get_env DB_USERNAME)"
DB_PASSWORD="$(get_env DB_PASSWORD)"

mkdir -p "$BACKUP_DIR"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
OUT_FILE="$BACKUP_DIR/${DB_DATABASE}_${TIMESTAMP}.sql"

case "$DB_CONNECTION" in
    pgsql)
        SCHEMA_FLAG=""
        [ "$SCHEMA_ONLY" = true ] && SCHEMA_FLAG="--schema-only"
        PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" \
            $SCHEMA_FLAG "$DB_DATABASE" > "$OUT_FILE"
        ;;
    mysql)
        SCHEMA_FLAG=""
        [ "$SCHEMA_ONLY" = true ] && SCHEMA_FLAG="--no-data"
        mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
            $SCHEMA_FLAG "$DB_DATABASE" > "$OUT_FILE"
        ;;
    *)
        echo "Unsupported DB_CONNECTION '$DB_CONNECTION' (expected pgsql or mysql)"
        exit 1
        ;;
esac

echo "==> Wrote $OUT_FILE"
if [ "$SCHEMA_ONLY" = true ]; then
    cp "$OUT_FILE" "$ROOT_DIR/sql/bos.sql"
    echo "==> Also copied to sql/bos.sql"
fi
