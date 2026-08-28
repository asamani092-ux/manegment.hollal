<?php

namespace Tests\Feature;

use App\Livewire\Hr\HrLifecycleIndex;
use App\Livewire\Users\UsersIndex;
use App\Models\EmployeeProfile;
use App\Models\OrgUnit;
use App\Models\Task;
use App\Models\User;
use App\Services\OffboardingService;
use App\Services\OnboardingService;
use App\Support\OrgJobCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** HR round-2: job from structure, single checklist assignee, contracts nav hidden. */
class HrRound2Test extends TestCase
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

    public function test_job_catalog_filters_by_unit(): void
    {
        $adminA = OrgUnit::create([
            'name' => 'إدارة أ',
            'level' => OrgUnit::LEVEL_ADMINISTRATION,
        ]);
        $unitA = OrgUnit::create([
            'name' => 'قسم أ',
            'level' => OrgUnit::LEVEL_UNIT,
            'parent_id' => $adminA->id,
        ]);
        $jobA = OrgUnit::create([
            'name' => 'أخصائي أ',
            'level' => OrgUnit::LEVEL_JOB,
            'parent_id' => $unitA->id,
        ]);
        $adminB = OrgUnit::create([
            'name' => 'إدارة ب',
            'level' => OrgUnit::LEVEL_ADMINISTRATION,
        ]);
        $unitB = OrgUnit::create([
            'name' => 'قسم ب',
            'level' => OrgUnit::LEVEL_UNIT,
            'parent_id' => $adminB->id,
        ]);
        OrgUnit::create([
            'name' => 'أخصائي ب',
            'level' => OrgUnit::LEVEL_JOB,
            'parent_id' => $unitB->id,
        ]);

        $options = OrgJobCatalog::optionsForUnit($unitA->id);
        $this->assertCount(1, $options);
        $this->assertSame($jobA->id, $options[0]['id']);
        $this->assertSame('أخصائي أ', OrgJobCatalog::resolveTitle($jobA->id));
    }

    public function test_create_user_uses_org_job_and_single_onboarding_assignee(): void
    {
        $admin = $this->hrAdmin();
        $assignee = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $assignee->assignRole('Employee');

        $administration = OrgUnit::create([
            'name' => 'التنفيذية',
            'level' => OrgUnit::LEVEL_ADMINISTRATION,
        ]);
        $unit = OrgUnit::create([
            'name' => 'قسم المشاريع',
            'level' => OrgUnit::LEVEL_UNIT,
            'parent_id' => $administration->id,
        ]);
        $job = OrgUnit::create([
            'name' => 'منسق مشاريع',
            'level' => OrgUnit::LEVEL_JOB,
            'parent_id' => $unit->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('openCreateModal')
            ->set('name', 'موظف هيكل')
            ->set('email', 'struct@example.com')
            ->set('phone', '0555111222')
            ->set('password', 'password123')
            ->set('roleName', 'Employee')
            ->set('administration_id', $administration->id)
            ->set('unit_id', $unit->id)
            ->set('job_org_unit_id', $job->id)
            ->set('onboarding_assignee_id', $assignee->id)
            ->set('employment_type', 'دوام_كامل')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'struct@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($job->id, $user->org_unit_id);
        $this->assertDatabaseHas('employees_profile', [
            'user_id' => $user->id,
            'job_title' => 'منسق مشاريع',
        ]);

        $tasks = Task::query()
            ->where('related_user_id', $user->id)
            ->where('role_label', OnboardingService::ROLE_LABEL)
            ->get();
        $this->assertCount(4, $tasks);
        $this->assertTrue($tasks->every(fn (Task $t) => (int) $t->assigned_to === (int) $assignee->id));
    }

    public function test_offboarding_assigns_one_assignee_to_all_tasks(): void
    {
        $admin = $this->hrAdmin();
        $assignee = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $target = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
        ]);
        EmployeeProfile::create(['user_id' => $target->id, 'job_title' => 'موظف']);

        Livewire::actingAs($admin)
            ->test(HrLifecycleIndex::class)
            ->call('askStartOffboarding', $target->id)
            ->set('checklistAssigneeId', $assignee->id)
            ->call('startOffboarding', $target->id)
            ->assertHasNoErrors();

        $tasks = Task::query()
            ->where('related_user_id', $target->id)
            ->where('role_label', OffboardingService::ROLE_LABEL)
            ->get();
        $this->assertCount(4, $tasks);
        $this->assertTrue($tasks->every(fn (Task $t) => (int) $t->assigned_to === (int) $assignee->id));
        $this->assertNotNull($target->fresh()->offboarding_started_at);
    }

    public function test_contracts_route_hidden_from_navigation_config(): void
    {
        $items = collect(config('navigation.groups'))
            ->flatMap(fn ($g) => $g['items'] ?? [])
            ->pluck('route');

        $this->assertFalse($items->contains('contracts.index'));
    }
}
