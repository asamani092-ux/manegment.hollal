<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AttendanceDeductionService;
use App\Services\AttendanceService;
use App\Services\PayrollRunService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Path-2ج: post-shift extra work is display-only for HR —
 * never auto-applied to payroll or cycle deductions.
 */
class AttendanceExtraWorkPath2cTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_extra_work_minutes_after_shift_end_display_only(): void
    {
        $shift = WorkShift::create([
            'name' => 'صباحية',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_minutes' => 0,
            'weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $employee = User::factory()->create([
            'attendance_enabled' => true,
            'name' => 'موظف عمل إضافي',
            'employment_status' => User::STATUS_ACTIVE,
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'weekly_hours' => 40,
            'overtime_hour_value' => 50,
            'overtime_unlocked' => false,
            'employment_type' => 'دوام_كامل',
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-24',
            'type' => 'حضور',
            'source' => 'يدوي',
            'check_in_at' => Carbon::parse('2026-08-24 08:00:00'),
            'check_out_at' => Carbon::parse('2026-08-24 17:30:00'),
            'declared_by' => $employee->id,
        ]);

        $svc = app(AttendanceService::class);
        $report = $svc->monthlyReport('2026-08', $employee->id);

        $this->assertSame(90, $report['rows'][0]['extra_work_minutes']);
        $this->assertSame('1 س و 30 د', $report['rows'][0]['extra_work_label']);
        $this->assertSame(0, $report['rows'][0]['early_leave_minutes']);

        // Cycle deductions must not treat post-shift work as payable overtime.
        $from = Carbon::parse('2026-08-01');
        $to = Carbon::parse('2026-08-31');
        $summary = app(AttendanceDeductionService::class)
            ->employeeCycleSummary($employee, $from, $to, 5000.0);
        $this->assertSame(0.0, $summary['overtime_hours']);

        // Locked overtime gate → amount stays 0 even if hours exist elsewhere.
        SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_BASE,
            'label_ar' => 'أساسي',
            'amount' => 5000,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);
        $run = app(PayrollRunService::class)->generate('2026-08');
        $item = $run->items->firstWhere('employee_id', $employee->id);
        $this->assertNotNull($item);
        $this->assertSame('0.00', $item->overtime_amount);
    }

    public function test_no_extra_work_when_checkout_before_or_at_shift_end(): void
    {
        $shift = WorkShift::create([
            'name' => 'صباحية',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_minutes' => 0,
            'weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);

        $onTime = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-24',
            'type' => 'حضور',
            'source' => 'يدوي',
            'check_in_at' => Carbon::parse('2026-08-24 08:00:00'),
            'check_out_at' => Carbon::parse('2026-08-24 16:00:00'),
            'declared_by' => $employee->id,
        ]);
        $onTime->setRelation('employee', $employee->load('profile.workShift'));

        $svc = app(AttendanceService::class);
        $this->assertSame(0, $svc->extraWorkMinutes($onTime));
        $this->assertSame('—', $svc->formatExtraWorkLabel(0));
        $this->assertSame('45 د', $svc->formatExtraWorkLabel(45));
        $this->assertSame('2 س', $svc->formatExtraWorkLabel(120));
    }
}
