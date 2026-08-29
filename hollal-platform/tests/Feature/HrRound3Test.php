<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Hr\HeaderAttendancePunch;
use App\Livewire\Hr\LeavesIndex;
use App\Livewire\Hr\ResponsibilitiesIndex;
use App\Livewire\Users\EmployeeProfileShow;
use App\Models\EvaluationScore;
use App\Models\OrgUnit;
use App\Models\EmployeeProfile;
use App\Models\PeriodicEvaluation;
use App\Models\ProfileAccessLog;
use App\Models\Responsibility;
use App\Models\User;
use App\Services\EvaluationService;
use App\Services\OrgStructureService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** HR round-3: UAT tab-1 fixes. */
class HrRound3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function hrAdmin(): User
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'attendance_enabled' => true,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_org_create_unit_uses_department_level_label(): void
    {
        $service = app(OrgStructureService::class);

        $admin = $service->createUnit('إدارة التقنية', OrgUnit::LEVEL_ADMINISTRATION);
        $unit = $service->createUnit('قسم التقنيات', OrgUnit::LEVEL_UNIT, $admin);
        $job = $service->createUnit('تقني', OrgUnit::LEVEL_JOB, $unit);

        $this->assertSame('قسم', $unit->level);
        $this->assertSame(OrgUnit::LEVEL_UNIT, $unit->fresh()->level);
        $this->assertSame($admin->id, $unit->parent_id);
        $this->assertSame($unit->id, $job->parent_id);
    }

    public function test_evaluations_index_shows_employee_summary_without_preview_button(): void
    {
        $admin = $this->hrAdmin();
        $employee = User::factory()->create([
            'is_active' => true,
            'name' => 'موظف تقييم',
            'employment_status' => User::STATUS_ACTIVE,
            'manager_id' => $admin->id,
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'دوام_كامل',
        ]);

        $service = app(\App\Services\QuarterlyEvaluationService::class);
        $template = $service->createTemplate('قالب ر3', [
            ['section' => 'مدير', 'question_text' => 'أ', 'weight' => 70, 'sort_order' => 1],
            ['section' => 'موارد', 'question_text' => 'ب', 'weight' => 30, 'sort_order' => 2],
        ]);
        $cycle = $service->createCycle(2026, 2, $template, '2026-04-01', '2026-06-30');
        $service->openCycle($cycle);
        $service->bulkOpen($cycle->fresh());

        Livewire::actingAs($admin)
            ->test(EvaluationsIndex::class)
            ->assertSee('موظف تقييم', false)
            ->assertSee('الربع 2 / 2026', false)
            ->assertDontSee('إظهار للموظف', false)
            ->assertSee('اعتماد جماعي', false);
    }

    public function test_archive_requires_complete_scores(): void
    {
        $admin = $this->hrAdmin();
        $employee = User::factory()->create(['is_active' => true]);
        $resp = Responsibility::create([
            'employee_id' => $employee->id,
            'body' => 'جودة العمل',
            'order' => 1,
            'is_active' => true,
        ]);
        $evaluation = PeriodicEvaluation::create([
            'employee_id' => $employee->id,
            'period' => '2026-Q3',
            'evaluator_id' => $admin->id,
            'status' => PeriodicEvaluation::STATUS_DRAFT,
        ]);

        $this->expectException(\RuntimeException::class);
        app(EvaluationService::class)->archive($evaluation);

        EvaluationScore::create([
            'periodic_evaluation_id' => $evaluation->id,
            'responsibility_id' => $resp->id,
            'score' => 4,
        ]);

        app(EvaluationService::class)->archive($evaluation->fresh());
        $this->assertSame(PeriodicEvaluation::STATUS_ARCHIVED, $evaluation->fresh()->status);
    }

    public function test_responsibilities_groups_by_employee(): void
    {
        $admin = $this->hrAdmin();
        $employee = User::factory()->create(['is_active' => true, 'name' => 'مسؤوليات X']);
        Responsibility::create(['employee_id' => $employee->id, 'body' => 'بند 1', 'order' => 1, 'is_active' => true]);
        Responsibility::create(['employee_id' => $employee->id, 'body' => 'بند 2', 'order' => 2, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ResponsibilitiesIndex::class)
            ->assertSee('مسؤوليات X')
            ->assertSee('2')
            ->call('deactivateAllForEmployee', $employee->id);

        $this->assertSame(0, Responsibility::query()->where('employee_id', $employee->id)->where('is_active', true)->count());
    }

    public function test_leaves_view_mode_toggle_and_balance_in_form(): void
    {
        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $user->givePermissionTo(['hr.leaves.request', 'hr.leaves.view-all']);
        EmployeeProfile::create(['user_id' => $user->id, 'annual_leave_balance' => 18]);

        Livewire::actingAs($user)
            ->test(LeavesIndex::class)
            ->assertSet('viewMode', 'table')
            ->call('setViewMode', 'cards')
            ->assertSet('viewMode', 'cards')
            ->call('openForm')
            ->assertSet('showForm', true)
            ->assertSee('18');
    }

    public function test_header_attendance_opens_punch_panel(): void
    {
        $admin = $this->hrAdmin();

        Livewire::actingAs($admin)
            ->test(HeaderAttendancePunch::class)
            ->assertSee('تسجيل الحضور')
            ->call('openPanel')
            ->assertSet('showPanel', true)
            ->assertSee('إقرار نوع اليوم');
    }

    public function test_profile_log_tab_lists_salary_access(): void
    {
        $admin = $this->hrAdmin();
        $employee = User::factory()->create(['is_active' => true]);
        ProfileAccessLog::create([
            'user_id' => $admin->id,
            'target_user_id' => $employee->id,
            'tab_accessed' => 'salary',
            'accessed_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->call('setTab', 'log')
            ->assertSee('وصول تبويب')
            ->assertSee('الراتب');
    }

    public function test_guest_layout_uses_direct_stylesheets(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('css/base.css', false)
            ->assertSee('css/components.css', false);
    }
}
