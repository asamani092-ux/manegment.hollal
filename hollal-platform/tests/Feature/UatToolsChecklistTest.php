<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT tools page is available pre-production and removed on publish.
 */
class UatToolsChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'phone' => '0500000000',
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_enabled_page_renders_catalog_and_copy_control(): void
    {
        config(['uat_tools.enabled' => true]);

        $this->actingAs($this->admin())
            ->get(route('uat.tools'))
            ->assertOk()
            ->assertSee('تقييم أدوات المنصة (UAT)', false)
            ->assertSee('نسخ التقرير كاملاً', false)
            ->assertSee('تحميل التقييم السابق', false)
            ->assertSee('تقييم التجربة الثانية', false)
            ->assertSee('دليل العاملين', false)
            ->assertSee('الملاحظة', false);
    }

    public function test_baseline_round2_covers_prior_verdicts(): void
    {
        $baseline = config('uat_tools.baseline');

        $this->assertSame('2026-08-13 15:22', $baseline['date']);
        $this->assertSame('يحتاج تحسين', $baseline['verdicts']['bell']);
        $this->assertSame('يعتمد', $baseline['verdicts']['sidebar']);
        $this->assertSame('غير مجرّب', $baseline['verdicts']['smtp']);
        $this->assertStringContainsString('صفحة سوداء', $baseline['notes']['bell']);
        $this->assertGreaterThanOrEqual(50, count($baseline['verdicts']));
    }

    public function test_disabled_page_returns_not_found_and_leaves_nav(): void
    {
        config(['uat_tools.enabled' => false]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/uat/tools')
            ->assertNotFound();

        $routes = collect(NavigationHelper::allItems())->pluck('route');
        $this->assertNotContains('uat.tools', $routes->all());
    }

    public function test_nav_exposes_uat_entry_when_enabled(): void
    {
        config(['uat_tools.enabled' => true]);

        $routes = collect(NavigationHelper::allItems())->pluck('route');
        $this->assertContains('uat.tools', $routes->all());
    }
}
