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
            ->assertSee('تقييم أدوات المنصة (UAT) — 3 مراحل', false)
            ->assertSee('نسخ التقرير كاملاً', false)
            ->assertSee('تحميل التقييم المحفوظ (14 أغسطس)', false)
            ->assertSee('تحميل التجربة الثانية', false)
            ->assertSee('2026-08-14 19:04', false)
            ->assertSee('المرحلة 1 — الأساس والموارد', false)
            ->assertSee('المرحلة 2 — التشغيل والمالية', false)
            ->assertSee('المرحلة 3 — النمو والمحتوى', false)
            ->assertSee('دليل العاملين', false)
            ->assertSee('الملاحظة', false)
            ->assertSee('قاعدة المراحل', false);
    }

    public function test_phases_cover_all_groups_without_overlap(): void
    {
        $phases = config('uat_tools.phases');
        $groups = collect(config('uat_tools.groups'));

        $this->assertCount(3, $phases);

        $assigned = collect($phases)->flatMap(fn (array $p) => $p['group_ids'])->sort()->values();
        $allIds = $groups->pluck('id')->sort()->values();

        $this->assertSame($allIds->all(), $assigned->all());

        foreach ($groups as $group) {
            $this->assertContains($group['phase'], [1, 2, 3]);
            $phase = collect($phases)->firstWhere('id', $group['phase']);
            $this->assertContains($group['id'], $phase['group_ids']);
        }

        $counts = [];
        foreach ($phases as $phase) {
            $counts[$phase['id']] = $groups
                ->whereIn('id', $phase['group_ids'])
                ->sum(fn (array $g) => count($g['items']));
        }

        // Balanced within ~±5 of mean (~21)
        foreach ($counts as $n) {
            $this->assertGreaterThanOrEqual(18, $n);
            $this->assertLessThanOrEqual(25, $n);
        }
    }

    public function test_baseline_round3_is_default_and_covers_prior_verdicts(): void
    {
        $baseline = config('uat_tools.baseline');

        $this->assertSame('2026-08-14 19:04', $baseline['date']);
        $this->assertSame('يعتمد', $baseline['verdicts']['bell']);
        $this->assertSame('يعتمد', $baseline['verdicts']['sidebar']);
        $this->assertSame('غير مجرّب', $baseline['verdicts']['smtp']);
        $this->assertSame('يحتاج تحسين', $baseline['verdicts']['attendance']);
        $this->assertStringContainsString('تفكيك', $baseline['notes']['attendance']);
        $this->assertGreaterThanOrEqual(60, count($baseline['verdicts']));
    }

    public function test_baseline_round2_remains_available(): void
    {
        $round2 = config('uat_tools.baseline_round2');

        $this->assertSame('2026-08-13 15:22', $round2['date']);
        $this->assertSame('يحتاج تحسين', $round2['verdicts']['bell']);
        $this->assertStringContainsString('صفحة سوداء', $round2['notes']['bell']);
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
