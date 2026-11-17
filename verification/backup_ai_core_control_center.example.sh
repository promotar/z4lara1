#!/usr/bin/env bash
set -euo pipefail

# Copy this file to a local, ignored filename before use. Configure every value
# through environment variables; do not commit production paths or credentials.
: "${APP_ROOT:?Set APP_ROOT to the Laravel application directory}"
: "${BACKUP_ROOT:?Set BACKUP_ROOT to a protected backup directory}"
: "${DB_HOST:?Set DB_HOST}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?Set DB_DATABASE}"
: "${DB_USERNAME:?Set DB_USERNAME}"
: "${DB_PASSWORD:?Set DB_PASSWORD}"

stamp="${1:-$(date +%Y%m%d-%H%M%S)}"
target="${BACKUP_ROOT%/}/ai-core-control-center-${stamp}"

mkdir -p "$target"
cp -a "${APP_ROOT%/}/modules/ai-core" "$target/ai-core"

MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    "$DB_DATABASE" | gzip > "$target/database.sql.gz"

echo "$target"
