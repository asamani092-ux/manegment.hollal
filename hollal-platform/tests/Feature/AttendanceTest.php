<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\Setting;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 01-B4 — attendance check-in/out, overtime lock default, monthly overtime
 * scheduler.
 */
class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_creates_a_record(): void
    {
        $employee = User::factory()->create(['attendance_enabled' => true]);

        app(AttendanceService::class)->checkIn($employee);

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id,
            'type' => 'حضور',
        ]);
        $this->assertNotNull($employee->fresh()->id);
    }

    public function test_check_in_blocked_when_attendance_disabled(): void
    {
        $employee = User::factory()->create(['attendance_enabled' => false]);

        $this->expectException(\InvalidArgumentException::class);
        app(AttendanceService::class)->checkIn($employee);
    }

    public function test_check_out_updates_same_day_record(): void
    {
        $employee = User::factory()->create(['attendance_enabled' => true]);
        $service = app(AttendanceService::class);

        $service->checkIn($employee);
        $service->checkOut($employee);

        $this->assertSame(1, \App\Models\AttendanceRecord::where('employee_id', $employee->id)->count());
        $record = \App\Models\AttendanceRecord::where('employee_id', $employee->id)->first();
        $this->assertNotNull($record->check_in_at);
        $this->assertNotNull($record->check_out_at);
    }

    public function test_overtime_is_locked_by_default(): void
    {
        $user = User::factory()->create();
        $profile = EmployeeProfile::create(['user_id' => $user->id]);

        $this->assertFalse($profile->fresh()->overtime_unlocked);

        $profile->unlockOvertime();

        $this->assertTrue($profile->fresh()->overtime_unlocked);
    }

    public function test_scheduler_applies_monthly_overtime_days(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
        Setting::set('hr.overtime_monthly_days', 3);

        $enabled = User::factory()->create(['attendance_enabled' => true]);
        $enabledProfile = EmployeeProfile::create(['user_id' => $enabled->id]);

        $disabled = User::factory()->create(['attendance_enabled' => false]);
        $disabledProfile = EmployeeProfile::create(['user_id' => $disabled->id]);

        $this->artisan('attendance:apply-monthly-overtime')->assertSuccessful();

        $this->assertSame(3, $enabledProfile->fresh()->overtime_days_this_month);
        $this->assertSame(0, $disabledProfile->fresh()->overtime_days_this_month);
    }

    public function test_lateness_minutes_against_office_start(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
        Setting::set('attendance.office_start_time', '08:00');

        $employee = User::factory()->create(['attendance_enabled' => true]);
        $record = \App\Models\AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => today(),
            'type' => 'حضور',
            'check_in_at' => today()->setTime(8, 25),
            'declared_by' => $employee->id,
        ]);

        $service = app(AttendanceService::class);
        $this->assertSame(25, $service->latenessMinutes($record));

        $onTime = \App\Models\AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => today()->subDay(),
            'type' => 'حضور',
            'check_in_at' => today()->subDay()->setTime(7, 55),
            'declared_by' => $employee->id,
        ]);
        $this->assertSame(0, $service->latenessMinutes($onTime));
    }

    public function test_monthly_report_includes_lateness(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
        Setting::set('attendance.office_start_time', '09:00');

        $employee = User::factory()->create(['attendance_enabled' => true, 'name' => 'موظف تجريبي']);
        \App\Models\AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => now()->startOfMonth()->addDays(2),
            'type' => 'حضور',
            'check_in_at' => now()->startOfMonth()->addDays(2)->setTime(9, 12),
            'declared_by' => $employee->id,
        ]);

        $report = app(AttendanceService::class)->monthlyReport(now()->format('Y-m'), $employee->id);

        $this->assertSame('09:00', $report['office_start']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame(12, $report['rows'][0]['late_minutes']);
        $this->assertSame('موظف تجريبي', $report['rows'][0]['employee']);
    }
}
