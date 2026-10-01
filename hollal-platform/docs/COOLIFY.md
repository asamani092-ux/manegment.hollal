# نشر تجريبي على Coolify (VPS)

النشر دائماً من الفرع `main` فقط.

---

## تشخيص الأعطال التي ظهرت

| العرض | السبب الحقيقي |
|--------|----------------|
| `404 page not found` على http | البروكسي لا يخدم http لهذا النطاق — استخدم https |
| `no available server` على https | حاوية `app` غير صحية أو لا تستمع؛ سابقاً بسبب `healthcheck` أثناء migrate |
| `Bad Gateway` | لا استماع على منفذ البروكسي أثناء انتظار DB — الآن يُقلع على 80 و8080 فوراً قبل أي انتظار |
| `ERROR: APP_KEY is not set` | المتغير غير مضاف في Coolify |
| `database not ready` | بيانات `DB_*` ناقصة أو حجم MySQL قديم بكلمة مرور مختلفة |

---

## إعداد Coolify (مرة واحدة)

1. Resource من المستودع `asamani092-ux/manegment.hollal`
2. **Branch:** `main`
3. **Build Pack:** Docker Compose
4. **Base Directory:** `/hollal-platform`
5. **Docker Compose Location:** `/docker-compose.coolify.yml`
6. **Domains for app:**  
   `https://s1jdubrp1eit4tuqu6v1y0hu.91.98.234.130.sslip.io`  
   بدون `:8080` وبدون `:80` — اترك queue/scheduler فارغين
7. احفظ ثم **Redeploy**
8. افتح: `https://.../login` (https فقط)

---

## متغيرات البيئة

```env
APP_NAME=منصة حلل
APP_ENV=production
APP_DEBUG=false
APP_URL=https://s1jdubrp1eit4tuqu6v1y0hu.91.98.234.130.sslip.io
APP_KEY=base64:aVHAfOAajbqY5UEkbHhSxk9+bExXQfN0nEdNyhzCPGc=
APP_LOCALE=ar

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=hollal
DB_USERNAME=hollal
DB_PASSWORD=HollalDb2026!
DB_ROOT_PASSWORD=HollalRoot2026!

SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
LOG_CHANNEL=stderr
LOG_LEVEL=warning

ADMIN_INITIAL_PASSWORD=12341234
RUN_SEED=true
```

بعد أول دخول ناجح: `RUN_SEED=false`.

إن غيّرت كلمات مرور DB بعد نشر سابق: احذف حجم `hollal_mysql` من Storages ثم Redeploy.

---

## الدخول

- الجوال: `0500000000`
- كلمة المرور: قيمة `ADMIN_INITIAL_PASSWORD`

---

## ملاحظات

- التطبيق يستمع داخل الحاوية على المنفذ 80
- لا تُضف healthcheck لخدمة `app` في Compose
- التخزين على `hollal_storage`
- للإنتاج النهائي عند العميل: `docs/DEPLOYMENT.md` (Hostinger)
