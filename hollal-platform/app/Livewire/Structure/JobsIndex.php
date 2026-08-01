<?php

namespace App\Livewire\Structure;

use App\Models\OrgUnit;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Job cards (OrgUnit LEVEL_JOB) list. Time: O(n) | Space: O(page). */
class JobsIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('structure.positions.manage')
            || auth()->user()->can('structure.view'),
            403
        );
    }

    public function render(): View
    {
        return view('livewire.structure.jobs-index', [
            'jobs' => OrgUnit::query()
                ->select(['id', 'name', 'level', 'parent_id', 'manager_id', 'job_purpose'])
                ->where('level', OrgUnit::LEVEL_JOB)
                ->with(['parent:id,name', 'manager:id,name'])
                ->orderBy('name')
                ->paginate(20),
        ]);
    }
}
