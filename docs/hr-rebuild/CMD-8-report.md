# تقرير CMD-8 — التهيئة والهيكل والمدير الفعلي

النتائج أولاً: التعيين على وظيفة يمنح `default_role` إن وُجد. رئيس الوحدة يشتق مدير الموظف ما لم يُحفظ استثناء في `manager_override_id`. قائمة التهيئة تُولَّد بنودًا مفتوحة ويمكن إكمالها أو تحويلها إلى مهمة بتاريخ استحقاق. الالتزامان `7e95016` و`b6edd6b`.

## 1. الملفات

- ترحيل: `2026_10_03_120000_add_org_role_and_onboarding_items.php`
- ترحيل: `2026_10_04_080000_add_document_review_and_manager_override.php` (عمود الاستثناء)
- ترحيل ضمان: `2026_10_04_190000_ensure_employee_onboarding_items.php`
- خدمات: `OnboardingChecklistService`, `OrgStructureService`
- نماذج: `EmployeeOnboardingItem`, `OrgUnit`
- اختبار: `HrOrgOnboardingTest`, `HrDocumentOnboardingTest`

## 2. الترحيلات

كلها إضافية و`up()` يتحقق من العمود أو الجدول قبل الإنشاء حتى تنجح إعادة المحاولة على MySQL بعد انقطاع. `down()` في ترحيل الضمان فارغ عمدًا حتى لا يحذف جدولًا أنشأه ترحيل أقدم.

على قاعدة هذه الآلة بعد `migrate --force` ظهرت الحالات Ran للترحيلات من `2026_10_02_140000` حتى `2026_10_04_190000`. خادم كولفاي يطبّقها من نقطة الدخول، وليس من هذه البيئة.

## 3. البذور

بنود التهيئة المرجعية تُضاف عبر `hr:seed-reference` إن غابت. `OnboardingSeeder` لا يعيد كتابة مستخدم موجود.

## 4. الاختبارات

`php artisan test`: 818 ناجحًا، 0 فشل.

## 5. قبل / بعد

- قبل: غياب جدول `employee_onboarding_items` يُسقط صفحة الملف بخطأ 500.
- بعد: الاستعلام محروس، وترحيل الضمان ينشئ الجدول إن غاب.

## 6. التجربة

`/hr-lifecycle` و`/users/{id}/profile`. العلامة المؤكدة سابقًا `hr-ext-official`.

المستودع: `https://github.com/asamani092-ux/manegment.hollal`.

## انحراف

`recomputeDerivedManager` لا يمسح `manager_id` اليدوي إذا لم يُوجد رئيس وحدة. بعض حقول `manager_id` في المشاريع تبقى مدير مشروع وليست مدير الموظف.

النسخة الاحتياطية: مسؤولية المالك عبر كولفاي
