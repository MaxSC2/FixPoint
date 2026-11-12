#!/bin/sh
set -eu

cd /var/www/html

php /usr/local/bin/fixpoint-wait-for-db.php

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php database/migrate.php
fi

if [ "${SEED_DATABASE:-false}" = "true" ]; then
    php database/seed.php
fi

# Migrations and seed may create root-owned logs; Apache runs as www-data.
chown -R www-data:www-data storage

exec "$@"
