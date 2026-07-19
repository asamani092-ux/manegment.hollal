# نشر منصة حلّل — الإنتاج

دليل نشر Laravel 13 على **Hostinger** / VPS — بيئة إنتاجية دائمة (لا Railway/Render).

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

## 10. Hostinger — الإطلاق (13-B1)

> نوع الخطة النهائي بانتظار قرار عبدالله. الخطوات أدناه تنطبق على Business / Cloud
> (SSH + cron + PHP 8.3). خطة Shared بلا SSH تتطلب رفع `vendor/` جاهزًا وتشغيل
> الأوامر عبر hPanel → Advanced → Cron Jobs.

### 10.1 تجهيز البيئة

1. hPanel → Websites → Manage → **PHP Configuration**: PHP **8.3**، وتفعيل
   `bcmath, ctype, curl, dom, fileinfo, gd, mbstring, openssl, pdo_mysql, tokenizer, xml, zip`.
2. hPanel → **Databases → MySQL**: أنشئ قاعدة `hollal_prod` ومستخدمًا خاصًا بها.
3. hPanel → **SSL**: فعّل Let's Encrypt وأجبر HTTPS.
4. اجعل **document root** للنطاق يشير إلى `hollal-platform/public` (وليس جذر المستودع).

### 10.2 النشر

```bash
cd ~/domains/<domain>/hollal-platform
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=PlatformSettingsSeeder --force
php artisan db:seed --class=PlanTemplateSeeder --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```

### 10.3 `.env` الإنتاج (المفاتيح الحرجة)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain>
DB_CONNECTION=mysql
DB_DATABASE=hollal_prod
QUEUE_CONNECTION=database
SESSION_DRIVER=database
MAIL_MAILER=smtp
```

> إعدادات SMTP التشغيلية تُدار من داخل المنصة (`/settings/notifications`)، وتُطبَّق
> على الـ mailer عند الإقلاع عبر `AppServiceProvider::applyMailSettings()`.

### 10.4 Cron و Queue على Hostinger

hPanel → Advanced → **Cron Jobs**:

```
* * * * * cd ~/domains/<domain>/hollal-platform && php artisan schedule:run >> /dev/null 2>&1
```

عامل الطابور (Business/Cloud عبر SSH أو cron حارس):

```
* * * * * cd ~/domains/<domain>/hollal-platform && php artisan queue:work --stop-when-empty --tries=3 >> storage/logs/queue.log 2>&1
```

المهام المجدولة الفعلية في `routes/console.php`: تنبيهات المهام والعقود، التقرير
الأسبوعي، العمل الإضافي الشهري، المهام المتكررة، **تنبيهات الموازنة (04-B6)**،
**مراجعة السياسات (07-B1)**، **توليد المشاريع المعلقة (06B-B1)**.

### 10.5 النسخ الاحتياطي والاستعادة

- نسخة يدوية من داخل المنصة: `BackupService::run()` (تُسجَّل في `audit_logs`
  وتحدّث `backup.last_run_at`).
- نسخة الخادم اليومية: راجع القسم 7 أعلاه.
- **اختبار الاستعادة إلزامي قبل الإطلاق** — القسم 8.

### 10.6 قائمة تحقق الإطلاق

- [ ] `APP_DEBUG=false` و`APP_ENV=production`
- [ ] HTTPS مفروض + شهادة سارية
- [ ] `php artisan migrate --force` نُفِّذت بلا أخطاء
- [ ] البذور الأربع (صلاحيات/أدوار/إعدادات/قوالب الخطط) نُفِّذت
- [ ] cron يعمل (`schedule:run` كل دقيقة)
- [ ] عامل الطابور يعمل وتصل الإشعارات
- [ ] SMTP الإنتاج مضبوط ورسالة اختبار وصلت
- [ ] نسخة احتياطية + **اختبار استعادة** ناجح
- [ ] وضع الصيانة (11-B1) يعمل ويُرفع من `/settings`
- [ ] `php artisan test` كامل أخضر على الفرع المنشور

---

*آخر تحديث: يوليو 2026 — 13-B1 (Hostinger go-live)*
