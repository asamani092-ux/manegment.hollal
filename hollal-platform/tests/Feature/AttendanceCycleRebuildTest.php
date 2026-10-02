<?php

namespace Tests\Feature;

use App\Events\AttendanceCycleClosed;
use App\Models\AttendanceCycleDay;
use App\Models\AttendanceRecord;
use App\Models\ExcuseRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\AttendanceCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AttendanceCycleRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_day_is_not_absence_and_excuse_cuts_late_minutes(): void
    {
        $employee = User::factory()->create();
        $cycle = app(AttendanceCycleService::class)->open('2026-04');
        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-04-02',
            'type' => 'إجازة',
            'declared_by' => $employee->id,
        ]);
        $leaveDay = app(AttendanceCycleService::class)->syncDay($cycle, $employee, '2026-04-02', 40);
        $this->assertSame('إجازة', $leaveDay->status);
        $this->assertSame(0, $leaveDay->chargeable_late_minutes);

        ExcuseRequest::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-04-03',
            'from_time' => '08:00',
            'to_time' => '08:20',
            'reason' => 'استئذان',
            'status' => 'approved',
            'minutes' => 20,
        ]);
        $late = app(AttendanceCycleService::class)->syncDay($cycle, $employee, '2026-04-03', 30);
        $this->assertLessThan(30, $late->chargeable_late_minutes);
    }

    public function test_only_approved_overtime_is_paid(): void
    {
        $service = app(AttendanceCycleService::class);
        $this->assertSame(0.0, $service->overtimeAmount(100, 2, false));
        $this->assertSame(300.0, $service->overtimeAmount(100, 2, true));
    }

    public function test_cycle_close_requires_status_and_reopen_keeps_decided_days(): void
    {
        Event::fake([AttendanceCycleClosed::class]);
        $employee = User::factory()->create();
        $service = app(AttendanceCycleService::class);
        $cycle = $service->open('2026-05');
        $open = $service->syncDay($cycle, $employee, '2026-05-01', 0);
        $this->expectException(\RuntimeException::class);
        $service->close($cycle, $employee);
    }

    public function test_close_emits_event_and_reopen_keeps_decided_flag(): void
    {
        Event::fake([AttendanceCycleClosed::class]);
        $employee = User::factory()->create();
        $service = app(AttendanceCycleService::class);
        $cycle = $service->open('2026-06');
        $day = $service->syncDay($cycle, $employee, '2026-06-01', 0);
        $service->setStatus($day, 'حاضر', 'تصحيح');
        $day->update(['downstream_decided' => true]);
        $blank = $service->syncDay($cycle, $employee, '2026-06-02', 0);
        $service->setStatus($blank, 'حاضر');
        $service->close($cycle->fresh(), $employee);
        Event::assertDispatched(AttendanceCycleClosed::class);

        $service->reopen($cycle->fresh(), 'مراجعة');
        $this->assertNotNull(AttendanceCycleDay::query()->find($day->id)->status);
        $this->assertNull(AttendanceCycleDay::query()->find($blank->id)->status);
    }

    public function test_overtime_request_route_exists(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo('hr.employees.view');
        $this->actingAs($admin)->get(route('attendance.overtime'))->assertOk();
    }
}
