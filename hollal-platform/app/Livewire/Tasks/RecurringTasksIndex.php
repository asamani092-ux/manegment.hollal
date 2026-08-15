<?php

namespace App\Livewire\Tasks;

use App\Models\Project;
use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Recurring templates with start/end dates and follow-up report.
 * Time: O(n) | Space: O(n)
 */
class RecurringTasksIndex extends Component
{
    use AuthorizesRequests;

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

    public int $instancesLimit = 8;

    public ?int $followUpUserId = null;

    public function mount(): void
    {
        $this->authorize('esnad.tasks.create');
    }

    public function openCreate(): void
    {
        $this->authorize('esnad.tasks.create');
        $this->reset(['title', 'description', 'assigned_to_id', 'project_id', 'day_of_week', 'day_of_month', 'required_evidence', 'starts_on', 'ends_on']);
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
        $this->dispatch('toast', type: 'success', message: 'تم حفظ القالب المتكرر');
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('esnad.tasks.update');
        $template = RecurringTaskTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);
    }

    public function render(): View
    {
        $limit = max(1, min(30, $this->instancesLimit));
        $user = auth()->user();

        $templatesQuery = RecurringTaskTemplate::with([
            'assignee:id,name',
            'generatedTasks' => fn ($q) => $q->latest()->limit($limit)->select([
                'id', 'title', 'status', 'due_date', 'recurring_template_id', 'assigned_to',
            ]),
        ])->latest();

        if (! $user->can('esnad.tasks.all.view')) {
            $subIds = User::query()->where('manager_id', $user->id)->pluck('id')->push($user->id);
            $templatesQuery->where(function ($q) use ($user, $subIds) {
                $q->where('created_by', $user->id)
                    ->orWhereIn('assigned_to_id', $subIds);
            });
        }

        $templates = $templatesQuery->get();

        $followUp = collect();
        if ($this->followUpUserId) {
            $followUp = Task::query()
                ->whereNotNull('recurring_template_id')
                ->where('assigned_to', $this->followUpUserId)
                ->with('assignee:id,name')
                ->latest()
                ->limit(40)
                ->get(['id', 'title', 'status', 'due_date', 'assigned_to', 'recurring_template_id', 'completed_at']);
        }

        return view('livewire.tasks.recurring-tasks-index', [
            'templates' => $templates,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'followUp' => $followUp,
            'statusLabels' => [
                'new' => 'جديدة',
                'in_progress' => 'قيد التنفيذ',
                'pending_review' => 'بانتظار المراجعة',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ],
        ])->layout('layouts.app', ['title' => 'المهام المتكررة']);
    }
}
