<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 00-B2 — verifies the hierarchical tab.section.action permission rename
 * and the new role #7 (مدير الشراكات / Partnerships Manager).
 */
class HierarchicalPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_permissions_use_hierarchical_names_and_old_names_are_absent(): void
    {
        foreach ([
            'hr.employees.view',
            'hr.salaries.manage',
            'structure.departments.view',
            'esnad.tasks.view',
            'finance.expenses.approve',
            'partnerships.contracts.manage',
        ] as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
        }

        foreach ([
            'expenses.approve',
            'salaries.manage',
            'tasks.view',
            'users.view',
            'contracts.manage',
            'departments.view',
        ] as $old) {
            $this->assertDatabaseMissing('permissions', ['name' => $old, 'guard_name' => 'web']);
        }
    }

    public function test_route_protection_uses_new_permission_names(): void
    {
        $allowed = User::factory()->create([
            'phone' => '0501111000',
            'must_change_password' => false,
        ]);
        $allowed->givePermissionTo('esnad.tasks.view');

        $this->actingAs($allowed)->get(route('tasks.index'))->assertOk();

        $denied = User::factory()->create([
            'phone' => '0501111001',
            'must_change_password' => false,
        ]);

        $this->actingAs($denied)->get(route('tasks.index'))->assertForbidden();
    }

    public function test_partnerships_manager_role_has_expected_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::where('name', 'Partnerships Manager')->firstOrFail();

        $this->assertTrue($role->hasPermissionTo('partnerships.view'));
        $this->assertTrue($role->hasPermissionTo('partnerships.contracts.manage'));
        $this->assertTrue($role->hasPermissionTo('projects.view'));

        // Must not leak finance/hr powers.
        $this->assertFalse($role->hasPermissionTo('finance.expenses.approve'));
        $this->assertFalse($role->hasPermissionTo('hr.salaries.manage'));
    }

    public function test_partnerships_manager_user_gates_correctly(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'phone' => '0501111002',
            'must_change_password' => false,
        ]);
        $user->assignRole('Partnerships Manager');

        $this->assertTrue($user->can('partnerships.view'));
        $this->assertFalse($user->can('finance.expenses.approve'));
    }
}
