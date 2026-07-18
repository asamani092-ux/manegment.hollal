<?php

namespace Tests\Feature;

use App\Livewire\DashboardIndex;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 00-B5 — dashboard: attendance placeholder gating, personal workspace,
 * and loads cleanly for every operational role.
 */
class DashboardEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function user(bool $attendance = false): User
    {
        return User::factory()->create([
            'must_change_password' => false,
            'attendance_enabled' => $attendance,
        ]);
    }

    public function test_checkin_buttons_visible_only_when_attendance_enabled(): void
    {
        $enabled = $this->user(attendance: true);
        $enabled->givePermissionTo('dashboard.view');

        Livewire::actingAs($enabled)
            ->test(DashboardIndex::class)
            ->assertSee('تسجيل حضور')
            ->assertSee('تسجيل انصراف');

        $disabled = $this->user(attendance: false);
        $disabled->givePermissionTo('dashboard.view');

        Livewire::actingAs($disabled)
            ->test(DashboardIndex::class)
            ->assertDontSee('تسجيل حضور');
    }

    public function test_checkin_action_blocked_without_attendance_flag(): void
    {
        $user = $this->user(attendance: false);
        $user->givePermissionTo('dashboard.view');

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->call('checkIn')
            ->assertForbidden();
    }

    public function test_employee_sees_personal_workspace(): void
    {
        $employee = $this->user();
        $employee->assignRole('Employee');

        Livewire::actingAs($employee)
            ->test(DashboardIndex::class)
            ->assertOk()
            ->assertSee('مساحة عملي');
    }

    public function test_dashboard_loads_for_every_role(): void
    {
        $roles = [
            'Super Admin', 'General Manager', 'Executive Manager',
            'Project Manager', 'Finance', 'Employee', 'Partnerships Manager',
        ];

        foreach ($roles as $role) {
            $user = $this->user();
            $user->assignRole($role);

            Livewire::actingAs($user)
                ->test(DashboardIndex::class)
                ->assertOk();
        }
    }
}
