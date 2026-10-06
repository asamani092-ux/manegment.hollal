<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\User;
use App\Services\OrgChartService;
use App\Services\OrgStructureService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertNotSame([], $tree);
        $this->assertSame('top', $tree[0]['type']);
    }
}
