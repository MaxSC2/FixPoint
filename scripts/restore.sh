#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
[[ -f .env ]] || { echo "ERROR: .env not found." >&2; exit 1; }

BACKUP="${1:-}"
if [[ -z "$BACKUP" || ! -f "$BACKUP" ]]; then
  echo "Usage: $0 /absolute/or/relative/path/to/fixpoint-YYYYMMDDTHHMMSSZ.sql.gz" >&2
  exit 2
fi
if [[ "$BACKUP" != *.sql.gz ]]; then
  echo "ERROR: expected a .sql.gz backup." >&2
  exit 2
fi

gzip -t "$BACKUP"
echo "WARNING: this overwrites tables in the configured database from the selected dump."
echo "Database: read from .env (never type credentials into this command)."
read -r -p 'Type RESTORE to continue: ' CONFIRM
[[ "$CONFIRM" == RESTORE ]] || { echo "Restore cancelled."; exit 0; }

COMPOSE=(docker compose --env-file .env -f docker-compose.yml -f docker-compose.prod.yml)
gzip -dc -- "$BACKUP" | "${COMPOSE[@]}" exec -T db sh -ec \
  'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -uroot "$MYSQL_DATABASE"'

echo "Restore completed from: $BACKUP"
