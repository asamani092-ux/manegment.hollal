#!/bin/sh
set -e

ROLE="${CONTAINER_ROLE:-web}"
SERVER_PHP="/app/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Generate: php artisan key:generate --show"
  exit 1
fi

start_web_servers() {
  # راوتر لارافيل الرسمي مع cwd=public — يتجنب 500 الناتج عن راوتر مخصص
  # الاستماع فوراً قبل انتظار DB على 80 و8080
  echo "Starting Laravel PHP servers on :80 and :8080"
  (
    cd /app/public
    exec php -d display_errors=stderr -d log_errors=1 -d error_log=/proc/self/fd/2 \
      -S 0.0.0.0:80 "$SERVER_PHP"
  ) &
  PID80=$!
  (
    cd /app/public
    exec php -d display_errors=stderr -d log_errors=1 -d error_log=/proc/self/fd/2 \
      -S 0.0.0.0:8080 "$SERVER_PHP"
  ) &
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
  # تجنّب config:cache على Coolify — غالباً يسبب 500 عند اختلاف المتغيرات
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
  php artisan cache:clear 2>/dev/null

  echo "Running migrations..."
  php artisan migrate --force --no-interaction
  MIGRATE_STATUS=$?

  echo "Seeding HR reference data..."
  php artisan hr:seed-reference --no-interaction
  php artisan optimize:clear --no-interaction

  if [ "$RUN_SEED" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force --no-interaction
  fi
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
  wait "$PID80" "$PID8080"
  exit $?
fi

wait_for_database

if [ "$ROLE" = "queue" ]; then
  php artisan config:clear
  exec php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
fi

if [ "$ROLE" = "scheduler" ]; then
  php artisan config:clear
  while true; do
    php artisan schedule:run --verbose --no-interaction
    sleep 60
  done
fi

echo "Unknown CONTAINER_ROLE=$ROLE (use web|queue|scheduler)"
exit 1
