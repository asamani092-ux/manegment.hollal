<?php

namespace App\Livewire\Projects;

use App\Models\MeasurementForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Measurement forms list. Time: O(n) | Space: O(page). */
class MeasurementIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('projects.measurement.view'), 403);
    }

    public function render(): View
    {
        return view('livewire.projects.measurement-index', [
            'forms' => MeasurementForm::query()
                ->select(['id', 'program_id', 'title', 'kind', 'created_at'])
                ->with('program:id,name')
                ->latest()
                ->paginate(20),
        ]);
    }
}
