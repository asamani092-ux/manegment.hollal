<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeHub;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PeriodicEvaluation;
use App\Models\Responsibility;
use App\Models\Task;
use App\Models\User;
use App\Services\EvaluationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** EMP-1/2 — employee hub and live cycles as Employee role. */
class EmployeeHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function employeeUser(): User
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'attendance_enabled' => true,
        ]);
        EmployeeProfile::create(['user_id' => $user->id, 'annual_leave_balance' => 20]);
        $user->assignRole('Employee');

        return $user->fresh();
    }

    public function test_employee_can_open_hub_and_see_sections(): void
    {
        $user = $this->employeeUser();
        Task::factory()->create([
            'assigned_to' => $user->id,
            'title' => 'مهمة موظف',
            'status' => 'جديدة',
        ]);
        Responsibility::create(['employee_id' => $user->id, 'body' => 'متابعة الملفات', 'is_active' => true, 'order' => 1]);

        $this->actingAs($user)->get(route('employee-hub.index'))->assertOk()->assertSee('مساحتي')->assertSee('مهمة موظف');
    }

    public function test_employee_can_submit_leave_from_hub(): void
    {
        $user = $this->employeeUser();

        Livewire::actingAs($user)
            ->test(EmployeeHub::class)
            ->set('leaveType', LeaveRequest::TYPE_ANNUAL)
            ->set('leaveFrom', now()->addDays(3)->toDateString())
            ->set('leaveTo', now()->addDays(4)->toDateString())
            ->set('leaveReason', 'راحة')
            ->call('submitLeave')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $user->id,
            'status' => LeaveRequest::STATUS_SUBMITTED,
        ]);
    }

    public function test_employee_can_comment_published_evaluation_from_hub(): void
    {
        $user = $this->employeeUser();
        $eval = PeriodicEvaluation::create([
            'employee_id' => $user->id,
            'period' => '2026-Q3',
            'status' => PeriodicEvaluation::STATUS_DRAFT,
        ]);
        app(EvaluationService::class)->publish($eval);

        Livewire::actingAs($user)
            ->test(EmployeeHub::class)
            ->set('evalComment', 'شكراً على التقييم')
            ->call('saveEvalComment', $eval->id)
            ->assertHasNoErrors();

        $this->assertSame('شكراً على التقييم', $eval->fresh()->employee_comment);
    }

    public function test_hub_shows_own_executed_payslip(): void
    {
        $user = $this->employeeUser();
        $run = PayrollRun::create([
            'month' => '2026-08',
            'status' => PayrollRun::STATUS_EXECUTED,
        ]);
        PayrollRunItem::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $user->id,
            'base' => 5000,
            'allowances' => 0,
            'deductions' => 0,
            'overtime_hours' => 0,
            'overtime_amount' => 0,
            'variables' => [],
            'gross' => 5000,
            'net' => 5000,
        ]);

        $this->actingAs($user)
            ->get(route('employee-hub.index'))
            ->assertOk()
            ->assertSee('5,000.00');
    }
}
