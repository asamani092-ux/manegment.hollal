<?php

namespace App\Livewire\Tasks;

use App\Models\Project;
use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use App\Services\TaskLifecycleService;
use App\Services\WorkloadService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * متابعة الفريق: اعتماد · مهام الفريق · متأخرة · أحمال · قوالب ومتابعة.
 * Time: O(n) | Space: O(n)
 */
class TeamTasksIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'approval';

    public ?int $assigneeId = null;

    /** @var array<string, array<string, mixed>> */
    protected $queryString = [
        'tab' => ['except' => 'approval'],
        'assigneeId' => ['except' => null],
    ];

    /** @var array<int, string> */
    public array $approveRating = [];

    /** @var array<int, string> */
    public array $approveNote = [];

    /** @var array<int, string> */
    public array $managerStatus = [];

    public bool $showDetail = false;

    public ?int $detailTaskId = null;

    public bool $showModal = false;

    public string $title = '';

    public string $description = '';

    public ?int $assigned_to_id = null;

    public ?int $project_id = null;

    public string $pattern = 'أسبوعي';

    public ?int $day_of_week = null;

    public ?int $day_of_month = null;

    public ?string $starts_on = null;

    public ?string $ends_on = null;

    public string $required_evidence = '';

    public ?int $followUpUserId = null;

    /** templates | reminders — sub-panel inside recurring tab */
    public string $recurringPanel = 'templates';

    public function mount(): void
    {
        $this->authorize('esnad.tasks.team.view');

        $requested = request()->query('tab', $this->tab);
        if (in_array($requested, ['approval', 'team', 'overdue', 'loads', 'recurring'], true)) {
            $this->tab = $requested;
        }
        if ($requested === 'reminders') {
            $this->tab = 'recurring';
            $this->recurringPanel = 'reminders';
        }
    }

    public function openDetail(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('view', $task);
        $this->detailTaskId = $task->id;
        $this->managerStatus[$taskId] = $task->status;
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

    public function managerUpdateStatus(int $taskId): void
    {
        $this->authorize('esnad.tasks.team.view');
        $task = Task::findOrFail($taskId);
        $to = $this->managerStatus[$taskId] ?? '';

        try {
            app(TaskLifecycleService::class)->managerSetStatus($task, auth()->user(), $to);
            $this->dispatch('toast', type: 'success', message: 'تم تحديث حالة المهمة');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function managerComplete(int $taskId): void
    {
        $this->managerStatus[$taskId] = 'completed';
        $this->managerUpdateStatus($taskId);
    }

    public function openCreate(): void
    {
        $this->authorize('esnad.tasks.create');
        $this->reset([
            'title', 'description', 'assigned_to_id', 'project_id',
            'day_of_week', 'day_of_month', 'required_evidence', 'starts_on', 'ends_on',
        ]);
        $this->pattern = 'أسبوعي';
        $this->starts_on = now()->toDateString();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('esnad.tasks.create');

        $this->validate([
            'title' => 'required|string|max:255',
            'assigned_to_id' => 'required|exists:users,id',
            'project_id' => 'nullable|exists:projects,id',
            'pattern' => 'required|in:أسبوعي,شهري',
            'day_of_week' => 'nullable|integer|min:0|max:6|required_if:pattern,أسبوعي',
            'day_of_month' => 'nullable|integer|min:1|max:31|required_if:pattern,شهري',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
        ]);

        RecurringTaskTemplate::create([
            'title' => $this->title,
            'description' => $this->description ?: null,
            'required_evidence' => $this->required_evidence ?: null,
            'assigned_to_id' => $this->assigned_to_id,
            'created_by' => auth()->id(),
            'project_id' => $this->project_id,
            'pattern' => $this->pattern,
            'day_of_week' => $this->pattern === 'أسبوعي' ? $this->day_of_week : null,
            'day_of_month' => $this->pattern === 'شهري' ? $this->day_of_month : null,
            'starts_on' => $this->starts_on ?: null,
            'ends_on' => $this->ends_on ?: null,
            'is_active' => true,
        ]);

        $this->showModal = false;
        $this->tab = 'recurring';
        $this->recurringPanel = 'templates';
        $this->dispatch('toast', type: 'success', message: 'تم حفظ القالب المتكرر');
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('esnad.tasks.update');
        $template = RecurringTaskTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);
    }

    public function sendReminder(int $userId, ?int $templateId = null): void
    {
        $this->authorize('esnad.tasks.team.view');

        /** @var User $manager */
        $manager = auth()->user();
        $employee = User::query()
            ->where('id', $userId)
            ->where('manager_id', $manager->id)
            ->firstOrFail();

        $task = null;
        $message = 'تذكير: راجع مهامك المفتوحة في إسناد';

        if ($templateId) {
            $template = RecurringTaskTemplate::query()
                ->where('id', $templateId)
                ->where('assigned_to_id', $employee->id)
                ->firstOrFail();
            $task = Task::query()
                ->where('recurring_template_id', $template->id)
                ->where('assigned_to', $employee->id)
                ->whereNotIn('status', ['completed'])
                ->latest()
                ->first();
            $message = 'تذكير بالمهمة المتكررة: '.$template->title;
        }

        $employee->notify(new TaskReminder($task, $message));

        $channels = ['إشعار داخل المنصة'];
        if (! empty($employee->email)) {
            $channels[] = 'بريد';
        }

        $this->dispatch('toast', type: 'success', message: 'أُرسل التذكير: '.implode(' + ', $channels));
    }

    public function sendTeamReminder(): void
    {
        $this->authorize('esnad.tasks.team.view');
        /** @var User $manager */
        $manager = auth()->user();
        $employees = User::query()->where('manager_id', $manager->id)->where('is_active', true)->get();
        $sent = 0;
        foreach ($employees as $employee) {
            $hasOpen = Task::query()
                ->where('assigned_to', $employee->id)
                ->whereNotIn('status', ['completed'])
                ->exists();
            if (! $hasOpen) {
                continue;
            }
            $employee->notify(new TaskReminder(null, 'تذكير جماعي: راجع مهامك المفتوحة في إسناد'));
            $sent++;
        }
        $this->dispatch('toast', type: 'success', message: "أُرسل تذكير جماعي إلى {$sent} موظفاً");
    }

    /** @return Collection<int, Task> */
    private function overdueTasks(User $user): Collection
    {
        $scopeIds = collect([$user->id])->merge(
            User::query()->where('manager_id', $user->id)->pluck('id')
        );

        return Task::query()
            ->overdue()
            ->whereIn('assigned_to', $scopeIds->unique())
            ->when($this->assigneeId, fn ($q) => $q->where('assigned_to', $this->assigneeId))
            ->with(['assignee:id,name', 'project:id,name'])
            ->latest('due_date')
            ->get();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $service = app(WorkloadService::class);
        $subordinateIds = User::query()->where('manager_id', $user->id)->pluck('id');
        $teamScopeIds = $subordinateIds->values()->push($user->id)->unique()->values();

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

        $teamTasks = Task::query()
            ->teamOf($user)
            ->when($this->assigneeId, fn ($q) => $q->where('assigned_to', $this->assigneeId))
            ->with(['assignee:id,name', 'project:id,name'])
            ->latest()
            ->get();

        $templatesQuery = RecurringTaskTemplate::query()
            ->with(['assignee:id,name'])
            ->withCount([
                'generatedTasks as open_instances_count' => fn ($q) => $q->whereNotIn('status', ['completed']),
                'generatedTasks as completed_instances_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->latest();

        if (! $user->can('esnad.tasks.all.view')) {
            $templatesQuery->where(function ($q) use ($user, $teamScopeIds) {
                $q->where('created_by', $user->id)
                    ->orWhereIn('assigned_to_id', $teamScopeIds);
            });
        }

        $followUp = collect();
        if ($this->followUpUserId) {
            $followUp = Task::query()
                ->whereNotNull('recurring_template_id')
                ->where('assigned_to', $this->followUpUserId)
                ->whereNotIn('status', ['completed'])
                ->with('assignee:id,name')
                ->latest()
                ->limit(40)
                ->get(['id', 'title', 'status', 'due_date', 'assigned_to', 'recurring_template_id', 'completed_at']);
        }

        $openRecurringAssigneeIds = Task::query()
            ->whereNotNull('recurring_template_id')
            ->whereNotIn('status', ['completed'])
            ->when(
                ! $user->can('esnad.tasks.all.view'),
                fn ($q) => $q->whereIn('assigned_to', $teamScopeIds)
            )
            ->distinct()
            ->pluck('assigned_to');

        $followUsers = User::query()
            ->where('is_active', true)
            ->whereIn('id', $openRecurringAssigneeIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($this->followUpUserId && ! $followUsers->contains('id', $this->followUpUserId)) {
            $this->followUpUserId = null;
            $followUp = collect();
        }

        $assigneeUsers = $user->can('esnad.tasks.all.view')
            ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : User::query()
                ->where('is_active', true)
                ->whereIn('id', $teamScopeIds)
                ->orderBy('name')
                ->get(['id', 'name']);

        $statusLabels = [
            'new' => 'جديدة',
            'in_progress' => 'قيد التنفيذ',
            'pending_review' => 'بانتظار المراجعة',
            'completed' => 'مكتملة',
            'overdue' => 'متأخرة',
        ];

        return view('livewire.tasks.team-tasks-index', [
            'approvalQueue' => Task::query()
                ->pendingApprovalFor($user)
                ->when($this->assigneeId, fn ($q) => $q->where('assigned_to', $this->assigneeId))
                ->with(['assignee:id,name', 'project:id,name'])
                ->latest()
                ->get(),
            'teamTasks' => $teamTasks,
            'overdueTasks' => $this->overdueTasks($user),
            'ratings' => Task::RATINGS,
            'detailTask' => $detailTask,
            'statusLabels' => $statusLabels,
            'rows' => $service->board($user),
            'threshold' => $service->threshold(),
            'recurringTemplates' => $templatesQuery->get(),
            'followUp' => $followUp,
            'followUsers' => $followUsers,
            'users' => $assigneeUsers,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'متابعة الفريق']);
    }
}
