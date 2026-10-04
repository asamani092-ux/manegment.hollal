<?php

namespace Tests\Feature;

use App\Models\ApprovalRule;
use App\Models\Custody;
use App\Models\ExcuseRequest;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\AttendanceCycleService;
use App\Services\CustodyService;
use App\Services\LeaveService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalChainCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_user_step_approves_custody_leave_overtime_and_excuse(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);

        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        \App\Models\EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'annual_leave_balance' => 21,
            'job_title' => 'موظف',
        ]);

        foreach (['custody', 'leave', 'overtime', 'excuse'] as $type) {
            ApprovalRule::query()->create([
                'transaction_type' => $type,
                'min_amount' => 0,
                'max_amount' => null,
                'is_active' => true,
                'approval_steps' => [['type' => 'user', 'user_id' => $employee->id]],
            ]);
        }

        $custody = app(CustodyService::class)->request($employee, 100, 'عهدة', null, null, null, $employee);
        $this->assertSame('معتمدة', app(CustodyService::class)->approve($custody, $employee)->status);

        $leave = app(LeaveService::class)->submit($employee, 'سنوية', now()->addDays(10)->toDateString(), now()->addDays(11)->toDateString(), 'راحة');
        $this->assertSame('معتمد', app(LeaveService::class)->approve($leave, $employee)->status);

        $overtime = OvertimeRequest::query()->create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'hours' => 2,
            'reason' => 'دوام',
            'status' => 'pending',
        ]);
        $this->assertSame('approved', app(AttendanceCycleService::class)->approveOvertime($overtime, $employee)->status);

        $excuse = ExcuseRequest::query()->create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'from_time' => '09:00',
            'to_time' => '10:00',
            'reason' => 'موعد',
            'status' => 'pending',
            'minutes' => 60,
        ]);
        $this->assertSame('approved', app(AttendanceCycleService::class)->approveExcuse($excuse, $employee)->status);
    }
}
