<?php

namespace Tests\Feature;

use App\Livewire\Settings\ApprovalChainsIndex;
use App\Models\ApprovalChain;
use App\Models\ApprovalRequest;
use App\Models\Custody;
use App\Models\User;
use App\Services\Approval\ApprovalChainDeriver;
use App\Services\Approval\ApprovalEngine;
use App\Services\ApprovalChainService;
use App\Services\CustodyService;
use App\Support\ChainThresholdNormalizer;
use Database\Seeders\ApprovalRulesSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * سلسلة واحدة لكل نوع، ومساران مختلفان بالمبلغ داخلها.
 * Time: O(steps) | Space: O(steps)
 */
class ClarityChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_chain_splits_five_hundred_and_fifteen_thousand(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ApprovalRulesSeeder::class);

        $this->assertSame(1, ApprovalChain::query()->where('request_type', 'expense')->count());
        $this->assertSame(count(ApprovalChain::TYPES), ApprovalChain::query()->count());

        $service = app(ApprovalChainService::class);
        $low = $service->stepsFor('expense', 500);
        $high = $service->stepsFor('expense', 15000);

        $this->assertCount(1, $low);
        $this->assertCount(3, $high);
        $this->assertSame($low[0], $high[0]);
        $this->assertNotSame($low, $high);
    }

    public function test_named_employee_approves_and_inflight_keeps_old_steps(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $employee = User::factory()->create(['name' => 'أحمد المعتمد']);
        $employee->assignRole('Employee');
        $requester = User::factory()->create(['name' => 'محمد الطالب', 'manager_id' => $employee->id]);

        ApprovalChain::query()->create(['request_type' => 'custody', 'is_active' => true])
            ->steps()->create([
                'position' => 1,
                'approver_type' => 'user',
                'user_id' => $employee->id,
                'on_unresolved' => 'skip',
                'label_ar' => 'أحمد المعتمد',
            ]);

        $custody = app(CustodyService::class)->request($requester, 100, 'عهدة', null, null, null, $requester);
        app(ApprovalEngine::class)->open('custody', $custody->id, $requester->id, app(ApprovalChainService::class)->stepsFor('custody', 100));

        $other = User::factory()->create(['name' => 'سارة الجديدة']);
        ApprovalChain::query()->where('request_type', 'custody')->first()->steps()->update(['user_id' => $other->id]);

        $open = ApprovalRequest::query()->where('approvable_type', 'custody')->where('approvable_id', $custody->id)->first();
        $this->assertSame('user:'.$employee->id, $open->steps()->first()->definition['legacy_stage']);
        $this->assertSame(Custody::STATUS_APPROVED, app(CustodyService::class)->approve($custody, $employee)->status);

        $next = app(CustodyService::class)->request($requester, 80, 'عهدة لاحقة', null, null, null, $requester);
        $this->assertSame(Custody::STATUS_APPROVED, app(CustodyService::class)->approve($next, $other)->status);
    }

    public function test_preview_changes_when_threshold_changes_and_page_is_arabic(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create(['name' => 'مدير النظام', 'must_change_password' => false]);
        $admin->givePermissionTo('settings.manage');
        $asker = User::factory()->create(['name' => 'محمد الطالب']);

        Livewire::actingAs($admin)
            ->test(ApprovalChainsIndex::class)
            ->call('selectType', 'expense')
            ->call('addStep')
            ->set('steps.0.approver_type', 'user')
            ->call('chooseEmployee', 0, $asker->id)
            ->set('steps.0.condition_operator', 'gt')
            ->set('steps.0.condition_value', '10000')
            ->set('previewRequesterId', $asker->id)
            ->set('previewValue', '500')
            ->assertSee('لا توجد خطوة تنطبق', false)
            ->set('previewValue', '15000')
            ->assertSee('محمد الطالب', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['user:'.$asker->id], app(ApprovalChainService::class)->stepsFor('expense', 15000));
        $this->assertSame([], app(ApprovalChainService::class)->stepsFor('expense', 500));

        $html = Livewire::actingAs($admin)->test(ApprovalChainsIndex::class)->html();
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/si', ' ', $html) ?? $html;
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/si', ' ', $text) ?? $text;
        $text = strip_tags($text);
        preg_match_all('/[a-z_]{4,}/', $text, $found);
        $allowed = ['pdf', 'smtp', 'uat'];
        $leaks = array_values(array_filter($found[0], fn (string $word) => ! in_array(strtolower($word), $allowed, true)));
        $this->assertSame([], $leaks, 'نص ظاهر غير عربي: '.implode(' ', $leaks));
    }

    public function test_deriver_reports_role_conversion(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $holder = User::factory()->create(['name' => 'نورة المالية']);
        $holder->assignRole('Finance');
        $this->seed(ApprovalRulesSeeder::class);
        app(ApprovalChainDeriver::class)->syncAll();

        $step = ApprovalChain::query()->where('request_type', 'expense')->first()
            ->steps()->where('label_ar', 'حاملو المالية')->first();
        $this->assertNotNull($step);
        $this->assertSame([$holder->id], array_map('intval', $step->user_ids));
        $this->assertSame('gt', $step->condition_operator);
        $this->assertEquals(1000, (float) $step->condition_value);
    }

    public function test_fractional_gte_migrates_to_gt_once(): void
    {
        $chain = ApprovalChain::query()->create(['request_type' => 'expense', 'is_active' => true]);
        $chain->steps()->create([
            'position' => 1,
            'approver_type' => 'direct_manager',
            'condition_operator' => 'gte',
            'condition_value' => 1000.01,
            'on_unresolved' => 'skip',
        ]);
        $chain->steps()->create([
            'position' => 2,
            'approver_type' => 'direct_manager',
            'condition_operator' => 'gte',
            'condition_value' => 500,
            'on_unresolved' => 'skip',
        ]);

        ChainThresholdNormalizer::run();
        ChainThresholdNormalizer::run();

        $steps = $chain->steps()->orderBy('position')->get();
        $this->assertSame('gt', $steps[0]->condition_operator);
        $this->assertEquals(1000, (float) $steps[0]->condition_value);
        $this->assertSame('gte', $steps[1]->condition_operator);
        $this->assertEquals(500, (float) $steps[1]->condition_value);
    }

    public function test_preview_names_approver_and_explains_missing_manager(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create(['name' => 'مدير النظام', 'must_change_password' => false]);
        $admin->givePermissionTo('settings.manage');
        $nora = User::factory()->create(['name' => 'نورة المالية']);
        $hidden = User::factory()->create(['name' => 'خالد المخفي']);

        ApprovalChain::query()->create(['request_type' => 'expense', 'is_active' => true])
            ->steps()->createMany([
                [
                    'position' => 1,
                    'approver_type' => 'direct_manager',
                    'on_unresolved' => 'skip',
                    'label_ar' => 'المدير المباشر',
                ],
                [
                    'position' => 2,
                    'approver_type' => 'any_of_users',
                    'user_ids' => [$nora->id],
                    'condition_operator' => 'gt',
                    'condition_value' => 1000,
                    'on_unresolved' => 'skip',
                    'label_ar' => 'نورة المالية',
                ],
            ]);

        Livewire::actingAs($admin)
            ->test(ApprovalChainsIndex::class)
            ->assertSet('previewRequesterId', $admin->id)
            ->assertSet('previewValue', '1001')
            ->assertSee('نورة المالية — إذا تجاوز المبلغ 1,000', false)
            ->assertSee('لا يوجد مدير مباشر لـ مدير النظام — ستُتخطى الخطوة', false)
            ->assertDontSee('أحد الموظفين', false)
            ->assertDontSee('مدير غير محدد', false)
            ->assertDontSeeHtml('wire:click="addEmployee')
            ->set('picker', 'خالد')
            ->assertSeeHtml('addEmployee')
            ->assertSee('خالد المخفي', false)
            ->call('setThreshold', 1, '2,500')
            ->assertSet('steps.1.condition_value', '2500')
            ->assertSee('2,500', false);

        $this->assertNotSame($hidden->id, $nora->id);
    }
}
