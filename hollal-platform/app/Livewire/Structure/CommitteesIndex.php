<?php

namespace App\Livewire\Structure;

use App\Models\Committee;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Committees list. Time: O(n) | Space: O(page). */
class CommitteesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $activeFilter = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'search' => ['except' => ''],
        'activeFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('structure.committees.manage')
            || auth()->user()->can('structure.view'),
            403
        );
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.structure.committees-index', [
            'committees' => Committee::query()
                ->select(['id', 'name', 'mandate', 'chair_id', 'is_active', 'created_at'])
                ->with('chair:id,name')
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->activeFilter !== '', fn ($q) => $q->where('is_active', $this->activeFilter === '1'))
                ->latest()
                ->paginate(20),
        ]);
    }
}
