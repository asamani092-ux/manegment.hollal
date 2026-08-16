<?php

namespace App\Livewire\Tasks;

use App\Models\Project;
use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use App\Services\WorkloadService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Workload board: team loads | recurring templates CRUD | follow-up reminders.
 * Time: O(n) | Space: O(n)
 */
class WorkloadBoard extends Component
{
    use AuthorizesRequests;

    public string $tab = 'loads';

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

    public function mount(?string $tab = null): void
    {
        abort_unless(
            auth()->user()->can('esnad.tasks.team.view') || auth()->user()->can('esnad.tasks.create'),
            403
        );

        $requested = $tab ?: request()->query('tab');
        if (in_array($requested, ['loads', 'recurring', 'reminders'], true)) {
            $this->tab = $requested;
        } elseif (! auth()->user()->can('esnad.tasks.team.view')) {
            $this->tab = 'recurring';
        }
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

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $service = app(WorkloadService::class);
        $subordinateIds = User::query()->where('manager_id', $user->id)->pluck('id');

        $templatesQuery = RecurringTaskTemplate::query()
            ->with(['assignee:id,name'])
            ->withCount([
                'generatedTasks as open_instances_count' => fn ($q) => $q->whereNotIn('status', ['completed']),
                'generatedTasks as completed_instances_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->latest();

        if (! $user->can('esnad.tasks.all.view')) {
            $scopeIds = $subordinateIds->values()->push($user->id)->unique()->values();
            $templatesQuery->where(function ($q) use ($user, $scopeIds) {
                $q->where('created_by', $user->id)
                    ->orWhereIn('assigned_to_id', $scopeIds);
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

        $teamScopeIds = $subordinateIds->values()->push($user->id)->unique()->values();

        // قائمة المتابعة: فقط من لديهم مهمة متكررة قائمة (غير مكتملة).
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

        return view('livewire.tasks.workload-board', [
            'rows' => $user->can('esnad.tasks.team.view') ? $service->board($user) : collect(),
            'threshold' => $service->threshold(),
            'recurringTemplates' => $templatesQuery->get(),
            'followUp' => $followUp,
            'followUsers' => $followUsers,
            'users' => $assigneeUsers,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'statusLabels' => [
                'new' => 'جديدة',
                'in_progress' => 'قيد التنفيذ',
                'pending_review' => 'بانتظار المراجعة',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ],
        ])->layout('layouts.app', ['title' => 'لوحة الأحمال']);
    }
}
