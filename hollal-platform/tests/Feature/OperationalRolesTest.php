<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationHelper;
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
        $this->actingAs($user);

        return collect(NavigationHelper::allItems())
            ->filter(fn (array $item): bool => NavigationHelper::userCanSee($item['permission']))
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

    public function test_general_manager_sees_core_finance_and_hr_nav(): void
    {
        $routes = $this->visibleNavRoutes($this->makeUserForRole('General Manager', '0501111111'));

        foreach (['dashboard', 'users.index', 'budgets.index', 'financial-reports.index', 'reports.index'] as $route) {
            $this->assertContains($route, $routes);
        }
    }

    public function test_executive_manager_sidebar_visibility(): void
    {
        $routes = $this->visibleNavRoutes($this->makeUserForRole('Executive Manager', '0502222222'));

        foreach (['dashboard', 'users.index', 'tasks.index', 'meetings.index', 'custodies.index', 'projects.index'] as $route) {
            $this->assertContains($route, $routes);
        }

        $this->assertNotContains('settings.index', $routes);
    }

    public function test_project_manager_sidebar_visibility(): void
    {
        $routes = $this->visibleNavRoutes($this->makeUserForRole('Project Manager', '0503333333'));

        foreach (['dashboard', 'tasks.index', 'meetings.index', 'projects.index', 'documents.index'] as $route) {
            $this->assertContains($route, $routes);
        }

        $this->assertNotContains('custodies.index', $routes);
    }

    public function test_finance_sidebar_visibility(): void
    {
        $routes = $this->visibleNavRoutes($this->makeUserForRole('Finance', '0504444444'));

        foreach (['dashboard', 'tax-invoices.index', 'budgets.index', 'custodies.index', 'assets.index', 'revenues.index'] as $route) {
            $this->assertContains($route, $routes);
        }
    }

    public function test_employee_sidebar_visibility(): void
    {
        $routes = $this->visibleNavRoutes($this->makeUserForRole('Employee', '0505555555'));

        foreach (['dashboard', 'tasks.index', 'meetings.index', 'projects.index', 'documents.index'] as $route) {
            $this->assertContains($route, $routes);
        }

        $this->assertNotContains('payroll.index', $routes);
    }

    public function test_employee_cannot_access_payroll_or_roles_settings(): void
    {
        $user = $this->makeUserForRole('Employee', '0505555555');

        $this->actingAs($user)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.roles'))->assertForbidden();
    }
}
