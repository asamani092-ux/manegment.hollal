# 12-B1 — تقرير التحقق الشامل و UAT

> يغطي أوامر البناء **04-B6 → 11-B1** المنفّذة في هذه الدفعة، إضافة إلى إعادة
> تدقيق أمني على الكيانات الجديدة وبوابة الجهة.

---

## 1. قائمة تحقق المواصفات

| التبويب | المطلوب في المواصفة | الحالة | الدليل |
|---|---|---|---|
| المالية | موازنات مجمّعة + تنبيه 80%/100% | ✅ | `BudgetService`, `BudgetsBoard`, `BudgetAndFinancialReportTest` |
| المالية | تقارير مالية بالاشتقاق فقط + مطابقة | ✅ | `FinancialReportService::reconciles()` |
| المالية | فوترة ضريبية Phase A (تسلسل/TLV/إشعارات) | ✅ | `TaxInvoiceService`, `TlvQr`, `TaxInvoiceTest` |
| البرامج | بطاقة البرنامج كاملة + مشروع تطوير | ✅ | `ProgramShow`, `ProgramService`, `ProgramCardTest` |
| البرامج | محرر القوالب الخماسي + زرع 61/135 + علم المراجعة | ✅ | `PlanTemplateService`, `PlanTemplateSeeder`, `PlanTemplateTest` |
| الشراكات | سجل الجهات + صفحة الجهة (رحلات/مشاريع/أثر/خط زمني) | ✅ | `OrganizationsIndex`, `OrganizationShow` |
| الشراكات | الرحلة السباعية + سجلات المراحل + الركود | ✅ | `PartnershipPipelineService`, `PartnershipStale` |
| الشراكات | عروض الأسعار بالإصدارات + PDF بالرقم الضريبي | ✅ | `QuoteService`, `CompanyProfile` |
| الشراكات | العقد + جدول الدفعات + النسخة الموقعة (hash) + شرطا التعاقد | ✅ | `PartnershipContractService` |
| الشراكات | الرابط الفريد: عزل + rate-limit + تسجيل كامل | ✅ | `PartnerPortal`, `PartnerPortalService`, `throttle:portal` |
| الشراكات | الدفعات → إيراد مؤكد مرة واحدة + إصدار فاتورة | ✅ | `PartnershipPaymentService` |
| الشراكات | زر توليد مشروع → تسليم لـ 06B | ✅ | `ProjectGenerationRequestService` |
| المشاريع | محرك التوليد (بنود/تواريخ/أدوار/هرمية) | ✅ | `ProjectGenerationService`, `ProjectExecutionTest` |
| المشاريع | صفحة المشروع: شجرة الخطة + الإنجاز الموزون + الفريق | ✅ | `ProjectProgressService`, `ProjectExecution` |
| المشاريع | الزيارات والاستشارات + المهمة التصحيحية + العدادات | ✅ | `VisitService` |
| المشاريع | القياس القبلي/البعدي + صعود الأثر | ✅ | `MeasurementService` |
| المشاريع | الإغلاق + التقرير الختامي + الدرس + فرصة التجديد | ✅ | `ProjectClosureService` |
| المستندات | ربط المصدر + الإصدارات + النماذج + مراجعة السياسات | ✅ | `DocumentLibraryService`, `PolicyReviewDue` |
| التقارير | المركز الموحّد + اللقطات غير القابلة للتعديل | ✅ | `ReportCenterService`, `ReportSnapshot` |
| التقارير | الأثر + KPIs + سجل النشاط (عرض فقط + تصدير) | ✅ | `AuditLogIndex` |
| الهيكل | الشجرة إدارة←وحدة←وظيفة + بطاقات + نقل + لجان | ✅ | `OrgStructureService`, `OrgTreeIndex` |
| الأدوار | شاشة المنح + الاستثناءات + مصفوفة «من يملك ماذا» | ✅ | `PermissionGrantService`, `GrantsIndex` |
| الإعدادات | الأقسام المتبقية + وضع الصيانة + النسخ الاحتياطي | ✅ | `PlatformSettingsSeeder`, `MaintenanceMode`, `BackupService` |

---

## 2. إعادة التدقيق الأمني (IDOR + البوابة)

الاختبارات في `tests/Feature/VerificationAudit12B1Test.php`:

- الجهات والبرامج والموازنات والتقارير المالية والفواتير الضريبية وقوالب الخطط
  ومصفوفة الصلاحيات والهيكل التنظيمي — كلها مغلقة بلا صلاحية صريحة.
- شاشة تنفيذ المشروع تمر عبر `ProjectPolicy`.
- **بوابة الجهة:** الرمز يفتح شراكته فقط؛ محاولة الوصول لعرض جهة أخرى ترجع
  `ModelNotFoundException` ولا تسرّب أي بيانات.
- الرمز المُبطل أو المنتهي أو المجهول → 404.
- **فحص شامل للمسارات:** كل مسار مُوثَّق يحمل `permission:` أو يُستثنى صراحة
  (تسجيل الدخول، البوابة العامة، تنزيلات الملفات المحكومة بسياساتها).

---

## 3. الأداء

- كل مؤشرات التقارير مشتقة عند القراءة مع فهارس على المفاتيح الساخنة
  (`report_snapshots(kind, period)`, `measurement_responses(project_id, phase)`,
  `template_items(template_version_id, level)`, `tax_invoices(source_type, source_id)`).
- شجرتا الخطة والهيكل تُبنيان بجلب واحد + `groupBy` في الذاكرة (لا N+1).
- زمن تنفيذ الحزمة الكاملة (391 اختبارًا): ~55 ثانية على sqlite in-memory.

**ملاحظة للإنتاج:** لوحة المشروع تستدعي `ProjectProgressService` لكل مشروع في
حساب KPI؛ عند تجاوز ~200 مشروع يُنصح بتخزين مؤقت لخمس دقائق.

---

## 4. جولات UAT

| الجولة | السيناريو | النتيجة |
|---|---|---|
| 1 | شراكة كاملة: فرصة → عرض → عقد → دفعة → فاتورة → توليد مشروع | ✅ مغطّاة آليًا في `PartnershipModuleTest` + `ProjectExecutionTest` |
| 2 | مشروع كامل: توليد → زيارة → توصية → مهمة تصحيحية → قياس → إغلاق → تجديد | ✅ `ProjectExecutionTest::test_closure_flow_generates_approves_delivers_and_opens_renewal` |
| 3 | مالية: موازنة 80/100% + تقرير شهري مطابق + فاتورة ضريبية | ✅ `BudgetAndFinancialReportTest`, `TaxInvoiceTest` |
| 4 | صلاحيات: استثناء بسبب + مصفوفة + سجل تدقيق | ✅ `StructureRolesSettingsTest` |

**بانتظار المستخدم:** جلسة المراجعة مع عبدالله على محرر القوالب (`needs_review`
مفعّل على القالبين، والتوليد الحقيقي متوقف حتى الاعتماد) — وهذا سلوك مقصود لا خلل.

---

*آخر تحديث: يوليو 2026 — 12-B1*
