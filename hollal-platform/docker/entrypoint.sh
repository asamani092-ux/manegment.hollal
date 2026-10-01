#!/bin/sh
set -e

ROLE="${CONTAINER_ROLE:-web}"
# كولفاي قد يحقن PORT بقيمة أخرى — ثبّت 80 لتطابق ترافيك
WEB_PORT=80

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Generate: php artisan key:generate --show"
  exit 1
fi

wait_for_database() {
  if [ -z "$DB_HOST" ] || [ "$DB_CONNECTION" != "mysql" ]; then
    return 0
  fi

  if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
    echo "ERROR: DB_DATABASE / DB_USERNAME / DB_PASSWORD must be set"
    exit 1
  fi

  echo "Waiting for database ${DB_HOST}:${DB_PORT:-3306} db=${DB_DATABASE} user=${DB_USERNAME}..."
  i=0
  while true; do
    if err=$(php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0);} catch (Throwable \$e) { fwrite(STDERR, \$e->getMessage()); exit(1);}" 2>&1); then
      echo "Database is ready."
      return 0
    fi
    i=$((i + 1))
    if [ $((i % 15)) -eq 0 ]; then
      echo "Still waiting for database (attempt ${i}): ${err}"
    fi
    sleep 2
  done
}

wait_for_database

if [ "$ROLE" = "web" ]; then
  echo "Starting web server on 0.0.0.0:${WEB_PORT} (before migrate, to avoid Bad Gateway)"
  php -S "0.0.0.0:${WEB_PORT}" -t /app/public /app/docker/router.php &
  SERVER_PID=$!
  sleep 1

  if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    echo "ERROR: web server failed to start on port ${WEB_PORT}"
    exit 1
  fi

  set +e
  echo "Running migrations..."
  php artisan migrate --force --no-interaction
  MIGRATE_STATUS=$?

  if [ "$RUN_SEED" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force --no-interaction
  fi

  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  set -e

  if [ "$MIGRATE_STATUS" -ne 0 ]; then
    echo "WARNING: migrate exited with status ${MIGRATE_STATUS} — server kept running"
  else
    echo "Bootstrap finished."
  fi

  wait "$SERVER_PID"
  exit $?
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
