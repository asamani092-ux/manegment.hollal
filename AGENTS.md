# Hollal Platform — Cursor Cloud

## تشغيل المنصة على Cursor Cloud

1. أضف Secrets في [Cursor Dashboard → Cloud Agents](https://cursor.com/dashboard?tab=cloud-agents):
   - `ADMIN_INITIAL_PASSWORD` — كلمة مرور المدير الأولى
2. من Cursor Desktop: اختر **Cloud** من القائمة تحت حقل الـ Agent.
3. أو من جهاز آخر: افتح [cursor.com/agents](https://cursor.com/agents) وسجّل الدخول.
4. ابدأ Cloud Agent على مستودع `manegment.hollal` — البيئة تُحمَّل من `.cursor/environment.json`.
5. بعد اكتمال `install`، يعمل خادم Laravel على المنفذ **8000** تلقائياً.
6. افتح المنفذ من أيقونة **🔌 Ports** في واجهة الـ Agent، أو استخدم **Remote Desktop** لاختبار الواجهة داخل السحابة.

## تسجيل الدخول

```
الجوال: 0500000000
كلمة المرور: قيمة ADMIN_INITIAL_PASSWORD من Secrets (نفسها في .env محلياً)
```

إذا فشل الدخول بعد تغيير الـ Secret: أعد تشغيل Cloud Agent أو نفّذ `php artisan db:seed --class=AdminUserSeeder --force` داخل `hollal-platform`.

سيُطلب تغيير كلمة المرور عند أول دخول.

## أوامر يدوية (إن لزم)

```bash
cd hollal-platform
php artisan migrate:fresh --seed --force
php artisan serve --host=0.0.0.0 --port=8000
```

## ملاحظة

هذا الإعداد لـ **Cursor Cloud Agents** (VM معزولة + port forwarding).  
للنشر الإنتاجي الدائم على **Hostinger** راجع `hollal-platform/docs/DEPLOYMENT.md`.
