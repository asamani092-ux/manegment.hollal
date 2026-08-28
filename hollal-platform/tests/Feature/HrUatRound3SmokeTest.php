<?php

namespace Tests\Feature;

use App\Livewire\Hr\AttendanceIndex;
use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Hr\HeaderAttendancePunch;
use App\Livewire\NotificationBell;
use App\Models\AttendanceRecord;
use App\Models\PeriodicEvaluation;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UAT round-3 HR decision smoke (nav + HR tools).
 */
class HrUatRound3SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);

        $this->admin = User::factory()->create([
            'phone' => '0500000000',
            'must_change_password' => false,
            'attendance_enabled' => true,
        ]);
        $this->admin->givePermissionTo([
            'dashboard.view',
            'hr.employees.view',
            'hr.employees.update',
            'hr.salaries.view',
            'partnerships.contracts.view',
            'partnerships.contracts.manage',
        ]);
    }

    public function test_notification_resolves_task_open_query(): void
    {
        $task = Task::factory()->create();
        $this->admin->notify(new TaskAssigned($task));
        $notification = $this->admin->notifications()->latest()->first();

        $this->assertSame('/tasks?open='.$task->id, NotificationBell::resolveTarget($notification));
    }

    public function test_payroll_index_redirects_to_runs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payroll.index'))
            ->assertRedirect(route('payroll-runs.index'));
    }

    public function test_contracts_page_shows_monthly_salary_label(): void
    {
        $this->actingAs($this->admin)
            ->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('الراتب الشهري', false);
    }

    public function test_dashboard_action_panel_collapsed_by_default(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\DashboardIndex::class)
            ->assertSet('actionPanelOpen', false)
            ->call('toggleActionPanel')
            ->assertSet('actionPanelOpen', true);
    }

    public function test_evaluations_employee_menu_without_preview(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        PeriodicEvaluation::create([
            'employee_id' => $employee->id,
            'period' => '2026-Q3',
            'evaluator_id' => $this->admin->id,
            'status' => PeriodicEvaluation::STATUS_DRAFT,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EvaluationsIndex::class)
            ->assertSee('عرض جميع التقييمات')
            ->assertDontSee('إظهار للموظف');
    }

    public function test_attendance_management_tools_render(): void
    {
        Setting::set('attendance.office_start_time', '08:00');

        AttendanceRecord::create([
            'employee_id' => $this->admin->id,
            'date' => today(),
            'type' => 'حضور',
            'check_in_at' => today()->setTime(8, 40),
            'declared_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AttendanceIndex::class)
            ->assertSee('إدارة الحضور')
            ->assertSee('سجل الحضور والانصراف')
            ->assertSee('طباعة السجل الشهري')
            ->set('printMonth', now()->format('Y-m'))
            ->call('openMonthlyPrint')
            ->assertSet('showPrint', true)
            ->assertSee('سجل الحضور الشهري');
    }

    public function test_header_single_punch_button(): void
    {
        Livewire::actingAs($this->admin)
            ->test(HeaderAttendancePunch::class)
            ->assertSee('تسجيل الحضور')
            ->call('openPanel')
            ->assertSet('showPanel', true);
    }
}
