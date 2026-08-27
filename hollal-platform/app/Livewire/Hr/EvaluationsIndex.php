<?php

namespace App\Livewire\Hr;

use App\Models\PeriodicEvaluation;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Periodic evaluations index + create/publish.
 * Time: O(n) | Space: O(page).
 */
class EvaluationsIndex extends Component
{
    use WithPagination;

    public bool $showCreate = false;

    public bool $showBulkCreate = false;

    public ?int $employee_id = null;

    /** @var list<int> */
    public array $bulkEmployeeIds = [];

    /** Shared criteria lines for bulk evaluation (one per line). */
    public string $bulkCriteria = '';

    public string $period = '';

    public string $statusFilter = '';

    public string $periodFilter = '';

    public string $search = '';

    public ?int $scoringId = null;

    public ?int $previewId = null;

    /** @var array<int, array{score: string, note: string}> */
    public array $scoreInputs = [];

    public string $employeeComment = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'periodFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPeriodFilter(): void
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
        $this->period = now()->format('Y').'-Q'.ceil(now()->month / 3);
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->showCreate = true;
        $this->showBulkCreate = false;
    }

    public function openBulkCreate(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->showBulkCreate = true;
        $this->showCreate = false;
        $this->bulkEmployeeIds = [];
        $this->bulkCriteria = "جودة العمل\nالالتزام بالمواعيد\nالتعاون مع الفريق\nالمبادرة والتطوير";
    }

    public function createEvaluation(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'employee_id' => 'required|exists:users,id',
            'period' => 'required|string|max:20|regex:/^\d{4}-Q[1-4]$/',
        ], [
            'period.regex' => 'صيغة الفترة يجب أن تكون مثل 2026-Q3.',
        ]);

        $exists = PeriodicEvaluation::query()
            ->where('employee_id', $this->employee_id)
            ->where('period', $this->period)
            ->exists();

        if ($exists) {
            $this->addError('period', 'يوجد تقييم لهذا الموظف في الفترة نفسها.');

            return;
        }

        app(EvaluationService::class)->create(
            User::findOrFail($this->employee_id),
            $this->period,
            auth()->user()
        );

        $this->showCreate = false;
        $this->dispatch('toast', type: 'success', message: 'تم إنشاء التقييم');
    }

    /**
     * Create evaluations for many employees with shared criteria as responsibilities if missing.
     * Time: O(e × c) | Space: O(e)
     */
    public function createBulkEvaluations(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'period' => 'required|string|max:20|regex:/^\d{4}-Q[1-4]$/',
            'bulkEmployeeIds' => 'required|array|min:1',
            'bulkEmployeeIds.*' => 'integer|exists:users,id',
            'bulkCriteria' => 'required|string|min:3|max:4000',
        ], [
            'period.regex' => 'صيغة الفترة يجب أن تكون مثل 2026-Q3.',
            'bulkEmployeeIds.required' => 'اختر موظفاً واحداً على الأقل.',
        ]);

        $criteria = collect(preg_split('/\r\n|\r|\n/', $this->bulkCriteria) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();

        if ($criteria === []) {
            $this->addError('bulkCriteria', 'أدخل بنداً واحداً على الأقل.');

            return;
        }

        $created = app(EvaluationService::class)->createBulk(
            $this->bulkEmployeeIds,
            $this->period,
            auth()->user(),
            $criteria,
        );

        $this->showBulkCreate = false;
        $this->dispatch('toast', type: 'success', message: "أُنشئ {$created} تقييماً جماعياً");
    }

    public function publish(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $evaluation = PeriodicEvaluation::findOrFail($id);
        app(EvaluationService::class)->publish($evaluation);
        $this->previewId = null;
        $this->dispatch('toast', type: 'success', message: 'أُظهر التقييم للموظف (خيار اختياري) — الدورة الافتراضية تبقى داخلية للموارد');
    }

    public function archive(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $evaluation = PeriodicEvaluation::findOrFail($id);
        app(EvaluationService::class)->archive($evaluation);
        $this->scoringId = null;
        $this->previewId = null;
        $this->dispatch('toast', type: 'success', message: 'أُرشف التقييم — يظهر في سجل الملف الوظيفي');
    }

    public function openPreview(int $id): void
    {
        $evaluation = PeriodicEvaluation::with(['employee:id,name', 'evaluator:id,name', 'scores'])->findOrFail($id);
        abort_unless(
            auth()->user()->can('hr.employees.view')
            || auth()->user()->can('hr.employees.update')
            || $evaluation->evaluator_id === auth()->id(),
            403
        );
        $this->previewId = $id;
        $this->scoringId = null;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function openScoring(int $id): void
    {
        $evaluation = PeriodicEvaluation::with('employee')->findOrFail($id);
        abort_unless(
            auth()->user()->can('hr.employees.view')
            || auth()->user()->can('hr.employees.update')
            || $evaluation->evaluator_id === auth()->id()
            || $evaluation->employee_id === auth()->id(),
            403
        );

        $this->scoringId = $id;
        $this->employeeComment = (string) ($evaluation->employee_comment ?? '');
        $responsibilities = \App\Models\Responsibility::query()
            ->where('employee_id', $evaluation->employee_id)
            ->active()
            ->orderBy('order')
            ->get();
        $existing = $evaluation->scores()->get()->keyBy('responsibility_id');
        $this->scoreInputs = [];
        foreach ($responsibilities as $item) {
            $this->scoreInputs[$item->id] = [
                'score' => (string) ($existing[$item->id]->score ?? ''),
                'note' => (string) ($existing[$item->id]->note ?? ''),
            ];
        }
    }

    public function saveScores(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update') || PeriodicEvaluation::findOrFail($this->scoringId)->evaluator_id === auth()->id(), 403);

        $evaluation = PeriodicEvaluation::findOrFail($this->scoringId);
        $service = app(EvaluationService::class);

        foreach ($this->scoreInputs as $responsibilityId => $input) {
            if ($input['score'] === '') {
                continue;
            }
            $this->validate([
                "scoreInputs.$responsibilityId.score" => 'integer|min:1|max:5',
                "scoreInputs.$responsibilityId.note" => 'nullable|string|max:500',
            ]);
            $service->recordScore(
                $evaluation,
                \App\Models\Responsibility::findOrFail($responsibilityId),
                (int) $input['score'],
                $input['note'] !== '' ? $input['note'] : null,
            );
        }

        $this->dispatch('toast', type: 'success', message: 'حُفظت الدرجات');
    }

    public function saveComment(): void
    {
        $evaluation = PeriodicEvaluation::findOrFail($this->scoringId);
        abort_unless($evaluation->employee_id === auth()->id(), 403);

        $this->validate(['employeeComment' => 'required|string|max:2000']);

        try {
            app(EvaluationService::class)->addEmployeeComment($evaluation, $this->employeeComment);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'سُجّل تعليقك');
    }

    public function render(): View
    {
        // Anyone with hr.employees.view (required in mount) sees the full list.
        // Create / score / publish remain gated by hr.employees.update (or evaluator for scores).
        $query = PeriodicEvaluation::query()
            ->select(['id', 'employee_id', 'period', 'evaluator_id', 'status', 'created_at'])
            ->with(['employee:id,name', 'evaluator:id,name'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->periodFilter, fn ($q) => $q->where('period', $this->periodFilter))
            ->when($this->search, fn ($q) => $q->whereHas(
                'employee',
                fn ($e) => $e->where('name', 'like', '%'.$this->search.'%')
            ))
            ->latest();

        return view('livewire.hr.evaluations-index', [
            'evaluations' => $query->paginate(15),
            'periods' => PeriodicEvaluation::query()->distinct()->orderByDesc('period')->pluck('period'),
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employeeOptions' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'label' => $u->name])
                ->all(),
            'canManage' => auth()->user()->can('hr.employees.update'),
            'scoringEvaluation' => $this->scoringId
                ? PeriodicEvaluation::with(['employee:id,name', 'scores'])->find($this->scoringId)
                : null,
            'scoringResponsibilities' => $this->scoringId
                ? \App\Models\Responsibility::query()
                    ->where('employee_id', PeriodicEvaluation::find($this->scoringId)?->employee_id)
                    ->active()
                    ->orderBy('order')
                    ->get()
                : collect(),
            'previewEvaluation' => $this->previewId
                ? PeriodicEvaluation::with(['employee:id,name', 'evaluator:id,name', 'scores.responsibility'])->find($this->previewId)
                : null,
        ]);
    }
}
