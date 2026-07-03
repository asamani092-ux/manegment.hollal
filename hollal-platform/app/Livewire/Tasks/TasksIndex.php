<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Tasks (Esnad) — full CRUD, attachments, status updates, pagination.
 */
class TasksIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public string $statusFilter = '';

    public string $taskSearch = '';

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

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'taskSearch' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('tasks.view');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage('myTasksPage');
        $this->resetPage('delegatedPage');
    }

    public function updatingTaskSearch(): void
    {
        $this->resetPage('myTasksPage');
        $this->resetPage('delegatedPage');
    }

    public function openTaskCreate(): void
    {
        $this->authorize('tasks.create');
        $this->resetTaskForm();
        $this->showTaskModal = true;
    }

    public function openTaskEdit(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('update', $task);
        $this->fillTaskForm($task);
        $this->taskViewOnly = false;
        $this->showTaskModal = true;
    }

    public function openTaskView(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);
        $this->fillTaskForm($task);
        $this->taskViewOnly = true;
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
            $this->authorize('tasks.create');
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

        $this->validate($rules);

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
        $this->resetValidation();
    }

    protected function taskQuery(int $userId, string $scope)
    {
        $query = Task::query()
            ->select(['id', 'title', 'description', 'status', 'priority', 'due_date', 'project_id', 'assigned_by', 'assigned_to', 'attachment_path', 'submitted_file'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->taskSearch, fn ($q) => $q->where('title', 'like', '%'.$this->taskSearch.'%'));

        if ($scope === 'my') {
            $query->where('assigned_to', $userId)
                ->with(['project:id,name', 'assigner:id,name']);
        } else {
            $query->where('assigned_by', $userId)
                ->with(['project:id,name', 'assignee:id,name']);
        }

        return $query->latest();
    }

    public function render(): View
    {
        $userId = auth()->id();

        return view('livewire.tasks.tasks-index', [
            'myTasks' => $this->taskQuery($userId, 'my')->paginate(6, pageName: 'myTasksPage'),
            'assignedByMe' => $this->taskQuery($userId, 'delegated')->paginate(6, pageName: 'delegatedPage'),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'statusOptions' => ['new', 'in_progress', 'pending_review', 'completed', 'overdue'],
        ])->layout('layouts.app', ['title' => 'إسناد']);
    }
}
