#!/bin/sh
set -e

chown -R www-data:$(whoami) var bin
chmod -R ug+rw var
chmod -R ug+rx bin

if [ "$APP_ENV" != 'prod' ]; then
    # install dependencies (including dev), when the container was
    COMPOSER_MEMORY_LIMIT=-1 composer install --prefer-dist --no-progress  --no-interaction

    echo "Checking JWT key..."
    bin/create-jwt-key.sh
fi

echo "Waiting for db to be ready..."
until bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
    sleep 1
done

if [ "$APP_ENV" != 'prod' ]; then
    echo "Updating DB schema if necessary..."
    bin/console doctrine:schema:update --force --no-interaction
fi

exec docker-php-entrypoint "$@"