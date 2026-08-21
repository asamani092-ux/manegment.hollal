<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceCycleApproval;
use App\Models\PayrollRun;
use App\Services\AttendanceDeductionService;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * ATT-2/4 — cycle report, approve deductions, CSV import, barcode/field tools.
 * Time: O(employees) | Space: O(page)
 */
class AttendanceCycleIndex extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $asOf = '';

    public string $barcodeToken = '';

    public string $fieldLocation = '';

    public $csvFile = null;

    public ?int $applyRunId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->asOf = now()->toDateString();
    }

    public function approve(): void
    {
        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(\Illuminate\Support\Carbon::parse($this->asOf));
        $svc->approveCycle($cycle['from'], $cycle['to'], auth()->user());
        $this->dispatch('toast', type: 'success', message: 'تم اعتماد تقرير دورة الحضور');
    }

    public function applyToPayroll(): void
    {
        $this->validate(['applyRunId' => 'required|exists:payroll_runs,id']);
        $approval = AttendanceCycleApproval::query()->where('status', 'معتمد')->latest('id')->first();
        if (! $approval) {
            $this->dispatch('toast', type: 'error', message: 'لا يوجد تقرير معتمد');

            return;
        }
        $run = PayrollRun::query()->findOrFail($this->applyRunId);
        $n = app(AttendanceDeductionService::class)->applyApprovedToPayrollDraft($run, $approval);
        $this->dispatch('toast', type: 'success', message: "طُبّق الخصم على {$n} موظفاً");
    }

    public function importCsv(): void
    {
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:5120']);
        $path = $this->csvFile->storeAs('attendance-imports', 'import-'.now()->timestamp.'.csv');
        $abs = storage_path('app/'.$path);
        $result = app(AttendanceService::class)->importCsv($abs, auth()->user());
        $this->reset('csvFile');
        $this->dispatch('toast', type: 'success', message: "استيراد {$result['rows']} سجل");
    }

    public function scanBarcode(): void
    {
        $this->validate(['barcodeToken' => 'required|string|max:120']);
        try {
            app(AttendanceService::class)->checkInViaBarcode(auth()->user(), $this->barcodeToken);
            $this->dispatch('toast', type: 'success', message: 'حضور بالباركود');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function startField(): void
    {
        $this->validate(['fieldLocation' => 'required|string|max:255']);
        try {
            auth()->user()->loadMissing('profile');
            app(AttendanceService::class)->startFieldWork(auth()->user(), $this->fieldLocation);
            $this->dispatch('toast', type: 'success', message: 'تسجيل ميداني بانتظار الاعتماد');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approveField(int $recordId): void
    {
        $record = \App\Models\AttendanceRecord::query()->findOrFail($recordId);
        app(AttendanceService::class)->approveFieldWork($record, auth()->user());
        $this->dispatch('toast', type: 'success', message: 'اعتمد العمل الميداني');
    }

    public function render(): View
    {
        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(\Illuminate\Support\Carbon::parse($this->asOf ?: now()));
        $rows = $svc->cycleReport($cycle['from'], $cycle['to']);
        $approval = AttendanceCycleApproval::query()
            ->whereDate('cycle_from', $cycle['from']->toDateString())
            ->whereDate('cycle_to', $cycle['to']->toDateString())
            ->first();

        return view('livewire.hr.attendance-cycle-index', [
            'cycle' => $cycle,
            'rows' => $rows,
            'approval' => $approval,
            'draftRuns' => PayrollRun::query()->whereIn('status', [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED])->latest('id')->limit(20)->get(),
            'pendingField' => \App\Models\AttendanceRecord::query()
                ->where('approval_status', 'بانتظار')
                ->with('employee:id,name')
                ->latest('id')
                ->limit(30)
                ->get(),
        ])->layout('layouts.app', ['title' => 'دورة الحضور والخصم']);
    }
}
