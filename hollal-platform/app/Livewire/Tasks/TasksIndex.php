<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\TaskLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Tasks (Esnad) — full CRUD, attachments, status updates, pagination.
 * Time: O(n) | Space: O(n) for page-sized result sets.
 */
class TasksIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public string $statusFilter = '';

    public string $taskSearch = '';

    /** my | delegated | all */
    public string $listScope = 'my';

    /** cards | table */
    public string $viewMode = 'cards';

    /** @var array<int, string> */
    public array $approveRating = [];

    /** @var array<int, string> */
    public array $approveNote = [];

    public bool $showCompleted = false;

    public bool $showTaskModal = false;

    public bool $taskViewOnly = false;

    public ?int $taskId = null;

    public string $title = '';

    public string $description = '';

    public ?int $assigned_to = null;

    public ?int $project_id = null;

    public ?string $due_date = null;

    public string $priority = 'medium';

    public string $status = 'new';

    public ?TemporaryUploadedFile $attachment = null;

    public ?TemporaryUploadedFile $submittedFile = null;

    public ?string $existingAttachmentPath = null;

    public ?string $existingSubmittedPath = null;

    public string $noteBody = '';

    /** @var \Illuminate\Support\Collection<int, TaskNote> */
    public $taskNotes;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'taskSearch' => ['except' => ''],
        'listScope' => ['except' => 'my'],
        'viewMode' => ['except' => 'cards'],
        'open' => ['except' => null],
    ];

    public ?int $open = null;

    public function mount(): void
    {
        $this->authorize('esnad.tasks.view');
        $this->taskNotes = collect();

        if (! in_array($this->listScope, ['my', 'delegated', 'all'], true)) {
            $this->listScope = 'my';
        }

        if ($this->listScope === 'all' && ! auth()->user()->can('esnad.tasks.all.view')) {
            $this->listScope = 'my';
        }

        if (! in_array($this->viewMode, ['cards', 'table'], true)) {
            $this->viewMode = 'cards';
        }

        if ($this->open) {
            $this->openTaskView($this->open);
        }
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['cards', 'table'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function updatingStatusFilter(): void
    {
        $this->resetTaskPages();
    }

    public function updatingTaskSearch(): void
    {
        $this->resetTaskPages();
    }

    public function updatingListScope(): void
    {
        $this->resetTaskPages();
    }

    private function resetTaskPages(): void
    {
        $this->resetPage('myTasksPage');
        $this->resetPage('delegatedPage');
        $this->resetPage('allTasksPage');
        $this->resetPage('myCompletedPage');
        $this->resetPage('delegatedCompletedPage');
        $this->resetPage('allCompletedPage');
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
            unset($this->approveRating[$taskId], $this->approveNote[$taskId]);
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
            unset($this->approveRating[$taskId], $this->approveNote[$taskId]);
            $this->dispatch('toast', type: 'success', message: 'أُعيدت المهمة للتعديل');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function openTaskCreate(): void
    {
        $this->authorize('esnad.tasks.create');
        $this->resetTaskForm();
        $this->showTaskModal = true;
    }

    public function openTaskEdit(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('update', $task);
        $this->fillTaskForm($task);
        $this->taskViewOnly = false;
        $this->loadTaskNotes($task);
        $this->showTaskModal = true;
    }

    public function openTaskView(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);
        $this->fillTaskForm($task);
        $this->taskViewOnly = true;
        $this->loadTaskNotes($task);
        $this->showTaskModal = true;
    }

    public function saveTask(): void
    {
        if ($this->taskViewOnly) {
            return;
        }

        $isEdit = (bool) $this->taskId;

        if ($isEdit) {
            $task = Task::findOrFail($this->taskId);
            $this->authorize('update', $task);
        } else {
            $this->authorize('esnad.tasks.create');
        }

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'required|exists:users,id',
            'project_id' => 'nullable|exists:projects,id',
            'due_date' => 'nullable|date',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:new,in_progress,pending_review,completed,overdue',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
            'submittedFile' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
        ];

        $this->validate($rules, [
            'title.required' => 'عنوان المهمة مطلوب',
            'assigned_to.required' => 'يجب اختيار المُسند إليه',
            'assigned_to.exists' => 'المستخدم المحدد غير موجود',
        ]);

        $data = [
            'title' => $this->title,
            'description' => $this->description ?: null,
            'type' => 'single',
            'assigned_to' => $this->assigned_to,
            'project_id' => $this->project_id,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->due_date,
        ];

        if (! $isEdit) {
            $data['assigned_by'] = auth()->id();
        }

        if ($this->attachment) {
            $data['attachment_path'] = $this->attachment->store('tasks', 'local');
        }

        if ($this->submittedFile) {
            $data['submitted_file'] = $this->submittedFile->store('tasks', 'local');
        }

        if ($isEdit) {
            $task = Task::findOrFail($this->taskId);
            $previousAssignee = $task->assigned_to;
            $task->update($data);
            $task->refresh();

            if ($previousAssignee !== $task->assigned_to && $task->assigned_to) {
                User::find($task->assigned_to)?->notify(new TaskAssigned($task));
            }
        } else {
            $task = Task::create($data);
            User::find($task->assigned_to)?->notify(new TaskAssigned($task));

            // 02-B3 — non-blocking overload warning shown to the assigner.
            if (app(\App\Services\WorkloadService::class)->isOverloaded((int) $task->assigned_to)) {
                $this->dispatch('toast', type: 'warning', message: 'تنبيه: عبء عمل المُسند إليه مرتفع');
            }
        }

        $this->closeTaskModal();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث المهمة' : 'تم إسناد المهمة');
    }

    public function updateTaskStatus(int $taskId, string $newStatus): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);

        if (! in_array($newStatus, ['new', 'in_progress', 'pending_review', 'completed', 'overdue'], true)) {
            $this->dispatch('toast', type: 'error', message: 'حالة غير صالحة');

            return;
        }

        Task::findOrFail($taskId)->update(['status' => $newStatus]);
        $this->dispatch('toast', type: 'success', message: 'تم تحديث حالة المهمة');
    }

    public function addTaskNote(): void
    {
        $task = Task::findOrFail($this->taskId);
        $this->authorize('addNote', $task);

        $this->validate([
            'noteBody' => 'required|string|min:2|max:2000',
        ], [
            'noteBody.required' => 'نص الملاحظة مطلوب',
        ]);

        TaskNote::create([
            'task_id' => $task->id,
            'author_id' => auth()->id(),
            'body' => $this->noteBody,
        ]);

        $this->noteBody = '';
        $this->loadTaskNotes($task);
        $this->dispatch('toast', type: 'success', message: 'تمت إضافة الملاحظة');
    }

    public function deleteTask(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('delete', $task);
        $task->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف المهمة');
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
        $this->resetTaskForm();
    }

    protected function fillTaskForm(Task $task): void
    {
        $this->taskId = $task->id;
        $this->title = $task->title;
        $this->description = $task->description ?? '';
        $this->assigned_to = $task->assigned_to;
        $this->project_id = $task->project_id;
        $this->due_date = $task->due_date?->format('Y-m-d\TH:i');
        $this->priority = $task->priority;
        $this->status = $task->status;
        $this->existingAttachmentPath = $task->attachment_path;
        $this->existingSubmittedPath = $task->submitted_file;
    }

    protected function loadTaskNotes(Task $task): void
    {
        $this->taskNotes = $task->notes()->with('author:id,name')->get();
    }

    protected function resetTaskForm(): void
    {
        $this->taskId = null;
        $this->taskViewOnly = false;
        $this->title = '';
        $this->description = '';
        $this->assigned_to = null;
        $this->project_id = null;
        $this->due_date = null;
        $this->priority = 'medium';
        $this->status = 'new';
        $this->attachment = null;
        $this->submittedFile = null;
        $this->existingAttachmentPath = null;
        $this->existingSubmittedPath = null;
        $this->noteBody = '';
        $this->taskNotes = collect();
        $this->resetValidation();
    }

    /**
     * @param  'active'|'completed'|'any'  $completion
     */
    protected function taskQuery(int $userId, string $scope, string $completion = 'active')
    {
        $query = Task::query()
            ->select(['id', 'title', 'description', 'status', 'priority', 'due_date', 'project_id', 'assigned_by', 'assigned_to', 'attachment_path', 'submitted_file', 'self_rating'])
            ->when($this->taskSearch, fn ($q) => $q->where('title', 'like', '%'.$this->taskSearch.'%'))
            ->with(['project:id,name', 'assigner:id,name', 'assignee:id,name']);

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        } elseif ($completion === 'active') {
            $query->where('status', '!=', 'completed');
        } elseif ($completion === 'completed') {
            $query->where('status', 'completed');
        }

        if ($scope === 'my') {
            $query->where('assigned_to', $userId);
        } elseif ($scope === 'delegated') {
            $query->where('assigned_by', $userId);
        } elseif ($scope === 'all') {
            abort_unless(auth()->user()->can('esnad.tasks.all.view'), 403);
        }

        return $query->latest();
    }

    public function render(): View
    {
        $userId = auth()->id();
        $canSeeAll = auth()->user()->can('esnad.tasks.all.view');
        $filterIsCompleted = $this->statusFilter === 'completed';
        $filterIsActiveOnly = $this->statusFilter !== '' && $this->statusFilter !== 'completed';

        $showActiveLists = ! $filterIsCompleted;
        $showCompletedLists = $filterIsCompleted || ($this->statusFilter === '' && $this->showCompleted);

        return view('livewire.tasks.tasks-index', [
            'myTasks' => $showActiveLists
                ? $this->taskQuery($userId, 'my', $filterIsActiveOnly ? 'any' : 'active')->paginate(6, pageName: 'myTasksPage')
                : null,
            'assignedByMe' => $showActiveLists
                ? $this->taskQuery($userId, 'delegated', $filterIsActiveOnly ? 'any' : 'active')->paginate(6, pageName: 'delegatedPage')
                : null,
            'allTasks' => $canSeeAll && $this->listScope === 'all' && $showActiveLists
                ? $this->taskQuery($userId, 'all', $filterIsActiveOnly ? 'any' : 'active')->paginate(12, pageName: 'allTasksPage')
                : null,
            'myCompleted' => ($this->listScope !== 'all' && ($filterIsCompleted || $this->showCompleted))
                ? $this->taskQuery($userId, 'my', 'completed')->paginate(6, pageName: 'myCompletedPage')
                : null,
            'delegatedCompleted' => ($this->listScope !== 'all' && ($filterIsCompleted || $this->showCompleted))
                ? $this->taskQuery($userId, 'delegated', 'completed')->paginate(6, pageName: 'delegatedCompletedPage')
                : null,
            'allCompleted' => $canSeeAll && $this->listScope === 'all' && ($filterIsCompleted || $this->showCompleted)
                ? $this->taskQuery($userId, 'all', 'completed')->paginate(12, pageName: 'allCompletedPage')
                : null,
            'approvalQueue' => Task::query()
                ->pendingApprovalFor(auth()->user())
                ->with(['assignee:id,name', 'project:id,name'])
                ->latest()
                ->get(),
            'ratings' => Task::RATINGS,
            'showActiveLists' => $showActiveLists,
            'showCompletedLists' => $showCompletedLists || $filterIsCompleted,
            'canSeeAll' => $canSeeAll,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'statusOptions' => ['new', 'in_progress', 'pending_review', 'completed', 'overdue'],
            'currentTask' => $this->taskId ? Task::with(['assigner:id,name', 'assignee:id,name', 'project:id,name'])->find($this->taskId) : null,
        ])->layout('layouts.app', ['title' => 'إسناد']);
    }
}
