#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
[[ -f .env ]] || { echo "ERROR: .env not found." >&2; exit 1; }

BACKUP_DIR="${BACKUP_DIR:-$ROOT_DIR/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
umask 077

COMPOSE=(docker compose --env-file .env -f docker-compose.yml -f docker-compose.prod.yml)
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FINAL="$BACKUP_DIR/fixpoint-$STAMP.sql.gz"
TMP="$FINAL.tmp"
trap 'rm -f "$TMP"' EXIT

"${COMPOSE[@]}" exec -T db sh -ec \
  'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --single-transaction --routines --triggers "$MYSQL_DATABASE"' \
  | gzip -9 > "$TMP"

gzip -t "$TMP"
mv "$TMP" "$FINAL"
chmod 600 "$FINAL"
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'fixpoint-*.sql.gz' -mtime "+$RETENTION_DAYS" -delete

echo "Backup created: $FINAL"
sha256sum "$FINAL"
