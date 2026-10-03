<?php

namespace Tests\Feature;

use App\Models\EmployeeOnboardingItem;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\OnboardingChecklistService;
use App\Services\OrgStructureService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrOrgOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checklist_is_created_once_and_job_grants_default_role(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);

        $user = User::factory()->create();
        app(OnboardingChecklistService::class)->generate($user);
        $first = EmployeeOnboardingItem::query()->where('user_id', $user->id)->count();
        app(OnboardingChecklistService::class)->generate($user);
        $this->assertSame($first, EmployeeOnboardingItem::query()->where('user_id', $user->id)->count());
        $this->assertGreaterThan(0, $first);

        $service = app(OrgStructureService::class);
        $top = $service->createUnit('الإدارة العليا', OrgUnit::LEVEL_TOP);
        $admin = $service->createUnit('الإدارة', OrgUnit::LEVEL_ADMINISTRATION, $top);
        $unit = $service->createUnit('قسم', OrgUnit::LEVEL_UNIT, $admin);
        $job = $service->createUnit('محلل', OrgUnit::LEVEL_JOB, $unit, ['default_role' => 'Employee']);

        $placed = $service->placeEmployee($user, $job);
        $this->assertSame($job->id, $placed->org_unit_id);
        $this->assertTrue($placed->hasRole('Employee'));
        $this->assertSame('Employee', $placed->auto_role_name);
    }
}
