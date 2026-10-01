#!/bin/sh
set -e

ROLE="${CONTAINER_ROLE:-web}"

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Generate: php artisan key:generate --show"
  exit 1
fi

start_web_servers() {
  # ابدأ الاستماع فوراً قبل انتظار DB — وإلا ترافيك يعيد Bad Gateway
  # استمع على 80 و8080 لتغطية إعداد النطاق في كولفاي أياً كان
  echo "Starting web listeners on 0.0.0.0:80 and 0.0.0.0:8080"
  php -S 0.0.0.0:80 -t /app/public /app/docker/router.php &
  PID80=$!
  php -S 0.0.0.0:8080 -t /app/public /app/docker/router.php &
  PID8080=$!
  sleep 1

  if ! kill -0 "$PID80" 2>/dev/null; then
    echo "ERROR: failed to bind port 80"
    exit 1
  fi
  if ! kill -0 "$PID8080" 2>/dev/null; then
    echo "ERROR: failed to bind port 8080"
    exit 1
  fi
  echo "Web listeners are up (pids ${PID80}, ${PID8080})"
}

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

bootstrap_laravel() {
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
    echo "WARNING: migrate exited with status ${MIGRATE_STATUS} — listeners kept running"
  else
    echo "Bootstrap finished."
  fi
}

if [ "$ROLE" = "web" ]; then
  start_web_servers
  wait_for_database
  bootstrap_laravel
  # أبقِ الحاوية حية طالما أحد المستمعين يعمل
  wait "$PID80" "$PID8080"
  exit $?
fi

wait_for_database

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
