<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Navbar attendance punch — manual / barcode / geofence + day-type approval.
 * Time: O(1) punch · O(7) recent | Space: O(7)
 */
class HeaderAttendancePunch extends Component
{
    public bool $showPanel = false;

    public string $declareType = AttendanceService::TYPE_PRESENT;

    public string $declareNotes = '';

    public string $barcodeToken = '';

    public function openPanel(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        $today = AttendanceRecord::query()
            ->where('employee_id', $user->id)
            ->whereDate('date', today())
            ->first();

        $this->declareType = (string) ($today?->type ?? AttendanceService::TYPE_PRESENT);
        if (! in_array($this->declareType, AttendanceService::DAY_TYPES, true)) {
            $this->declareType = AttendanceService::TYPE_PRESENT;
        }
        $this->declareNotes = (string) ($today?->notes ?? '');
        $this->barcodeToken = '';
        $this->showPanel = true;
    }

    public function closePanel(): void
    {
        $this->showPanel = false;
    }

    public function checkIn(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        try {
            app(AttendanceService::class)->checkIn($user);
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل الحضور');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function checkInViaBarcode(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        $this->validate([
            'barcodeToken' => 'required|string|max:120',
        ], [], ['barcodeToken' => 'باركود المقر']);

        try {
            app(AttendanceService::class)->checkInViaBarcode($user, $this->barcodeToken);
            $this->barcodeToken = '';
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل الحضور بالباركود');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function checkInViaGeo(float $latitude, float $longitude): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        try {
            $record = app(AttendanceService::class)->checkInViaLocation($user, $latitude, $longitude);
            $place = $record->field_location ?: 'موقع مسموح';
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل الحضور من: '.$place);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function geoFailed(?string $message = null): void
    {
        $this->dispatch(
            'toast',
            type: 'error',
            message: $message ?: 'تعذّر تحديد موقعك — اسمح بالوصول للموقع أو سجّل بالباركود.'
        );
    }

    public function checkOut(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        try {
            app(AttendanceService::class)->checkOut($user);
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل الانصراف');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function saveDeclaration(): void
    {
        $this->validate([
            'declareType' => 'required|string|in:حضور,عن بعد,ميداني',
            'declareNotes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        try {
            app(AttendanceService::class)->declareDayType(
                $user,
                $this->declareType,
                $this->declareNotes !== '' ? $this->declareNotes : null,
            );
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $msg = in_array($this->declareType, [AttendanceService::TYPE_REMOTE, AttendanceService::TYPE_FIELD], true)
            ? 'تم تسجيل الإقرار — بانتظار اعتماد المدير'
            : 'تم تسجيل الإقرار';
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    public function approvePending(int $recordId): void
    {
        $record = AttendanceRecord::query()->with('employee')->findOrFail($recordId);
        try {
            app(AttendanceService::class)->approveDayType($record, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم اعتماد اليوم');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function rejectPending(int $recordId): void
    {
        $record = AttendanceRecord::query()->with('employee')->findOrFail($recordId);
        try {
            app(AttendanceService::class)->rejectDayType($record, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم رفض اليوم');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        $enabled = (bool) ($user?->attendance_enabled ?? false);
        $service = app(AttendanceService::class);
        $expected = $user ? $service->expectedStartFor($user) : ['start' => $service->officeStartTime(), 'grace' => 0, 'shift' => null];

        $todayRecord = null;
        $recentRecords = collect();
        $todayLate = 0;
        $pendingForManager = collect();
        if ($enabled && $user) {
            $todayRecord = AttendanceRecord::query()
                ->where('employee_id', $user->id)
                ->whereDate('date', today())
                ->first();

            if ($todayRecord) {
                $todayLate = $service->latenessMinutes($todayRecord);
            }

            $recentRecords = AttendanceRecord::query()
                ->where('employee_id', $user->id)
                ->orderByDesc('date')
                ->limit(7)
                ->get(['id', 'date', 'type', 'check_in_at', 'check_out_at', 'approval_status', 'late_minutes', 'source']);
        }

        if ($user) {
            $subIds = \App\Models\User::query()->where('manager_id', $user->id)->pluck('id');
            if ($subIds->isNotEmpty() || $user->can('hr.employees.update')) {
                $pendingQuery = AttendanceRecord::query()
                    ->select(['id', 'employee_id', 'date', 'type', 'approval_status'])
                    ->with('employee:id,name,manager_id')
                    ->where('approval_status', AttendanceService::APPROVAL_PENDING)
                    ->whereIn('type', [AttendanceService::TYPE_REMOTE, AttendanceService::TYPE_FIELD])
                    ->latest('date')
                    ->limit(20);
                if (! $user->can('hr.employees.update')) {
                    $pendingQuery->whereIn('employee_id', $subIds);
                }
                $pendingForManager = $pendingQuery->get()
                    ->filter(fn ($r) => $service->canDecideDayType($r, $user));
            }
        }

        return view('livewire.hr.header-attendance-punch', [
            'enabled' => $enabled,
            'officeStart' => $expected['start'],
            'shiftGrace' => $expected['grace'],
            'userShift' => $expected['shift'],
            'todayRecord' => $todayRecord,
            'todayLate' => $todayLate,
            'recentRecords' => $recentRecords,
            'pendingForManager' => $pendingForManager,
            'canManageAttendance' => (bool) $user?->can('hr.employees.update'),
            'showPanelAnyway' => $pendingForManager->isNotEmpty(),
            'barcodeConfigured' => $service->siteBarcodeToken() !== '',
        ]);
    }
}
