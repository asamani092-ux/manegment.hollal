<?php

namespace App\Livewire\Users;

use App\Models\EmployeeProfile;
use App\Models\ProfileAccessLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * 01-B1 — employee job profile with tabs. The salary tab is gated on
 * hr.salaries.view and every access is recorded in profile_access_logs.
 */
class EmployeeProfileShow extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public int $userId;

    public string $activeTab = 'data';

    public bool $attendanceEnabled = false;

    public string $weeklyHours = '';

    /** مقفل|مفتوح — قائمة منسدلة لفتح الساعات الإضافية */
    public string $overtimeGate = 'مقفل';

    public function mount(User $user): void
    {
        $this->authorize('hr.employees.view');
        $this->userId = $user->id;
        $this->attendanceEnabled = (bool) $user->attendance_enabled;
        $this->weeklyHours = (string) ($user->profile?->weekly_hours ?? '');
        $this->overtimeGate = $user->profile?->overtime_unlocked ? 'مفتوح' : 'مقفل';
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'salary') {
            $this->authorize('hr.salaries.view');
            $this->logSalaryAccess();
        }

        $this->activeTab = $tab;
    }

    public function canViewSalary(): bool
    {
        return auth()->user()->can('hr.salaries.view');
    }

    /**
     * Amendments HR — تفعيل الحضور + الساعات الأساسية.
     * Time: O(1) | Space: O(1)
     */
    public function saveAttendanceSettings(): void
    {
        $this->authorize('hr.employees.update');

        $this->validate([
            'attendanceEnabled' => 'boolean',
            'weeklyHours' => 'nullable|integer|min:1|max:80',
        ]);

        $user = User::findOrFail($this->userId);
        $user->forceFill(['attendance_enabled' => $this->attendanceEnabled])->save();

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['job_title' => $user->name],
        );
        $profile->forceFill([
            'weekly_hours' => $this->weeklyHours !== '' ? (int) $this->weeklyHours : null,
        ])->save();

        $this->dispatch('ds-toast', message: 'حُفظت إعدادات الحضور');
    }

    /**
     * Amendments Q1 — فتح/قفل الساعات الإضافية من القائمة المنسدلة.
     * Time: O(1) | Space: O(1)
     */
    public function saveOvertimeGate(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'overtimeGate' => 'required|in:مقفل,مفتوح',
        ]);

        $user = User::findOrFail($this->userId);
        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['job_title' => $user->name],
        );
        $profile->setOvertimeUnlocked($this->overtimeGate === 'مفتوح');

        $this->dispatch('ds-toast', message: $this->overtimeGate === 'مفتوح'
            ? 'فُتحت الساعات الإضافية لهذا الموظف'
            : 'أُقفلت الساعات الإضافية لهذا الموظف');
    }

    private function logSalaryAccess(): void
    {
        ProfileAccessLog::create([
            'user_id' => auth()->id(),
            'target_user_id' => $this->userId,
            'tab_accessed' => 'salary',
            'accessed_at' => now(),
        ]);
    }

    public function render(): View
    {
        $user = User::with(['department:id,name', 'manager:id,name', 'profile', 'roles:id,name'])
            ->findOrFail($this->userId);

        return view('livewire.users.employee-profile-show', [
            'user' => $user,
            'canViewSalary' => $this->canViewSalary(),
            'canManageOvertime' => auth()->user()->can('hr.salaries.manage'),
        ])->layout('layouts.app', ['title' => 'الملف الوظيفي — '.$user->name]);
    }
}
