<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollRunService;
use App\Services\SalaryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Aug base 200 → Sep after change to 300 + allowance 50 (+ OT if unlocked).
 */
class PayrollSalarySnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_run_uses_components_effective_for_that_month(): void
    {
        $this->seed(PermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-08-15'));

        $employee = User::factory()->create([
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'attendance_enabled' => false,
        ]);
        $employee->profile()->create(['user_id' => $employee->id, 'employment_type' => SalaryService::REGULAR_TYPE]);

        $salary = app(SalaryService::class);
        $salary->setBaseAmount($employee, 200);

        $aug = app(PayrollRunService::class)->generate('2026-08');
        $augItem = $aug->items()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($augItem);
        $this->assertEquals(200.0, (float) $augItem->base);
        $this->assertEquals(0.0, (float) $augItem->allowances);

        Carbon::setTestNow(Carbon::parse('2026-09-05'));
        $salary->setBaseAmount($employee->fresh(), 300);
        $salary->addComponent($employee->fresh(), \App\Models\SalaryComponent::TYPE_ALLOWANCE, 'بدل سكن', 50.0);

        $sep = app(PayrollRunService::class)->generate('2026-09');
        $sepItem = $sep->items()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($sepItem);
        $this->assertEquals(300.0, (float) $sepItem->base);
        $this->assertEquals(50.0, (float) $sepItem->allowances);

        // Prior month snapshot unchanged
        $augItem->refresh();
        $this->assertEquals(200.0, (float) $augItem->base);

        Carbon::setTestNow();
    }
}
