<?php

namespace Tests\Feature;

use App\Livewire\Structure\OrgTreeIndex;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\OrgChartService;
use App\Services\OrgStructureService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * شجرة الهيكل تُرسم من الخادم بلا مكتبات خارجية.
 * Time: O(n) | Space: O(n)
 */
class OrgChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_html_lists_units_and_occupants_without_cdn_scripts(): void
    {
        $this->seed(PermissionSeeder::class);
        $viewer = User::factory()->create(['must_change_password' => false, 'name' => 'أحمد المدير العام']);
        $viewer->givePermissionTo('structure.view');
        $service = app(OrgStructureService::class);
        $top = $service->createUnit('الإدارة العليا', OrgUnit::LEVEL_TOP);
        $general = $service->createUnit('المدير العام', OrgUnit::LEVEL_TOP_POSITION, $top);
        $executive = $service->createUnit('المدير التنفيذي', OrgUnit::LEVEL_TOP_POSITION, $general);
        $administration = $service->createUnit('إدارة البرامج', OrgUnit::LEVEL_ADMINISTRATION, $executive);
        $section = $service->createUnit('قسم التدريب', OrgUnit::LEVEL_UNIT, $administration);
        $job = $service->createUnit('مدرب أول', OrgUnit::LEVEL_JOB, $section, ['default_role' => 'Employee']);
        $service->createUnit('وظيفة شاغرة', OrgUnit::LEVEL_JOB, $section);
        $viewer->forceFill(['org_unit_id' => $general->id])->save();
        $worker = User::factory()->create(['name' => 'محمد الموظف', 'must_change_password' => false]);
        $worker->forceFill(['org_unit_id' => $job->id])->save();

        $html = $this->actingAs($viewer)->get(route('structure.org-tree'))->assertOk()->getContent();
        preg_match_all('/<script[^>]+src="([^"]*)"/i', $html, $sources);
        foreach ($sources[1] as $src) {
            $this->assertDoesNotMatchRegularExpression('/jsdelivr|unpkg|cdnjs/i', $src);
        }
        $this->assertStringNotContainsString('d3-org-chart', $html);
        $this->assertStringNotContainsString('>الهيكل<', $html);
        foreach (['المدير العام', 'المدير التنفيذي', 'إدارة البرامج', 'قسم التدريب', 'مدرب أول — محمد الموظف', 'أحمد المدير العام', 'وظيفة شاغرة — شاغر', 'موظف'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
    }

    public function test_tree_stays_within_six_queries_for_a_wide_chart(): void
    {
        $service = app(OrgStructureService::class);
        $top = $service->createUnit('الإدارة العليا', OrgUnit::LEVEL_TOP);
        $seat = $service->createUnit('المدير العام', OrgUnit::LEVEL_TOP_POSITION, $top);
        for ($adminIndex = 0; $adminIndex < 8; $adminIndex++) {
            $administration = $service->createUnit('إدارة '.$adminIndex, OrgUnit::LEVEL_ADMINISTRATION, $seat);
            for ($sectionIndex = 0; $sectionIndex < 3; $sectionIndex++) {
                $section = $service->createUnit('قسم '.$adminIndex.'-'.$sectionIndex, OrgUnit::LEVEL_UNIT, $administration);
                $service->createUnit('وظيفة '.$adminIndex.'-'.$sectionIndex, OrgUnit::LEVEL_JOB, $section);
            }
        }
        User::factory()->count(100)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $tree = app(OrgChartService::class)->tree();
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
        $this->assertNotSame([], $tree['roots']);
        $this->assertSame([], $tree['unlinked']);
        $this->assertSame('top', $tree['roots'][0]['type']);
        $this->assertAccentsAvoidNavyAndGold($tree['roots']);
    }

    public function test_orphan_administration_is_rejected_once_a_top_position_exists(): void
    {
        $service = app(OrgStructureService::class);
        $service->createUnit('إدارة قبل المنصب', OrgUnit::LEVEL_ADMINISTRATION);
        $service->addTopPosition('المدير العام', null, 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('الإدارة يجب أن تتبع منصباً أعلى');
        $service->createUnit('إدارة يتيمة', OrgUnit::LEVEL_ADMINISTRATION);
    }

    public function test_unlinked_administrations_stack_below_the_single_tree(): void
    {
        $this->seed(PermissionSeeder::class);
        $service = app(OrgStructureService::class);
        $orphan = $service->createUnit('إدارة يتيمة', OrgUnit::LEVEL_ADMINISTRATION);
        $container = OrgUnit::query()->where('level', OrgUnit::LEVEL_TOP)->first()
            ?? $service->createUnit('الإدارة العليا', OrgUnit::LEVEL_TOP);
        $underContainer = OrgUnit::query()->create([
            'name' => 'إدارة الحاوية',
            'level' => OrgUnit::LEVEL_ADMINISTRATION,
            'parent_id' => $container->id,
            'position' => 0,
        ]);
        $seat = $service->addTopPosition('المدير العام', null, 1);
        $linked = $service->createUnit('إدارة مربوطة', OrgUnit::LEVEL_ADMINISTRATION, $seat);

        $tree = app(OrgChartService::class)->tree();
        $this->assertSame(['المدير العام'], array_column($tree['roots'], 'title'));
        $this->assertEqualsCanonicalizing(
            ['إدارة يتيمة', 'إدارة الحاوية'],
            array_column($tree['unlinked'], 'title')
        );
        $this->assertNotContains('#0F3446', array_column($tree['unlinked'], 'accent'));
        $this->assertNotContains('#C4A052', array_column($tree['unlinked'], 'accent'));
        $this->assertSame($linked->parent_id, $seat->id);

        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo(['structure.view', 'structure.manage']);
        $html = $this->actingAs($viewer)->get(route('structure.org-tree'))->assertOk()->getContent();
        $this->assertStringContainsString('إدارات غير مرتبطة', $html);
        $this->assertStringContainsString('اربطها بـ', $html);
        $this->assertStringContainsString('flex-direction: column', $html);
        $this->assertLessThan(
            strpos($html, 'إدارات غير مرتبطة'),
            strpos($html, 'org-chart')
        );

        Livewire::actingAs($viewer)->test(OrgTreeIndex::class)
            ->set('linkChoice.'.$orphan->id, $seat->id)
            ->call('linkAdministration', $orphan->id)
            ->call('openDrawer', $linked->id)
            ->assertSee('يتبع لـ');
        $this->assertSame($seat->id, $orphan->fresh()->parent_id);
        $this->assertSame($container->id, $underContainer->fresh()->parent_id);
    }

    public function test_add_administration_defaults_to_the_last_top_position(): void
    {
        $this->seed(PermissionSeeder::class);
        $service = app(OrgStructureService::class);
        $service->addTopPosition('المدير العام', null, 1);
        $last = $service->addTopPosition('المدير التنفيذي', null, 2);
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo(['structure.view', 'structure.manage']);

        Livewire::actingAs($admin)->test(OrgTreeIndex::class)
            ->call('openUnitModal')
            ->assertSet('parentId', $last->id)
            ->assertSet('unitLevel', OrgUnit::LEVEL_ADMINISTRATION)
            ->assertSee('يتبع لـ')
            ->set('parentId', null)
            ->set('unitName', 'إدارة بلا أب')
            ->set('unitLevel', OrgUnit::LEVEL_ADMINISTRATION)
            ->call('saveUnit')
            ->assertHasErrors(['parentId' => 'اختر المنصب الأعلى الذي تتبعه الإدارة']);
    }

    public function test_unit_drawer_is_a_body_level_panel(): void
    {
        $this->seed(PermissionSeeder::class);
        $service = app(OrgStructureService::class);
        $seat = $service->addTopPosition('المدير العام', null, 1);
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo('structure.view');

        $html = Livewire::actingAs($viewer)->test(OrgTreeIndex::class)
            ->call('openDrawer', $seat->id)
            ->assertSee('org-drawer')
            ->assertSeeHtml('x-teleport="body"')
            ->html();

        $this->assertStringContainsString('org-drawer-overlay', $html);
        $this->assertStringContainsString('z-index: 1300', $html);
        $this->assertStringContainsString('z-index: 1301', $html);
        $this->assertStringContainsString('left: 0', $html);
        $this->assertStringContainsString('max-height: 85vh', $html);
        $this->assertStringNotContainsString('inset-inline-start: 0', $html);
        $this->assertStringNotContainsString('z-index: 30', $html);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function assertAccentsAvoidNavyAndGold(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node['type'] === 'admin') {
                $this->assertNotContains($node['accent'], ['#0F3446', '#C4A052']);
                $this->assertContains($node['accent'], OrgChartService::PALETTE);
            }
            $this->assertAccentsAvoidNavyAndGold($node['children']);
        }
    }
}
