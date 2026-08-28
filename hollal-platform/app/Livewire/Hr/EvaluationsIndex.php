<?php

namespace App\Livewire\Hr;

use App\Models\EmployeeEvaluation;
use App\Models\EvaluationCycle;
use App\Models\EvaluationTemplateItem;
use App\Models\User;
use App\Services\QuarterlyEvaluationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * HR Round 4 batch 2ب — HR board for the current open quarterly cycle.
 * Replaces the legacy PeriodicEvaluation screen at /evaluations.
 */
class EvaluationsIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public string $statusFilter = '';

    public string $search = '';

    public ?int $scoringId = null;

    public string $amendReason = '';

    /** @var array<int, array{score: string, note: string}> */
    public array $scoreInputs = [];

    public bool $showReports = false;

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.view'), 403);
    }

    public function openScoring(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $evaluation = EmployeeEvaluation::with(['employee:id,name', 'cycle.items', 'scores', 'editLogs.user:id,name'])
            ->findOrFail($id);

        $this->scoringId = $id;
        $this->amendReason = '';
        $this->showReports = false;
        $this->scoreInputs = [];
        foreach ($evaluation->cycle?->items ?? [] as $item) {
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
        $this->amendReason = '';
        $this->showReports = false;
        $this->scoreInputs = [];
    }

    public function saveHrScores(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $evaluation = EmployeeEvaluation::findOrFail($this->scoringId);

        try {
            if ($evaluation->isApproved()) {
                $this->validate([
                    'amendReason' => 'required|string|min:3|max:1000',
                ], [
                    'amendReason.required' => 'سبب التعديل إلزامي بعد الاعتماد.',
                ]);
                app(QuarterlyEvaluationService::class)->amendAfterApproval(
                    $evaluation,
                    $this->scoreInputs,
                    $this->amendReason,
                    auth()->user(),
                );
                $this->amendReason = '';
                $this->dispatch('toast', type: 'success', message: 'عُدّل التقييم وسُجّل السبب');
            } else {
                app(QuarterlyEvaluationService::class)->recordSectionScores(
                    $evaluation,
                    EvaluationTemplateItem::SECTION_HR,
                    $this->scoreInputs,
                );
                $this->dispatch('toast', type: 'success', message: 'حُفظت درجات قسم الموارد');
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->openScoring($evaluation->id);
    }

    public function approve(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        try {
            app(QuarterlyEvaluationService::class)->approve(
                EmployeeEvaluation::findOrFail($id),
                auth()->user(),
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'اعتمد التقييم — يظهر للموظف فوراً');
        if ($this->scoringId === $id) {
            $this->openScoring($id);
        }
    }

    public function closeCycle(int $cycleId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        try {
            app(QuarterlyEvaluationService::class)->closeCycle(
                EvaluationCycle::findOrFail($cycleId),
                auth()->user(),
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->scoringId = null;
        $this->dispatch('toast', type: 'success', message: 'أُغلقت الدورة وأُرشفت كل التقييمات');
    }

    public function toggleReports(): void
    {
        $this->showReports = ! $this->showReports;
    }

    public function render(): View
    {
        $service = app(QuarterlyEvaluationService::class);
        $cycle = $service->currentOpenCycle();

        $rows = collect();
        if ($cycle) {
            $query = EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $cycle->id)
                ->with(['employee:id,name', 'evaluator:id,name', 'scores'])
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->search !== '', function ($q) {
                    $q->whereHas('employee', fn ($e) => $e->where('name', 'like', '%'.$this->search.'%'));
                })
                ->orderBy('id');

            $rows = $query->paginate(15);
            $cycle->load('items');
        }

        $scoringEvaluation = $this->scoringId
            ? EmployeeEvaluation::with([
                'employee:id,name',
                'cycle.items',
                'scores.cycleItem',
                'editLogs.user:id,name',
                'approver:id,name',
            ])->find($this->scoringId)
            : null;

        $reports = $scoringEvaluation
            ? $service->referenceReports($scoringEvaluation)
            : ['attendance' => collect(), 'tasks' => collect()];

        $managerComplete = $scoringEvaluation
            ? $service->sectionCompletionLabel($scoringEvaluation, EvaluationTemplateItem::SECTION_MANAGER)
            : null;
        $hrComplete = $scoringEvaluation
            ? $service->sectionCompletionLabel($scoringEvaluation, EvaluationTemplateItem::SECTION_HR)
            : null;

        return view('livewire.hr.evaluations-index', [
            'cycle' => $cycle,
            'rows' => $rows,
            'canManage' => auth()->user()->can('hr.employees.update'),
            'scoringEvaluation' => $scoringEvaluation,
            'hrItems' => $scoringEvaluation?->cycle?->items->where('section', EvaluationTemplateItem::SECTION_HR) ?? collect(),
            'managerItems' => $scoringEvaluation?->cycle?->items->where('section', EvaluationTemplateItem::SECTION_MANAGER) ?? collect(),
            'attendanceRows' => $reports['attendance'],
            'taskRows' => $reports['tasks'],
            'managerComplete' => $managerComplete,
            'hrComplete' => $hrComplete,
            'service' => $service,
        ]);
    }
}
