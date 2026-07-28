# مصفوفة إعادة مطابقة المواصfات — منصة حلل

**التاريخ:** 2026-07-20 · **الأساس:** `main` ≈ `09f4c85` · **الحكم:** الأوامر كانت تُوسَم «منفّذ» عند طبقة خدمات/اختبارات دون تنقل UAT.

**حالة الأوامر:** جميع ملفات `docs/build-orders/**` → **قيد إعادة المطابقة** حتى إغلاق موجتي 0+1 وتحقق 12-B1.

---

## جذر الفجوة

| # | السبب | الدليل |
|---|--------|--------|
| 1 | DoD = اختبار أخضر ≠ شاشة في التنقل | `config/navigation.php` + ملاحظات التجربة 1،12،15–19 |
| 2 | «المالية» = مصروفات فقط | `navigation.php` L33–38 |
| 3 | عهد/أصول بلا Livewire | `CustodyService`/`AssetService` + لا مسارات UI |
| 4 | اعتماد محضر بلا زر | `MeetingMinutes::approveMinutes()` بدون `wire:click` في Blade |
| 5 | بذرة فارغة | `DatabaseSeeder` لا يملأ «يحتاج تدخلك» |

---

## مصفوفة المراحل

| Phase | Claimed | Reality | Sev | Evidence |
|-------|---------|---------|-----|----------|
| 00-B1 | منجز | مطابق | — | hygiene |
| 00-B2 | منفّذ | تعريب دور #7 ناقص | P1 | `ds-role-label.blade.php` |
| 00-B3 | منفّذ | مطابق | P2 | notifications |
| 00-B4 | منفّذ | orgs/programs بلا nav | P1 | routes بدون `navigation.php` |
| 00-B5 | منفّذ | actionItems فارغة بلا seed | P1 | `DashboardIndex.php` |
| 00-B6 | منfّذ | settings بلا nav | P1 | `settings.index` route only |
| 01-B1..B5 | منfّذ | pay-scales/runs بلا nav | P2 | routes orphan |
| 02-B1..B4 | منfّذ | team/calendar/workload orphan | P2 | `web.php` |
| 03-B1 | منfّذ | approve UI missing | **P0** | `meeting-minutes.blade.php` |
| 03-B2 | منfّذ | PDF ok; print UX weak | P1 | trial note 8 |
| 04-B1 | منfّذ | validation ≠ trial | P1 | `ExpensesIndex.php` |
| 04-B2 | منfّذ | backend ok | P2 | payroll execution |
| 04-B3 | منfّذ | **no UI** | **P0** | no Livewire |
| 04-B4 | منfّذ | index orphan; no revenue CRUD UI | P1 | `FinancialDocumentsIndex` |
| 04-B5 | منfّذ | **no UI** | **P0** | no Livewire |
| 04-B6 | منfّذ | budgets/reports orphan | **P0** | nav |
| 04-B7 | منfّذ | tax-invoices orphan | **P0** | nav |
| 05-B1..B7 | منfّذ | routes ok; nav orphan | P1 | pipeline/orgs |
| 06A-B1/B2 | منfّذ | programs/templates orphan | P1 | nav |
| 06B-B1..B5 | منfّذ | backend ok | P2 | execution |
| 07-B1 | منfّذ | secondary «المزيد» | P1 | sidebar |
| 08-B1/B2 | منfّذ | center/audit orphan | P2 | nav |
| 09-B1 | منfّذ | org-tree orphan | P2 | nav |
| 10-B1 | منfّذ | grants orphan | P2 | nav |
| 11-B1 | منfّذ | chain on expenses page | P1 | trial note 13 |
| 12-B1 | منfّذ | لا يفحص nav orphan | P1 | `verification-12B1.md` |

---

## ملاحظات التجربة 1–19 → الفجوة

| # | Gap | Fix (موجة 0) |
|---|-----|--------------|
| 1 | أدوات «مكتملة» غير ظاهرة | nav مسطح |
| 2 | login checkbox | `login.blade.php` + CSS |
| 3 | يحتاج تدخلك فارغ | `DemoTrialSeeder` |
| 4 | بيانات افتراضية | seed تشغيلي |
| 5 | مهامي/أسندتها | tabs + badges |
| 6 | بطاقات مهام | شارة بيضاوية أعلى اليسار |
| 7 | أدوار | `ds-role-label` |
| 8 | طباعة محضر | `window.print()` + @media print |
| 9 | اعتماد محضر | زر approve |
| 10–11 | صرف مالي | rename + validation |
| 12 | مالية = مصروفات | nav مالي كامل |
| 13 | سلسلة في الإعدادات | `settings-index` |
| 14 | قوالب needs_review | مقصود حتى عبدالله |
| 15–18 | portal/contracts/templates/settings | nav |
| 19 | المزيد | إزالة + قائمة واحدة |

---

## ترتيب الإغلاق (منفّذ في هذا PR)

1. P0: approve + nav + custody/assets UI  
2. P1: expense form + settings + seed + dashboard enrich  
3. P2: polish + tests nav orphan  
4. 12-B1: suite + تحديث وسم الأوامر إلى **مطابق** للبنود المغلقة
