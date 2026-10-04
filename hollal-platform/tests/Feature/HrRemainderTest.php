<?php

namespace Tests\Feature;

use App\Livewire\Hr\DocumentReviewsIndex;
use App\Livewire\Hr\ViolationsIndex;
use App\Models\EvaluationCycle;
use App\Models\EvaluationScore;
use App\Models\Responsibility;
use App\Services\EvaluationService;
use App\Models\AttendanceCycleDay;
use App\Models\ChartOfAccount;
use App\Models\EmployeeOnboardingItem;
use App\Models\LeaveRequest;
use App\Models\OrgUnit;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PeriodicEvaluation;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Violation;
use App\Services\AttendanceCycleService;
use App\Services\DocumentRequirementService;
use App\Services\EvaluationCycleService;
use App\Services\JournalService;
use App\Services\LeaveBalanceService;
use App\Services\OnboardingChecklistService;
use App\Services\OrgStructureService;
use App\Services\PayrollAdjustmentService;
use App\Services\PerformanceService;
use App\Services\QuarterlyEvaluationService;
use App\Services\ReferenceListService;
use App\Services\ViolationService;
use App\Support\CoaCodes;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HrRemainderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);
    }

    public function test_paid_leave_creates_no_adjustment_and_modify_keeps_computed_amount(): void
    {
        $employee = User::factory()->create();
        $annual = app(ReferenceListService::class)->item('leave_types', 'annual');
        $leave = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => 'سنوية',
            'reference_item_id' => $annual->id,
            'from_date' => '2026-04-01',
            'to_date' => '2026-04-02',
            'days_count' => 2,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        app(LeaveBalanceService::class)->recordPayImpacts($leave->fresh('referenceItem'));
        $this->assertSame(0, PayrollAdjustment::query()->count());

        $sick = app(ReferenceListService::class)->item('leave_types', 'sick');
        $sickLeave = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => 'مرضية',
            'reference_item_id' => $sick->id,
            'from_date' => '2026-05-01',
            'to_date' => '2026-05-02',
            'days_count' => 2,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        app(LeaveBalanceService::class)->recordPayImpacts($sickLeave->fresh('referenceItem'));
        $this->assertSame(0, PayrollAdjustment::query()->count());

        $item = app(ReferenceListService::class)->item('payroll_adjustment_items', 'excellence_bonus');
        $line = app(PayrollAdjustmentService::class)->manualEarning($employee, $item, '2026-08', 100, 'مشروع أ');
        try {
            app(PayrollAdjustmentService::class)->assertNoProposed('2026-08');
            $this->fail('كان يجب منع الاعتماد');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('تسويات', $e->getMessage());
        }
        $changed = app(PayrollAdjustmentService::class)->modify($line, 80, 'تخفيض');
        $this->assertSame('100.00', number_format((float) $changed->computed_amount, 2, '.', ''));
        $this->assertSame('80.00', number_format((float) $changed->amount, 2, '.', ''));
        app(PayrollAdjustmentService::class)->assertNoProposed('2026-08');
    }

    public function test_journal_stays_balanced_per_account_code(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $employee = User::factory()->create();
        $run = PayrollRun::query()->create([
            'month' => '2026-09',
            'status' => PayrollRun::STATUS_EXECUTED,
            'cycle_from' => '2026-09-01',
            'cycle_to' => '2026-09-30',
        ]);
        $item = PayrollRunItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'base' => 1000,
            'gross' => 1000,
            'net' => 1000,
            'variables' => [],
        ]);
        $bonus = app(ReferenceListService::class)->item('payroll_adjustment_items', 'project_bonus');
        PayrollAdjustment::query()->create([
            'employee_id' => $employee->id,
            'month' => '2026-09',
            'reference_item_id' => $bonus->id,
            'amount' => 100,
            'computed_amount' => 100,
            'status' => 'posted',
            'payroll_run_item_id' => $item->id,
        ]);

        $entry = app(JournalService::class)->postPayrollExecuted($run);
        $entry->load('lines');
        $this->assertEquals(
            round((float) $entry->lines->sum('debit'), 2),
            round((float) $entry->lines->sum('credit'), 2)
        );
        $this->assertTrue(
            $entry->lines->contains(fn ($line) => (int) $line->account_id === (int) ChartOfAccount::query()->where('code', CoaCodes::EXP_SALARIES)->value('id'))
        );
    }

    public function test_violation_statement_gate_cap_and_suggestion(): void
    {
        $employee = User::factory()->create();
        $other = User::factory()->create();
        $hr = User::factory()->create();
        $service = app(ReferenceListService::class);
        $draft = $service->createDraft('violations', 'late-sample', 'تأخر — للاختبار', [
            'category' => 'مواعيد العمل',
            'auto_detectable' => 'absence',
            'origin' => 'company',
            'penalties' => [['type' => 'deduct_days', 'value' => 6]],
        ], now()->toDateString());
        $item = $service->publish($draft, null, 'اختبار');

        $cycle = app(AttendanceCycleService::class)->open('2026-08');
        $day = AttendanceCycleDay::query()->create([
            'attendance_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'date' => '2026-08-03',
            'status' => 'غياب',
            'late_minutes' => 0,
            'chargeable_late_minutes' => 0,
            'overtime_hours' => 0,
        ]);
        $this->assertSame(1, app(ViolationService::class)->suggestFromCycle($cycle));
        $suggested = Violation::query()->where('source_ref', 'day:'.$day->id.':absence')->first();
        app(ViolationService::class)->exclude($suggested, 'إذن مسبق', $hr);
        $this->assertSame('excluded', $suggested->fresh()->status);

        $open = app(ViolationService::class)->recordManual($employee, $item, 'وقائع', Carbon::parse('2026-08-04'), $hr);
        $this->expectException(\RuntimeException::class);
        app(ViolationService::class)->submitStatement($open, $other, 'ليست إفادتي');
    }

    public function test_decision_waits_for_statement_then_flags_monthly_cap(): void
    {
        $employee = User::factory()->create();
        $hr = User::factory()->create();
        $service = app(ReferenceListService::class);
        $draft = $service->createDraft('violations', 'cap-sample', 'سقف — للاختبار', [
            'category' => 'مواعيد العمل',
            'origin' => 'company',
            'penalties' => [['type' => 'deduct_days', 'value' => 6]],
        ], now()->toDateString());
        $item = $service->publish($draft, null, 'اختبار');
        $violation = app(ViolationService::class)->recordManual($employee, $item, 'وقائع', now(), $hr);

        try {
            app(ViolationService::class)->decide($violation, 'apply', $hr, 'مبكر');
            $this->fail('كان يجب منع القرار');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('الإفادة', $e->getMessage());
        }

        app(ViolationService::class)->submitStatement($violation->fresh(), $employee, 'أوضح السبب');
        $decided = app(ViolationService::class)->decide($violation->fresh(), 'apply', $hr, 'تطبيق');
        $this->assertTrue($decided->cap_flagged);
        $this->assertSame(6, (int) $decided->decided_penalty['value']);
        $this->assertSame('applied', $decided->status);
    }

    public function test_flexible_cycles_summary_and_legacy_archive(): void
    {
        $template = app(QuarterlyEvaluationService::class)->createTemplate('قالب حر', [
            ['section' => 'مدير', 'question_text' => 'الالتزام', 'weight' => 100],
        ]);
        $first = app(EvaluationCycleService::class)->createFlexibleCycle($template->name.' أ', $template, '2026-01-01', '2026-02-01');
        $second = app(EvaluationCycleService::class)->createFlexibleCycle($template->name.' ب', $template, '2026-06-01', '2026-07-01');
        $this->assertNull($first->year);
        $this->assertNull($second->quarter);
        $this->assertNotSame($first->id, $second->id);

        $employee = User::factory()->create();
        $project = Project::factory()->create();
        Task::query()->create([
            'title' => 'منجزة',
            'assigned_by' => $employee->id,
            'assigned_to' => $employee->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'due_date' => now(),
            'completed_at' => now()->subHour(),
            'final_rating' => 'متميز',
        ]);
        Task::query()->create([
            'title' => 'متأخرة',
            'assigned_by' => $employee->id,
            'assigned_to' => $employee->id,
            'status' => 'overdue',
            'due_date' => now()->subDay(),
        ]);
        $summary = app(PerformanceService::class)->summary($employee, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame(2, $summary['tasks']['count']);
        $this->assertSame(50.0, $summary['tasks']['on_time_pct']);
        $this->assertSame(1, $summary['tasks']['overdue']);
        $this->assertArrayHasKey($project->id, $summary['projects']);

        $legacy = PeriodicEvaluation::query()->create([
            'employee_id' => $employee->id,
            'period' => '2024-Q1',
            'status' => PeriodicEvaluation::STATUS_ARCHIVED,
        ]);
        $archived = app(EvaluationCycleService::class)->importLegacy($legacy);
        $this->assertSame('أرشيف — 2024-Q1', $archived->name);
    }

    public function test_document_matrix_colors_and_review_permission(): void
    {
        $employee = User::factory()->create();
        $hr = User::factory()->create();
        $hr->givePermissionTo('hr.documents.review');
        $matrix = app(DocumentRequirementService::class)->matrix($employee);
        $id = collect($matrix)->firstWhere('code', 'id_card');
        $this->assertSame('red', $id['color']);

        $doc = app(\App\Services\DocumentReviewService::class)->submit($employee, 'هوية', 'ids/a.pdf');
        $pending = collect(app(DocumentRequirementService::class)->matrix($employee))->firstWhere('code', 'id_card');
        $this->assertSame('yellow', $pending['color']);
        app(\App\Services\DocumentReviewService::class)->approve($doc, $hr);
        $approved = collect(app(DocumentRequirementService::class)->matrix($employee))->firstWhere('code', 'id_card');
        $this->assertSame('green', $approved['color']);

        $next = app(\App\Services\DocumentReviewService::class)->submit($employee, 'هوية', 'ids/b.pdf');
        app(\App\Services\DocumentReviewService::class)->approve($next, $hr);
        $this->assertSame('superseded', $doc->fresh()->status);
        $rejected = app(\App\Services\DocumentReviewService::class)->submit($employee, 'هوية', 'ids/c.pdf');
        app(\App\Services\DocumentReviewService::class)->reject($rejected, $hr, 'غير واضحة');
        $this->assertSame('red', collect(app(DocumentRequirementService::class)->matrix($employee))->firstWhere('code', 'id_card')['color']);

        Livewire::actingAs($employee)->test(DocumentReviewsIndex::class)->assertForbidden();

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(DocumentRequirementService::class)->matrix($employee);
        $this->assertLessThanOrEqual(15, count(DB::getQueryLog()));
    }

    public function test_onboarding_task_uses_manager_and_head_updates_effective_manager(): void
    {
        $manager = User::factory()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        app(OnboardingChecklistService::class)->generate($employee);
        $item = EmployeeOnboardingItem::query()->where('user_id', $employee->id)->where('status', 'open')->first();
        $task = app(OnboardingChecklistService::class)->convertToTask($item, $employee, $manager, '2026-12-01');
        $this->assertSame($manager->id, $task->assigned_to);
        $this->assertSame('2026-12-01', $task->due_date->toDateString());

        $head = User::factory()->create();
        $next = User::factory()->create();
        $service = app(OrgStructureService::class);
        $top = $service->createUnit('الإدارة العليا', OrgUnit::LEVEL_TOP);
        $admin = $service->createUnit('الإدارة', OrgUnit::LEVEL_ADMINISTRATION, $top);
        $unit = $service->createUnit('قسم', OrgUnit::LEVEL_UNIT, $admin);
        $unit->update(['manager_id' => $head->id]);
        $job = $service->createUnit('محلل', OrgUnit::LEVEL_JOB, $unit, ['default_role' => 'Employee']);
        $placed = $service->placeEmployee($employee, $job);
        $this->assertSame($head->id, $placed->fresh()->manager_id);
        $unit->update(['manager_id' => $next->id]);
        $this->assertSame($next->id, $employee->fresh()->manager_id);
    }

    public function test_window_hides_old_violations_and_manual_record_uses_the_list(): void
    {
        $hr = User::factory()->create();
        $hr->givePermissionTo(['hr.violations.view', 'hr.violations.manage']);
        $employee = User::factory()->create(['name' => 'موظف النافذة']);
        $service = app(ReferenceListService::class);
        $draft = $service->createDraft('violations', 'window-sample', 'نافذة — للاختبار', [
            'category' => 'سلوك العامل',
            'origin' => 'company',
            'penalties' => [['type' => 'warning', 'value' => 0]],
        ], now()->toDateString());
        $item = $service->publish($draft, null, 'اختبار');

        Violation::query()->create([
            'employee_id' => $employee->id,
            'reference_item_id' => $item->id,
            'status' => 'applied',
            'occurred_on' => now()->subDays(10)->toDateString(),
            'discovered_on' => now()->subDays(10)->toDateString(),
            'facts' => 'داخل النافذة',
        ]);
        Violation::query()->create([
            'employee_id' => $employee->id,
            'reference_item_id' => $item->id,
            'status' => 'applied',
            'occurred_on' => now()->subDays(400)->toDateString(),
            'discovered_on' => now()->subDays(400)->toDateString(),
            'facts' => 'خارج النافذة',
        ]);

        Livewire::actingAs($hr)
            ->test(ViolationsIndex::class)
            ->set('tab', 'window')
            ->assertSee('داخل النافذة', false)
            ->assertDontSee('خارج النافذة', false)
            ->set('manualEmployeeId', $employee->id)
            ->set('manualItemId', $item->id)
            ->set('manualFacts', 'وقائع من القائمة')
            ->set('manualOccurred', now()->toDateString())
            ->call('recordManualFromList')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('violations', [
            'employee_id' => $employee->id,
            'reference_item_id' => $item->id,
            'facts' => 'وقائع من القائمة',
            'status' => 'awaiting_statement',
        ]);

        $resp = Responsibility::query()->create([
            'employee_id' => $employee->id,
            'body' => 'جودة',
            'order' => 1,
            'is_active' => true,
        ]);
        $legacy = PeriodicEvaluation::query()->create([
            'employee_id' => $employee->id,
            'period' => '2025-Q4',
            'evaluator_id' => $hr->id,
            'status' => PeriodicEvaluation::STATUS_DRAFT,
        ]);
        EvaluationScore::query()->create([
            'periodic_evaluation_id' => $legacy->id,
            'responsibility_id' => $resp->id,
            'score' => 5,
        ]);
        app(EvaluationService::class)->archive($legacy);
        $this->assertTrue(
            EvaluationCycle::query()->where('name', 'أرشيف — 2025-Q4')->exists()
        );
    }
}
