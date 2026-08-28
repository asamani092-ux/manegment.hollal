<?php

namespace App\Livewire\Hr;

use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Navbar quick check-in / check-out for attendance-enabled users.
 * Time: O(1) | Space: O(1)
 */
class HeaderAttendancePunch extends Component
{
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

    public function render(): View
    {
        $enabled = (bool) (auth()->user()?->attendance_enabled ?? false);

        return view('livewire.hr.header-attendance-punch', [
            'enabled' => $enabled,
        ]);
    }
}
