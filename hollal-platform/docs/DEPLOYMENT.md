# نشر منصة حلّل — الإنتاج

دليل نشر Laravel 13 على VPS / Railway / أي بيئة إنتاجية دائمة.

---

## 1. المتطلبات

- PHP 8.3+ مع امتدادات: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` أو `imagick`
- MySQL 8+
- Composer 2
- Node (اختياري — الأصول ثابتة في `public/css`)
- Supervisor أو systemd لـ queue worker و cron

---

## 2. إعداد البيئة

```bash
cp .env.production.example .env
php artisan key:generate
```

عدّل `.env`:

| المتغير | القيمة |
|---------|--------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain.example` |
| `APP_LOCALE` | `ar` |
| `DB_*` | بيانات MySQL |
| `ADMIN_INITIAL_PASSWORD` | كلمة مرور المدير الأولى (لا تُرفع للمستودع) |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_LIFETIME` | `60` |
| `SESSION_SECURE_COOKIE` | `true` |

`AppServiceProvider` يفرض `https` تلقائياً في `production`.

---

## 3. التثبيت والهجرة

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**تسجيل الدخول الأول:** جوال `0500000000` + قيمة `ADMIN_INITIAL_PASSWORD`.

---

## 4. Cron (مهام مجدولة)

```cron
* * * * * cd /path/to/hollal-platform && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Queue Worker

```ini
[program:hollal-worker]
command=php /path/to/hollal-platform/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```

---

## 6. التخزين الدائم للملفات

الملفات الحساسة تُخزَّن على قرص `local` → `storage/app/private` (مهام، مصروفات، عقود، مستندات).

**تحذير:** حاويات Docker / نشر بدون volume تفقد الملفات عند إعادة التشغيل.

| البيئة | الحل الموصى به |
|--------|----------------|
| VPS | volume مربوط بـ `storage/app/private` |
| Kubernetes | PersistentVolumeClaim |
| S3-compatible | `FILESYSTEM_DISK=s3` + ضبط `AWS_*` في `.env` |

لا تشغّل `php artisan storage:link` للملفات الخاصة — التحميل عبر مسارات `/files/*` المحمية فقط.

---

## 7. النسخ الاحتياطي اليومي

```bash
# MySQL
mysqldump -u hollal -p hollal > backup-$(date +%F).sql

# ملفات خاصة
tar -czf private-$(date +%F).tar.gz storage/app/private
```

احفظ النسخ خارج الخادم (S3 / NAS). اختبر الاستعادة شهرياً.

---

## 8. RESTORE TEST — قائمة تحقق

- [ ] استعادة dump MySQL إلى بيئة staging
- [ ] استعادة `storage/app/private` إلى نفس المسار
- [ ] `php artisan migrate --force` (إن لزم)
- [ ] تسجيل دخول مدير + موظف
- [ ] تحميل مرفق مصروف / مستند / مهمة عبر `/files/*`
- [ ] إرسال طلب مصروف وموافقة
- [ ] مراجعة `audit_logs` لوجود سجلات حديثة

---

## 9. الأمان (مُدمَج)

- رؤوس HTTP: HSTS (إنتاج)، `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, CSP Report-Only
- جلسات: 60 دقيقة، تشفير، إعادة توليد عند الدخول
- تحميل الملفات: سياسات + rate limit 30/دقيقة على `/files/*`
- سجل تدقيق: `audit_logs` (append-only)

راجع `docs/SECURITY-REVIEW.md` للتفاصيل.

---

*آخر تحديث: يوليو 2026 — Batch 4 (Part J)*
