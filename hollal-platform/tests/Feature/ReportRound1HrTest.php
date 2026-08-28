<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Hr\PayScalesIndex;
use App\Livewire\Hr\PayrollRunsIndex;
use App\Livewire\Users\EmployeeProfileShow;
use App\Livewire\Users\UsersIndex;
use App\Models\AttendanceRecord;
use App\Models\Contract;
use App\Models\EmployeeProfile;
use App\Models\PayScale;
use App\Models\PeriodicEvaluation;
use App\Models\Responsibility;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\ContractService;
use App\Services\PayrollRunService;
use App\Services\SalaryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 1 — HR-1..8.
 */
class ReportRound1HrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_directory_defers_edit_to_profile(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);
        $target = User::factory()->create(['name' => 'موظف تجريبي']);

        Livewire::actingAs($hr)
            ->test(UsersIndex::class)
            ->set('viewMode', 'table')
            ->assertSee('الملف الوظيفي', false)
            ->assertDontSeeHtml('wire:click="openEditModal('.$target->id.')"');

        Livewire::actingAs($hr)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->assertSee('تعديل', false);
    }

    public function test_profile_edit_can_set_password_and_deactivate(): void
    {
        $target = User::factory()->create([
            'password' => Hash::make('old-password-99'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
        EmployeeProfile::create(['user_id' => $target->id, 'job_title' => 'موظف']);
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);

        Livewire::actingAs($hr)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('openEdit')
            ->assertSee('كلمة المرور الجديدة', false)
            ->assertSee('الحساب نشط', false)
            ->set('editPassword', 'new-secure-99')
            ->set('editIsActive', false)
            ->call('saveProfile')
            ->assertHasNoErrors();

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertTrue($target->must_change_password);
        $this->assertTrue(Hash::check('new-secure-99', $target->password));
    }

    public function test_profile_edit_saves_job_fields(): void
    {
        $target = User::factory()->create();
        EmployeeProfile::create(['user_id' => $target->id, 'job_title' => 'قديم']);
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);

        Livewire::actingAs($hr)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('openEdit')
            ->set('editName', 'موظف محدّث')
            ->set('editPhone', '0599990001')
            ->set('editEmail', 'updated@test.local')
            ->set('editJobTitle', 'محلل')
            ->set('editEmploymentType', 'دوام_كامل')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertSame('موظف محدّث', $target->fresh()->name);
        $this->assertSame('محلل', $target->fresh()->profile->job_title);
    }

    public function test_assign_grade_stores_scale_on_profile_and_derives_monthly(): void
    {
        $employee = User::factory()->create();
        EmployeeProfile::create(['user_id' => $employee->id, 'employment_type' => 'دوام_كامل']);
        $scale = PayScale::create([
            'name_ar' => 'سلم تجريبي',
            'grades' => [['label' => 'أ', 'base_amount' => 8000]],
            'is_active' => true,
        ]);

        app(SalaryService::class)->assignGrade($scale, $employee, 'أ');
        $employee->refresh();

        $this->assertSame($scale->id, $employee->profile->pay_scale_id);
        $this->assertSame('أ', $employee->profile->grade_label);
        $this->assertSame(8000.0, app(SalaryService::class)->monthlyFromComponents($employee)['monthly']);
    }

    public function test_fixed_deduction_rejected_for_non_regular(): void
    {
        $employee = User::factory()->create();
        EmployeeProfile::create(['user_id' => $employee->id, 'employment_type' => 'متعاون']);

        $this->expectException(\InvalidArgumentException::class);
        app(SalaryService::class)->addComponent($employee, SalaryComponent::TYPE_DEDUCTION, 'تأمين', 100);
    }

    public function test_payroll_generate_pulls_overtime_from_attendance(): void
    {
        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'weekly_hours' => 40,
            'overtime_hour_value' => 50,
            'overtime_unlocked' => true,
            'employment_type' => 'دوام_كامل',
        ]);
        SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_BASE,
            'label_ar' => 'أساسي',
            'amount' => 5000,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);
        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-07-01',
            'check_in_at' => '2026-07-01 08:00:00',
            'check_out_at' => '2026-07-01 18:00:00',
            'type' => 'حضور',
            'declared_by' => $employee->id,
        ]);

        $run = app(PayrollRunService::class)->generate('2026-07');
        $item = $run->items->firstWhere('employee_id', $employee->id);

        $this->assertSame('2.00', $item->overtime_hours);
        $this->assertSame('100.00', $item->overtime_amount);
    }

    public function test_variable_deduction_requires_reason(): void
    {
        $employee = User::factory()->create();
        EmployeeProfile::create(['user_id' => $employee->id]);
        SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_BASE,
            'label_ar' => 'أساسي',
            'amount' => 5000,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);
        $run = app(PayrollRunService::class)->generate('2026-07');

        $this->expectException(\InvalidArgumentException::class);
        app(PayrollRunService::class)->addVariable($run->items->first(), 'غياب', '   ', 100, 'deduction');
    }

    public function test_evaluation_scores_and_employee_comment_on_profile(): void
    {
        $hr = User::factory()->create(['must_change_password' => false, 'is_active' => true, 'employment_status' => User::STATUS_ACTIVE]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update', 'dashboard.view']);
        $employee = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'manager_id' => $hr->id,
        ]);
        $employee->givePermissionTo(['dashboard.view', 'hr.employees.view']);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'دوام_كامل',
        ]);

        $service = app(\App\Services\QuarterlyEvaluationService::class);
        $template = $service->createTemplate('قالب تقرير1', [
            ['section' => 'مدير', 'question_text' => 'إدارة الملفات', 'weight' => 60, 'sort_order' => 1],
            ['section' => 'موارد', 'question_text' => 'سياسات', 'weight' => 40, 'sort_order' => 2],
        ]);
        $cycle = $service->createCycle(2026, 3, $template, '2026-07-01', '2026-09-30');
        $service->openCycle($cycle);
        $service->bulkOpen($cycle->fresh());

        $evaluation = \App\Models\EmployeeEvaluation::query()
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $items = $cycle->fresh()->items()->orderBy('sort_order')->get();

        Livewire::actingAs($hr)
            ->test(EvaluationsIndex::class)
            ->call('openScoring', $evaluation->id)
            ->set('scoreInputs.'.$items[1]->id.'.score', '4')
            ->set('scoreInputs.'.$items[1]->id.'.note', 'جيد')
            ->call('saveHrScores')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_evaluation_scores', [
            'employee_evaluation_id' => $evaluation->id,
            'evaluation_cycle_item_id' => $items[1]->id,
            'score' => 4,
        ]);

        $service->recordScore($evaluation->fresh(), $items[0], 5);
        $service->approve($evaluation->fresh(), $hr);

        Livewire::actingAs($employee)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->call('setTab', 'evaluations')
            ->assertSee('الربع 3 / 2026', false)
            ->assertDontSee('تعليقك', false);
    }

    public function test_pay_scale_lists_employee_count(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.salaries.manage');
        $scale = PayScale::create([
            'name_ar' => 'سلم العد',
            'grades' => [['label' => 'أ', 'base_amount' => 1000]],
            'is_active' => true,
        ]);
        $employee = User::factory()->create();
        EmployeeProfile::create(['user_id' => $employee->id, 'pay_scale_id' => $scale->id, 'grade_label' => 'أ']);

        Livewire::actingAs($hr)
            ->test(PayScalesIndex::class)
            ->assertSee('عدد الموظفين', false)
            ->assertSee('1', false);
    }

    public function test_contract_status_labels_and_renew_extends_same_row(): void
    {
        $this->assertSame('ساري', Contract::STATUS_LABELS['active']);
        $this->assertSame('منتهي', Contract::STATUS_LABELS['expired']);
        $this->assertSame('مكتمل', Contract::STATUS_LABELS['terminated']);
        $this->assertSame('معلق', Contract::STATUS_LABELS['pending']);

        $hr = User::factory()->create();
        $contract = Contract::factory()->create([
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        app(ContractService::class)->renew($contract, '2027-12-31', $hr);
        $contract->refresh();

        $this->assertSame('2027-12-31', $contract->end_date->toDateString());
        $this->assertCount(1, $contract->renewal_history);
        $this->assertSame(1, Contract::count());
    }

    public function test_payroll_detail_modal_opens(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.salaries.view', 'hr.salaries.manage']);
        User::factory()->create(['is_active' => true, 'employment_status' => 'نشط']);
        $run = app(PayrollRunService::class)->generate('2026-07');

        Livewire::actingAs($hr)
            ->test(PayrollRunsIndex::class)
            ->call('openRun', $run->id)
            ->assertSet('viewingRunId', $run->id)
            ->assertSee('تفاصيل المسيّر', false);
    }

    public function test_contract_inline_preview_header(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('partnerships.contracts.view');
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('contracts/c.pdf', 'pdf');
        $contract = Contract::factory()->create(['contract_file' => 'contracts/c.pdf']);

        $this->actingAs($hr)
            ->get(route('contracts.files.download', $contract).'?inline=1')
            ->assertOk()
            ->assertHeader('Content-Disposition', \App\Support\DownloadHeaders::contentDisposition('c.pdf', 'inline'));
    }
}
