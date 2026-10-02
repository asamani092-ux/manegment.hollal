# خط أساس الاختبارات الفاشلة

قبل CMD-0 كان `php artisan test` يفشل في 15 اختباراً. هذه أسماؤها كما ظهرت على فرع CMD-1 قبل إصلاح تسميات الصلاحيات (العدد لم يزد):

1. Tests\Feature\ContractValueVisibilityTest::test_finance_user_can_see_contract_value_in_response
2. Tests\Feature\ContractValueVisibilityTest::test_masked_value_method_returns_amount_for_finance
3. Tests\Feature\ExpenseCategoryEnforcementTest::test_department_and_project_are_optional
4. Tests\Feature\HrLifecyclePanelTest::test_unified_panel_opens_holds_and_tasks_tabs
5. Tests\Feature\MeetingArchiveTest::test_stale_decision_surfaces_on_dashboard
6. Tests\Feature\MeetingTest::test_open_decisions_lists_unexecuted_items
7. Tests\Feature\OperationalRolesTest::test_employee_cannot_access_payroll_or_roles_settings
8. Tests\Feature\PermissionLabelsTest::test_every_seeded_permission_has_an_arabic_label
9. Tests\Feature\ReportRound1HrTest::test_profile_edit_can_set_password_and_deactivate
10. Tests\Feature\ReportRound1HrTest::test_profile_edit_saves_job_fields
11. Tests\Feature\ReportRound1PartMeetTest::test_partnership_contract_section_is_visible
12. Tests\Feature\ReportRound2FinTest::test_expense_reject_visible_on_all_tab_and_return_reopens_for_requester
13. Tests\Feature\ReportRound2HrTest::test_evaluations_page_shows_lifecycle_text
14. Tests\Feature\SpecRematchWave0Test::test_expense_form_accepts_optional_project_and_department
15. Tests\Feature\VerificationAudit12B1Test::test_every_authenticated_route_carries_a_permission_or_a_policy

البند 8 أُصلح في CMD-1 بإضافة التسميات العربية للصلاحيات الجديدة. العدد الحالي المتوقع 14. لا يُقبل فشل جديد. CMD-10 يعالج الباقي من الجذر.
