#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
COMPOSE_SERVICE="${COMPOSE_SERVICE:-postgres}"
DB_NAME="${DB_DATABASE:-tinhouse}"
DB_USER="${DB_USERNAME:-tinhouse}"
BACKUP_DIR="${BACKUP_DIR:-$APP_DIR/storage/app/backups/postgres}"
R2_PROFILE="${R2_PROFILE:-r2}"
R2_BACKUP_BUCKET="${R2_BACKUP_BUCKET:?Set R2_BACKUP_BUCKET, for example tinhouse-backups}"
R2_ENDPOINT_URL="${R2_ENDPOINT_URL:?Set R2_ENDPOINT_URL, for example https://<account_id>.r2.cloudflarestorage.com}"
KEEP_LOCAL_DAYS="${KEEP_LOCAL_DAYS:-7}"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
file_name="${DB_NAME}-${timestamp}.sql.gz"
file_path="${BACKUP_DIR}/${file_name}"

mkdir -p "$BACKUP_DIR"

cd "$APP_DIR"

docker compose exec -T "$COMPOSE_SERVICE" pg_dump -U "$DB_USER" "$DB_NAME" | gzip -9 > "$file_path"

aws s3 cp \
  "$file_path" \
  "s3://${R2_BACKUP_BUCKET}/postgres/${file_name}" \
  --profile "$R2_PROFILE" \
  --endpoint-url "$R2_ENDPOINT_URL"

find "$BACKUP_DIR" -type f -name "${DB_NAME}-*.sql.gz" -mtime +"$KEEP_LOCAL_DAYS" -delete

echo "Uploaded database backup to s3://${R2_BACKUP_BUCKET}/postgres/${file_name}"
