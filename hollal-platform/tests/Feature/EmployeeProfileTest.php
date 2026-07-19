<?php

namespace Tests\Feature;

use App\Livewire\Users\EmployeeProfileShow;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 01-B1 — job profile: salary-tab gating + access logging, status transitions.
 */
class EmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_employee_cannot_open_another_users_salary_tab(): void
    {
        $target = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('hr.employees.view');

        Livewire::actingAs($viewer)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('setTab', 'salary')
            ->assertForbidden();

        $this->assertDatabaseCount('profile_access_logs', 0);
    }

    public function test_salary_tab_access_is_logged(): void
    {
        $target = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['hr.employees.view', 'hr.salaries.view']);

        Livewire::actingAs($viewer)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('setTab', 'salary')
            ->assertSet('activeTab', 'salary');

        $this->assertDatabaseHas('profile_access_logs', [
            'user_id' => $viewer->id,
            'target_user_id' => $target->id,
            'tab_accessed' => 'salary',
        ]);
    }

    public function test_freezing_blocks_login_gate(): void
    {
        $user = User::factory()->create(['employment_status' => 'نشط', 'is_active' => true]);

        $user->transitionStatus(User::STATUS_FROZEN);

        $this->assertSame('مجمد', $user->fresh()->employment_status);
        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_reactivation_restores_login_gate(): void
    {
        $user = User::factory()->create(['employment_status' => 'مجمد', 'is_active' => false]);

        $user->transitionStatus(User::STATUS_ACTIVE);

        $this->assertTrue((bool) $user->fresh()->is_active);
    }

    public function test_termination_requires_offboarding(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $user->transitionStatus(User::STATUS_TERMINATED);
    }

    public function test_termination_allowed_via_offboarding(): void
    {
        $user = User::factory()->create();

        $user->transitionStatus(User::STATUS_TERMINATED, viaOffboarding: true);

        $this->assertSame('منتهية_علاقته', $user->fresh()->employment_status);
        $this->assertFalse((bool) $user->fresh()->is_active);
    }
}
