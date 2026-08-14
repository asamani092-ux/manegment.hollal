<?php

namespace App\Livewire\Payroll;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollRunService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payroll — monthly salaries, CRUD gated by hr.salaries.manage.
 * Time: O(n) per page | Space: O(n).
 */
class PayrollIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $search = '';

    public string $monthFilter = '';

    public bool $showModal = false;

    public bool $viewOnly = false;

    public ?int $payrollId = null;

    public ?int $employee_id = null;

    public ?string $month = null;

    public string $base = '0';

    public string $additions = '0';

    public string $deductions = '0';

    public string $transfer_status = 'pending';

    protected $queryString = [
        'search' => ['except' => ''],
        'monthFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Payroll::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingMonthFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Payroll::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorize('update', $payroll);
        $this->fillForm($payroll);
        $this->viewOnly = false;
        $this->showModal = true;
    }

    public function openView(int $id): void
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorize('view', $payroll);
        $this->fillForm($payroll);
        $this->viewOnly = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        if ($this->viewOnly) {
            return;
        }

        $isEdit = (bool) $this->payrollId;

        if ($isEdit) {
            $payroll = Payroll::findOrFail($this->payrollId);
            $this->authorize('update', $payroll);
        } else {
            $this->authorize('create', Payroll::class);
        }

        $this->validate([
            'employee_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m',
            'base' => 'required|numeric|min:0',
            'additions' => 'required|numeric|min:0',
            'deductions' => 'required|numeric|min:0',
            'transfer_status' => 'required|in:pending,transferred,failed',
        ]);

        $monthDate = $this->month.'-01';
        $net = Payroll::computeNet($this->base, $this->additions, $this->deductions);

        $payroll = Payroll::updateOrCreate(
            ['id' => $this->payrollId],
            [
                'employee_id' => $this->employee_id,
                'month' => $monthDate,
                'base' => $this->base,
                'additions' => $this->additions,
                'deductions' => $this->deductions,
                'net' => $net,
                'transfer_status' => $this->transfer_status,
            ]
        );

        app(PayrollRunService::class)->mirrorMonthlyPayrollToProfile($payroll);

        $syncResult = null;
        try {
            $syncResult = app(PayrollRunService::class)->syncFromMonthlyPayroll(
                auth()->user(),
                $this->month,
                (int) $this->employee_id,
            );
        } catch (\InvalidArgumentException $e) {
            // Run exists but not editable — keep payroll save; surface sync message below.
            $syncResult = ['skipped' => true, 'message' => $e->getMessage(), 'updated' => 0, 'created' => 0];
        }

        $this->closeModal();
        $message = $isEdit ? 'تم تحديث الراتب' : 'تم إنشاء الراتب';
        if ($syncResult && ! ($syncResult['skipped'] ?? true) && (($syncResult['updated'] ?? 0) + ($syncResult['created'] ?? 0)) > 0) {
            $message .= ' — وتُزامن إلى مسيّر الشهر والملف الوظيفي';
        } else {
            $message .= ' — حُدّث الملف الوظيفي (المسيّر يُحدَّث عند وجود مسودة قابلة للتعديل)';
        }
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /**
     * Push all monthly payroll rows for a month into the draft/returned run.
     * Public Livewire method name kept stable for the UI button.
     */
    public function syncToPayrollRun(?string $month = null): void
    {
        $this->authorize('hr.salaries.manage');

        $targetMonth = $month ?: ($this->monthFilter ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
            $this->dispatch('toast', type: 'error', message: 'اختر شهرًا صالحًا للمزامنة');

            return;
        }

        try {
            $result = app(PayrollRunService::class)->syncFromMonthlyPayroll(auth()->user(), $targetMonth);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $type = ($result['skipped'] ?? false) ? 'warning' : 'success';
        $this->dispatch('toast', type: $type, message: $result['message']);
    }

    public function delete(int $id): void
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorize('delete', $payroll);
        $payroll->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف الراتب');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function fillForm(Payroll $payroll): void
    {
        $this->payrollId = $payroll->id;
        $this->employee_id = $payroll->employee_id;
        $this->month = $payroll->month?->format('Y-m');
        $this->base = (string) $payroll->base;
        $this->additions = (string) $payroll->additions;
        $this->deductions = (string) $payroll->deductions;
        $this->transfer_status = $payroll->transfer_status;
    }

    protected function resetForm(): void
    {
        $this->payrollId = null;
        $this->viewOnly = false;
        $this->employee_id = null;
        $this->month = null;
        $this->base = '0';
        $this->additions = '0';
        $this->deductions = '0';
        $this->transfer_status = 'pending';
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.payroll.payroll-index', [
            'payrolls' => Payroll::query()
                ->select(['id', 'employee_id', 'month', 'base', 'additions', 'deductions', 'net', 'transfer_status'])
                ->with(['employee:id,name'])
                ->when($this->search, fn ($q) => $q->whereHas(
                    'employee',
                    fn ($e) => $e->where('name', 'like', '%'.$this->search.'%')
                ))
                ->when($this->monthFilter, fn ($q) => $q->where('month', $this->monthFilter.'-01'))
                ->latest('month')
                ->paginate(10),
            'employees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'الرواتب']);
    }
}
