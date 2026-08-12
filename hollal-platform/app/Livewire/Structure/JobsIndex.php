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

    public string $search = '';

    public string $parentFilter = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'search' => ['except' => ''],
        'parentFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('structure.positions.manage')
            || auth()->user()->can('structure.view'),
            403
        );
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingParentFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.structure.jobs-index', [
            'jobs' => OrgUnit::query()
                ->select(['id', 'name', 'level', 'parent_id', 'manager_id', 'job_purpose'])
                ->where('level', OrgUnit::LEVEL_JOB)
                ->with(['parent:id,name', 'manager:id,name'])
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->parentFilter, fn ($q) => $q->where('parent_id', $this->parentFilter))
                ->orderBy('name')
                ->paginate(20),
            'parentUnits' => OrgUnit::query()
                ->select(['id', 'name'])
                ->whereIn('level', [OrgUnit::LEVEL_ADMINISTRATION, OrgUnit::LEVEL_UNIT])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
