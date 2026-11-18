#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f .env ]]; then
  echo "ERROR: .env not found. Copy deploy/env.demo.example to .env and fill it in." >&2
  exit 1
fi

# The deployment .env is controlled by the operator and contains only simple
# KEY=value entries generated from deploy/env.demo.example.
set -a
# shellcheck disable=SC1091
source .env
set +a

for name in DEMO_DOMAIN APP_URL DB_PASSWORD DB_ROOT_PASSWORD SEED_PASSWORD; do
  if [[ -z "${!name:-}" ]]; then
    echo "ERROR: required variable $name is empty." >&2
    exit 1
  fi
done

if [[ "$DEMO_DOMAIN" == *example.invalid* || "$DB_PASSWORD" == CHANGE_ME* || "$DB_ROOT_PASSWORD" == CHANGE_ME* || "$SEED_PASSWORD" == CHANGE_ME* ]]; then
  echo "ERROR: replace demo placeholders in .env before deployment." >&2
  exit 1
fi

if [[ "$APP_ENV" != "staging" || "$APP_DEBUG" != "false" || "$APP_PORT" != "127.0.0.1:8080" || "${SESSION_SECURE:-false}" != "true" ]]; then
  echo "ERROR: public demo must use APP_ENV=staging, APP_DEBUG=false, SESSION_SECURE=true and APP_PORT=127.0.0.1:8080." >&2
  exit 1
fi
if [[ "$APP_URL" != "https://$DEMO_DOMAIN" ]]; then
  echo "ERROR: APP_URL must be exactly https://$DEMO_DOMAIN." >&2
  exit 1
fi
if [[ "$DB_PASSWORD" == "$DB_ROOT_PASSWORD" ]]; then
  echo "ERROR: DB_PASSWORD and DB_ROOT_PASSWORD must be different." >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: Docker Engine with the Compose plugin is required." >&2
  exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
  echo "ERROR: curl is required for the HTTPS health check." >&2
  exit 1
fi

COMPOSE=(docker compose --env-file .env -f docker-compose.yml -f docker-compose.prod.yml)

# Validate the exact production configuration before changing containers.
"${COMPOSE[@]}" config --quiet
"${COMPOSE[@]}" build app
"${COMPOSE[@]}" up -d --remove-orphans

HEALTH_URL="${APP_URL%/}/health"
for attempt in $(seq 1 60); do
  if curl --fail --silent --show-error --max-time 8 "$HEALTH_URL"; then
    echo
    "${COMPOSE[@]}" rm -f init >/dev/null 2>&1 || true
    echo "FixPoint deployment is healthy: $HEALTH_URL"
    echo "Source commit: $(git rev-parse --short HEAD 2>/dev/null || echo unknown)"
    exit 0
  fi
  sleep 3
done

echo "ERROR: health check did not pass: $HEALTH_URL" >&2
"${COMPOSE[@]}" ps >&2 || true
"${COMPOSE[@]}" logs --tail=120 app caddy db >&2 || true
exit 1
