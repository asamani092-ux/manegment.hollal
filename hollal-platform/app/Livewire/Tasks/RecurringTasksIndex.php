<?php

namespace App\Livewire\Tasks;

use App\Models\Project;
use App\Models\RecurringTaskTemplate;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 02-B3 — manage recurring task templates.
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

    public string $required_evidence = '';

    public function mount(): void
    {
        $this->authorize('esnad.tasks.create');
    }

    public function openCreate(): void
    {
        $this->authorize('esnad.tasks.create');
        $this->reset(['title', 'description', 'assigned_to_id', 'project_id', 'day_of_week', 'day_of_month', 'required_evidence']);
        $this->pattern = 'أسبوعي';
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
        return view('livewire.tasks.recurring-tasks-index', [
            'templates' => RecurringTaskTemplate::with('assignee:id,name')->latest()->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'المهام المتكررة']);
    }
}
