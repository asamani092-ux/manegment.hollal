<?php

namespace Tests\Feature;

use App\Livewire\Hr\LeavesIndex;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    public function test_leaves_index_opens(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->get(route('leaves.index'))->assertOk()->assertSee('الإجازات', false);
    }

    public function test_submit_approve_deducts_annual_balance(): void
    {
        $manager = User::factory()->create(['must_change_password' => false, 'email' => 'mgr@test.local']);
        $manager->assignRole('General Manager');

        $employee = User::factory()->create([
            'must_change_password' => false,
            'manager_id' => $manager->id,
            'email' => 'emp@test.local',
        ]);
        $employee->assignRole('Employee');
        $employee->givePermissionTo(['hr.leaves.request']);

        EmployeeProfile::create([
            'user_id' => $employee->id,
            'annual_leave_balance' => 10,
        ]);

        Livewire::actingAs($employee)
            ->test(LeavesIndex::class)
            ->call('openForm')
            ->set('type', LeaveRequest::TYPE_ANNUAL)
            ->set('from_date', now()->toDateString())
            ->set('to_date', now()->addDays(2)->toDateString())
            ->set('reason', 'إجازة تجريبية')
            ->call('submitLeave')
            ->assertHasNoErrors();

        $leave = LeaveRequest::first();
        $this->assertNotNull($leave);
        $this->assertSame(LeaveRequest::STATUS_SUBMITTED, $leave->status);
        $this->assertSame(3, $leave->days_count);

        Notification::assertSentTo($manager, \App\Notifications\LeaveRequested::class);

        Livewire::actingAs($manager)
            ->test(LeavesIndex::class)
            ->call('approve', $leave->id)
            ->assertHasNoErrors();

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status);
        $this->assertSame(7, (int) $employee->fresh()->profile->annual_leave_balance);

        Notification::assertSentTo($employee, \App\Notifications\LeaveDecision::class);
    }
}
