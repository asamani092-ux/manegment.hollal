<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Delegation;
use App\Models\EmployeeProfile;
use App\Models\LeavePayImpact;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\LeaveService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveRebuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);
    }

    public function test_entitlement_switches_on_fifth_anniversary(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2020-03-01',
            'annual_leave_balance' => 21,
        ]);
        $user->load('profile');
        $type = app(\App\Services\ReferenceListService::class)->item('leave_types', 'annual');
        $service = app(LeaveBalanceService::class);

        $this->assertSame(21, $service->entitlementDays($user, $type, Carbon::parse('2025-02-28')));
        $this->assertSame(30, $service->entitlementDays($user, $type, Carbon::parse('2025-03-01')));
    }

    public function test_sick_tiers(): void
    {
        $service = app(LeaveBalanceService::class);
        $this->assertSame(100, $service->sickPayPercent(30));
        $this->assertSame(75, $service->sickPayPercent(31));
        $this->assertSame(0, $service->sickPayPercent(91));
    }

    public function test_annual_leave_has_no_pay_impact_and_marks_attendance(): void
    {
        $approver = User::factory()->create();
        $employee = User::factory()->create();
        EmployeeProfile::create(['user_id' => $employee->id, 'annual_leave_balance' => 21]);
        $leave = app(LeaveService::class)->submit($employee, 'سنوية', '2026-04-01', '2026-04-02', 'راحة');
        app(LeaveService::class)->approve($leave, $approver);

        $this->assertSame(0, LeavePayImpact::query()->where('leave_request_id', $leave->id)->count());
        $this->assertSame(2, AttendanceRecord::query()->where('employee_id', $employee->id)->where('type', 'إجازة')->count());
    }

    public function test_exceptional_leave_splits_unpaid_days_by_month(): void
    {
        $type = app(\App\Services\ReferenceListService::class)->item('leave_types', 'exceptional');
        $employee = User::factory()->create();
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => 'استثنائية',
            'reference_item_id' => $type->id,
            'from_date' => '2026-01-30',
            'to_date' => '2026-02-03',
            'days_count' => 5,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        app(LeaveBalanceService::class)->recordPayImpacts($leave->fresh('referenceItem'));

        $jan = LeavePayImpact::query()->where('leave_request_id', $leave->id)->where('month', '2026-01')->first();
        $feb = LeavePayImpact::query()->where('leave_request_id', $leave->id)->where('month', '2026-02')->first();
        $this->assertSame('2.00', number_format((float) $jan->unpaid_days, 2, '.', ''));
        $this->assertSame('3.00', number_format((float) $feb->unpaid_days, 2, '.', ''));
    }

    public function test_extension_moves_delegation_end_and_cut_returns_days(): void
    {
        $employee = User::factory()->create();
        EmployeeProfile::create(['user_id' => $employee->id, 'annual_leave_balance' => 10]);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => 'سنوية',
            'from_date' => '2026-05-01',
            'to_date' => '2026-05-05',
            'days_count' => 5,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        $delegation = Delegation::query()->create([
            'delegator_id' => $employee->id,
            'delegate_id' => User::factory()->create()->id,
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-05',
            'status' => Delegation::STATUS_ACTIVE,
            'source_type' => $leave->getMorphClass(),
            'source_id' => $leave->id,
        ]);

        $child = app(LeaveService::class)->extend($leave, '2026-05-08');
        app(LeaveService::class)->acceptExtension($child);
        $this->assertSame('2026-05-08', $delegation->fresh()->ends_on->toDateString());

        app(LeaveService::class)->cut($leave->fresh(), '2026-05-04');
        $this->assertSame(Delegation::STATUS_ENDED, $delegation->fresh()->status);
        $this->assertSame(15, (int) EmployeeProfile::query()->where('user_id', $employee->id)->value('annual_leave_balance'));
    }

    public function test_leave_validation_does_not_use_type_constants(): void
    {
        $source = file_get_contents(app_path('Services/LeaveService.php'));
        $this->assertStringNotContainsString('TYPE_ANNUAL', $source);
        $this->assertStringNotContainsString('TYPE_SICK', $source);
        $this->assertStringNotContainsString('TYPE_EXCEPTIONAL', $source);
    }
}
