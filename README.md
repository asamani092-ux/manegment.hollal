# منصة حلّل الإدارية

مستودع تطبيق Laravel (`hollal-platform/`) بنظام تصميم `ds-*`.

## التشغيل المحلي

```bash
cd hollal-platform
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

تسجيل الدخول: جوال `0500000000` / كلمة المرور من `ADMIN_INITIAL_PASSWORD` في `.env`.

## الوثائق

| المسار | المحتوى |
|--------|---------|
| `hollal-platform/docs/DEPLOYMENT.md` | نشر الإنتاج (Hostinger) |
| `hollal-platform/docs/build-orders/` | بروتوكول وأوامر البناء |
| `hollal-platform/docs/specs/` | المواصفات v1.0 + تعديلات v1.1 |
| `hollal-platform/docs/ui-reference/` | مرجع الواجهة البصري |

نظام التصميم الحي: `hollal-platform/public/css/` (لا مجلد `halal-design-system` منفصل).
