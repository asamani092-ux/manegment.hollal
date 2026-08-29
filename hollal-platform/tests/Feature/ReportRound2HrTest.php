<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Hr\PayScalesIndex;
use App\Models\Contract;
use App\Models\EmployeeProfile;
use App\Models\PayScale;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Services\PayrollRunService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 2 — Batch 1 HR (contracts renew visibility, pay-scale employees,
 * payroll sync, evaluations lifecycle copy).
 */
class ReportRound2HrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_renew_button_visibility_for_expired_or_near_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13'));

        $expired = Contract::factory()->create([
            'end_date' => '2026-01-01',
            'status' => 'expired',
        ]);
        $near = Contract::factory()->create([
            'end_date' => '2026-08-20',
            'status' => 'active',
        ]);
        $far = Contract::factory()->create([
            'end_date' => '2027-12-31',
            'status' => 'active',
        ]);
        $terminated = Contract::factory()->create([
            'end_date' => '2026-08-15',
            'status' => 'terminated',
        ]);

        $this->assertTrue($expired->isRenewable());
        $this->assertTrue($near->isRenewable());
        $this->assertFalse($far->isRenewable());
        $this->assertFalse($terminated->isRenewable());

        Carbon::setTestNow();
    }

    public function test_pay_scale_lists_assigned_employee_name(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.salaries.manage');

        $scale = PayScale::create([
            'name_ar' => 'سلم الربط',
            'grades' => [['label' => 'أ', 'base_amount' => 5000]],
            'is_active' => true,
        ]);
        $employee = User::factory()->create(['name' => 'موظف السلم التجريبي']);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'pay_scale_id' => $scale->id,
            'grade_label' => 'أ',
        ]);

        Livewire::actingAs($hr)
            ->test(PayScalesIndex::class)
            ->assertSee('ربط الموظفين بالسلم يتم من تبويب الراتب', false)
            ->assertSee('موظف السلم التجريبي', false)
            ->assertSee('عدد الموظفين', false);
    }

    public function test_payroll_sync_updates_draft_run_item(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.salaries.manage');

        $employee = User::factory()->create(['is_active' => true, 'employment_status' => 'نشط']);
        EmployeeProfile::create(['user_id' => $employee->id]);

        $run = PayrollRun::create(['month' => '2026-07', 'status' => PayrollRun::STATUS_DRAFT]);
        $item = new PayrollRunItem([
            'employee_id' => $employee->id,
            'base' => 1000,
            'allowances' => 0,
            'deductions' => 0,
            'overtime_hours' => 0,
            'overtime_amount' => 0,
            'variables' => [],
        ]);
        $item->payroll_run_id = $run->id;
        $item->recalculate();
        $item->save();

        Payroll::create([
            'employee_id' => $employee->id,
            'month' => '2026-07-01',
            'base' => 5500,
            'additions' => 200,
            'deductions' => 50,
            'net' => 5650,
            'transfer_status' => 'pending',
        ]);

        $result = app(PayrollRunService::class)->syncFromMonthlyPayroll($hr, '2026-07');

        $this->assertFalse($result['skipped']);
        $this->assertSame(1, $result['updated']);
        $item->refresh();
        $this->assertSame('5500.00', $item->base);
        $this->assertSame('200.00', $item->allowances);
        $this->assertSame('50.00', $item->deductions);
    }

    public function test_evaluations_page_shows_lifecycle_text(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);

        Livewire::actingAs($hr)
            ->test(EvaluationsIndex::class)
            ->assertSee('مسار واحد متسلسل', false)
            ->assertSee('مسودة', false)
            ->assertSee('اعتماد جماعي', false)
            ->assertSee('أرشفة', false);
    }
}
