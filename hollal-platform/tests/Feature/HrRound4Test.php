<?php

namespace Tests\Feature;

use App\Livewire\Users\UsersIndex;
use App\Models\OrgUnit;
use App\Models\User;
use App\Support\OrgJobCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** HR Round 4 batch 1 — drop departments page; org level قسم; optional cascade. */
class HrRound4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function hrAdmin(): User
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_departments_route_is_gone(): void
    {
        $admin = $this->hrAdmin();

        $this->actingAs($admin)->get('/departments')->assertNotFound();
        $this->assertFalse(
            collect(config('navigation.groups'))
                ->flatMap(fn ($g) => $g['items'] ?? [])
                ->pluck('route')
                ->contains('departments.index')
        );
    }

    public function test_org_unit_mid_level_is_department_arabic(): void
    {
        $this->assertSame('قسم', OrgUnit::LEVEL_UNIT);

        $admin = OrgUnit::create(['name' => 'إدارة', 'level' => OrgUnit::LEVEL_ADMINISTRATION]);
        $unit = OrgUnit::create([
            'name' => 'قسم تقني',
            'level' => OrgUnit::LEVEL_UNIT,
            'parent_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('org_units', [
            'id' => $unit->id,
            'level' => 'قسم',
        ]);
        $this->assertDatabaseMissing('org_units', ['level' => 'وحدة']);
    }

    public function test_employee_org_cascade_is_optional_and_saves_job(): void
    {
        $admin = $this->hrAdmin();
        $assignee = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $assignee->assignRole('Employee');

        $administration = OrgUnit::create([
            'name' => 'إدارة الموارد',
            'level' => OrgUnit::LEVEL_ADMINISTRATION,
        ]);
        $unit = OrgUnit::create([
            'name' => 'قسم التوظيف',
            'level' => OrgUnit::LEVEL_UNIT,
            'parent_id' => $administration->id,
        ]);
        $job = OrgUnit::create([
            'name' => 'أخصائي موارد',
            'level' => OrgUnit::LEVEL_JOB,
            'parent_id' => $unit->id,
        ]);

        // Optional: create without cascade
        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('openCreateModal')
            ->set('name', 'بدون هيكل')
            ->set('email', 'no-org@example.com')
            ->set('phone', '0555000001')
            ->set('password', 'password123')
            ->set('roleName', 'Employee')
            ->set('onboarding_assignee_id', $assignee->id)
            ->call('save')
            ->assertHasNoErrors();

        $plain = User::query()->where('email', 'no-org@example.com')->first();
        $this->assertNotNull($plain);
        $this->assertNull($plain->org_unit_id);

        // With full cascade إدارة → قسم → وظيفة
        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('openCreateModal')
            ->set('name', 'مع هيكل')
            ->set('email', 'with-org@example.com')
            ->set('phone', '0555000002')
            ->set('password', 'password123')
            ->set('roleName', 'Employee')
            ->set('administration_id', $administration->id)
            ->assertSet('unit_id', null)
            ->set('unit_id', $unit->id)
            ->assertSet('job_org_unit_id', null)
            ->set('job_org_unit_id', $job->id)
            ->set('onboarding_assignee_id', $assignee->id)
            ->call('save')
            ->assertHasNoErrors();

        $placed = User::query()->where('email', 'with-org@example.com')->first();
        $this->assertNotNull($placed);
        $this->assertSame($job->id, $placed->org_unit_id);
        $this->assertDatabaseHas('employees_profile', [
            'user_id' => $placed->id,
            'job_title' => 'أخصائي موارد',
        ]);

        $cascade = OrgJobCatalog::cascadeFromJob($job->id);
        $this->assertSame($administration->id, $cascade['administration_id']);
        $this->assertSame($unit->id, $cascade['unit_id']);
    }
}
