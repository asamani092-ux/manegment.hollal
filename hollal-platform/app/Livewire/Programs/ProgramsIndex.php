<?php

namespace App\Livewire\Programs;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Program;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 06A-B1 — programs library (مكتبة برامج حلّل).
 */
class ProgramsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $search = '';

    public string $stageFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $description = null;

    public string $stage = Program::STAGE_DEVELOPMENT;

    public ?string $target_audience = null;

    public ?string $sessions_count = null;

    public ?string $hours_count = null;

    public ?string $execution_requirements = null;

    public ?string $platform_url = null;

    public ?string $platform_notes = null;

    public ?string $platform_steps = null;

    public function mount(): void
    {
        $this->authorize('projects.programs.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('projects.programs.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('projects.programs.manage');
        $program = Program::findOrFail($id);

        $this->editingId = $program->id;
        $this->name = $program->name;
        $this->description = $program->description;
        $this->stage = $program->stage;
        $this->target_audience = $program->target_audience;
        $this->sessions_count = $program->sessions_count !== null ? (string) $program->sessions_count : null;
        $this->hours_count = $program->hours_count !== null ? (string) $program->hours_count : null;
        $this->execution_requirements = $program->execution_requirements;
        $this->platform_url = $program->platform_url;
        $this->platform_notes = $program->platform_notes;
        $this->platform_steps = $program->platform_steps;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('projects.programs.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stage' => 'required|in:'.implode(',', [Program::STAGE_DEVELOPMENT, Program::STAGE_ACTIVE, Program::STAGE_SUSPENDED]),
            'target_audience' => 'nullable|string|max:255',
            'sessions_count' => 'nullable|integer|min:0',
            'hours_count' => 'nullable|integer|min:0',
            'execution_requirements' => 'nullable|string',
            'platform_url' => 'nullable|url|max:255',
            'platform_notes' => 'nullable|string',
            'platform_steps' => 'nullable|string',
        ], [], [
            'name' => 'اسم البرنامج',
            'stage' => 'المرحلة',
        ]);

        if ($this->editingId) {
            Program::findOrFail($this->editingId)->update($data);
        } else {
            Program::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('ds-toast', message: 'تم حفظ البرنامج');
    }

    public function render(): View
    {
        $programs = Program::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->stageFilter !== '', fn ($q) => $q->where('stage', $this->stageFilter))
            ->withCount('projects')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.programs.programs-index', [
            'programs' => $programs,
        ])->layout('layouts.app', ['title' => 'برامج حلّل']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = null;
        $this->stage = Program::STAGE_DEVELOPMENT;
        $this->target_audience = null;
        $this->sessions_count = null;
        $this->hours_count = null;
        $this->execution_requirements = null;
        $this->platform_url = null;
        $this->platform_notes = null;
        $this->platform_steps = null;
    }
}
