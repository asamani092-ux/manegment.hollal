# نشر تجريبي على Coolify (VPS)

دليل رفع منصة حلّل على Coolify للتجربة على نطاقك، قبل النقل لاحقاً إلى Hostinger العميل.

---

## 1. ما أُعدّ في المستودع

| ملف | الغرض |
|-----|--------|
| `hollal-platform/Dockerfile` | صورة PHP 8.3 + GD + MySQL |
| `hollal-platform/docker-compose.coolify.yml` | تطبيق + طابور + مجدوّل + MySQL |
| `hollal-platform/docker/entrypoint.sh` | ترحيل / بذرة / تشغيل حسب الدور |

---

## 2. إعداد Coolify (من لوحتك)

1. **New Resource → Application** من مستودع GitHub: `asamani092-ux/manegment.hollal`
2. الفرع: `main` (أو `cursor/coolify-port-80-d8fc` حتى الدمج)
3. **Build Pack:** Docker Compose
4. **Base Directory:** `/hollal-platform`
5. **Docker Compose Location:** `/docker-compose.coolify.yml`
6. **Domains for app:** الرابط فقط بدون منفذ (التطبيق يستمع على 80)
7. فعّل HTTPS (Let's Encrypt من Coolify) إن رغبت

---

## 3. متغيرات البيئة (Environment Variables)

ضع هذه القيم في Coolify (Shared / للخدمات كلها عدا ما يخص MySQL فقط):

```env
APP_NAME=منصة حلل
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hollal.yourdomain.com
APP_KEY=base64:XXXX   # ولّدها محلياً: php artisan key:generate --show
APP_LOCALE=ar

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=hollal
DB_USERNAME=hollal
DB_PASSWORD=كلمة_قوية
DB_ROOT_PASSWORD=كلمة_جذر_قوية

SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
LOG_CHANNEL=stderr
LOG_LEVEL=warning

ADMIN_INITIAL_PASSWORD=كلمة_مدير_أولى
RUN_SEED=true
```

> بعد أول نشر ناجح غيّر `RUN_SEED=false` حتى لا تُعاد البذرة في كل إعادة نشر.

---

## 4. بعد أول Deploy

1. افتح `https://نطاقك/login`
2. الجوال: `0500000000`
3. كلمة المرور: قيمة `ADMIN_INITIAL_PASSWORD`
4. غيّر كلمة المرور عند الطلب
5. عطّل `RUN_SEED` في Coolify واحفظ

---

## 5. ملاحظات مهمة

- التخزين على volume دائم (`hollal_storage`) — المرفقات لا تُفقد عند إعادة النشر
- الطابور والمجدول يعملان كحاويات منفصلة
- للانتقال لاحقاً إلى Hostinger: صدّر MySQL + انسخ `storage/app` ثم اتبع `docs/DEPLOYMENT.md`
- لا ترفع أسراراً إلى Git — كلها من لوحة Coolify فقط

---

## 6. وصول الوكيل للمساعدة في النشر

إن احتجت مساعدة مباشرة من الوكيل على السيرفر:

1. أضف عنوان لوحة Coolify (مثال `https://coolify.yourdomain.com`)
2. أنشئ مستخدماً بصلاحية Deploy فقط (لا root كامل إن أمكن) وأرسل بيانات الدخول عبر قناة آمنة / Secrets في Cursor
3. أو: اربط المستودع بـ GitHub App في Coolify ثم اضغط Deploy بعد دمج هذا الفرع — غالباً يكفي بلا مشاركة كلمة مرور

---

*للإنتاج النهائي عند العميل استخدم Hostinger حسب `DEPLOYMENT.md`.*
