#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../hollal-platform"

port_open() {
  php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);'
}

if ! port_open; then
  php artisan serve --host=0.0.0.0 --port=8000 >/tmp/laravel-serve.log 2>&1 &
fi

for _ in $(seq 1 30); do
  if port_open; then
    exit 0
  fi
  sleep 1
done

echo "Laravel did not become ready on :8000" >&2
exit 1
