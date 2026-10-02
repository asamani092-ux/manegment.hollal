# تقرير CMD-0 — محرك القوائم المرجعية والشروحات

النتائج أولاً: المحرك مدمج في `main` بالالتزام `915e557`. اختبارات `ReferenceListServiceTest` نجحت (6). المسار الجديد غير منشور بعد على التجربة (`/settings/lists` يعيد 404 لأن صورة كولفاي الحالية أقدم). `/login` يعيد 200.

## 1. الملفات

- ترحيلات: `database/migrations/2026_10_02_140000_create_reference_lists_tables.php`
- نماذج: `ReferenceList`, `ReferenceItem`, `Concerns/HasReferenceItem`
- خدمات: `ReferenceListService`
- إعداد: `config/reference_lists.php`, `config/navigation.php`
- واجهة: `ReferenceListsIndex` + `reference-lists-index.blade.php` + `help-button` + `field-hint` + ترويسة الصفحة
- بذور: `ReferenceListsSeeder`, صلاحيات في `PermissionSeeder`
- مسارات: `routes/web.php`
- اختبارات: `tests/Feature/ReferenceListServiceTest.php`

## 2. الترحيلات على الخادم

لم تُطبَّق بعد. لا توجد قشرة على خادم كولفاي من هذه البيئة، والنسخ الاحتياطي البعيد غير متاح. الترحيل إضافي و`down()` يحذف الجدولين الجديدين فقط. يُنفَّذ تلقائياً عند إعادة النشر لأن `docker/entrypoint.sh` يشغّل `php artisan migrate --force`.

## 3. البذور

`ReferenceListsSeeder` لم يُشغَّل على الخادم. بعد إعادة النشر:

```bash
php artisan db:seed --class=ReferenceListsSeeder --force
php artisan optimize:clear
```

## 4. الاختبارات

`ReferenceListServiceTest`: 6 ناجحة.

`php artisan test` الكامل: 773 اختباراً، 758 ناجحاً، 15 فشلاً كانت موجودة على `main` قبل هذا الأمر (منها `ContractValueVisibilityTest` و`ExpenseCategoryEnforcementTest` و`VerificationAudit12B1Test`). لم تُضعَّف.

## 5. إثبات البحث

```bash
rg "TYPE_ANNUAL|TYPE_SICK" hollal-platform/app/Services/ReferenceListService.php hollal-platform/database/seeders/ReferenceListsSeeder.php
```

لا نتائج. لم تُزرع قيم إجازات أو مخالفات؛ زُرعت تعريفات القوائم فقط مع أسباب الاستبعاد وشروح الشاشة.

## 6. القبول

- مراجعة عنصر مُشار إليه تنشئ النسخة 2 ويبقى المؤشر على النسخة 1 ويعرض قيمها. قبل: لا نسخ. بعد: اختبار `test_revising_referenced_item_keeps_version_one_pointer`.
- حذف المُشار إليه يُرفض بالرسالة «لا يمكن حذف عنصر مُشار إليه».
- `activeItems` في تاريخ سابق يعيد النسخة السارية حينها.
- معاينة الاستيراد تعرض الإضافات والتعديلات، والاعتماد يسجّل السبب في سجل التدقيق.
- زر الشرح يظهر للموضوع `settings.lists` ويختفي عند غياب الموضوع والصلاحية.

## 7. التحقق الحي

- `https://s1jdubrp1eit4tuqu6v1y0hu.91.98.234.130.sslip.io/login` → 200
- `.../settings/lists` → 404 (المسار غير موجود في النشر الحالي)

## 8. الدمج

`git ls-remote origin main` → `915e557c15158999758096020b3aa7f38d07cf1a`

الدمج تم بتتبّع سريع إلى `main` لأن إنشاء طلب السحب عبر التكامل مرفوض (صلاحية collaborator).

## 9. النسخ الاحتياطي

لم يُؤخذ. لا وصول إلى قاعدة MySQL على الخادم. لا تحويل لبيانات قائمة.

## 10. انحرافات وأسئلة

- النشر على كولفاي يدوي من لوحة المالك (إعادة نشر فرع `main`). لا خط أنابيب جديد.
- أسئلة مفتوحة: لا شيء في هذا الأمر.
