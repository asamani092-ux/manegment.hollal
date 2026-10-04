<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\PayrollRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimePayrollFormulaTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlocked_without_approved_request_pays_zero_and_approved_request_uses_formula(): void
    {
        $user = User::factory()->create([
            'attendance_enabled' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'overtime_hour_value' => 100,
            'overtime_unlocked' => true,
            'job_title' => 'موظف',
            'employment_type' => 'دوام_كامل',
        ]);
        \App\Models\SalaryComponent::query()->create([
            'employee_id' => $user->id,
            'type' => \App\Models\SalaryComponent::TYPE_BASE,
            'label_ar' => 'أساسي',
            'amount' => 3000,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $plain = app(PayrollRunService::class)->generate('2026-08');
        $this->assertSame('0.00', $plain->items->first()->overtime_amount);

        OvertimeRequest::query()->create([
            'employee_id' => $user->id,
            'date' => '2026-09-02',
            'hours' => 2,
            'reason' => 'إضافي',
            'status' => 'approved',
        ]);
        $paid = app(PayrollRunService::class)->generate('2026-09');
        $this->assertSame('300.00', $paid->items->first()->overtime_amount);
    }
}
