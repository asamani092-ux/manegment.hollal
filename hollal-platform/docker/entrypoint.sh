#!/bin/sh
set -e

ROLE="${CONTAINER_ROLE:-web}"

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Generate: php artisan key:generate --show"
  exit 1
fi

# انتظار قاعدة البيانات (MySQL على Coolify)
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
  if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
    echo "ERROR: DB_DATABASE / DB_USERNAME / DB_PASSWORD must be set"
    exit 1
  fi
  echo "Waiting for database ${DB_HOST}:${DB_PORT:-3306} db=${DB_DATABASE} user=${DB_USERNAME}..."
  i=0
  last_err=""
  until last_err=$(php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0);} catch (Throwable \$e) { fwrite(STDERR, \$e->getMessage()); exit(1);}" 2>&1); do
    i=$((i+1))
    if [ "$i" -ge 90 ]; then
      echo "ERROR: database not ready after ${i} attempts: ${last_err}"
      exit 1
    fi
    sleep 2
  done
  echo "Database is ready."
fi

if [ "$ROLE" = "web" ]; then
  php artisan migrate --force --no-interaction

  if [ "$RUN_SEED" = "true" ]; then
    php artisan db:seed --force --no-interaction
  fi

  php artisan config:cache
  php artisan route:cache
  php artisan view:cache

  exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
fi

if [ "$ROLE" = "queue" ]; then
  php artisan config:cache
  exec php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
fi

if [ "$ROLE" = "scheduler" ]; then
  php artisan config:cache
  while true; do
    php artisan schedule:run --verbose --no-interaction
    sleep 60
  done
fi

echo "Unknown CONTAINER_ROLE=$ROLE (use web|queue|scheduler)"
exit 1
