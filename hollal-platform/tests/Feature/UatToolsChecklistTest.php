<?php

namespace Tests\Feature;

use App\Livewire\Uat\ToolsChecklist;
use App\Models\UatToolChecklist;
use App\Models\UatToolChecklistSnapshot;
use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertSee('تقييم أدوات المنصة (UAT) — 11 تبويباً', false)
            ->assertSee('نسخ التقرير كاملاً', false)
            ->assertSee('تقييم المرحلة 3 (17 أغسطس)', false)
            ->assertSee('تقييم 20:27', false)
            ->assertSee('تقييم 19:04', false)
            ->assertSee('2026-08-17 15:23', false)
            ->assertSee('التبويب 1 — الموارد البشرية', false)
            ->assertSee('التبويب 9 — المالية', false)
            ->assertSee('التبويب 11 — المشاريع', false)
            ->assertSee('دليل العاملين', false)
            ->assertSee('الحضور الشهري', false)
            ->assertSee('دليل الحسابات', false)
            ->assertSee('أسئلة التشخيص', false)
            ->assertSee('دورة الحياة', false)
            ->assertSee('الملاحظة', false)
            ->assertSee('قاعدة التبويبات', false)
            ->assertSee('التقييم محفوظ على السيرفر', false);
    }

    public function test_persist_state_is_shared_and_snapshots_accumulate(): void
    {
        config(['uat_tools.enabled' => true]);

        $admin = $this->admin();
        $other = User::factory()->create([
            'phone' => '0501111111',
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $other->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(ToolsChecklist::class)
            ->call('persistState', [
                'verdicts' => ['tasks' => 'يعتمد', 'sidebar' => 'يحتاج تحسين', 'unknown' => 'يعتمد'],
                'tags' => ['tasks' => 'UI ناقص', 'unknown' => 'أخرى'],
                'notes' => ['tasks' => 'أُغلقت بعد الإصلاح'],
                'activePhase' => 2,
                'snapshot' => true,
                'source' => 'import-local',
            ])
            ->assertOk();

        $row = UatToolChecklist::query()->where('slot', 'shared')->first();
        $this->assertNotNull($row);
        $this->assertSame(2, $row->active_phase);
        $this->assertSame('يعتمد', $row->verdicts['tasks']);
        $this->assertSame('يحتاج تحسين', $row->verdicts['sidebar']);
        $this->assertArrayNotHasKey('unknown', $row->verdicts);
        $before = UatToolChecklistSnapshot::query()->count();
        $this->assertGreaterThanOrEqual(1, $before);

        Livewire::actingAs($admin)
            ->test(ToolsChecklist::class)
            ->call('persistState', [
                'verdicts' => ['tasks' => 'يعتمد', 'sidebar' => 'يعتمد'],
                'tags' => ['tasks' => ''],
                'notes' => ['tasks' => 'أُغلقت بعد الإصلاح'],
                'activePhase' => 5,
                'snapshot' => true,
                'source' => 'copy-report',
            ])
            ->assertOk();

        $this->assertSame($before + 1, UatToolChecklistSnapshot::query()->count());
        $this->assertContains('copy-report', UatToolChecklistSnapshot::query()->pluck('source')->all());
        $this->assertContains('import-local', UatToolChecklistSnapshot::query()->pluck('source')->all());

        $this->actingAs($other)->get(route('uat.tools'))->assertOk();

        $shared = app(\App\Services\UatToolChecklistService::class)->current();
        $this->assertNotNull($shared);
        $this->assertSame(5, $shared['activePhase']);
        $this->assertSame('يعتمد', $shared['verdicts']['tasks']);
        $this->assertSame('أُغلقت بعد الإصلاح', $shared['notes']['tasks']);
    }

    public function test_tabs_cover_all_groups_without_overlap(): void
    {
        $phases = config('uat_tools.phases');
        $groups = collect(config('uat_tools.groups'));

        $this->assertCount(11, $phases);

        $assigned = collect($phases)->flatMap(fn (array $p) => $p['group_ids'])->sort()->values();
        $allIds = $groups->pluck('id')->sort()->values();

        $this->assertSame($allIds->all(), $assigned->all());

        foreach ($groups as $group) {
            $this->assertGreaterThanOrEqual(1, $group['phase']);
            $this->assertLessThanOrEqual(11, $group['phase']);
            $phase = collect($phases)->firstWhere('id', $group['phase']);
            $this->assertContains($group['id'], $phase['group_ids']);
        }
    }

    public function test_all_tools_have_unique_ids_and_nonempty_checks(): void
    {
        $ids = [];
        foreach (config('uat_tools.groups', []) as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $this->assertNotEmpty($item['id'] ?? '');
                $this->assertNotEmpty($item['tool'] ?? '');
                $this->assertNotEmpty($item['checks'] ?? '');
                $this->assertArrayNotHasKey($item['id'], $ids, "Duplicate tool id: {$item['id']}");
                $ids[$item['id']] = true;
            }
        }

        $this->assertGreaterThanOrEqual(60, count($ids));
    }

    public function test_active_phase_accepts_up_to_eleven(): void
    {
        $service = app(\App\Services\UatToolChecklistService::class);

        $state = $service->normalize(['activePhase' => 11]);
        $this->assertSame(11, $state['activePhase']);

        $state = $service->normalize(['activePhase' => 12]);
        $this->assertSame(1, $state['activePhase']);
    }

    public function test_phase3_report_is_default_and_unlocks_phase_three(): void
    {
        $baseline = config('uat_tools.baseline');

        $this->assertSame('2026-08-17 15:23', $baseline['date']);
        $this->assertSame('يعتمد', $baseline['verdicts']['sidebar']);
        $this->assertSame('يعتمد', $baseline['verdicts']['tasks']);
        $this->assertSame('يعتمد', $baseline['verdicts']['programs']);
        $this->assertSame('يحتاج تحسين', $baseline['verdicts']['orgs']);
        $this->assertSame('يحتاج تحسين', $baseline['verdicts']['smtp']);
        $this->assertStringContainsString('تجديد لمشروع', $baseline['notes']['org-show']);
        $this->assertGreaterThanOrEqual(60, count($baseline['verdicts']));
    }

    public function test_baseline_round4_remains_available(): void
    {
        $round4 = config('uat_tools.baseline_round4');

        $this->assertSame('2026-08-14 20:27', $round4['date']);
        $this->assertSame('يحتاج تحسين', $round4['verdicts']['evaluations']);
        $this->assertStringContainsString('أرشفة', $round4['notes']['evaluations']);
    }

    public function test_baseline_round3_remains_available(): void
    {
        $round3 = config('uat_tools.baseline_round3');

        $this->assertSame('2026-08-14 19:04', $round3['date']);
        $this->assertSame('يحتاج تحسين', $round3['verdicts']['attendance']);
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
