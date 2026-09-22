<?php

namespace Tests\Feature;

use App\Livewire\Finance\CustodiesIndex;
use App\Models\Custody;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * إغلاق فجوة: الموظف يطلب عهدته ويتابعها دون صرف/تسوية/اعتماد.
 */
class EmployeeCustodyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_employee_can_open_custodies_and_request_own(): void
    {
        $employee = User::factory()->create(['must_change_password' => false]);
        $employee->assignRole('Employee');

        $this->actingAs($employee)
            ->get(route('custodies.index'))
            ->assertOk();

        Livewire::actingAs($employee)
            ->test(CustodiesIndex::class)
            ->call('openRequestModal')
            ->set('amount', '750')
            ->set('purpose', 'مستلزمات ميدانية')
            ->set('employee_id', $employee->id)
            ->call('submitRequest')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('custodies', [
            'employee_id' => $employee->id,
            'amount' => 750,
            'status' => Custody::STATUS_REQUESTED,
        ]);
    }

    public function test_employee_sees_only_own_custodies(): void
    {
        $employee = User::factory()->create(['must_change_password' => false]);
        $employee->assignRole('Employee');
        $other = User::factory()->create();

        Custody::create([
            'employee_id' => $employee->id,
            'amount' => 100,
            'purpose' => 'عهدي',
            'requested_by' => $employee->id,
            'status' => Custody::STATUS_REQUESTED,
        ]);
        Custody::create([
            'employee_id' => $other->id,
            'amount' => 200,
            'purpose' => 'عهدة غيره',
            'requested_by' => $other->id,
            'status' => Custody::STATUS_REQUESTED,
        ]);

        Livewire::actingAs($employee)
            ->test(CustodiesIndex::class)
            ->assertSee('عهدي')
            ->assertDontSee('عهدة غيره');
    }

    public function test_employee_cannot_settle(): void
    {
        $employee = User::factory()->create(['must_change_password' => false]);
        $employee->assignRole('Employee');

        $custody = Custody::create([
            'employee_id' => $employee->id,
            'amount' => 500,
            'disbursed_amount' => 500,
            'purpose' => 'مصروف',
            'requested_by' => $employee->id,
            'status' => Custody::STATUS_DISBURSED,
        ]);

        Livewire::actingAs($employee)
            ->test(CustodiesIndex::class)
            ->call('openSettle', $custody->id)
            ->assertForbidden();
    }

    public function test_employee_role_includes_custodies_view(): void
    {
        $role = Role::findByName('Employee');
        $this->assertTrue($role->hasPermissionTo('finance.custodies.view'));
        $this->assertFalse($role->hasPermissionTo('finance.custodies.approve'));
        $this->assertFalse($role->hasPermissionTo('finance.custodies.disburse'));
    }
}
