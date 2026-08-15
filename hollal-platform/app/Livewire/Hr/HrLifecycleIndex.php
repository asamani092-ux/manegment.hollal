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

    public ?int $confirmFreezeId = null;

    public ?int $confirmCancelOffboardingId = null;

    public ?int $confirmUnfreezeId = null;

    /** Unified floating panel for tasks + holds. */
    public ?int $detailUserId = null;

    /** tasks|holds */
    public string $detailTab = 'holds';

    /** Deep link support: /hr-lifecycle?open=<userId> opens the holds panel directly. */
    public ?int $open = null;

    protected $queryString = [
        'open' => ['except' => null],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        if ($this->open) {
            $this->openDetails($this->open);
        }
    }

    public function askStartOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);
        $this->closeDetails();
        $this->confirmStartId = $userId;
        $this->confirmCompleteId = null;
        $this->confirmFreezeId = null;
        $this->confirmCancelOffboardingId = null;
        $this->confirmUnfreezeId = null;
    }

    public function askFreeze(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);
        $this->closeDetails();
        $this->confirmFreezeId = $userId;
        $this->confirmStartId = null;
        $this->confirmCompleteId = null;
        $this->confirmCancelOffboardingId = null;
        $this->confirmUnfreezeId = null;
    }

    public function askUnfreeze(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->closeDetails();
        $this->confirmUnfreezeId = $userId;
        $this->confirmStartId = null;
        $this->confirmCompleteId = null;
        $this->confirmFreezeId = null;
        $this->confirmCancelOffboardingId = null;
    }

    public function askCancelOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);
        $this->closeDetails();
        $this->confirmCancelOffboardingId = $userId;
        $this->confirmStartId = null;
        $this->confirmCompleteId = null;
        $this->confirmFreezeId = null;
        $this->confirmUnfreezeId = null;
    }

    public function askCompleteOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);
        $this->closeDetails();
        $this->confirmCompleteId = $userId;
        $this->confirmStartId = null;
    }

    /**
     * Open unified floating panel. O(1).
     */
    public function openDetails(int $userId, string $tab = 'holds'): void
    {
        $this->cancelConfirm();
        $this->detailUserId = $userId;
        $this->detailTab = in_array($tab, ['tasks', 'holds'], true) ? $tab : 'holds';
    }

    public function setDetailTab(string $tab): void
    {
        if (! in_array($tab, ['tasks', 'holds'], true) || $this->detailUserId === null) {
            return;
        }
        $this->detailTab = $tab;
    }

    public function closeDetails(): void
    {
        $this->detailUserId = null;
        $this->detailTab = 'holds';
    }

    public function cancelConfirm(): void
    {
        $this->confirmStartId = null;
        $this->confirmCompleteId = null;
        $this->confirmFreezeId = null;
        $this->confirmCancelOffboardingId = null;
        $this->confirmUnfreezeId = null;
    }

    public function startOffboarding(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        abort_if($userId === auth()->id(), 403);

        try {
            app(OffboardingService::class)->offboard(User::findOrFail($userId), auth()->user());
            $this->confirmStartId = null;
            $this->dispatch('toast', type: 'success', message: 'أُنشئت مهام إنهاء العلاقة في إسناد — الحساب يبقى نشطًا حتى إكمالها');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
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
            $this->confirmCancelOffboardingId = null;
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
        $this->confirmFreezeId = null;
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
        $this->confirmUnfreezeId = null;
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

        $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $holds = $service->holdsForMany($ids);

        $taskCounts = Task::query()
            ->whereIn('related_user_id', $ids)
            ->where('role_label', 'إنهاء_علاقة')
            ->selectRaw('related_user_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [TaskLifecycleService::STATUS_COMPLETED])
            ->groupBy('related_user_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->related_user_id);

        $offboardingTasks = Task::query()
            ->select(['id', 'title', 'status', 'related_user_id'])
            ->whereIn('related_user_id', $ids)
            ->where('role_label', 'إنهاء_علاقة')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $t) => (int) $t->related_user_id);

        $detailUser = $this->detailUserId
            ? User::query()->select(['id', 'name'])->find($this->detailUserId)
            : null;

        $detailHolds = $this->detailUserId
            ? ($holds[(int) $this->detailUserId] ?? $service->holds(User::findOrFail($this->detailUserId)))
            : [];

        $detailTasks = $this->detailUserId
            ? ($offboardingTasks->get((int) $this->detailUserId) ?? collect())
            : collect();

        return view('livewire.hr.hr-lifecycle-index', [
            'users' => $users,
            'holds' => $holds,
            'taskCounts' => $taskCounts,
            'offboardingTasks' => $offboardingTasks,
            'detailUser' => $detailUser,
            'detailHolds' => $detailHolds,
            'detailTasks' => $detailTasks,
        ]);
    }
}
