<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_flat_navigation_has_no_secondary_items(): void
    {
        $this->assertSame([], config('navigation.secondary'));
        $this->assertNotEmpty(config('navigation.groups'));
    }

    public function test_finance_routes_are_registered_in_navigation(): void
    {
        $routes = NavigationHelper::allRoutes();

        foreach (['expenses.index', 'custodies.index', 'assets.index', 'revenues.index', 'tax-invoices.index', 'budgets.index', 'financial-reports.index'] as $route) {
            $this->assertContains($route, $routes, "Missing nav route: {$route}");
        }
    }

    public function test_super_admin_can_open_custodies_screen(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get(route('custodies.index'))
            ->assertOk();
    }

    public function test_super_admin_can_open_assets_and_revenues_screens(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->get(route('assets.index'))->assertOk();
        $this->actingAs($admin)->get(route('revenues.index'))->assertOk();
    }
}
