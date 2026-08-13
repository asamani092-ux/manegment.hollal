<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 02-B2 — team tasks, overdue (own/team scope), and the approval queue
 * («بانتظار اعتمادي») with in-place approve / return + detail modal.
 */
class TeamTasksIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'approval';

    /** @var array<int, string> per-task final rating input */
    public array $approveRating = [];

    /** @var array<int, string> per-task note input */
    public array $approveNote = [];

    public bool $showDetail = false;

    public ?int $detailTaskId = null;

    public function mount(): void
    {
        $this->authorize('esnad.tasks.view');
    }

    public function openDetail(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('view', $task);
        $this->detailTaskId = $task->id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->detailTaskId = null;
    }

    public function approveFromForm(int $taskId): void
    {
        $this->approve($taskId, $this->approveRating[$taskId] ?? '', $this->approveNote[$taskId] ?? null);
    }

    public function returnFromForm(int $taskId): void
    {
        $this->returnTask($taskId, $this->approveNote[$taskId] ?? 'يرجى التعديل');
    }

    public function approve(int $taskId, string $rating, ?string $notes = null): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('addRating', $task);

        try {
            app(TaskLifecycleService::class)->recordFinalRating($task, auth()->user(), $rating, $notes);
            $this->dispatch('toast', type: 'success', message: 'تم اعتماد المهمة');
            if ($this->detailTaskId === $taskId) {
                $this->closeDetail();
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function returnTask(int $taskId, string $note): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('addRating', $task);

        try {
            app(TaskLifecycleService::class)->requestRevision($task, auth()->user(), $note);
            $this->dispatch('toast', type: 'success', message: 'أُعيدت المهمة للتعديل');
            if ($this->detailTaskId === $taskId) {
                $this->closeDetail();
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    /** @return Collection<int, Task> */
    private function overdueTasks(User $user): Collection
    {
        $scopeIds = collect([$user->id]);

        if ($user->can('esnad.tasks.team.view')) {
            $scopeIds = $scopeIds->merge(
                User::query()->where('manager_id', $user->id)->pluck('id')
            );
        }

        return Task::query()
            ->overdue()
            ->whereIn('assigned_to', $scopeIds->unique())
            ->with(['assignee:id,name', 'project:id,name'])
            ->latest('due_date')
            ->get();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $detailTask = null;
        if ($this->detailTaskId) {
            $detailTask = Task::query()
                ->with([
                    'assignee:id,name',
                    'assigner:id,name',
                    'project:id,name',
                    'notes.author:id,name',
                    'statusLogs' => fn ($q) => $q->orderByDesc('created_at')->limit(20),
                ])
                ->find($this->detailTaskId);
        }

        return view('livewire.tasks.team-tasks-index', [
            'approvalQueue' => Task::query()
                ->pendingApprovalFor($user)
                ->with(['assignee:id,name', 'project:id,name'])
                ->latest()
                ->get(),
            'teamTasks' => $user->can('esnad.tasks.team.view')
                ? Task::query()->teamOf($user)->with(['assignee:id,name', 'project:id,name'])->latest()->get()
                : new Collection,
            'overdueTasks' => $this->overdueTasks($user),
            'ratings' => Task::RATINGS,
            'detailTask' => $detailTask,
            'statusLabels' => [
                'new' => 'جديدة',
                'in_progress' => 'قيد التنفيذ',
                'pending_review' => 'بانتظار المراجعة',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ],
        ])->layout('layouts.app', ['title' => 'مهام الفريق']);
    }
}
