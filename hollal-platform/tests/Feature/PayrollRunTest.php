<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Notifications\PayrollReturnedToHr;
use App\Notifications\PayrollSubmittedToFinance;
use App\Services\PayrollRunService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 01-B3 — payroll run: generation, derived net, submit/return cycle, and the
 * post-submit edit lock.
 */
class PayrollRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function employeeWithSalary(float $base, float $allowance, float $deduction, float $overtimeValue = 0): User
    {
        $user = User::factory()->create();
        EmployeeProfile::create(['user_id' => $user->id, 'overtime_hour_value' => $overtimeValue]);

        foreach ([
            [SalaryComponent::TYPE_BASE, $base],
            [SalaryComponent::TYPE_ALLOWANCE, $allowance],
            [SalaryComponent::TYPE_DEDUCTION, $deduction],
        ] as [$type, $amount]) {
            SalaryComponent::create([
                'employee_id' => $user->id,
                'type' => $type,
                'label_ar' => $type,
                'amount' => $amount,
                'valid_from' => now()->subMonths(3)->startOfMonth(),
                'valid_to' => null,
                'is_active' => true,
            ]);
        }

        return $user;
    }

    public function test_generate_populates_items_for_active_employees(): void
    {
        $this->employeeWithSalary(5000, 1000, 200);
        $this->employeeWithSalary(6000, 0, 0);

        // Frozen employee must be excluded.
        $frozen = $this->employeeWithSalary(9000, 0, 0);
        $frozen->update(['employment_status' => 'مجمد', 'is_active' => false]);

        $run = app(PayrollRunService::class)->generate('2026-07');

        $this->assertCount(2, $run->items);
    }

    public function test_net_is_reconciled_from_components(): void
    {
        $this->employeeWithSalary(5000, 1000, 200);

        $run = app(PayrollRunService::class)->generate('2026-07');
        $item = $run->items->first();

        // net = base + allowances - deductions + overtime
        $this->assertSame('5800.00', $item->net);
        $this->assertSame('6000.00', $item->gross);
    }

    public function test_overtime_amount_is_hours_times_hour_value(): void
    {
        $this->employeeWithSalary(5000, 0, 0, overtimeValue: 50);
        $run = app(PayrollRunService::class)->generate('2026-07');
        $item = $run->items->first();

        app(PayrollRunService::class)->setOvertime($item, 10);
        $item->refresh();

        $this->assertSame('500.00', $item->overtime_amount);
        $this->assertSame('5500.00', $item->net);
    }

    public function test_submit_and_return_cycle_notifies(): void
    {
        Notification::fake();

        $finance = User::factory()->create();
        $finance->assignRole('Finance');
        $hr = User::factory()->create();

        $run = app(PayrollRunService::class)->generate('2026-07');
        app(PayrollRunService::class)->submitToFinance($run, $hr);

        $this->assertSame(PayrollRun::STATUS_SUBMITTED, $run->fresh()->status);
        Notification::assertSentTo($finance, PayrollSubmittedToFinance::class);

        app(PayrollRunService::class)->returnForCorrection($run, 'يرجى مراجعة بدل الموظف س');

        $this->assertSame(PayrollRun::STATUS_RETURNED, $run->fresh()->status);
        Notification::assertSentTo($hr, PayrollReturnedToHr::class);
    }

    public function test_amounts_cannot_be_edited_after_submission(): void
    {
        $this->employeeWithSalary(5000, 0, 0, overtimeValue: 50);
        $hr = User::factory()->create();

        $run = app(PayrollRunService::class)->generate('2026-07');
        app(PayrollRunService::class)->submitToFinance($run, $hr);

        $this->expectException(\InvalidArgumentException::class);
        app(PayrollRunService::class)->setOvertime($run->items->first(), 5);
    }
}
