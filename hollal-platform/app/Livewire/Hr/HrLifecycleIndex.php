<?php

namespace App\Livewire\Hr;

use App\Models\User;
use App\Services\OffboardingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Onboarding/offboarding lifecycle actions.
 * Time: O(n) | Space: O(page).
 */
class HrLifecycleIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
    }

    public function startOffboarding(int $userId): void
    {
        $employee = User::findOrFail($userId);
        $service = app(OffboardingService::class);

        try {
            $service->offboard($employee, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم بدء إنهاء العلاقة');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        $service = app(OffboardingService::class);

        $users = User::query()
            ->select(['id', 'name', 'phone', 'is_active', 'employment_status'])
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(15);

        $holds = [];
        foreach ($users as $user) {
            $holds[$user->id] = $service->holds($user);
        }

        return view('livewire.hr.hr-lifecycle-index', [
            'users' => $users,
            'holds' => $holds,
        ]);
    }
}
