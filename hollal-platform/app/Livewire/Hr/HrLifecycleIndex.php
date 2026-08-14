<?php

namespace App\Livewire\Hr;

use App\Models\Task;
use App\Models\User;
use App\Services\OffboardingService;
use App\Services\TaskLifecycleService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Onboarding/offboarding lifecycle + freeze. Disable account is last step.
 * Time: O(n) | Space: O(page).
 */
class HrLifecycleIndex extends Component
{
    use WithPagination;

    public ?int $confirmStartId = null;

    public ?int $confirmCompleteId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
    }

    public function askStartOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);
        $this->confirmStartId = $userId;
        $this->confirmCompleteId = null;
    }

    public function cancelConfirm(): void
    {
        $this->confirmStartId = null;
        $this->confirmCompleteId = null;
    }

    public function startOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);

        $employee = User::findOrFail($userId);
        $service = app(OffboardingService::class);

        try {
            $service->offboard($employee, auth()->user());
            $this->confirmStartId = null;
            $this->dispatch('toast', type: 'success', message: 'أُنشئت مهام إنهاء العلاقة في إسناد — الحساب يبقى نشطًا حتى إكمالها');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function askCompleteOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);
        $this->confirmCompleteId = $userId;
        $this->confirmStartId = null;
    }

    public function completeOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);

        try {
            app(OffboardingService::class)->complete(User::findOrFail($userId), auth()->user());
            $this->confirmCompleteId = null;
            $this->dispatch('toast', type: 'success', message: 'أُغلق إنهاء العلاقة وعُطّل الحساب');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function cancelOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);

        try {
            app(OffboardingService::class)->cancel(User::findOrFail($userId));
            $this->dispatch('toast', type: 'success', message: 'تم التراجع عن بدء إنهاء العلاقة');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function freezeAccount(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);

        $user = User::findOrFail($userId);
        if ($user->offboarding_started_at) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكن التجميد أثناء إنهاء علاقة جارٍ — ألغِ الإنهاء أولًا أو أكمله');

            return;
        }

        $user->transitionStatus(User::STATUS_FROZEN);
        $this->dispatch('toast', type: 'success', message: 'جُمّد الحساب — ممنوع الدخول حتى إلغاء التجميد');
    }

    public function unfreezeAccount(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $user = User::findOrFail($userId);
        if ($user->employment_status !== User::STATUS_FROZEN) {
            $this->dispatch('toast', type: 'error', message: 'الحساب ليس مجمدًا');

            return;
        }

        $user->transitionStatus(User::STATUS_ACTIVE);
        $this->dispatch('toast', type: 'success', message: 'أُلغي التجميد — الحساب نشط');
    }

    public function render(): View
    {
        $service = app(OffboardingService::class);

        $users = User::query()
            ->select(['id', 'name', 'phone', 'is_active', 'employment_status', 'offboarding_started_at'])
            ->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhere('employment_status', User::STATUS_FROZEN)
                    ->orWhereNotNull('offboarding_started_at');
            })
            ->where('employment_status', '!=', User::STATUS_TERMINATED)
            ->orderBy('name')
            ->paginate(15);

        $holds = $service->holdsForMany($users->pluck('id')->all());

        $taskCounts = Task::query()
            ->whereIn('related_user_id', $users->pluck('id'))
            ->where('role_label', 'إنهاء_علاقة')
            ->selectRaw('related_user_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [TaskLifecycleService::STATUS_COMPLETED])
            ->groupBy('related_user_id')
            ->get()
            ->keyBy('related_user_id');

        $offboardingTasks = Task::query()
            ->select(['id', 'title', 'status', 'related_user_id'])
            ->whereIn('related_user_id', $users->pluck('id'))
            ->where('role_label', 'إنهاء_علاقة')
            ->orderBy('id')
            ->get()
            ->groupBy('related_user_id');

        return view('livewire.hr.hr-lifecycle-index', [
            'users' => $users,
            'holds' => $holds,
            'taskCounts' => $taskCounts,
            'offboardingTasks' => $offboardingTasks,
        ]);
    }
}
