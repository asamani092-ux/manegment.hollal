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
 * («بانتظار اعتمادي») with in-place approve / return.
 */
class TeamTasksIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'approval';

    /** @var array<int, string> per-task final rating input */
    public array $approveRating = [];

    /** @var array<int, string> per-task note input */
    public array $approveNote = [];

    public function mount(): void
    {
        $this->authorize('esnad.tasks.view');
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
        ])->layout('layouts.app', ['title' => 'مهام الفريق']);
    }
}
