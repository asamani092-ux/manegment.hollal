# كتالوج مكوّنات نظام التصميم — منصة حلّل

مرجع لتطوير/إعادة بناء طبقة `ds-*` (Blade + CSS + توافق Livewire).  
الهوية: `#0F3446` · `#C4A052` · `#27A588` · خط IBM Plex Sans Arabic · RTL أولاً.

الملفات الحالية: `public/css/hollal-ds.css` → `tokens-*` · `base` · `layout` · `components`  
Blade الحالي: `resources/views/components/ds-*.blade.php`

---

## 0) الأساس (Tokens) — مطلوب تثبيته قبل المكوّنات

| الرمز | الغرض |
|--------|--------|
| `--ds-primary` / `--ds-secondary` / `--ds-accent` | ألوان الهوية |
| `--ds-danger` / `--ds-success` / `--ds-warning` / `--ds-info` | حالات |
| `--ds-bg` / `--ds-card` / `--ds-border` / `--ds-text` / `--ds-text-muted` | سطح ونص |
| `--ds-radius-xs..md` · `--ds-shadow-sm..lg` | شكل وظل |
| `--ds-font-primary` · أحجام `--ds-text-*` | طباعة |
| `--ds-space-*` · `--ds-navbar-height` · `--ds-sidebar-width` | تباعد وتخطيط |
| وضع داكن اختياري عبر `tokens-dark.css` | لا يُفرض على الشاشات الحالية |

---

## 1) موجود اليوم (Blade) — يُراجع ويُثبَّت API

| المكوّن | المسار | ملاحظات |
|---------|--------|---------|
| `x-ds-page` | `ds-page.blade.php` | غلاف صفحة RTL |
| `x-ds-page-header` | `ds-page-header.blade.php` | عنوان + زر إجراء اختياري |
| `x-ds-table` | `ds-table.blade.php` | لفّ جدول + تمرير |
| `x-ds-form-group` | `ds-form-group.blade.php` | تسمية + خطأ + تلميح |
| `x-ds-modal` | `ds-modal.blade.php` | **ضعيف مع Livewire** إذا أُزيل الجذر بـ `@if` |
| `x-ds-toast` | `ds-toast.blade.php` | إشعارات عابرة |
| `x-ds-empty-state` | `ds-empty-state.blade.php` | فراغ بجدول/قائمة |
| `x-ds-status-badge` | `ds-status-badge.blade.php` | حالات عربية |
| `x-ds-action-icons` | `ds-action-icons.blade.php` | عرض/تعديل/حذف |
| `x-ds-role-label` | `ds-role-label.blade.php` | تعريب اسم الدور |
| `x-ds-permission-label` | `ds-permission-label.blade.php` | تعريب صلاحية |
| ترقيم | `pagination/ds.blade.php` | روابط Livewire |

---

## 2) مطلوب بناؤه (أولوية التطوير)

### أ) حرج — يُستخدم في كل الشاشات ويمنع تكرار الكود / أعطال UAT

#### 1. `x-ds-button`
- **Variants:** `primary` · `outline` · `ghost` · `danger` · `secondary`
- **Sizes:** `sm` · `md` · `lg`
- **Props:** `type`, `href?`, `icon?`, `loading?`, `disabled?`, `wire:click?`
- **قاعدة:** لا أزرار خام بـ `class="ds-btn …"` في الشاشات الجديدة

#### 2. `x-ds-badge`
- **Variants:** `neutral` · `info` · `success` · `warning` · `danger` · `muted`
- **Props:** `count?` (رقم LTR)، `dot?`
- يستبدل `ds-badge-*` المبعثر + شارات العدّ بجانب الأزرار

#### 3. `x-ds-confirm` (نافذة تأكيد)
- تأكيد إجراء خطير (تجميد، حذف، إغلاق حساب)
- **Props:** `show`, `title`, `body`, `confirmLabel`, `cancelLabel`, `confirmAction`, `cancelAction`, `variant=danger|primary`
- **Livewire:** يبقى الـ overlay في الـ DOM أو يُدار بـ Alpine/`wire:ignore` — لا يُدمَّر الجذر بطريقة تكسر morph

#### 4. `x-ds-dialog` / إصلاح `x-ds-modal`
- نافذة عامة: `sm|md|lg` · عنوان · جسم · تذييل اختياري · Escape · نقر الخلفية
- **متطلب صارم:** يعمل مع Livewire 3 بدون اختفاء بعد `wire:click`
- `z-index ≥ 1300` فوق الشريط والقوائم

#### 5. `x-ds-drawer` (لوحة عائمة جانبية أو وسطية كبيرة)
- لمتابعة التفاصيل: مهام + موانع · أعضاء لجنة · بطاقة وظيفة
- **Props:** `show`, `title`, `size`, `tabs?`, `closeAction`
- جسم قابل للتمرير؛ تذييل ثابت للإغلاق/الحفظ

#### 6. `x-ds-tabs`
- تبويبات داخل الصفحة أو داخل الـ drawer
- **Props:** `items[{id,label,count?}]`, `active`, `wire:click` / `setTab`
- يستبدل أزرار الفلتر كـ«تبويب» في الهيكل/المنح/التهيئة

#### 7. `x-ds-filters-row`
- صف فلاتر موحّد (بحث + selects + تاريخ)
- **Slots:** حقول؛ زر مسح اختياري
- معيار P1 في `SELF-REVIEW-LOG`

#### 8. `x-ds-alert`
- **Variants:** `info` · `success` · `warning` · `danger`
- عنوان اختياري + نص؛ قابل للإغلاق

#### 9. `x-ds-card`
- حاوية محتوى تفاعلية (قائمة مهام، موانع، بطاقة مشروع)
- **Props:** `title?`, `footer?`, `hover?`, `selected?`
- حالات خاصة لاحقاً: `ds-task-card` · `is-completed`

#### 10. `x-ds-input` + `x-ds-select` + `x-ds-textarea` + `x-ds-checkbox`
- حقول نموذج بهوية موحّدة + حالة خطأ من `ds-form-group`
- دعم `dir=ltr` للأرقام/تواريخ/جوال

---

### ب) مهم — قوائم وجداول وتفاعل

| المكوّن | الغرض |
|---------|--------|
| `x-ds-link` | رابط نصي داخل جدول/بطاقة (بدل `ds-link` الخام) |
| `x-ds-toolbar` | مجموعة أزرار أفقية (`ds-toolbar-actions`) |
| `x-ds-section` | عنوان قسم + محتوى (`ds-section` / `ds-section-title`) |
| `x-ds-stat` | بطاقة مؤشر (لوحة التحكم) |
| `x-ds-progress` | نسبة إنجاز (مشاريع / مهام إنهاء `1/4`) |
| `x-ds-count-button` | زر موحّد: تسمية + `(n)` أو شارة عدد — **لتوحيد مهام/موانع** |
| `x-ds-list-item` | صف قائمة داخل drawer (أيقونة حالة + عنوان + إجراء) |
| `x-ds-file-chip` | مرفق: اسم عربي سليم + تنزيل + حذف |
| `x-ds-avatar` | أحرف الاسم / صورة مصغّرة |

---

### ج) تخطيط وقشرة التطبيق

| المكوّن / النمط | الغرض |
|-----------------|--------|
| Sidebar group + item | طي/فتح · بحث · `data-nav-label` · حالة نشطة |
| Navbar | حساب · إشعارات · Toast أعلى الوسط |
| `x-ds-collapsible` | أقسام قابلة للطي (يحتاج تدخلك في الرئيسية) |
| `x-ds-skeleton` | تحميل Livewire |
| Print / PDF sheet | تخطيط طباعة عربي (محاضر · فواتير · تقارير) — طبقة منفصلة |

---

### د) نطاق خاص (يُبنى بعد الأساس)

| المكوّن | أين يُستخدم |
|---------|-------------|
| `x-ds-org-node` | شجرة الهيكل (إدارة/وحدة/وظيفة) |
| `x-ds-calendar` | تقويم المهام (بديل الشكل الحالي) |
| `x-ds-kanban-column` | رحلة الشراكات / أحمال |
| `x-ds-permission-grid` | منح الصلاحيات (الكل / قسم) |
| `x-ds-chart-frame` | إطار رسوم التقارير |

---

## 3) قواعد إلزامية لكل مكوّن جديد

1. **RTL افتراضي**؛ الأرقام والتواريخ والجوال بـ `ds-ltr-num` أو `dir=ltr`.
2. **لا بطاقات في الـ hero**؛ البطاقات فقط لحاوية تفاعل (قائمة/اختيار).
3. **إمكانية وصول:** `aria-*` · تركيز · Escape للإغلاق · تباين كافٍ.
4. **Livewire-safe:** لا تعتمد على تدمير جذر المكوّن لإخفائه إن كان ذلك يكسر الـ morph؛ فضّل `wire:key` ثابت + إظهار/إخفاء، أو Alpine محلياً.
5. **واجهة ثابتة:** أسماء props لا تُكسر الشاشات القائمة؛ التوسيع بالإضافة التراكمية.
6. **تعريب الحالات** داخل `status-badge` لا نصوص إنجليزية خام في UI.
7. كل مكوّن: مثال Blade قصير + اختبار بصري/Feature عند السلوك التفاعلي.

---

## 4) ترتيب تنفيذ مقترح

```
Tokens مراجعة
→ Button · Badge · Alert · Form controls
→ Dialog/Confirm (Livewire-safe) · Drawer · Tabs · CountButton
→ FiltersRow · Card · Toolbar · Empty/Status (تثبيت الحالي)
→ FileChip · Progress · ListItem
→ OrgNode · Calendar · Kanban (حسب المرحلة)
```

---

## 5) خريطة استبدال سريعة (من الكود الحالي)

| اليوم (مبعثر) | الهدف |
|----------------|--------|
| `button.ds-btn.ds-btn-outline.ds-btn-sm` | `x-ds-button` / `x-ds-count-button` |
| `span.ds-badge.ds-badge-warning` | `x-ds-badge` |
| تكرار `ds-modal-overlay` يدوياً في التهيئة | `x-ds-confirm` + `x-ds-drawer` |
| أزرار تبويب `wire:click="$set('tab'…)"` | `x-ds-tabs` |
| `ds-filters-row` مكرّر | `x-ds-filters-row` |
| بطاقات مهام مكتملة خضراء | `x-ds-card` + modifier `completed` |

---

*آخر تحديث مرتبط بملاحظات UAT المرحلة 1 (تهيئة/موانع، منح+أدوار، تقييم/أرشيف، هيكل).*
