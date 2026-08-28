<?php

namespace App\Livewire\Hr;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Task;
use App\Models\User;
use App\Services\OffboardingService;
use App\Services\OnboardingService;
use App\Services\TaskLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Onboarding/offboarding lifecycle + freeze. One assignee covers all checklist tasks.
 * Time: O(n) | Space: O(page).
 */
class HrLifecycleIndex extends Component
{
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public ?int $confirmStartId = null;

    public ?int $confirmCompleteId = null;

    public ?int $confirmFreezeId = null;

    public ?int $confirmCancelOffboardingId = null;

    public ?int $confirmUnfreezeId = null;

    /** One assignee for all four offboarding checklist tasks. */
    public ?int $checklistAssigneeId = null;

    /** Unified floating panel for tasks + holds. */
    public ?int $detailUserId = null;

    /** tasks|holds */
    public string $detailTab = 'holds';

    public ?int $taskStatusId = null;

    public string $taskStatus = 'new';

    public ?int $taskAttachId = null;

    public $taskAttachment = null;

    /** Deep link support: /hr-lifecycle?open=<userId> opens the holds panel directly. */
    public ?int $open = null;

    protected $queryString = [
        'open' => ['except' => null],
    ];

    /** @return list<string> */
    private function lifecycleRoleLabels(): array
    {
        return [OffboardingService::ROLE_LABEL, OnboardingService::ROLE_LABEL];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->checklistAssigneeId = auth()->id();

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
        $this->checklistAssigneeId = auth()->id();
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

    public function openDetails(int $userId, string $tab = 'holds'): void
    {
        $this->cancelConfirm();
        $this->detailUserId = $userId;
        $this->detailTab = in_array($tab, ['tasks', 'holds'], true) ? $tab : 'holds';
        $this->taskStatusId = null;
        $this->taskAttachId = null;
        $this->taskAttachment = null;
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
        $this->taskStatusId = null;
        $this->taskAttachId = null;
        $this->taskAttachment = null;
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

        $this->validate([
            'checklistAssigneeId' => 'required|exists:users,id',
        ], [], [
            'checklistAssigneeId' => 'مسؤول المهام',
        ]);

        try {
            $assignee = User::findOrFail($this->checklistAssigneeId);
            app(OffboardingService::class)->offboard(
                User::findOrFail($userId),
                auth()->user(),
                $assignee,
            );
            $this->confirmStartId = null;
            $this->dispatch('toast', type: 'success', message: 'أُنشئت مهام إنهاء العلاقة لمسؤول واحد — الحساب يبقى نشطًا حتى إكمالها');
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

    public function beginTaskStatus(int $taskId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $task = $this->lifecycleTaskOrFail($taskId);
        $this->taskStatusId = $task->id;
        $this->taskStatus = $task->status;
        $this->taskAttachId = null;
    }

    public function saveTaskStatus(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->validate([
            'taskStatusId' => 'required|integer',
            'taskStatus' => 'required|in:new,in_progress,pending_review,completed,cancelled',
        ]);

        $task = $this->lifecycleTaskOrFail((int) $this->taskStatusId);
        $task->forceFill(['status' => $this->taskStatus])->save();
        if ($this->taskStatus === TaskLifecycleService::STATUS_COMPLETED) {
            $task->forceFill(['completed_at' => now()])->save();
        }
        $this->taskStatusId = null;
        $this->dispatch('toast', type: 'success', message: 'حُدّثت حالة المهمة');
    }

    public function beginTaskAttach(int $taskId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->lifecycleTaskOrFail($taskId);
        $this->taskAttachId = $taskId;
        $this->taskAttachment = null;
        $this->taskStatusId = null;
    }

    public function saveTaskAttachment(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->validate([
            'taskAttachId' => 'required|integer',
            'taskAttachment' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
        ], [], [
            'taskAttachment' => 'المرفق',
        ]);

        $task = $this->lifecycleTaskOrFail((int) $this->taskAttachId);
        /** @var TemporaryUploadedFile $file */
        $file = $this->taskAttachment;
        if ($task->attachment_path) {
            Storage::disk('local')->delete($task->attachment_path);
        }
        $task->forceFill([
            'attachment_path' => $file->store('tasks', 'local'),
        ])->save();
        $this->taskAttachId = null;
        $this->taskAttachment = null;
        $this->dispatch('toast', type: 'success', message: 'أُرفق الملف بالمهمة');
    }

    public function deleteLifecycleTask(int $taskId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $task = $this->lifecycleTaskOrFail($taskId);
        if ($task->status === TaskLifecycleService::STATUS_COMPLETED) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكن حذف مهمة مكتملة');

            return;
        }
        if ($task->attachment_path) {
            Storage::disk('local')->delete($task->attachment_path);
        }
        $task->delete();
        $this->dispatch('toast', type: 'success', message: 'حُذفت المهمة');
    }

    private function lifecycleTaskOrFail(int $taskId): Task
    {
        return Task::query()
            ->whereKey($taskId)
            ->whereIn('role_label', $this->lifecycleRoleLabels())
            ->when($this->detailUserId, fn ($q) => $q->where('related_user_id', $this->detailUserId))
            ->firstOrFail();
    }

    public function render(): View
    {
        $service = app(OffboardingService::class);
        $labels = $this->lifecycleRoleLabels();

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
            ->whereIn('role_label', $labels)
            ->selectRaw('related_user_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [TaskLifecycleService::STATUS_COMPLETED])
            ->groupBy('related_user_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->related_user_id);

        $lifecycleTasks = Task::query()
            ->select(['id', 'title', 'status', 'related_user_id', 'role_label', 'attachment_path', 'assigned_to'])
            ->with(['assignee:id,name'])
            ->whereIn('related_user_id', $ids)
            ->whereIn('role_label', $labels)
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
            ? ($lifecycleTasks->get((int) $this->detailUserId) ?? collect())
            : collect();

        return view('livewire.hr.hr-lifecycle-index', [
            'users' => $users,
            'holds' => $holds,
            'taskCounts' => $taskCounts,
            'lifecycleTasks' => $lifecycleTasks,
            'detailUser' => $detailUser,
            'detailHolds' => $detailHolds,
            'detailTasks' => $detailTasks,
            'assigneeOptions' => User::query()
                ->where('is_active', true)
                ->where('employment_status', '!=', User::STATUS_TERMINATED)
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'التهيئة وإنهاء العلاقة']);
    }
}
