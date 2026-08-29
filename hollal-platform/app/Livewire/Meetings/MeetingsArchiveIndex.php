<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use App\Models\MeetingAmendment;
use App\Services\MeetingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Archived / approved meeting minutes. Time: O(n) | Space: O(page). */
class MeetingsArchiveIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $month = '';

    public bool $showAmendModal = false;

    public ?int $amendingMeetingId = null;

    public string $amendNote = '';

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

    public function openAmendRequest(int $meetingId): void
    {
        abort_unless(auth()->user()->can('meetings.update'), 403);
        $this->amendingMeetingId = $meetingId;
        $this->amendNote = '';
        $this->showAmendModal = true;
    }

    public function submitAmendRequest(): void
    {
        abort_unless(auth()->user()->can('meetings.update'), 403);
        $this->validate(['amendNote' => 'required|string|max:500'], [], ['amendNote' => 'سبب التعديل']);

        $meeting = Meeting::query()
            ->where('approval_status', Meeting::APPROVAL_APPROVED)
            ->findOrFail($this->amendingMeetingId);

        try {
            app(MeetingService::class)->requestAmendment($meeting, auth()->user(), $this->amendNote);
        } catch (\Throwable $e) {
            $this->dispatch('ds-toast', message: $e->getMessage());

            return;
        }

        $this->showAmendModal = false;
        $this->amendingMeetingId = null;
        $this->amendNote = '';
        $this->dispatch('ds-toast', message: 'أُرسل طلب التعديل بانتظار الموافقة');
    }

    /** Step 2 — unlock minutes for item edits. */
    public function approveAmendment(int $amendmentId): void
    {
        abort_unless(auth()->user()->can('meetings.update'), 403);
        $amendment = MeetingAmendment::query()->findOrFail($amendmentId);

        try {
            app(MeetingService::class)->approveAmendment($amendment, auth()->user());
            $this->dispatch('ds-toast', message: 'فُتح المحضر للتعديل — عدّل البنود ثم اعتمد التغيير');
        } catch (\Throwable $e) {
            $this->dispatch('ds-toast', message: $e->getMessage());
        }
    }

    /** Step 4 — publish labeled DocumentVersion after edits. */
    public function finalizeAmendment(int $amendmentId): void
    {
        abort_unless(auth()->user()->can('meetings.update'), 403);
        $amendment = MeetingAmendment::query()->findOrFail($amendmentId);

        try {
            app(MeetingService::class)->finalizeAmendment($amendment, auth()->user());
            $this->dispatch('ds-toast', message: 'اعتُمد التغيير ونُشرت نسخة موسومة (الأصل محفوظ)');
        } catch (\Throwable $e) {
            $this->dispatch('ds-toast', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.meetings.meetings-archive-index', [
            'meetings' => Meeting::query()
                ->select(['id', 'title', 'scheduled_at', 'approval_status', 'version', 'updated_at', 'signed_document_id', 'archived_document_id'])
                ->where('approval_status', Meeting::APPROVAL_APPROVED)
                ->with(['amendments' => fn ($q) => $q->orderByDesc('id')])
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
