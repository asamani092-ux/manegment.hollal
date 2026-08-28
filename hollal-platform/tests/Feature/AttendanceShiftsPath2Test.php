<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AttendanceService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Path-2 attendance: shifts, grace lateness, remote/field pending + manager approve,
 * attendance_enabled gate.
 */
class AttendanceShiftsPath2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_shift_assignment_and_check_in_on_shift_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:05:00')); // Monday

        $shift = WorkShift::create([
            'name' => 'صباحية',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_minutes' => 10,
            'weekdays' => [1, 2, 3, 4, 5], // Mon–Fri
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);
        $employee->load('profile.workShift');

        $record = app(AttendanceService::class)->checkIn($employee);

        $this->assertSame(AttendanceService::TYPE_PRESENT, $record->type);
        $this->assertSame(AttendanceService::SOURCE_MANUAL, $record->source);
        $this->assertSame(0, (int) $record->late_minutes);

        Carbon::setTestNow();
    }

    public function test_lateness_respects_shift_grace_flexibility(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:00:00'));

        $shift = WorkShift::create([
            'name' => 'صباحية',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_minutes' => 15,
            'weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);

        $withinGrace = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-24',
            'type' => 'حضور',
            'check_in_at' => Carbon::parse('2026-08-24 08:12:00'),
            'declared_by' => $employee->id,
        ]);
        $withinGrace->setRelation('employee', $employee->load('profile.workShift'));

        $beyondGrace = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-25',
            'type' => 'حضور',
            'check_in_at' => Carbon::parse('2026-08-25 08:25:00'),
            'declared_by' => $employee->id,
        ]);
        $beyondGrace->setRelation('employee', $employee);

        $svc = app(AttendanceService::class);
        $this->assertSame(0, $svc->latenessMinutes($withinGrace)); // 12 ≤ 15
        $this->assertSame(10, $svc->latenessMinutes($beyondGrace)); // 25 − 15

        Carbon::setTestNow();
    }

    public function test_field_day_stays_pending_until_manager_approves(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 09:00:00'));

        $manager = User::factory()->create(['must_change_password' => false]);
        $employee = User::factory()->create([
            'attendance_enabled' => true,
            'manager_id' => $manager->id,
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'is_field_worker' => true,
        ]);
        $employee->load('profile');

        $svc = app(AttendanceService::class);
        $field = $svc->startFieldWork($employee, 'موقع تجريبي');

        $this->assertSame(AttendanceService::TYPE_FIELD, $field->type);
        $this->assertSame(AttendanceService::APPROVAL_PENDING, $field->approval_status);

        $stranger = User::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        $svc->approveDayType($field, $stranger);
    }

    public function test_manager_can_approve_pending_field(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 09:00:00'));

        $manager = User::factory()->create(['must_change_password' => false]);
        $employee = User::factory()->create([
            'attendance_enabled' => true,
            'manager_id' => $manager->id,
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'is_field_worker' => true,
        ]);
        $employee->load('profile');

        $svc = app(AttendanceService::class);
        $field = $svc->startFieldWork($employee, 'موقع تجريبي');
        $approved = $svc->approveDayType($field, $manager);

        $this->assertSame(AttendanceService::APPROVAL_APPROVED, $approved->approval_status);

        Carbon::setTestNow();
    }

    public function test_hr_can_proxy_approve_remote(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 09:00:00'));

        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.employees.update');

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create(['user_id' => $employee->id]);

        $svc = app(AttendanceService::class);
        $remote = $svc->declareDayType($employee, AttendanceService::TYPE_REMOTE, 'عمل من المنزل');

        $this->assertSame(AttendanceService::APPROVAL_PENDING, $remote->approval_status);

        $approved = $svc->approveDayType($remote, $hr);
        $this->assertSame(AttendanceService::APPROVAL_APPROVED, $approved->approval_status);

        Carbon::setTestNow();
    }

    public function test_check_in_blocked_when_attendance_disabled(): void
    {
        $employee = User::factory()->create(['attendance_enabled' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('برنامج الحضور غير مُفعّل');
        app(AttendanceService::class)->checkIn($employee);
    }

    public function test_path2_punch_feeds_monthly_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:30:00'));

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
            'name' => 'موظف مسار ٢',
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);
        $employee->load('profile.workShift');

        $svc = app(AttendanceService::class);
        $svc->checkIn($employee);

        $report = $svc->monthlyReport('2026-08', $employee->id);
        $this->assertCount(1, $report['rows']);
        $this->assertSame('موظف مسار ٢', $report['rows'][0]['employee']);
        $this->assertSame(30, $report['rows'][0]['late_minutes']);

        Carbon::setTestNow();
    }

    public function test_monthly_report_lateness_uses_employee_shift_not_office_start(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
        \App\Support\Setting::set('attendance.office_start_time', '08:00');

        // Shift starts later than org office — report must use 10:00 + grace 10.
        $shift = WorkShift::create([
            'name' => 'متأخرة',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'grace_minutes' => 10,
            'weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $employee = User::factory()->create([
            'attendance_enabled' => true,
            'name' => 'موظف وردية متأخرة',
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-24',
            'type' => 'حضور',
            'source' => 'يدوي',
            'check_in_at' => Carbon::parse('2026-08-24 10:25:00'),
            'declared_by' => $employee->id,
        ]);

        $report = app(AttendanceService::class)->monthlyReport('2026-08', $employee->id);

        $this->assertSame('08:00', $report['office_start']); // org default still reported
        $this->assertSame('10:00', $report['rows'][0]['shift_start']);
        // vs shift 10:00: 25 − grace 10 = 15. If wrongly using office 08:00 → 145.
        $this->assertSame(15, $report['rows'][0]['late_minutes']);
        $this->assertNotSame(145, $report['rows'][0]['late_minutes']);
    }

    public function test_early_leave_minutes_from_shift_end_display_only(): void
    {
        $shift = WorkShift::create([
            'name' => 'صباحية',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_minutes' => 0,
            'weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true, 'name' => 'منصرف مبكراً']);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-24',
            'type' => 'حضور',
            'source' => 'يدوي',
            'check_in_at' => Carbon::parse('2026-08-24 08:00:00'),
            'check_out_at' => Carbon::parse('2026-08-24 15:30:00'),
            'declared_by' => $employee->id,
        ]);

        $svc = app(AttendanceService::class);
        $report = $svc->monthlyReport('2026-08', $employee->id);

        $this->assertSame(30, $report['rows'][0]['early_leave_minutes']);
        $this->assertSame(0, $report['rows'][0]['late_minutes']);
    }

    public function test_platform_punch_cannot_overwrite_imported_fingerprint(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 10:00:00'));

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create(['user_id' => $employee->id]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => today(),
            'type' => 'حضور',
            'source' => 'بصمة',
            'check_in_at' => Carbon::parse('2026-08-24 08:00:00'),
            'declared_by' => $employee->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('بصمة مستورد');
        app(AttendanceService::class)->checkIn($employee);
    }

    public function test_check_in_rejected_outside_shift_weekdays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 08:05:00')); // Saturday

        $shift = WorkShift::create([
            'name' => 'أيام العمل',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_minutes' => 0,
            'weekdays' => [0, 1, 2, 3, 4], // Sun–Thu
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);
        $employee->load('profile.workShift');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ليس ضمن أيام وردية');
        app(AttendanceService::class)->checkIn($employee);
    }
}
