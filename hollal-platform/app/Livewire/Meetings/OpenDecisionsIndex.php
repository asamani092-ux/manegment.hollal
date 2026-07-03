<?php

namespace App\Livewire\Meetings;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\MeetingItem;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class OpenDecisionsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $search = '';

    protected $queryString = ['search' => ['except' => '']];

    public function mount(): void
    {
        $this->authorize('meetings.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $decisions = MeetingItem::query()
            ->select(['id', 'meeting_id', 'topic', 'decision', 'responsible_id', 'due_date', 'status', 'task_id'])
            ->whereNotNull('decision')
            ->where('decision', '!=', '')
            ->where('status', '!=', 'done')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('topic', 'like', '%'.$this->search.'%')
                    ->orWhere('decision', 'like', '%'.$this->search.'%');
            }))
            ->with([
                'meeting:id,title',
                'responsible:id,name',
                'task:id,title',
            ])
            ->latest()
            ->paginate(10);

        return view('livewire.meetings.open-decisions-index', [
            'decisions' => $decisions,
        ])->layout('layouts.app', ['title' => 'قرارات مفتوحة']);
    }
}
