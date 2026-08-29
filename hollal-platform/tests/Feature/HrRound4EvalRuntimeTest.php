<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Users\EmployeeProfileShow;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationEditLog;
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

/** HR Round 4 batch 2ب — approve / amend / close / role UIs. */
class HrRound4EvalRuntimeTest extends TestCase
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
     * @return array{service: QuarterlyEvaluationService, cycle: EvaluationCycle, manager: User, employee: User, evaluation: EmployeeEvaluation}
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

        $template = $service->createTemplate('قالب تشغيل', [
            ['section' => 'مدير', 'question_text' => 'جودة العمل', 'weight' => 50, 'sort_order' => 1],
            ['section' => 'موارد', 'question_text' => 'الالتزام بالسياسات', 'weight' => 50, 'sort_order' => 2],
        ]);
        $cycle = $service->createCycle(2026, 1, $template, '2026-01-01', '2026-03-31');
        $service->openCycle($cycle);
        $service->bulkOpen($cycle->fresh());

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

    public function test_period_label_is_arabic(): void
    {
        $ctx = $this->seededOpenEvaluation();
        $this->assertSame('الربع 1 / 2026', $ctx['cycle']->periodLabel());
    }

    public function test_manager_sees_team_without_total_or_hr_scores(): void
    {
        $ctx = $this->seededOpenEvaluation();
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $ctx['service']->recordScore($ctx['evaluation'], $items[0], 4, 'جيد');

        Livewire::actingAs($ctx['manager'])
            ->test(EvaluationsIndex::class)
            ->assertSee('موظف ربعى', false)
            ->assertSee('مكتمل', false)
            ->assertDontSee('الالتزام بالسياسات', false)
            ->call('openScoring', $ctx['evaluation']->id)
            ->assertSee('جودة العمل', false)
            ->assertDontSee('الالتزام بالسياسات', false);

        $html = Livewire::actingAs($ctx['manager'])->test(EvaluationsIndex::class)->html();
        $this->assertStringNotContainsString('المجموع:', $html);
    }

    public function test_approve_is_visible_to_employee_immediately(): void
    {
        Notification::fake();
        $ctx = $this->seededOpenEvaluation();
        $ctx['employee']->givePermissionTo('hr.employees.view');
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $ctx['service']->recordScore($ctx['evaluation'], $items[0], 5);
        $ctx['service']->recordScore($ctx['evaluation']->fresh(), $items[1], 4);

        $ctx['service']->approveAll($ctx['cycle']->fresh(), $ctx['hr']);

        $this->assertSame(EmployeeEvaluation::STATUS_APPROVED, $ctx['evaluation']->fresh()->status);
        Notification::assertSentTo($ctx['employee'], EvaluationApproved::class);

        Livewire::actingAs($ctx['employee'])
            ->test(EmployeeProfileShow::class, ['user' => $ctx['employee']])
            ->set('activeTab', 'log')
            ->assertSee('الربع 1 / 2026', false)
            ->assertDontSee('تعليقك', false);
    }

    public function test_amend_after_approval_requires_reason_and_logs(): void
    {
        $ctx = $this->seededOpenEvaluation();
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $ctx['service']->recordScore($ctx['evaluation'], $items[0], 3);
        $ctx['service']->recordScore($ctx['evaluation']->fresh(), $items[1], 3);
        $ctx['service']->approve($ctx['evaluation']->fresh(), $ctx['hr']);

        $this->expectException(\InvalidArgumentException::class);
        $ctx['service']->amendAfterApproval(
            $ctx['evaluation']->fresh(),
            [$items[1]->id => ['score' => 5, 'note' => '']],
            '   ',
            $ctx['hr'],
        );
    }

    public function test_amend_after_approval_persists_cumulative_log(): void
    {
        $ctx = $this->seededOpenEvaluation();
        $items = $ctx['cycle']->fresh()->items()->orderBy('sort_order')->get();
        $ctx['service']->recordScore($ctx['evaluation'], $items[0], 3);
        $ctx['service']->recordScore($ctx['evaluation']->fresh(), $items[1], 3);
        $ctx['service']->approve($ctx['evaluation']->fresh(), $ctx['hr']);

        Livewire::actingAs($ctx['hr'])
            ->test(EvaluationsIndex::class)
            ->call('openScoring', $ctx['evaluation']->id)
            ->set('scoreInputs.'.$items[1]->id.'.score', '5')
            ->set('amendReason', 'تصحيح بعد مراجعة السياسات')
            ->call('saveHrScores')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_evaluation_edit_logs', [
            'employee_evaluation_id' => $ctx['evaluation']->id,
            'reason' => 'تصحيح بعد مراجعة السياسات',
        ]);
        $this->assertSame(1, EmployeeEvaluationEditLog::query()
            ->where('employee_evaluation_id', $ctx['evaluation']->id)
            ->count());
        $this->assertSame(5, (int) $ctx['evaluation']->fresh()->scores()
            ->where('evaluation_cycle_item_id', $items[1]->id)
            ->value('score'));
    }

    public function test_close_cycle_archives_and_zeros_unapproved(): void
    {
        Notification::fake();
        $ctx = $this->seededOpenEvaluation();

        $closed = $ctx['service']->closeCycle($ctx['cycle']->fresh(), $ctx['hr']);
        $this->assertSame(EvaluationCycle::STATUS_CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);

        $evaluation = $ctx['evaluation']->fresh();
        $this->assertSame(EmployeeEvaluation::STATUS_ARCHIVED, $evaluation->status);
        $this->assertSame(0.0, (float) $evaluation->total_score);
        $this->assertNotNull($evaluation->archived_at);
        Notification::assertSentTo($ctx['employee'], EvaluationApproved::class);

        $ctx['employee']->givePermissionTo('hr.employees.view');

        Livewire::actingAs($ctx['employee'])
            ->test(EmployeeProfileShow::class, ['user' => $ctx['employee']])
            ->call('setTab', 'log')
            ->assertSee('مؤرشف', false)
            ->assertSee('الربع 1 / 2026', false);
    }

    public function test_evaluations_index_is_hr_board_for_open_cycle(): void
    {
        $ctx = $this->seededOpenEvaluation();

        Livewire::actingAs($ctx['hr'])
            ->test(EvaluationsIndex::class)
            ->set('step', 'score')
            ->assertSee('الربع 1 / 2026', false)
            ->assertSee('موظف ربعى', false)
            ->set('step', 'close')
            ->assertSee('إغلاق الدورة', false);

        $this->actingAs($ctx['hr'])->get(route('evaluations.index'))->assertOk();
        $this->actingAs($ctx['manager'])->get(route('evaluations.index'))->assertOk();
        $this->actingAs($ctx['employee'])->get(route('employee-evaluations.mine'))
            ->assertRedirect();
    }
}
