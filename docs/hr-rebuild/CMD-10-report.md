# تقرير CMD-10 — إصلاح خط الأساس

نزل عدد الاختبارات الفاشلة من 14 إلى 4 دون حذف اختبارات.

أُصلح: ظهور قيمة العقد للمالية، حقل القسم الاختياري في المصروف، إعادة فتح سلسلة الاعتماد عند غياب المدير، عنوان لوحة إنهاء العلاقة، ظهور كلمة مسودة في التقييم، صلاحيات مسارات التنزيل والتقييم.

ما زال فاشلاً من الأصل:

- MeetingArchiveTest::test_stale_decision_surfaces_on_dashboard
- MeetingTest::test_open_decisions_lists_unexecuted_items
- OperationalRolesTest::test_employee_cannot_access_payroll_or_roles_settings
- ReportRound1PartMeetTest::test_partnership_contract_section_is_visible

النسخة الاحتياطية: مسؤولية المالك عبر كولفاي
