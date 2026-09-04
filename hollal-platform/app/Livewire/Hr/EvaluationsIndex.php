<?php

namespace App\Livewire\Hr;

use App\Models\EmployeeEvaluation;
use App\Models\EvaluationCycle;
use App\Models\EvaluationTemplate;
use App\Models\EvaluationTemplateItem;
use App\Services\QuarterlyEvaluationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * HR Round 5 batch ب — single /evaluations wizard:
 * قالب → فتح دورة → فتح جماعي → تعبئة → اعتماد جماعي → إغلاق/أرشفة.
 */
class EvaluationsIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public const STEPS = [
        'template',
        'cycle',
        'bulk_open',
        'score',
        'approve',
        'close',
    ];

    public string $step = 'score';

    public string $statusFilter = '';

    public string $search = '';

    public ?int $scoringId = null;

    public string $amendReason = '';

    /** @var array<int, array{score: string, note: string}> */
    public array $scoreInputs = [];

    public bool $showReports = false;

    // ── Template form ──
    public bool $showTemplateForm = false;

    public ?int $editingTemplateId = null;

    public string $templateName = '';

    public bool $templateIsActive = true;

    /** @var list<array{section: string, question_text: string, weight: string, sort_order: string}> */
    public array $templateItems = [];

    public string $templateSearch = '';

    // ── Cycle form ──
    public bool $showCycleForm = false;

    public int $cycleYear;

    public int $cycleQuarter = 1;

    public ?int $cycleTemplateId = null;

    public string $cycleStartsAt = '';

    public string $cycleEndsAt = '';

    // ── DS confirm modal ──
    public ?string $confirmAction = null;

    public ?int $confirmCycleId = null;

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'step' => ['except' => 'score'],
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

    public function updatingTemplateSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();
        $canHr = $user->can('hr.employees.view') || $user->can('hr.employees.update');
        if (! $canHr && ! $this->userIsManager() && ! $user->can('dashboard.view')) {
            abort(403);
        }

        $this->cycleYear = (int) now()->year;
        $this->cycleQuarter = (int) ceil(now()->month / 3);
        $this->applyQuarterDates();

        $requested = request()->query('step', $this->step);
        $this->setStep(is_string($requested) ? $requested : 'score');
    }

    public function setStep(string $step): void
    {
        if (! in_array($step, self::STEPS, true)) {
            $step = 'score';
        }

        if (! $this->canManage() && $step !== 'score') {
            $step = 'score';
        }

        $this->step = $step;
        $this->resetPage();
        $this->closeScoring();
        $this->cancelConfirm();
    }

    // ── Confirm modal ──

    public function askConfirm(string $action, ?int $cycleId = null): void
    {
        abort_unless($this->canManage(), 403);
        if (! in_array($action, ['open_cycle', 'bulk_open', 'approve_all', 'close_cycle'], true)) {
            return;
        }
        $this->confirmAction = $action;
        $this->confirmCycleId = $cycleId;
    }

    public function cancelConfirm(): void
    {
        $this->confirmAction = null;
        $this->confirmCycleId = null;
    }

    public function executeConfirm(): void
    {
        abort_unless($this->canManage(), 403);
        $action = $this->confirmAction;
        $cycleId = $this->confirmCycleId;
        $this->cancelConfirm();

        match ($action) {
            'open_cycle' => $this->openCycle((int) $cycleId),
            'bulk_open' => $this->bulkOpen((int) $cycleId),
            'approve_all' => $this->approveAll((int) $cycleId),
            'close_cycle' => $this->closeCycle((int) $cycleId),
            default => null,
        };
    }

    // ── Templates ──

    public function openTemplateCreate(): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetValidation();
        $this->editingTemplateId = null;
        $this->templateName = '';
        $this->templateIsActive = true;
        $this->templateItems = [
            ['section' => EvaluationTemplateItem::SECTION_MANAGER, 'question_text' => '', 'weight' => '50', 'sort_order' => '1'],
            ['section' => EvaluationTemplateItem::SECTION_HR, 'question_text' => '', 'weight' => '50', 'sort_order' => '2'],
        ];
        $this->showTemplateForm = true;
    }

    public function openTemplateEdit(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetValidation();
        $template = EvaluationTemplate::with('items')->findOrFail($id);
        $this->editingTemplateId = $template->id;
        $this->templateName = $template->name;
        $this->templateIsActive = (bool) $template->is_active;
        $this->templateItems = $template->items->map(fn (EvaluationTemplateItem $item) => [
            'section' => $item->section,
            'question_text' => $item->question_text,
            'weight' => (string) $item->weight,
            'sort_order' => (string) $item->sort_order,
        ])->values()->all();
        if ($this->templateItems === []) {
            $this->templateItems = [
                ['section' => EvaluationTemplateItem::SECTION_MANAGER, 'question_text' => '', 'weight' => '100', 'sort_order' => '1'],
            ];
        }
        $this->showTemplateForm = true;
    }

    public function addTemplateItemRow(): void
    {
        $next = count($this->templateItems) + 1;
        $this->templateItems[] = [
            'section' => EvaluationTemplateItem::SECTION_MANAGER,
            'question_text' => '',
            'weight' => '0',
            'sort_order' => (string) $next,
        ];
    }

    public function removeTemplateItemRow(int $index): void
    {
        unset($this->templateItems[$index]);
        $this->templateItems = array_values($this->templateItems);
    }

    public function saveTemplate(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate([
            'templateName' => 'required|string|min:2|max:120',
            'templateIsActive' => 'boolean',
            'templateItems' => 'required|array|min:1',
            'templateItems.*.section' => 'required|in:مدير,موارد',
            'templateItems.*.question_text' => 'required|string|min:2|max:500',
            'templateItems.*.weight' => 'required|integer|min:1|max:100',
            'templateItems.*.sort_order' => 'nullable|integer|min:1|max:100',
        ], [
            'templateName.required' => 'اسم القالب مطلوب.',
            'templateItems.required' => 'أضف بنداً واحداً على الأقل.',
            'templateItems.*.question_text.required' => 'نص السؤال مطلوب.',
            'templateItems.*.weight.required' => 'الوزن مطلوب.',
        ]);

        $payload = collect($this->templateItems)->map(fn (array $row, int $i) => [
            'section' => $row['section'],
            'question_text' => trim($row['question_text']),
            'weight' => (int) $row['weight'],
            'sort_order' => (int) ($row['sort_order'] ?: ($i + 1)),
        ])->values()->all();

        $service = app(QuarterlyEvaluationService::class);

        try {
            if ($this->editingTemplateId) {
                $service->updateTemplate(
                    EvaluationTemplate::findOrFail($this->editingTemplateId),
                    $this->templateName,
                    $payload,
                    $this->templateIsActive,
                );
                $message = 'تم تحديث قالب التقييم';
            } else {
                $service->createTemplate($this->templateName, $payload, $this->templateIsActive);
                $message = 'تم إنشاء قالب التقييم';
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('templateItems', $e->getMessage());

            return;
        }

        $this->showTemplateForm = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toggleTemplateActive(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $template = EvaluationTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);
        $this->dispatch('toast', type: 'success', message: $template->is_active ? 'فُعّل القالب' : 'أُوقف القالب');
    }

    // ── Cycles ──

    public function openCycleCreate(): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetValidation();
        $this->cycleYear = (int) now()->year;
        $this->cycleQuarter = (int) ceil(now()->month / 3);
        $this->cycleTemplateId = EvaluationTemplate::query()->where('is_active', true)->orderBy('name')->value('id');
        $this->applyQuarterDates();
        $this->showCycleForm = true;
    }

    public function updatedCycleQuarter(): void
    {
        $this->applyQuarterDates();
    }

    public function updatedCycleYear(): void
    {
        $this->applyQuarterDates();
    }

    public function createCycle(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate([
            'cycleYear' => 'required|integer|min:2020|max:2100',
            'cycleQuarter' => 'required|integer|min:1|max:4',
            'cycleTemplateId' => 'required|exists:evaluation_templates,id',
            'cycleStartsAt' => 'required|date',
            'cycleEndsAt' => 'required|date|after_or_equal:cycleStartsAt',
        ], [
            'cycleTemplateId.required' => 'اختر قالب التقييم.',
            'cycleEndsAt.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي البداية.',
        ]);

        try {
            app(QuarterlyEvaluationService::class)->createCycle(
                $this->cycleYear,
                $this->cycleQuarter,
                EvaluationTemplate::findOrFail($this->cycleTemplateId),
                $this->cycleStartsAt,
                $this->cycleEndsAt,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addError('cycleQuarter', $e->getMessage());

            return;
        }

        $this->showCycleForm = false;
        $this->dispatch('toast', type: 'success', message: 'أُنشئت دورة التقييم (مسودة)');
    }

    public function openCycle(int $id): void
    {
        abort_unless($this->canManage(), 403);

        try {
            app(QuarterlyEvaluationService::class)->openCycle(EvaluationCycle::findOrFail($id));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'فُتحت الدورة ولُقطت بنود القالب');
        $this->setStep('bulk_open');
    }

    public function bulkOpen(int $id): void
    {
        abort_unless($this->canManage(), 403);

        try {
            $created = app(QuarterlyEvaluationService::class)->bulkOpen(EvaluationCycle::findOrFail($id));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: "فُتح تقييم لـ {$created} موظفاً مؤهلاً");
        $this->setStep('score');
    }

    public function approveAll(int $cycleId): void
    {
        abort_unless($this->canManage(), 403);

        try {
            $count = app(QuarterlyEvaluationService::class)->approveAll(
                EvaluationCycle::findOrFail($cycleId),
                auth()->user(),
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: "اُعتمد {$count} تقييماً جماعياً — يظهر للموظفين فوراً");
    }

    public function closeCycle(int $cycleId): void
    {
        abort_unless($this->canManage(), 403);

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

    // ── Scoring ──

    public function openScoring(int $id): void
    {
        $evaluation = EmployeeEvaluation::with([
            'employee:id,name,manager_id',
            'cycle.items',
            'scores.scorer:id,name',
            'editLogs.user:id,name',
        ])->findOrFail($id);

        $this->assertCanScore($evaluation);

        $this->scoringId = $id;
        $this->amendReason = '';
        $this->showReports = false;
        $this->scoreInputs = [];

        $items = $this->canManage()
            ? ($evaluation->cycle?->items ?? collect())
            : ($evaluation->cycle?->items->where('section', EvaluationTemplateItem::SECTION_MANAGER) ?? collect());

        foreach ($items as $item) {
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

    public function saveScores(): void
    {
        $evaluation = EmployeeEvaluation::with('employee')->findOrFail($this->scoringId);
        $this->assertCanScore($evaluation);
        $actor = auth()->user();
        $service = app(QuarterlyEvaluationService::class);

        try {
            if ($evaluation->isApproved()) {
                abort_unless($this->canManage(), 403);
                $this->validate([
                    'amendReason' => 'required|string|min:3|max:1000',
                ], [
                    'amendReason.required' => 'سبب التعديل إلزامي بعد الاعتماد.',
                ]);
                $service->amendAfterApproval(
                    $evaluation,
                    $this->scoreInputs,
                    $this->amendReason,
                    $actor,
                );
                $this->amendReason = '';
                $this->dispatch('toast', type: 'success', message: 'عُدّل التقييم وسُجّل السبب');
            } else {
                if (! $evaluation->isEditableByScorers()) {
                    $this->dispatch('toast', type: 'error', message: 'لا يمكن التعديل بعد الاعتماد');

                    return;
                }

                if ($this->canManage()) {
                    $service->recordSectionScores(
                        $evaluation,
                        EvaluationTemplateItem::SECTION_MANAGER,
                        $this->scoreInputs,
                        $actor,
                    );
                    $service->recordSectionScores(
                        $evaluation->fresh(),
                        EvaluationTemplateItem::SECTION_HR,
                        $this->scoreInputs,
                        $actor,
                    );
                    $this->dispatch('toast', type: 'success', message: 'حُفظت الدرجات (بما فيها نيابة قسم المدير إن وُجدت)');
                } else {
                    $service->recordSectionScores(
                        $evaluation,
                        EvaluationTemplateItem::SECTION_MANAGER,
                        $this->scoreInputs,
                        $actor,
                    );
                    $this->dispatch('toast', type: 'success', message: 'حُفظت بنود قسم المدير');
                }
            }
            $this->openScoring((int) $evaluation->id);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    /** @deprecated Individual approve removed — use approveAll via confirm modal. */
    public function saveHrScores(): void
    {
        $this->saveScores();
    }

    public function toggleReports(): void
    {
        $this->showReports = ! $this->showReports;
    }

    public function render(): View
    {
        $service = app(QuarterlyEvaluationService::class);
        $canManage = $this->canManage();
        $canHrView = auth()->user()->can('hr.employees.view') || $canManage;
        $openCycle = $service->currentOpenCycle();

        $templates = collect();
        $cycles = collect();
        $activeTemplates = collect();
        $rows = collect();
        $needsBulkOpen = false;
        $allFullyScored = false;
        $pendingApproveCount = 0;

        if ($canManage && in_array($this->step, ['template'], true)) {
            $templates = EvaluationTemplate::query()
                ->withCount('items')
                ->when($this->templateSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->templateSearch.'%'))
                ->orderByDesc('id')
                ->paginate(15);
        }

        if ($canManage && in_array($this->step, ['cycle', 'bulk_open', 'approve', 'close'], true)) {
            $cycles = EvaluationCycle::query()
                ->with(['template:id,name'])
                ->withCount(['items', 'employeeEvaluations'])
                ->orderByDesc('year')
                ->orderByDesc('quarter')
                ->paginate(15);
            $activeTemplates = EvaluationTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        if (in_array($this->step, ['score', 'approve', 'bulk_open'], true) && $openCycle) {
            $evalCount = EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $openCycle->id)
                ->count();
            $needsBulkOpen = $evalCount === 0;

            $query = EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $openCycle->id)
                ->with(['employee:id,name,manager_id', 'evaluator:id,name', 'scores', 'cycle.items'])
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->search !== '', function ($q) {
                    $q->whereHas('employee', fn ($e) => $e->where('name', 'like', '%'.$this->search.'%'));
                });

            if (! $canHrView) {
                $managerId = auth()->id();
                $query->where(function ($q) use ($managerId) {
                    $q->where('evaluator_id', $managerId)
                        ->orWhereHas('employee', fn ($e) => $e->where('manager_id', $managerId));
                });
            }

            $rows = $query->orderBy('id')->paginate(15);
            $openCycle->load('items');

            if ($canManage && $evalCount > 0) {
                $allEvals = EmployeeEvaluation::query()
                    ->where('evaluation_cycle_id', $openCycle->id)
                    ->with(['cycle.items', 'scores'])
                    ->get();
                $pending = $allEvals->filter(fn (EmployeeEvaluation $e) => ! $e->isApproved() && ! $e->isArchived());
                $pendingApproveCount = $pending->count();
                $allFullyScored = $pending->isEmpty()
                    || $pending->every(fn (EmployeeEvaluation $e) => $service->isEvaluationFullyScored($e));
            }
        }

        $scoringEvaluation = $this->scoringId
            ? EmployeeEvaluation::with([
                'employee:id,name,manager_id',
                'cycle.items',
                'scores.cycleItem',
                'scores.scorer:id,name',
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

        $stepLabels = [
            'template' => 'قالب',
            'cycle' => 'فتح دورة',
            'bulk_open' => 'فتح جماعي',
            'score' => 'تعبئة',
            'approve' => 'اعتماد جماعي',
            'close' => 'إغلاق/أرشفة',
        ];

        $confirmTitles = [
            'open_cycle' => 'فتح الدورة؟',
            'bulk_open' => 'فتح جماعي للتقييمات؟',
            'approve_all' => 'اعتماد جماعي لكل التقييمات؟',
            'close_cycle' => 'إغلاق الدورة وأرشفة؟',
        ];
        $confirmBodies = [
            'open_cycle' => 'ستُلقط بنود القالب في الدورة ولن تتأثر بتعديل القالب لاحقاً.',
            'bulk_open' => 'سيُنشأ تقييم مسودة لكل موظف مؤهل في الدورة المفتوحة.',
            'approve_all' => 'يُعتمد كل تقييم مكتمل الدرجات ويظهر للموظفين فوراً. لا يتم الاعتماد إن نقص أي بند.',
            'close_cycle' => 'التقييمات غير المعتمدة تُعتمد بصفر ثم تُؤرشف الكل وتُغلق الدورة.',
        ];

        return view('livewire.hr.evaluations-index', [
            'cycle' => $openCycle,
            'rows' => $rows,
            'templates' => $templates,
            'cycles' => $cycles,
            'activeTemplates' => $activeTemplates,
            'canManage' => $canManage,
            'canHrView' => $canHrView,
            'showTotals' => $canHrView,
            'needsBulkOpen' => $needsBulkOpen,
            'allFullyScored' => $allFullyScored,
            'pendingApproveCount' => $pendingApproveCount,
            'scoringEvaluation' => $scoringEvaluation,
            'hrItems' => $scoringEvaluation?->cycle?->items->where('section', EvaluationTemplateItem::SECTION_HR) ?? collect(),
            'managerItems' => $scoringEvaluation?->cycle?->items->where('section', EvaluationTemplateItem::SECTION_MANAGER) ?? collect(),
            'attendanceRows' => $reports['attendance'],
            'taskRows' => $reports['tasks'],
            'managerComplete' => $managerComplete,
            'hrComplete' => $hrComplete,
            'service' => $service,
            'stepLabels' => $stepLabels,
            'sections' => EvaluationTemplateItem::SECTIONS,
            'weightsTotal' => collect($this->templateItems)->sum(fn ($r) => (int) ($r['weight'] ?? 0)),
            'confirmTitle' => $confirmTitles[$this->confirmAction] ?? 'تأكيد',
            'confirmBody' => $confirmBodies[$this->confirmAction] ?? '',
        ])->layout('layouts.app', ['title' => 'التقييم الربعي']);
    }

    private function canManage(): bool
    {
        return auth()->user()->can('hr.employees.update');
    }

    private function userIsManager(): bool
    {
        $id = auth()->id();

        return \App\Models\User::query()->where('manager_id', $id)->exists()
            || EmployeeEvaluation::query()->where('evaluator_id', $id)->exists();
    }

    private function assertCanScore(EmployeeEvaluation $evaluation): void
    {
        if ($this->canManage()) {
            return;
        }

        $userId = auth()->id();
        $ok = (int) $evaluation->evaluator_id === (int) $userId
            || (int) $evaluation->employee?->manager_id === (int) $userId;

        abort_unless($ok, 403);
    }

    private function applyQuarterDates(): void
    {
        $q = max(1, min(4, (int) $this->cycleQuarter));
        $y = (int) $this->cycleYear;
        $startMonth = (($q - 1) * 3) + 1;
        $this->cycleStartsAt = Carbon::create($y, $startMonth, 1)->toDateString();
        $this->cycleEndsAt = Carbon::create($y, $startMonth, 1)->addMonths(3)->subDay()->toDateString();
    }
}
