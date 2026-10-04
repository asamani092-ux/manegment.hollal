<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationsIndex;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeProfile;
use App\Models\EvaluationCycle;
use App\Models\User;
use App\Notifications\EvaluationApproved;
use App\Services\QuarterlyEvaluationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** HR Round 5 batch ب — unified evaluations wizard + bulk approve + HR proxy. */
class HrRound5EvalWizardTest extends TestCase
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

    /**
     * @return array{service: QuarterlyEvaluationService, cycle: EvaluationCycle, manager: User, employee: User, evaluation: EmployeeEvaluation, hr: User}
     */
    private function seededOpenEvaluation(): array
    {
        $service = app(QuarterlyEvaluationService::class);
        $hr = $this->hrAdmin();
        $manager = User::factory()->create([
            'name' => 'مدير الفريق',
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
        ]);
        $manager->givePermissionTo('dashboard.view');

        $employee = User::factory()->create([
            'name' => 'موظف ربعى',
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'manager_id' => $manager->id,
        ]);
        $employee->givePermissionTo('dashboard.view');
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'دوام_كامل',
        ]);

        $template = $service->createTemplate('قالب جولة5ب', [
            ['section' => 'مدير', 'question_text' => 'جودة العمل', 'weight' => 50, 'sort_order' => 1],
            ['section' => 'موارد', 'question_text' => 'الالتزام بالسياسات', 'weight' => 50, 'sort_order' => 2],
        ]);
        $cycle = $service->createCycle(2026, 2, $template, '2026-04-01', '2026-06-30');
        $service->openCycle($cycle);
        $service->bulkOpen($cycle->fresh());

        // Keep a single-employee cycle for focused approve/score assertions.
        EmployeeEvaluation::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->where('employee_id', '!=', $employee->id)
            ->delete();

        $evaluation = EmployeeEvaluation::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return compact('service', 'cycle', 'manager', 'employee', 'evaluation') + ['hr' => $hr];
    }

    public function test_approve_all_blocked_until_all_scores_complete(): void
    {
        $ctx = $this->seededOpenEvaluation();
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $ctx['service']->recordScore($ctx['evaluation'], $items[0], 4);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن الاعتماد الجماعي — لم تكتمل درجات كل الموظفين لكل البنود.');
        $ctx['service']->approveAll($ctx['cycle']->fresh(), $ctx['hr']);
    }

    public function test_approve_all_succeeds_when_complete(): void
    {
        Notification::fake();
        $ctx = $this->seededOpenEvaluation();
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $ctx['service']->recordScore($ctx['evaluation'], $items[0], 5);
        $ctx['service']->recordScore($ctx['evaluation']->fresh(), $items[1], 4);

        $count = $ctx['service']->approveAll($ctx['cycle']->fresh(), $ctx['hr']);
        $this->assertSame(1, $count);
        $this->assertSame(EmployeeEvaluation::STATUS_APPROVED, $ctx['evaluation']->fresh()->status);
        Notification::assertSentTo($ctx['employee'], EvaluationApproved::class);

        // Second approveAll is a no-op (already approved) — confirm modal still runs cleanly.
        Livewire::actingAs($ctx['hr'])
            ->test(EvaluationsIndex::class)
            ->set('step', 'approve')
            ->call('askConfirm', 'approve_all', $ctx['cycle']->id)
            ->assertSet('confirmAction', 'approve_all')
            ->call('cancelConfirm')
            ->assertSet('confirmAction', null);
    }

    public function test_hr_can_proxy_fill_manager_section_with_scored_by(): void
    {
        $ctx = $this->seededOpenEvaluation();
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $managerItem = $items->firstWhere('section', 'مدير');

        Livewire::actingAs($ctx['hr'])
            ->test(EvaluationsIndex::class)
            ->set('step', 'score')
            ->call('openScoring', $ctx['evaluation']->id)
            ->set('scoreInputs.'.$managerItem->id.'.score', '3')
            ->set('scoreInputs.'.$managerItem->id.'.note', 'نيابة موارد')
            ->call('saveScores')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_evaluation_scores', [
            'employee_evaluation_id' => $ctx['evaluation']->id,
            'evaluation_cycle_item_id' => $managerItem->id,
            'score' => 3,
            'scored_by' => $ctx['hr']->id,
        ]);
    }

    public function test_manager_sees_team_without_total_on_unified_screen(): void
    {
        $ctx = $this->seededOpenEvaluation();
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $ctx['service']->recordScore($ctx['evaluation'], $items[0], 4, 'جيد', $ctx['manager']);

        $html = Livewire::actingAs($ctx['manager'])
            ->test(EvaluationsIndex::class)
            ->assertSee('موظف ربعى', false)
            ->assertDontSee('الالتزام بالسياسات', false)
            ->html();

        $this->assertStringNotContainsString('المجموع', $html);
    }

    public function test_extra_eval_nav_routes_are_hidden(): void
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

    public function test_legacy_eval_routes_redirect_to_wizard(): void
    {
        $hr = $this->hrAdmin();

        $this->actingAs($hr)
            ->get(route('evaluation-templates.index'))
            ->assertRedirect(route('evaluations.index', ['step' => 'template']));

        $this->actingAs($hr)
            ->get(route('evaluation-cycles.index'))
            ->assertRedirect(route('evaluations.index', ['step' => 'cycle']));

        $this->actingAs($hr)
            ->get(route('employee-evaluations.team'))
            ->assertRedirect(route('evaluations.index', ['step' => 'score']));
    }

    public function test_open_cycle_without_bulk_open_shows_clear_message(): void
    {
        $service = app(QuarterlyEvaluationService::class);
        $hr = $this->hrAdmin();
        $template = $service->createTemplate('قالب فارغ صفوف', [
            ['section' => 'مدير', 'question_text' => 'س1', 'weight' => 50, 'sort_order' => 1],
            ['section' => 'موارد', 'question_text' => 'س2', 'weight' => 50, 'sort_order' => 2],
        ]);
        $cycle = $service->createCycle(2026, 4, $template, '2026-10-01', '2026-12-31');
        $service->openCycle($cycle);

        Livewire::actingAs($hr)
            ->test(EvaluationsIndex::class)
            ->set('step', 'score')
            ->assertSee('لم يُنفَّذ الفتح الجماعي', false);
    }

    public function test_individual_approve_not_exposed_on_wizard(): void
    {
        $ctx = $this->seededOpenEvaluation();

        $html = Livewire::actingAs($ctx['hr'])
            ->test(EvaluationsIndex::class)
            ->set('step', 'score')
            ->html();

        $this->assertStringNotContainsString('wire:click="approve(', $html);
        $this->assertStringNotContainsString('wire:confirm', $html);
    }
}
