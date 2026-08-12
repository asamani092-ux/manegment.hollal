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

    public bool $showCloseModal = false;

    public ?int $closingId = null;

    public string $closeReason = '';

    protected $queryString = ['search' => ['except' => '']];

    public function mount(): void
    {
        $this->authorize('meetings.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openClose(int $id): void
    {
        $this->authorize('meetings.update');
        $this->closingId = $id;
        $this->closeReason = '';
        $this->showCloseModal = true;
    }

    public function closeDecision(): void
    {
        $this->authorize('meetings.update');

        $this->validate([
            'closeReason' => 'required|string|max:255',
        ], [], ['closeReason' => 'سبب الإغلاق']);

        $item = MeetingItem::query()
            ->where('status', '!=', 'done')
            ->findOrFail($this->closingId);

        $item->update([
            'status' => 'done',
            'close_reason' => $this->closeReason,
            'closed_at' => now(),
        ]);

        $this->showCloseModal = false;
        $this->closingId = null;
        $this->closeReason = '';
        $this->dispatch('ds-toast', message: 'أُغلق القرار');
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
