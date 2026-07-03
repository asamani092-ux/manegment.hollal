# Hollal Platform — Production Deployment

## Requirements

- PHP 8.3+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`
- MySQL 8+
- Node.js 20+ (asset build only)
- Composer 2.x

## Environment

1. Copy `.env.production.example` to `.env` on the server.
2. Set `APP_KEY` via `php artisan key:generate`.
3. Configure database, mail, and `APP_URL` (HTTPS).
4. Keep `APP_DEBUG=false` and `APP_ENV=production`.

## Install

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=PermissionSeeder --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Scheduler

Add a cron entry (runs queued jobs and scheduled commands):

```cron
* * * * * cd /path/to/hollal-platform && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks include task due/overdue alerts, contract expiry notices, and weekly report generation.

## Queue worker

When `QUEUE_CONNECTION=database`, run a persistent worker:

```bash
php artisan queue:work --sleep=3 --tries=3
```

## Security

- `SecurityHeadersMiddleware` adds baseline HTTP security headers on every response.
- Session lifetime defaults to 60 minutes (`SESSION_LIFETIME=60`); sessions regenerate on login.
- File downloads use policy checks and private `local` disk storage.
- Do not commit `.env` or production secrets.

## Health check

Laravel exposes `/up` for load balancer probes.

## Backup

Before each deploy, back up the database and uploaded files:

```bash
# Database (MySQL)
mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > "backups/hollal-$(date +%Y%m%d-%H%M).sql.gz"

# Uploaded files (tasks, contracts, expenses, documents)
tar -czf "backups/storage-$(date +%Y%m%d-%H%M).tar.gz" storage/app/private
```

Store backups off-server and verify archive integrity before proceeding.

## Restore test

Periodically validate backups on a staging host:

```bash
# Restore database
gunzip -c backups/hollal-YYYYMMDD-HHMM.sql.gz | mysql -u user -p hollal_staging

# Restore files
tar -xzf backups/storage-YYYYMMDD-HHMM.tar.gz -C /path/to/hollal-platform

# Smoke test
php artisan migrate --force
php artisan test
curl -f https://staging.example.com/up
```

Confirm login, file downloads, and scheduled commands before promoting to production.
