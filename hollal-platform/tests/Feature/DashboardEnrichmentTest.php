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
 * 00-B5 — dashboard: personal workspace, duties link, role smoke loads.
 * Check-in/out removed from dashboard (external attendance / QR path).
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

    public function test_dashboard_does_not_show_checkin_checkout_buttons(): void
    {
        $enabled = $this->user(attendance: true);
        $enabled->givePermissionTo('dashboard.view');

        Livewire::actingAs($enabled)
            ->test(DashboardIndex::class)
            ->assertDontSee('تسجيل حضور')
            ->assertDontSee('تسجيل انصراف');
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
