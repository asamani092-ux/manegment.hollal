<?php

namespace App\Livewire\Hr;

use App\Models\PayrollRun;
use App\Services\PayrollRunService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * 01-B3 — payroll runs: generate for a month, review, submit to finance.
 */
class PayrollRunsIndex extends Component
{
    use AuthorizesRequests;

    public string $month = '';

    public function mount(): void
    {
        $this->authorize('hr.salaries.view');
        $this->month = now()->format('Y-m');
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

    public function render(): View
    {
        return view('livewire.hr.payroll-runs-index', [
            'runs' => PayrollRun::withCount('items')
                ->withSum('items', 'net')
                ->latest('month')
                ->get(),
        ])->layout('layouts.app', ['title' => 'مسيّرات الرواتب']);
    }
}
