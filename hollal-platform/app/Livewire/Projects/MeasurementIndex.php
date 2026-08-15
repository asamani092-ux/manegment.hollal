<?php

namespace App\Livewire\Projects;

use App\Models\MeasurementForm;
use App\Models\MeasurementResponse;
use App\Models\Program;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Measurement forms + recent results. Time: O(n) | Space: O(page). */
class MeasurementIndex extends Component
{
    use WithPagination;

    public string $programFilter = '';

    public string $kindFilter = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'programFilter' => ['except' => ''],
        'kindFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('projects.measurement.view'), 403);
    }

    public function updatingProgramFilter(): void
    {
        $this->resetPage();
    }

    public function updatingKindFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.projects.measurement-index', [
            'forms' => MeasurementForm::query()
                ->select(['id', 'program_id', 'title', 'kind', 'created_at'])
                ->with('program:id,name')
                ->when($this->programFilter, fn ($q) => $q->where('program_id', $this->programFilter))
                ->when($this->kindFilter, fn ($q) => $q->where('kind', $this->kindFilter))
                ->latest()
                ->paginate(20),
            'responses' => MeasurementResponse::query()
                ->select(['id', 'project_id', 'measurement_form_id', 'phase', 'total_score', 'max_score', 'created_at'])
                ->with([
                    'form:id,title',
                    'project:id,name',
                ])
                ->latest('id')
                ->limit(25)
                ->get(),
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'kindOptions' => [
                MeasurementForm::KIND_TEST,
                MeasurementForm::KIND_SATISFACTION,
            ],
        ]);
    }
}
