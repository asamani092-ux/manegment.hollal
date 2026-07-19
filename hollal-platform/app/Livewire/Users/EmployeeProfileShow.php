<?php

namespace App\Livewire\Users;

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

    public function mount(User $user): void
    {
        $this->authorize('hr.employees.view');
        $this->userId = $user->id;
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
        ])->layout('layouts.app', ['title' => 'الملف الوظيفي — '.$user->name]);
    }
}
