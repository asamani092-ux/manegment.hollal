<?php

namespace App\Livewire\Hr;

use App\Models\EvaluationCycle;
use App\Models\EvaluationTemplate;
use App\Services\QuarterlyEvaluationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * HR Round 4 batch 2أ — evaluation cycles: create, open with snapshot, bulk open.
 */
class EvaluationCyclesIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public bool $showCreate = false;

    public int $year;

    public int $quarter = 1;

    public ?int $evaluation_template_id = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->year = (int) now()->year;
        $this->quarter = (int) ceil(now()->month / 3);
        $this->applyQuarterDates();
    }

    public function updatedQuarter(): void
    {
        $this->applyQuarterDates();
    }

    public function updatedYear(): void
    {
        $this->applyQuarterDates();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->resetValidation();
        $this->year = (int) now()->year;
        $this->quarter = (int) ceil(now()->month / 3);
        $this->evaluation_template_id = EvaluationTemplate::query()->where('is_active', true)->orderBy('name')->value('id');
        $this->applyQuarterDates();
        $this->showCreate = true;
    }

    public function createCycle(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'quarter' => 'required|integer|min:1|max:4',
            'evaluation_template_id' => 'required|exists:evaluation_templates,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
        ], [
            'evaluation_template_id.required' => 'اختر قالب التقييم.',
            'ends_at.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي البداية.',
        ]);

        try {
            app(QuarterlyEvaluationService::class)->createCycle(
                $this->year,
                $this->quarter,
                EvaluationTemplate::findOrFail($this->evaluation_template_id),
                $this->starts_at,
                $this->ends_at,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addError('quarter', $e->getMessage());

            return;
        }

        $this->showCreate = false;
        $this->dispatch('toast', type: 'success', message: 'أُنشئت دورة التقييم (مسودة)');
    }

    public function openCycle(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        try {
            app(QuarterlyEvaluationService::class)->openCycle(EvaluationCycle::findOrFail($id));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'فُتحت الدورة ولُقطت بنود القالب');
    }

    public function bulkOpen(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        try {
            $created = app(QuarterlyEvaluationService::class)->bulkOpen(EvaluationCycle::findOrFail($id));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: "فُتح تقييم لـ {$created} موظفاً مؤهلاً");
    }

    public function render(): View
    {
        $cycles = EvaluationCycle::query()
            ->with(['template:id,name'])
            ->withCount(['items', 'employeeEvaluations'])
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->paginate(15);

        return view('livewire.hr.evaluation-cycles-index', [
            'cycles' => $cycles,
            'templates' => EvaluationTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    private function applyQuarterDates(): void
    {
        $q = max(1, min(4, (int) $this->quarter));
        $y = (int) $this->year;
        $startMonth = (($q - 1) * 3) + 1;
        $this->starts_at = Carbon::create($y, $startMonth, 1)->toDateString();
        $this->ends_at = Carbon::create($y, $startMonth, 1)->addMonths(3)->subDay()->toDateString();
    }
}
