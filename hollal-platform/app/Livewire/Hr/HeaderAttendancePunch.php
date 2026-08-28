<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Navbar attendance punch — single entry opens integrated panel.
 * Time: O(1) punch · O(7) recent | Space: O(7)
 */
class HeaderAttendancePunch extends Component
{
    public bool $showPanel = false;

    public string $declareType = 'حضور';

    public string $declareNotes = '';

    public function openPanel(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        $today = AttendanceRecord::query()
            ->where('employee_id', $user->id)
            ->whereDate('date', today())
            ->first();

        $this->declareType = (string) ($today?->type ?? 'حضور');
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
            'declareType' => 'required|string|in:حضور,عن بعد,تكليف خارجي,انقطاع',
            'declareNotes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        abort_unless($user && ($user->attendance_enabled ?? false), 403);

        AttendanceRecord::updateOrCreate(
            ['employee_id' => $user->id, 'date' => today()],
            [
                'type' => $this->declareType,
                'notes' => $this->declareNotes !== '' ? $this->declareNotes : null,
                'declared_by' => $user->id,
            ]
        );

        $this->dispatch('toast', type: 'success', message: 'تم تسجيل الإقرار');
    }

    public function render(): View
    {
        $user = auth()->user();
        $enabled = (bool) ($user?->attendance_enabled ?? false);
        $service = app(AttendanceService::class);
        $officeStart = $service->officeStartTime();

        $todayRecord = null;
        $recentRecords = collect();
        if ($enabled && $user) {
            $todayRecord = AttendanceRecord::query()
                ->where('employee_id', $user->id)
                ->whereDate('date', today())
                ->first();

            $recentRecords = AttendanceRecord::query()
                ->where('employee_id', $user->id)
                ->orderByDesc('date')
                ->limit(7)
                ->get(['id', 'date', 'type', 'check_in_at', 'check_out_at']);
        }

        return view('livewire.hr.header-attendance-punch', [
            'enabled' => $enabled,
            'officeStart' => $officeStart,
            'todayRecord' => $todayRecord,
            'recentRecords' => $recentRecords,
            'canManageAttendance' => (bool) $user?->can('hr.employees.update'),
        ]);
    }
}
