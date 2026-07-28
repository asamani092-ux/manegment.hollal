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

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('structure.committees.manage')
            || auth()->user()->can('structure.view'),
            403
        );
    }

    public function render(): View
    {
        return view('livewire.structure.committees-index', [
            'committees' => Committee::query()
                ->select(['id', 'name', 'mandate', 'chair_id', 'is_active', 'created_at'])
                ->with('chair:id,name')
                ->latest()
                ->paginate(20),
        ]);
    }
}
