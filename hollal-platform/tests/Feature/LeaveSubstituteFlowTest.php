<?php

namespace Tests\Feature;

use App\Livewire\Hr\LeavesIndex;
use App\Models\Delegation;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\DelegationService;
use App\Services\LeaveService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveSubstituteFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_substitute_chain_creates_delegation_then_extend_and_cut(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);

        $manager = User::factory()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $substitute = User::factory()->create();
        $hr = User::factory()->create();
        $hr->givePermissionTo('hr.leaves.view-all');
        EmployeeProfile::query()->create(['user_id' => $employee->id, 'annual_leave_balance' => 21, 'job_title' => 'موظف']);

        $from = now()->addDay()->toDateString();
        $to = now()->addDays(2)->toDateString();
        $leave = app(LeaveService::class)->submit($employee, 'سنوية', $from, $to, 'سفر', $substitute->id);
        $this->assertSame('pending', $leave->substitute_status);

        app(LeaveService::class)->acceptSubstitute($leave, $substitute);
        $approved = app(LeaveService::class)->approve($leave->fresh(), $hr);
        $this->assertSame('معتمد', $approved->status);

        $delegation = Delegation::query()->where('source_id', $leave->id)->first();
        $this->assertNotNull($delegation);
        $this->assertSame(Delegation::STATUS_SCHEDULED, $delegation->status);

        Carbon::setTestNow(Carbon::parse($from)->setTime(8, 0));
        app(DelegationService::class)->syncDaily();
        $this->assertSame(Delegation::STATUS_ACTIVE, $delegation->fresh()->status);

        $child = app(LeaveService::class)->extend($approved, Carbon::parse($to)->addDay());
        app(LeaveService::class)->acceptExtension($child);
        $this->assertSame(Carbon::parse($to)->addDay()->toDateString(), $delegation->fresh()->ends_on->toDateString());

        app(LeaveService::class)->cut($approved->fresh(), Carbon::parse($from)->addDay());
        $this->assertSame(Delegation::STATUS_ENDED, $delegation->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_each_actor_sees_their_leave_action(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);

        $employee = User::factory()->create();
        $employee->givePermissionTo('hr.leaves.request');
        $substitute = User::factory()->create();
        $substitute->givePermissionTo('hr.leaves.request');
        $hr = User::factory()->create();
        $hr->givePermissionTo(['hr.leaves.view-all', 'hr.employees.update']);
        EmployeeProfile::query()->create(['user_id' => $employee->id, 'annual_leave_balance' => 10, 'job_title' => 'موظف']);

        $leave = app(LeaveService::class)->submit(
            $employee,
            'سنوية',
            now()->addDays(3)->toDateString(),
            now()->addDays(4)->toDateString(),
            'مهمة',
            $substitute->id,
        );

        Livewire::actingAs($substitute)->test(LeavesIndex::class)
            ->assertSee('قبول البديل', false)
            ->assertSee('بانتظار', false)
            ->assertSee('البديل', false);
        Livewire::actingAs($hr)->test(LeavesIndex::class)->assertSee('معاينة الإنابة', false);
        Livewire::actingAs($employee)->test(LeavesIndex::class)
            ->call('openForm')
            ->set('substitute_id', $substitute->id)
            ->assertSee('صلاحيات', false);
        $this->assertNotNull($leave->id);
    }
}
