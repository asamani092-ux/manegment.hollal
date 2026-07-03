# نشر منصة حلل على السحابة

## الخيار الموصى به: Railway (مجاني للتجربة)

### 1. تجهيز GitHub

المستودع: `https://github.com/asamani092-ux/manegment.hollal.git`

تأكد أن آخر commit مرفوع على `main`.

### 2. إنشاء مشروع Railway

1. ادخل [railway.app](https://railway.app) وسجّل بحساب GitHub.
2. **New Project** → **Deploy from GitHub repo** → اختر `manegment.hollal`.
3. **Settings** → **Root Directory** → `hollal-platform`

### 3. قاعدة بيانات MySQL

1. في المشروع: **+ New** → **Database** → **MySQL**.
2. افتح خدمة الويب → **Variables** → **Add Reference** من MySQL:
   - `DB_HOST` ← `MYSQLHOST`
   - `DB_PORT` ← `MYSQLPORT`
   - `DB_DATABASE` ← `MYSQLDATABASE`
   - `DB_USERNAME` ← `MYSQLUSER`
   - `DB_PASSWORD` ← `MYSQLPASSWORD`

### 4. متغيرات البيئة (خدمة الويب)

| المتغير | القيمة |
|---------|--------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | انسخ من `php artisan key:generate --show` محلياً |
| `APP_URL` | رابط Railway بعد أول deploy (مثل `https://xxx.up.railway.app`) |
| `ADMIN_INITIAL_PASSWORD` | كلمة مرور أولية للمدير |
| `RUN_SEED` | `true` (أول نشر فقط، ثم احذفها أو `false`) |
| `SESSION_DRIVER` | `database` |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_LIFETIME` | `60` |
| `FILESYSTEM_DISK` | `local` |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_STORE` | `database` |

### 5. النشر

Railway يبني من `Dockerfile` تلقائياً. بعد النجاح:

- افتح **Settings** → **Networking** → **Generate Domain**.
- حدّث `APP_URL` بالرابط الجديد وأعد deploy.

### 6. تسجيل الدخول

```
الجوال: 0500000000
كلمة المرور: قيمة ADMIN_INITIAL_PASSWORD
```

سيُطلب تغيير كلمة المرور عند أول دخول.

---

## بديل: Render

1. [render.com](https://render.com) → **New** → **Blueprint** → اربط المستودع.
2. Render يقرأ `render.yaml` من الجذر.
3. عيّن `ADMIN_INITIAL_PASSWORD` و`APP_URL` يدوياً بعد أول deploy.

---

## ملاحظات

- الملفات المرفوعة (مهام، مستندات، عقود) تُخزَّن على قرص الحاوية؛ للإنتاج الدائم أضف volume أو S3 لاحقاً.
- للجدولة (`schedule:run`) أضف **Cron** في Railway أو worker منفصل.
- فحص الصحة: `https://your-domain/up`
