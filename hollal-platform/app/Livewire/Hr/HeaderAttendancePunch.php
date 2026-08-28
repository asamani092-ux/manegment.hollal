<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Navbar attendance punch — respects shift lateness and day-type approval.
 * Time: O(1) punch · O(7) recent | Space: O(7)
 */
class HeaderAttendancePunch extends Component
{
    public bool $showPanel = false;

    public string $declareType = AttendanceService::TYPE_PRESENT;

    public string $declareNotes = '';

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

    public function render(): View
    {
        $user = auth()->user();
        $enabled = (bool) ($user?->attendance_enabled ?? false);
        $service = app(AttendanceService::class);
        $expected = $user ? $service->expectedStartFor($user) : ['start' => $service->officeStartTime(), 'grace' => 0, 'shift' => null];

        $todayRecord = null;
        $recentRecords = collect();
        $todayLate = 0;
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
                ->get(['id', 'date', 'type', 'check_in_at', 'check_out_at', 'approval_status', 'late_minutes']);
        }

        return view('livewire.hr.header-attendance-punch', [
            'enabled' => $enabled,
            'officeStart' => $expected['start'],
            'shiftGrace' => $expected['grace'],
            'userShift' => $expected['shift'],
            'todayRecord' => $todayRecord,
            'todayLate' => $todayLate,
            'recentRecords' => $recentRecords,
            'canManageAttendance' => (bool) $user?->can('hr.employees.update'),
        ]);
    }
}
