<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Archived / approved meeting minutes. Time: O(n) | Space: O(page). */
class MeetingsArchiveIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $month = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'search' => ['except' => ''],
        'month' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingMonth(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('meetings.view')
            || auth()->user()->can('documents.view'),
            403
        );
    }

    public function render(): View
    {
        return view('livewire.meetings.meetings-archive-index', [
            'meetings' => Meeting::query()
                ->select(['id', 'title', 'scheduled_at', 'approval_status', 'updated_at'])
                ->where('approval_status', Meeting::APPROVAL_APPROVED)
                ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->when(
                    preg_match('/^\d{4}-\d{2}$/', $this->month) === 1,
                    fn ($q) => $q->whereYear('scheduled_at', substr($this->month, 0, 4))
                        ->whereMonth('scheduled_at', substr($this->month, 5, 2))
                )
                ->latest('scheduled_at')
                ->paginate(20),
        ]);
    }
}
