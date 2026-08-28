<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceCycleApproval;
use App\Models\AttendanceImport;
use App\Models\AttendanceManualIndicator;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AttendanceDeductionService;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Path-1 monthly attendance: replace-upload + interactive Arabic column map,
 * unknown-fingerprint matching, manual late/absence indicators (formula-only),
 * HR approve, post-approve correction. Separate from shift/barcode path-2.
 * Time: O(employees) | Space: O(page)
 */
class AttendanceCycleIndex extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $asOf = '';

    public string $reportMonth = '';

    public bool $showMonthlyReport = false;

    public $uploadFile = null;

    public string $sourceLabel = 'افتراضي';

    public string $importMonth = '';

    /** @var list<string> */
    public array $fileHeaders = [];

    /** @var array<string, int|string|null> */
    public array $columnMap = [
        'fingerprint' => null,
        'date' => null,
        'check_in' => null,
        'check_out' => null,
    ];

    public string $wizardStep = 'upload'; // upload|map|match|done

    public ?int $pendingImportId = null;

    /** @var array<int, int|string|null> unmatched index => employee_id */
    public array $manualMatches = [];

    public ?int $applyRunId = null;

    public string $correctionReason = '';

    public ?int $manualEmployeeId = null;

    public string $manualLateHours = '0';

    public string $manualAbsenceDays = '0';

    public string $manualNotes = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->asOf = now()->toDateString();
        $this->reportMonth = now()->format('Y-m');
        $this->importMonth = now()->format('Y-m');
    }

    public function updatedUploadFile(): void
    {
        if (! $this->uploadFile) {
            return;
        }
        $this->validate([
            'uploadFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'sourceLabel' => 'required|string|max:120',
        ], [], [
            'uploadFile' => 'ملف الحركات',
            'sourceLabel' => 'المصدر / الجهة',
        ]);

        $ext = strtolower($this->uploadFile->getClientOriginalExtension() ?: 'csv');
        $path = $this->uploadFile->storeAs('attendance-imports', 'import-'.now()->timestamp.'.'.$ext);
        $abs = Storage::path($path);

        $svc = app(AttendanceService::class);
        $this->fileHeaders = $svc->parseFileHeaders($abs);
        if ($this->fileHeaders === []) {
            $this->dispatch('toast', type: 'error', message: 'تعذر قراءة عناوين الملف');

            return;
        }

        $suggested = $svc->suggestMapping($this->sourceLabel, $this->fileHeaders);
        $this->columnMap = [
            'fingerprint' => $suggested['fingerprint'] ?? 0,
            'date' => $suggested['date'] ?? 1,
            'check_in' => $suggested['check_in'] ?? 2,
            'check_out' => $suggested['check_out'] ?? 3,
        ];
        $this->pendingImportId = null;
        $this->manualMatches = [];
        $this->wizardStep = 'map';
        // Keep absolute path on a temp property via session-ish: store in Livewire as path string
        session(['attendance_import_abs' => $abs]);
    }

    public function confirmColumnMap(): void
    {
        $this->validate([
            'columnMap.fingerprint' => 'required|integer|min:0',
            'columnMap.date' => 'required|integer|min:0',
            'columnMap.check_in' => 'required|integer|min:0',
            'columnMap.check_out' => 'nullable|integer|min:0',
            'importMonth' => 'required|date_format:Y-m',
            'sourceLabel' => 'required|string|max:120',
        ], [], [
            'columnMap.fingerprint' => 'معرّف البصمة',
            'columnMap.date' => 'التاريخ',
            'columnMap.check_in' => 'وقت الحضور',
            'columnMap.check_out' => 'وقت الانصراف',
            'importMonth' => 'شهر الاستبدال',
            'sourceLabel' => 'المصدر / الجهة',
        ]);

        $abs = session('attendance_import_abs');
        if (! is_string($abs) || ! is_file($abs)) {
            $this->dispatch('toast', type: 'error', message: 'انتهت جلسة الملف — ارفع الملف من جديد');
            $this->wizardStep = 'upload';

            return;
        }

        $mapping = [
            'fingerprint' => (int) $this->columnMap['fingerprint'],
            'date' => (int) $this->columnMap['date'],
            'check_in' => (int) $this->columnMap['check_in'],
        ];
        if ($this->columnMap['check_out'] !== null && $this->columnMap['check_out'] !== '') {
            $mapping['check_out'] = (int) $this->columnMap['check_out'];
        }

        try {
            $import = app(AttendanceService::class)->stageImport(
                $abs,
                auth()->user(),
                $this->sourceLabel,
                $this->importMonth,
                $mapping,
            );
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->pendingImportId = $import->id;
        $this->manualMatches = [];
        if ($import->status === AttendanceImport::STATUS_NEEDS_MATCH) {
            foreach (array_keys($import->unmatched_rows ?? []) as $i) {
                $this->manualMatches[$i] = null;
            }
            $this->wizardStep = 'match';
            $this->dispatch('toast', type: 'warning', message: 'بعض الصفوف بلا بصمة معروفة — طابقها يدوياً');
        } else {
            $this->finalizePendingImport();
        }
    }

    public function confirmManualMatches(): void
    {
        $import = $this->pendingImport();
        if (! $import) {
            return;
        }

        $pairs = [];
        foreach ($this->manualMatches as $idx => $empId) {
            if ($empId) {
                $pairs[(int) $idx] = (int) $empId;
            }
        }

        $remaining = count($import->unmatched_rows ?? []);
        if (count($pairs) < $remaining) {
            $this->dispatch('toast', type: 'error', message: 'يجب مطابقة كل الصفوف غير المعروفة أو إلغاء الاستيراد');

            return;
        }

        app(AttendanceService::class)->applyManualFingerprintMatches($import, $pairs);
        $this->finalizePendingImport();
    }

    public function cancelImportWizard(): void
    {
        if ($this->pendingImportId) {
            AttendanceImport::query()->where('id', $this->pendingImportId)
                ->whereIn('status', [AttendanceImport::STATUS_DRAFT, AttendanceImport::STATUS_NEEDS_MATCH])
                ->delete();
        }
        $this->resetWizard();
        $this->dispatch('toast', type: 'info', message: 'أُلغي مسار الاستيراد');
    }

    public function approve(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(Carbon::parse($this->asOf));
        $existing = AttendanceCycleApproval::query()
            ->whereDate('cycle_from', $cycle['from']->toDateString())
            ->whereDate('cycle_to', $cycle['to']->toDateString())
            ->first();
        if ($existing && $existing->status === AttendanceCycleApproval::STATUS_APPROVED) {
            $this->dispatch('toast', type: 'error', message: 'الدورة معتمدة مسبقاً — اطلب تصحيحاً إن لزم');

            return;
        }
        if ($existing && $existing->status === AttendanceCycleApproval::STATUS_CORRECTION_PENDING) {
            $this->dispatch('toast', type: 'error', message: 'يوجد طلب تصحيح بانتظار الموافقة');

            return;
        }
        $svc->approveCycle($cycle['from'], $cycle['to'], auth()->user());
        $this->dispatch('toast', type: 'success', message: 'تم اعتماد خصم الحضور الشهري');
    }

    public function requestCorrection(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->validate([
            'correctionReason' => 'required|string|min:3|max:1000',
        ], [], ['correctionReason' => 'سبب التصحيح']);

        $approval = $this->currentApproval();
        if (! $approval) {
            $this->dispatch('toast', type: 'error', message: 'لا يوجد اعتماد لطلب التصحيح');

            return;
        }
        try {
            app(AttendanceDeductionService::class)->requestCorrection(
                $approval,
                $this->correctionReason,
                auth()->user(),
            );
            $this->correctionReason = '';
            $this->dispatch('toast', type: 'success', message: 'أُرسل طلب التصحيح بانتظار الموافقة');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approveCorrection(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $approval = $this->currentApproval();
        if (! $approval) {
            return;
        }
        try {
            app(AttendanceDeductionService::class)->approveCorrection($approval, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'وُافق على التصحيح — يمكن إعادة اعتماد الخصم بعد التعديل');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function saveManualIndicator(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->validate([
            'manualEmployeeId' => 'required|exists:users,id',
            'manualLateHours' => 'required|numeric|min:0|max:500',
            'manualAbsenceDays' => 'required|integer|min:0|max:31',
            'manualNotes' => 'nullable|string|max:500',
        ], [], [
            'manualEmployeeId' => 'الموظف',
            'manualLateHours' => 'ساعات التأخير',
            'manualAbsenceDays' => 'أيام الغياب',
        ]);

        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(Carbon::parse($this->asOf));
        $employee = User::query()->findOrFail($this->manualEmployeeId);
        $svc->saveManualIndicator(
            $employee,
            $cycle['from'],
            $cycle['to'],
            (float) $this->manualLateHours,
            (int) $this->manualAbsenceDays,
            auth()->user(),
            $this->manualNotes !== '' ? $this->manualNotes : null,
        );
        $this->manualLateHours = '0';
        $this->manualAbsenceDays = '0';
        $this->manualNotes = '';
        $this->dispatch('toast', type: 'success', message: 'حُفظت المؤشرات — يُحسب المبلغ من معادلات الإعدادات');
    }

    public function applyToPayroll(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->validate(['applyRunId' => 'required|exists:payroll_runs,id']);
        $approval = AttendanceCycleApproval::query()
            ->where('status', AttendanceCycleApproval::STATUS_APPROVED)
            ->latest('id')
            ->first();
        if (! $approval) {
            $this->dispatch('toast', type: 'error', message: 'لا يوجد تقرير معتمد');

            return;
        }
        $run = PayrollRun::query()->findOrFail($this->applyRunId);
        try {
            $n = app(AttendanceDeductionService::class)->applyApprovedToPayrollDraft($run, $approval);
            $this->dispatch('toast', type: 'success', message: "طُبّق الخصم على {$n} موظفاً");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(Carbon::parse($this->asOf ?: now()));
        $rows = $svc->cycleReport($cycle['from'], $cycle['to']);
        $approval = AttendanceCycleApproval::query()
            ->whereDate('cycle_from', $cycle['from']->toDateString())
            ->whereDate('cycle_to', $cycle['to']->toDateString())
            ->first();

        $pendingImport = $this->pendingImport();
        $attendees = User::query()
            ->where('attendance_enabled', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $manualRows = AttendanceManualIndicator::query()
            ->whereDate('cycle_from', $cycle['from']->toDateString())
            ->whereDate('cycle_to', $cycle['to']->toDateString())
            ->with('employee:id,name')
            ->get();

        return view('livewire.hr.attendance-cycle-index', [
            'cycle' => $cycle,
            'rows' => $rows,
            'approval' => $approval,
            'draftRuns' => PayrollRun::query()
                ->whereIn('status', [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED])
                ->latest('id')
                ->limit(20)
                ->get(),
            'monthlyReport' => $this->showMonthlyReport && $this->reportMonth !== ''
                ? app(AttendanceService::class)->monthlyReport($this->reportMonth)
                : null,
            'amountsTotal' => collect($rows)->sum(fn ($r) => (float) ($r['total_deduction'] ?? 0)),
            'roleLabels' => AttendanceService::columnRoleLabels(),
            'pendingImport' => $pendingImport,
            'attendees' => $attendees,
            'manualRows' => $manualRows,
            'hourValueHint' => 'قيمة الساعة = (الراتب ÷ أيام الدورة) ÷ الساعات اليومية — من الإعدادات',
        ])->layout('layouts.app', ['title' => 'الحضور الشهري والخصم']);
    }

    private function finalizePendingImport(): void
    {
        $import = $this->pendingImport();
        if (! $import) {
            return;
        }
        try {
            $result = app(AttendanceService::class)->commitReplaceImport($import->fresh(), auth()->user());
            $this->reportMonth = (string) $import->import_month;
            $this->showMonthlyReport = true;
            $this->resetWizard();
            $this->dispatch(
                'toast',
                type: 'success',
                message: "استُبدلت حركات الشهر ({$result['rows']} سجل) — البصمة المستوردة تغلب عند التعارض"
            );
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    private function resetWizard(): void
    {
        $this->wizardStep = 'upload';
        $this->pendingImportId = null;
        $this->fileHeaders = [];
        $this->manualMatches = [];
        $this->uploadFile = null;
        $this->columnMap = [
            'fingerprint' => null,
            'date' => null,
            'check_in' => null,
            'check_out' => null,
        ];
        session()->forget('attendance_import_abs');
    }

    private function pendingImport(): ?AttendanceImport
    {
        if (! $this->pendingImportId) {
            return null;
        }

        return AttendanceImport::query()->find($this->pendingImportId);
    }

    private function currentApproval(): ?AttendanceCycleApproval
    {
        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(Carbon::parse($this->asOf ?: now()));

        return AttendanceCycleApproval::query()
            ->whereDate('cycle_from', $cycle['from']->toDateString())
            ->whereDate('cycle_to', $cycle['to']->toDateString())
            ->first();
    }
}
