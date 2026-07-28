<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_sidebar_renders_grouped_navigation_without_more_group(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('General Manager');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('الموارد البشرية', false);
        $response->assertSee('طلبات الصرف', false);
        $response->assertDontSee('المزيد', false);
        $response->assertDontSee('id="ds-sidebar-more"', false);
    }

    public function test_secondary_entries_hidden_without_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('Employee');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('إعدادات المنصة', false);
        $response->assertSee('المهام', false);
    }

    public function test_navigation_helper_flattens_all_groups(): void
    {
        $nav = config('navigation');

        $this->assertArrayHasKey('groups', $nav);
        $this->assertSame([], $nav['secondary']);
        $this->assertCount(11, $nav['groups']);
        $this->assertGreaterThan(30, count(NavigationHelper::allItems()));
    }

    public function test_finance_routes_reachable_for_finance_role(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('Finance');

        foreach (['custodies.index', 'assets.index', 'revenues.index', 'tax-invoices.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }
}
