<?php

namespace App\Livewire\Hr;

use App\Models\EmployeeEvaluation;
use App\Models\EvaluationTemplateItem;
use App\Services\QuarterlyEvaluationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * Manager team evaluations — manager section only, no final total / no HR scores.
 */
class TeamEvaluationsIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public ?int $scoringId = null;

    /** @var array<int, array{score: string, note: string}> */
    public array $scoreInputs = [];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function openScoring(int $id): void
    {
        $evaluation = EmployeeEvaluation::with(['employee:id,name,manager_id', 'cycle.items', 'scores'])
            ->findOrFail($id);
        $this->assertCanScore($evaluation);

        $this->scoringId = $id;
        $this->scoreInputs = [];
        foreach ($evaluation->cycle?->items->where('section', EvaluationTemplateItem::SECTION_MANAGER) ?? [] as $item) {
            $existing = $evaluation->scores->firstWhere('evaluation_cycle_item_id', $item->id);
            $this->scoreInputs[$item->id] = [
                'score' => $existing?->score !== null ? (string) $existing->score : '',
                'note' => (string) ($existing?->note ?? ''),
            ];
        }
    }

    public function closeScoring(): void
    {
        $this->scoringId = null;
        $this->scoreInputs = [];
    }

    public function saveManagerScores(): void
    {
        $evaluation = EmployeeEvaluation::with('employee')->findOrFail($this->scoringId);
        $this->assertCanScore($evaluation);

        if (! $evaluation->isEditableByScorers()) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكن التعديل بعد الاعتماد');

            return;
        }

        try {
            app(QuarterlyEvaluationService::class)->recordSectionScores(
                $evaluation,
                EvaluationTemplateItem::SECTION_MANAGER,
                $this->scoreInputs,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'حُفظت بنود قسم المدير');
        $this->openScoring($evaluation->id);
    }

    public function render(): View
    {
        $managerId = auth()->id();
        $cycle = app(QuarterlyEvaluationService::class)->currentOpenCycle();

        $rows = EmployeeEvaluation::query()
            ->when($cycle, fn ($q) => $q->where('evaluation_cycle_id', $cycle->id), fn ($q) => $q->whereRaw('0=1'))
            ->where(function ($q) use ($managerId) {
                $q->where('evaluator_id', $managerId)
                    ->orWhereHas('employee', fn ($e) => $e->where('manager_id', $managerId));
            })
            ->with(['employee:id,name', 'scores', 'cycle.items'])
            ->orderBy('id')
            ->paginate(15);

        $scoringEvaluation = $this->scoringId
            ? EmployeeEvaluation::with(['employee:id,name', 'cycle.items', 'scores'])
                ->find($this->scoringId)
            : null;

        $service = app(QuarterlyEvaluationService::class);

        return view('livewire.hr.team-evaluations-index', [
            'cycle' => $cycle,
            'rows' => $rows,
            'scoringEvaluation' => $scoringEvaluation,
            'managerItems' => $scoringEvaluation?->cycle?->items
                ->where('section', EvaluationTemplateItem::SECTION_MANAGER) ?? collect(),
            'sectionLabel' => $scoringEvaluation
                ? $service->sectionCompletionLabel($scoringEvaluation, EvaluationTemplateItem::SECTION_MANAGER)
                : null,
            'service' => $service,
        ]);
    }

    private function assertCanScore(EmployeeEvaluation $evaluation): void
    {
        $userId = auth()->id();
        $ok = (int) $evaluation->evaluator_id === (int) $userId
            || (int) $evaluation->employee?->manager_id === (int) $userId
            || auth()->user()->can('hr.employees.update');

        abort_unless($ok, 403);
    }
}
