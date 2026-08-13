<?php

namespace App\Livewire\Hr;

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Services\PayrollRunService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 01-B3 + HR-6 — payroll runs: generate, item detail, HR edit with reason,
 * finance accept/reject with reason.
 */
class PayrollRunsIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $month = '';

    public string $statusFilter = '';

    public string $monthFilter = '';

    public ?int $viewingRunId = null;

    public string $variableLabel = '';

    public string $variableReason = '';

    public string $variableAmount = '';

    public string $variableKind = 'deduction';

    public ?int $variableItemId = null;

    public string $returnNote = '';

    public ?int $open = null;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'monthFilter' => ['except' => ''],
        'open' => ['except' => null],
    ];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMonthFilter(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('hr.salaries.view');
        $this->month = now()->format('Y-m');

        if ($this->open) {
            $this->openRun($this->open);
        }
    }

    public function generate(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        if (PayrollRun::where('month', $this->month)->exists()) {
            throw ValidationException::withMessages(['month' => 'يوجد مسيّر لهذا الشهر بالفعل.']);
        }

        app(PayrollRunService::class)->generate($this->month);

        $this->dispatch('toast', type: 'success', message: 'تم توليد مسيّر رواتب '.$this->month);
    }

    public function openRun(int $runId): void
    {
        $this->authorize('hr.salaries.view');
        $this->viewingRunId = $runId;
        $this->reset(['variableLabel', 'variableReason', 'variableAmount', 'variableItemId', 'returnNote']);
    }

    public function closeRun(): void
    {
        $this->viewingRunId = null;
    }

    public function addVariable(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'variableItemId' => 'required|exists:payroll_run_items,id',
            'variableLabel' => 'required|string|max:255',
            'variableReason' => 'required|string|max:500',
            'variableAmount' => 'required|numeric|min:0.01',
            'variableKind' => 'required|in:addition,deduction',
        ]);

        $item = PayrollRunItem::findOrFail($this->variableItemId);

        try {
            app(PayrollRunService::class)->addVariable(
                $item,
                $this->variableLabel,
                $this->variableReason,
                (float) $this->variableAmount,
                $this->variableKind,
            );
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->reset(['variableLabel', 'variableReason', 'variableAmount']);
        $this->dispatch('toast', type: 'success', message: 'أُضيف البند المتغير');
    }

    public function submit(int $runId): void
    {
        $this->authorize('hr.salaries.manage');

        $run = PayrollRun::findOrFail($runId);

        try {
            app(PayrollRunService::class)->submitToFinance($run, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم رفع المسيّر للمالية');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function financeApprove(int $runId): void
    {
        $this->authorize('finance.payroll.approve');

        try {
            app(PayrollRunService::class)->financeApprove(PayrollRun::findOrFail($runId), auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم الاعتماد المالي — يمكن الآن تنفيذ الصرف');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function financeReject(int $runId): void
    {
        $this->authorize('finance.payroll.approve');

        $this->validate([
            'returnNote' => 'required|string|max:1000',
        ]);

        try {
            app(PayrollRunService::class)->returnForCorrection(PayrollRun::findOrFail($runId), $this->returnNote);
            $this->dispatch('toast', type: 'success', message: 'أُعيد المسيّر للموارد بسبب: '.$this->returnNote);
            $this->returnNote = '';
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        $viewingRun = $this->viewingRunId
            ? PayrollRun::with(['items.employee:id,name'])->find($this->viewingRunId)
            : null;

        return view('livewire.hr.payroll-runs-index', [
            'runs' => PayrollRun::withCount('items')
                ->withSum('items', 'net')
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->monthFilter, fn ($q) => $q->where('month', $this->monthFilter))
                ->latest('month')
                ->paginate(10),
            'statusOptions' => [
                PayrollRun::STATUS_DRAFT,
                PayrollRun::STATUS_SUBMITTED,
                PayrollRun::STATUS_EXECUTED,
                PayrollRun::STATUS_RETURNED,
            ],
            'viewingRun' => $viewingRun,
        ])->layout('layouts.app', ['title' => 'مسيّرات الرواتب']);
    }
}
