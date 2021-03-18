#!/bin/sh
set -e

chown -R www-data:$(whoami) var bin
chmod -R ug+rw var
chmod -R ug+rx bin

echo "Waiting for db to be ready..."
until bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  sleep 1
done

echo "Running container command..."
exec "$@"
