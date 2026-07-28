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
                ->latest('scheduled_at')
                ->paginate(20),
        ]);
    }
}
