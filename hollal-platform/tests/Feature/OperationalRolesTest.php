<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    /**
     * @return list<string>
     */
    protected function visibleNavRoutes(User $user): array
    {
        return collect(config('navigation'))
            ->filter(fn (array $item): bool => $user->can($item['permission']))
            ->pluck('route')
            ->values()
            ->all();
    }

    protected function makeUserForRole(string $roleName, string $phone): User
    {
        $user = User::factory()->create([
            'phone' => $phone,
            'must_change_password' => false,
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    public function test_general_manager_sees_all_sidebar_entries(): void
    {
        $user = $this->makeUserForRole('General Manager', '0501111111');

        $expected = collect(config('navigation'))->pluck('route')->all();

        $this->assertSame($expected, $this->visibleNavRoutes($user));
    }

    public function test_executive_manager_sidebar_visibility(): void
    {
        $user = $this->makeUserForRole('Executive Manager', '0502222222');

        $this->assertSame(
            [
                'dashboard',
                'projects.index',
                'tasks.index',
                'meetings.index',
                'documents.index',
                'expenses.index',
                'reports.index',
                'users.index',
            ],
            $this->visibleNavRoutes($user)
        );
    }

    public function test_project_manager_sidebar_visibility(): void
    {
        $user = $this->makeUserForRole('Project Manager', '0503333333');

        $this->assertSame(
            [
                'dashboard',
                'projects.index',
                'tasks.index',
                'meetings.index',
                'documents.index',
                'expenses.index',
            ],
            $this->visibleNavRoutes($user)
        );
    }

    public function test_finance_sidebar_visibility(): void
    {
        $user = $this->makeUserForRole('Finance', '0504444444');

        $this->assertSame(
            [
                'dashboard',
                'payroll.index',
                'expenses.index',
                'contracts.index',
                'reports.index',
            ],
            $this->visibleNavRoutes($user)
        );
    }

    public function test_employee_sidebar_visibility(): void
    {
        $user = $this->makeUserForRole('Employee', '0505555555');

        $this->assertSame(
            [
                'dashboard',
                'projects.index',
                'tasks.index',
                'meetings.index',
                'documents.index',
                'expenses.index',
            ],
            $this->visibleNavRoutes($user)
        );
    }

    public function test_employee_cannot_access_payroll_or_roles_settings(): void
    {
        $user = $this->makeUserForRole('Employee', '0505555555');

        $this->actingAs($user)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.roles'))->assertForbidden();
    }
}
