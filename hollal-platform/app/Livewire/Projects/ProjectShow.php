<?php

namespace App\Livewire\Projects;

use App\Models\Document;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Project detail — tabbed overview, tasks, files, finance, updates.
 * Time: O(n) per tab scoped query | Space: O(n).
 */
class ProjectShow extends Component
{
    use AuthorizesRequests;

    public Project $project;

    public string $activeTab = 'overview';

    public string $done = '';

    public string $next = '';

    public string $blockers = '';

    public string $decision_needed = '';

    public ?string $update_date = null;

    protected $queryString = [
        'activeTab' => ['except' => 'overview'],
    ];

    public function mount(Project $project): void
    {
        $this->project = $project->load(['manager:id,name', 'team:id,name']);
        $this->authorize('view', $this->project);
        $this->update_date = now()->format('Y-m-d');
    }

    public function setTab(string $tab): void
    {
        $allowed = ['overview', 'tasks', 'files', 'finance', 'updates'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    public function submitWeeklyUpdate(): void
    {
        $this->authorize('submitUpdate', $this->project);

        $this->validate([
            'done' => 'required|string',
            'next' => 'required|string',
            'blockers' => 'nullable|string',
            'decision_needed' => 'nullable|string',
            'update_date' => 'required|date',
        ]);

        ProjectUpdate::create([
            'project_id' => $this->project->id,
            'author_id' => auth()->id(),
            'done' => $this->done,
            'next' => $this->next,
            'blockers' => $this->blockers ?: null,
            'decision_needed' => $this->decision_needed ?: null,
            'date' => $this->update_date,
        ]);

        $this->reset(['done', 'next', 'blockers', 'decision_needed']);
        $this->update_date = now()->format('Y-m-d');
        $this->resetValidation();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ التحديث الأسبوعي');
    }

    public function render(): View
    {
        $tasks = collect();
        $documents = collect();
        $expenses = collect();
        $updates = collect();
        $actualSpend = 0.0;
        $completionPercent = 0;

        if ($this->activeTab === 'overview' || $this->activeTab === 'tasks') {
            $tasks = Task::query()
                ->select(['id', 'title', 'status', 'priority', 'due_date', 'assigned_to', 'project_id'])
                ->where('project_id', $this->project->id)
                ->with(['assignee:id,name'])
                ->latest()
                ->get();
        }

        if ($this->activeTab === 'overview') {
            $totalTasks = Task::where('project_id', $this->project->id)->count();
            $completedTasks = Task::where('project_id', $this->project->id)->where('status', 'completed')->count();
            $completionPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;
        }

        if ($this->activeTab === 'files') {
            $documents = Document::query()
                ->select(['id', 'title', 'path', 'category', 'confidentiality', 'uploader_id', 'project_id', 'created_at'])
                ->where('project_id', $this->project->id)
                ->visibleTo(auth()->user())
                ->with(['uploader:id,name'])
                ->latest()
                ->get();
        }

        if ($this->activeTab === 'finance') {
            $actualSpend = $this->project->actualSpend();

            $expenses = ExpenseRequest::query()
                ->select(['id', 'type', 'amount', 'status', 'reason', 'requester_id', 'project_id', 'created_at'])
                ->where('project_id', $this->project->id)
                ->with(['requester:id,name'])
                ->latest()
                ->get();
        }

        if ($this->activeTab === 'updates') {
            $updates = ProjectUpdate::query()
                ->select(['id', 'done', 'next', 'blockers', 'decision_needed', 'date', 'author_id', 'project_id', 'created_at'])
                ->where('project_id', $this->project->id)
                ->with(['author:id,name'])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();
        }

        $budget = $this->project->budget !== null ? (float) $this->project->budget : null;
        $remaining = $this->project->remainingBudget();

        return view('livewire.projects.project-show', [
            'tasks' => $tasks,
            'documents' => $documents,
            'expenses' => $expenses,
            'updates' => $updates,
            'actualSpend' => $actualSpend,
            'remaining' => $remaining,
            'completionPercent' => $completionPercent,
        ])->layout('layouts.app', ['title' => $this->project->name]);
    }
}
