<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        foreach (['dashboard', 'employee-hub.index', 'tasks.index', 'meetings.index', 'projects.index', 'documents.index'] as $route) {
            $this->assertContains($route, $routes);
        }

        $this->assertNotContains('payroll.index', $routes);
    }

    public function test_super_admin_and_seven_roles_can_open_dashboard(): void
    {
        foreach ([
            'Super Admin',
            'General Manager',
            'Executive Manager',
            'Project Manager',
            'Finance',
            'Employee',
            'Partnerships Manager',
        ] as $i => $role) {
            $user = $this->makeUserForRole($role, '05070'.$i.'0000');
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
        }
    }

    public function test_finance_sees_accounting_nav_when_permitted(): void
    {
        $user = $this->makeUserForRole('Finance', '0504444444');
        if (! $user->can('finance.accounting.manage')) {
            $user->givePermissionTo('finance.accounting.manage');
        }
        $this->actingAs($user)->get(route('chart-of-accounts.index'))->assertOk();
    }

    public function test_grants_role_search_filters_roles(): void
    {
        $user = $this->makeUserForRole('General Manager', '0501111111');

        Livewire::actingAs($user)
            ->test(\App\Livewire\Settings\GrantsIndex::class)
            ->set('roleQuery', 'Employee')
            ->assertSee('Employee');
    }

    public function test_employee_cannot_access_payroll_or_roles_settings(): void
    {
        $user = $this->makeUserForRole('Employee', '0505555555');

        $this->actingAs($user)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.roles'))->assertForbidden();
        $this->actingAs($user)->get(route('custodies.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.grants'))->assertForbidden();
    }

    public function test_partnerships_manager_sees_pipeline_not_settings(): void
    {
        $routes = $this->visibleNavRoutes($this->makeUserForRole('Partnerships Manager', '0506666666'));

        foreach (['dashboard', 'organizations.index', 'partnerships.pipeline', 'projects.index'] as $route) {
            $this->assertContains($route, $routes);
        }

        $this->assertNotContains('settings.index', $routes);
        $this->assertNotContains('payroll.index', $routes);
    }

    public function test_finance_cannot_open_roles_or_hr_lifecycle(): void
    {
        $user = $this->makeUserForRole('Finance', '0504444444');

        $this->actingAs($user)->get(route('settings.roles'))->assertForbidden();
        $this->actingAs($user)->get(route('hr-lifecycle.index'))->assertForbidden();
    }

    public function test_project_manager_cannot_open_payroll_or_smtp(): void
    {
        $user = $this->makeUserForRole('Project Manager', '0503333333');

        $this->actingAs($user)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.notifications'))->assertForbidden();
    }

    public function test_jobs_and_committees_are_not_sidebar_entries(): void
    {
        $routes = $this->visibleNavRoutes($this->makeUserForRole('General Manager', '0501111111'));

        $this->assertNotContains('structure.jobs', $routes);
        $this->assertNotContains('structure.committees', $routes);
        $this->assertContains('structure.org-tree', $routes);
    }

    public function test_general_manager_can_open_org_tree_and_grants(): void
    {
        $user = $this->makeUserForRole('General Manager', '0501111111');

        $this->actingAs($user)->get(route('structure.org-tree'))->assertOk();
        $this->actingAs($user)->get(route('settings.grants'))->assertOk();
        $this->actingAs($user)->get('/departments')->assertNotFound();
    }

    public function test_employee_cannot_open_finance_or_audit_log(): void
    {
        $user = $this->makeUserForRole('Employee', '0505555555');

        $this->actingAs($user)->get(route('revenues.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.audit-log'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.notifications'))->assertForbidden();
    }

    public function test_partnerships_manager_can_open_pipeline_not_roles(): void
    {
        $user = $this->makeUserForRole('Partnerships Manager', '0506666666');

        $this->actingAs($user)->get(route('partnerships.pipeline'))->assertOk();
        $this->actingAs($user)->get(route('organizations.index'))->assertOk();
        $this->actingAs($user)->get(route('settings.roles'))->assertForbidden();
    }
}
