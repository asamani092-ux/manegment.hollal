<?php

namespace App\Livewire\Projects;

use App\Models\ProjectVisit;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Cross-project visits list. Time: O(n) | Space: O(page). */
class VisitsIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('projects.visits.view'), 403);
    }

    public function render(): View
    {
        return view('livewire.projects.visits-index', [
            'visits' => ProjectVisit::query()
                ->select(['id', 'project_id', 'visitor_id', 'scheduled_on', 'purpose', 'status'])
                ->with(['project:id,name', 'visitor:id,name'])
                ->latest('scheduled_on')
                ->paginate(20),
        ]);
    }
}
