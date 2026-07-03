#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Generate one locally: php artisan key:generate --show"
  exit 1
fi

php artisan migrate --force --no-interaction

if [ "$RUN_SEED" = "true" ]; then
  php artisan db:seed --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
