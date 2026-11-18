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

# Bootstrap-only variables are removed before the final process starts.
unset DB_ROOT_PASSWORD MYSQL_ROOT_PASSWORD SEED_PASSWORD RUN_MIGRATIONS SEED_DATABASE

# Migrations and seed may create root-owned logs; Apache runs as www-data.
chown -R www-data:www-data storage

exec "$@"
