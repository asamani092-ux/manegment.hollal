<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationCyclesIndex;
use App\Livewire\Hr\EvaluationTemplatesIndex;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeProfile;
use App\Models\EvaluationCycle;
use App\Models\EvaluationCycleItem;
use App\Models\EvaluationTemplate;
use App\Models\User;
use App\Services\QuarterlyEvaluationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** HR Round 4 batch 2أ — quarterly evaluation engine. */
class HrRound4EvalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function hrAdmin(): User
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    /** @return list<array{section: string, question_text: string, weight: int, sort_order: int}> */
    private function balancedItems(): array
    {
        return [
            ['section' => 'مدير', 'question_text' => 'جودة الإنجاز', 'weight' => 40, 'sort_order' => 1],
            ['section' => 'مدير', 'question_text' => 'الالتزام', 'weight' => 30, 'sort_order' => 2],
            ['section' => 'موارد', 'question_text' => 'الالتزام بالسياسات', 'weight' => 30, 'sort_order' => 3],
        ];
    }

    public function test_template_weights_must_sum_to_100(): void
    {
        $service = app(QuarterlyEvaluationService::class);

        $ok = $service->createTemplate('قالب سليم', $this->balancedItems());
        $this->assertSame(100, $ok->weightsSum());
        $this->assertCount(3, $ok->items);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('مجموع أوزان بنود القالب يجب أن يساوي 100');
        $service->createTemplate('قالب خاطئ', [
            ['section' => 'مدير', 'question_text' => 'بند', 'weight' => 50, 'sort_order' => 1],
        ]);
    }

    public function test_cycle_snapshot_unaffected_by_template_edit(): void
    {
        $service = app(QuarterlyEvaluationService::class);
        $template = $service->createTemplate('قالب لقطة', $this->balancedItems());

        $cycle = $service->createCycle(2026, 1, $template, '2026-01-01', '2026-03-31');
        $service->openCycle($cycle);

        $snapshotBefore = EvaluationCycleItem::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->orderBy('sort_order')
            ->pluck('question_text')
            ->all();

        $this->assertSame(['جودة الإنجاز', 'الالتزام', 'الالتزام بالسياسات'], $snapshotBefore);

        $service->updateTemplate($template, 'قالب لقطة معدّل', [
            ['section' => 'موارد', 'question_text' => 'سؤال جديد كلياً', 'weight' => 100, 'sort_order' => 1],
        ]);

        $snapshotAfter = EvaluationCycleItem::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->orderBy('sort_order')
            ->pluck('question_text')
            ->all();

        $this->assertSame($snapshotBefore, $snapshotAfter);
        $this->assertSame(['سؤال جديد كلياً'], $template->fresh()->items()->pluck('question_text')->all());
    }

    public function test_bulk_open_excludes_frozen_terminated_and_mid_quarter_hire(): void
    {
        $service = app(QuarterlyEvaluationService::class);
        $admin = $this->hrAdmin();
        $template = $service->createTemplate('قالب فتح', $this->balancedItems());
        $cycle = $service->createCycle(2026, 2, $template, '2026-04-01', '2026-06-30');
        $service->openCycle($cycle);

        $eligible = User::factory()->create([
            'name' => 'مؤهل',
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'manager_id' => $admin->id,
        ]);
        EmployeeProfile::create([
            'user_id' => $eligible->id,
            'hire_date' => '2026-03-15',
            'employment_type' => 'دوام_كامل',
        ]);

        $frozen = User::factory()->create([
            'name' => 'مجمد',
            'is_active' => false,
            'employment_status' => User::STATUS_FROZEN,
        ]);
        EmployeeProfile::create([
            'user_id' => $frozen->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'دوام_كامل',
        ]);

        $terminated = User::factory()->create([
            'name' => 'منتهي',
            'is_active' => false,
            'employment_status' => User::STATUS_TERMINATED,
        ]);
        EmployeeProfile::create([
            'user_id' => $terminated->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'دوام_كامل',
        ]);

        $midJoin = User::factory()->create([
            'name' => 'منتصف',
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
        ]);
        EmployeeProfile::create([
            'user_id' => $midJoin->id,
            'hire_date' => '2026-05-10',
            'employment_type' => 'دوام_كامل',
        ]);

        $created = $service->bulkOpen($cycle->fresh());

        $this->assertGreaterThanOrEqual(1, $created);
        $this->assertTrue(
            EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $cycle->id)
                ->where('employee_id', $eligible->id)
                ->exists()
        );
        $this->assertFalse(
            EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $cycle->id)
                ->where('employee_id', $frozen->id)
                ->exists()
        );
        $this->assertFalse(
            EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $cycle->id)
                ->where('employee_id', $terminated->id)
                ->exists()
        );
        $this->assertFalse(
            EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $cycle->id)
                ->where('employee_id', $midJoin->id)
                ->exists()
        );
    }

    public function test_cannot_create_two_cycles_for_same_year_quarter(): void
    {
        $service = app(QuarterlyEvaluationService::class);
        $template = $service->createTemplate('قالب تفرد', $this->balancedItems());
        $service->createCycle(2026, 3, $template, '2026-07-01', '2026-09-30');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('توجد دورة تقييم لنفس السنة والربع');
        $service->createCycle(2026, 3, $template, '2026-07-01', '2026-09-30');
    }

    public function test_weighted_total_from_cycle_item_scores(): void
    {
        $service = app(QuarterlyEvaluationService::class);
        $admin = $this->hrAdmin();
        $template = $service->createTemplate('قالب درجات', [
            ['section' => 'مدير', 'question_text' => 'أ', 'weight' => 60, 'sort_order' => 1],
            ['section' => 'موارد', 'question_text' => 'ب', 'weight' => 40, 'sort_order' => 2],
        ]);
        $cycle = $service->createCycle(2026, 4, $template, '2026-10-01', '2026-12-31');
        $service->openCycle($cycle);

        $employee = User::factory()->create([
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'manager_id' => $admin->id,
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'دوام_كامل',
        ]);

        $service->bulkOpen($cycle->fresh());
        $evaluation = EmployeeEvaluation::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $items = $cycle->fresh()->items()->orderBy('sort_order')->get();
        $service->recordScore($evaluation, $items[0], 5);
        $service->recordScore($evaluation->fresh(), $items[1], 3);

        $evaluation->refresh();
        // (5*60 + 3*40) / 100 = 4.20
        $this->assertSame(4.2, (float) $evaluation->total_score);
    }

    public function test_hr_templates_and_cycles_livewire_screens(): void
    {
        $admin = $this->hrAdmin();

        Livewire::actingAs($admin)
            ->test(EvaluationTemplatesIndex::class)
            ->call('openCreate')
            ->set('name', 'قالب واجهة')
            ->set('items', [
                ['section' => 'مدير', 'question_text' => 'سؤال مدير', 'weight' => '70', 'sort_order' => '1'],
                ['section' => 'موارد', 'question_text' => 'سؤال موارد', 'weight' => '30', 'sort_order' => '2'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluation_templates', ['name' => 'قالب واجهة']);

        $templateId = EvaluationTemplate::query()->where('name', 'قالب واجهة')->value('id');

        Livewire::actingAs($admin)
            ->test(EvaluationCyclesIndex::class)
            ->call('openCreate')
            ->set('year', 2027)
            ->set('quarter', 1)
            ->set('evaluation_template_id', $templateId)
            ->set('starts_at', '2027-01-01')
            ->set('ends_at', '2027-03-31')
            ->call('createCycle')
            ->assertHasNoErrors();

        $cycle = EvaluationCycle::query()->where('year', 2027)->where('quarter', 1)->firstOrFail();
        $this->assertSame(EvaluationCycle::STATUS_DRAFT, $cycle->status);

        Livewire::actingAs($admin)
            ->test(EvaluationCyclesIndex::class)
            ->call('openCycle', $cycle->id)
            ->assertHasNoErrors();

        $this->assertSame(EvaluationCycle::STATUS_OPEN, $cycle->fresh()->status);
        $this->assertSame(2, $cycle->fresh()->items()->count());

        Livewire::actingAs($admin)
            ->test(EvaluationCyclesIndex::class)
            ->call('bulkOpen', $cycle->id)
            ->assertHasNoErrors();

        $this->actingAs($admin)->get(route('evaluation-templates.index'))
            ->assertRedirect(route('evaluations.index', ['step' => 'template']));
        $this->actingAs($admin)->get(route('evaluation-cycles.index'))
            ->assertRedirect(route('evaluations.index', ['step' => 'cycle']));
    }

    public function test_navigation_hides_legacy_eval_routes(): void
    {
        $routes = collect(config('navigation.groups'))
            ->flatMap(fn ($g) => $g['items'] ?? [])
            ->pluck('route');

        $this->assertTrue($routes->contains('evaluations.index'));
        $this->assertFalse($routes->contains('evaluation-templates.index'));
        $this->assertFalse($routes->contains('evaluation-cycles.index'));
        $this->assertFalse($routes->contains('employee-evaluations.team'));
        $this->assertFalse($routes->contains('employee-evaluations.mine'));
    }

    public function test_period_label_arabic_format(): void
    {
        $cycle = new EvaluationCycle(['year' => 2026, 'quarter' => 1]);
        $this->assertSame('الربع 1 / 2026', $cycle->periodLabel());
    }
}
