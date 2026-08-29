<?php

namespace App\Livewire\Meetings;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Services\TaskLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Open decisions grouped by meeting, then drill-down to act.
 * Time: O(n) | Space: O(n)
 */
class OpenDecisionsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $search = '';

    public string $tab = 'open';

    public ?int $meetingId = null;

    public bool $showCloseModal = false;

    public ?int $closingId = null;

    public string $closeReason = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => 'open'],
        'meetingId' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->authorize('meetings.view');
        if (! in_array($this->tab, ['open', 'archived'], true)) {
            $this->tab = 'open';
        }

        // Close leftover open decisions whose task was completed before the sync fix.
        app(TaskLifecycleService::class)->healOpenDecisionsForCompletedTasks();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTab(): void
    {
        $this->meetingId = null;
        $this->resetPage();
    }

    public function selectMeeting(int $meetingId): void
    {
        $this->meetingId = $meetingId;
        $this->resetPage();
    }

    public function clearMeeting(): void
    {
        $this->meetingId = null;
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
        $this->dispatch('toast', type: 'success', message: 'أُغلق القرار');
    }

    /** @param  Builder<MeetingItem>  $query */
    private function applyDecisionScope(Builder $query, bool $archived): Builder
    {
        return $query
            ->whereNotNull('decision')
            ->where('decision', '!=', '')
            ->when(
                $archived,
                fn ($q) => $q->where('status', 'done'),
                fn ($q) => $q->where('status', '!=', 'done')
            )
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('topic', 'like', '%'.$this->search.'%')
                    ->orWhere('decision', 'like', '%'.$this->search.'%')
                    ->orWhere('close_reason', 'like', '%'.$this->search.'%');
            }));
    }

    public function render(): View
    {
        $archived = $this->tab === 'archived';

        $meetingGroups = null;
        $decisions = null;
        $selectedMeeting = null;

        if ($this->meetingId) {
            $selectedMeeting = Meeting::query()->select(['id', 'title', 'scheduled_at'])->find($this->meetingId);
            $decisions = $this->applyDecisionScope(MeetingItem::query(), $archived)
                ->where('meeting_id', $this->meetingId)
                ->select([
                    'id', 'meeting_id', 'topic', 'decision', 'responsible_id', 'due_date',
                    'status', 'task_id', 'close_reason', 'closed_at',
                ])
                ->with(['responsible:id,name', 'task:id,title'])
                ->latest()
                ->paginate(10);
        } else {
            $meetingGroups = Meeting::query()
                ->select(['id', 'title', 'scheduled_at'])
                ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->whereHas('items', fn ($q) => $this->applyDecisionScope($q, $archived))
                ->withCount(['items as open_count' => fn ($q) => $this->applyDecisionScope($q, $archived)])
                ->orderByDesc('scheduled_at')
                ->paginate(10);
        }

        return view('livewire.meetings.open-decisions-index', [
            'meetingGroups' => $meetingGroups,
            'decisions' => $decisions,
            'selectedMeeting' => $selectedMeeting,
            'archived' => $archived,
        ])->layout('layouts.app', ['title' => $archived ? 'قرارات مغلقة' : 'قرارات مفتوحة']);
    }
}
