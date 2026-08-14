<?php

namespace Tests\Feature;

use App\Livewire\Hr\HrLifecycleIndex;
use App\Models\User;
use App\Services\OffboardingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Unified lifecycle detail panel for tasks + holds.
 */
class HrLifecyclePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_panel_opens_holds_and_tasks_tabs(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $admin->assignRole('Super Admin');

        $employee = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'name' => 'موظف اختبار إنهاء',
        ]);

        app(OffboardingService::class)->offboard($employee, $admin);

        Livewire::actingAs($admin)
            ->test(HrLifecycleIndex::class)
            ->call('openDetails', $employee->id, 'holds')
            ->assertSet('detailUserId', $employee->id)
            ->assertSet('detailTab', 'holds')
            ->assertSee('متابعة إنهاء العلاقة', false)
            ->assertSee('موظف اختبار إنهاء', false)
            ->assertSee('مهام إنهاء غير مكتملة', false)
            ->call('setDetailTab', 'tasks')
            ->assertSet('detailTab', 'tasks')
            ->assertSee('تسليم الأعمال الجارية', false)
            ->call('closeDetails')
            ->assertSet('detailUserId', null);
    }
}
