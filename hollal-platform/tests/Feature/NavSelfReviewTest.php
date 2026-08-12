<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opens every unique sidebar route as Super Admin — self-review gate.
 */
class NavSelfReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_nav_route_opens_for_super_admin(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['must_change_password' => false, 'attendance_enabled' => true]);
        $admin->assignRole('Super Admin');

        $routes = collect(NavigationHelper::allRoutes())->unique()->values();
        $this->assertGreaterThan(25, $routes->count());

        foreach ($routes as $name) {
            $this->actingAs($admin)
                ->get(route($name))
                ->assertOk("فشل فتح المسار: {$name}");
        }
    }
}
