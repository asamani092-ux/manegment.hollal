# تقرير CMD-9 — مراجعة الوثائق ومساحتي

النتائج أولاً: الموظف يرفع وثيقة للمراجعة، والموارد البشرية تعتمد أو ترفض مع سبب. الاعتماد الجديد يعلّم السابق `superseded`. «مساحتي» تعرض ملف الموظف نفسه. الالتزامان `97d6959` و`b6edd6b`.

## 1. الملفات

- `app/Services/DocumentReviewService.php`
- `app/Services/DocumentRequirementService.php`
- `app/Livewire/Users/EmployeeProfileShow.php` (مصفوفة الوثائق)
- `resources/views/livewire/hr/employee-hub.blade.php`
- صلاحية `hr.documents.review`
- مسار `hr.document-reviews`
- اختبار: `HrRemainderTest::test_document_matrix_colors_and_review_permission` (حد 15 استعلامًا على المصفوفة)

## 2. الترحيلات

حقول المراجعة في `2026_10_04_080000_add_document_review_and_manager_override.php`. إضافية.

## 3. البذور

أنواع الوثائق من القائمة المرجعية `document_types` عبر `hr:seed-reference` دون استبدال بند عدّله المالك.

## 4. الاختبارات

ألوان المصفوفة: أحمر عند الغياب، أصفر عند الانتظار، أخضر بعد الاعتماد، وأحمر بعد الرفض. `php artisan test`: 818 ناجحًا، 0 فشل.

## 5. قبل / بعد

- قبل: الوثائق حقل رفع بلا حالة مراجعة.
- بعد: تقديم ثم اعتماد أو رفض، والمصفوفة تعرض اللون في تبويب النظرة والمستندات.

## 6. التجربة

`/hr/document-reviews` و`/employee-hub`. العلامة المؤكدة سابقًا `hr-ext-official`.

المستودع: `https://github.com/asamani092-ux/manegment.hollal`.

## سؤال مفتوح

لا يوجد سؤال جديد في هذا البند.

النسخة الاحتياطية: مسؤولية المالك عبر كولفاي
