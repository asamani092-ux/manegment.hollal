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

    public function test_sidebar_renders_primary_and_secondary_groups(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('General Manager');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('الفريق', false);
        $response->assertSee('إسناد', false);
        $response->assertSee('المزيد', false);
        $response->assertSee('المستندات', false);
        $response->assertSee('ds-sidebar-more', false);
    }

    public function test_secondary_entries_hidden_without_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('Employee');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('الرواتب', false);
        $response->assertDontSee('الأدوار والصلاحيات', false);
        $response->assertSee('إسناد', false);
    }

    public function test_navigation_helper_flattens_all_groups(): void
    {
        $nav = config('navigation');

        $this->assertArrayHasKey('primary', $nav);
        $this->assertArrayHasKey('secondary', $nav);
        $this->assertCount(5, $nav['primary']);
        $this->assertCount(6, $nav['secondary']);
        $this->assertCount(12, NavigationHelper::allItems());
    }

    public function test_secondary_module_routes_reachable_for_general_manager(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('General Manager');

        foreach (config('navigation.secondary') as $item) {
            $this->actingAs($user)
                ->get(route($item['route']))
                ->assertOk();
        }
    }
}
