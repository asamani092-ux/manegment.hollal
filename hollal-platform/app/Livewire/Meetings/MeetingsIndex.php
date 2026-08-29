<?php

namespace App\Livewire\Meetings;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Committee;
use App\Models\Meeting;
use App\Models\MeetingGuest;
use App\Models\User;
use App\Notifications\MeetingGuestInvite;
use App\Notifications\MeetingInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class MeetingsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $viewOnly = false;

    public ?int $meetingId = null;

    public string $title = '';

    public ?string $scheduled_at = null;

    public string $agenda = '';

    public string $location = '';

    /** Maps to meetings.link (remote meeting URL). */
    public string $remote_link = '';

    /** @var array<int> */
    public array $attendeeIds = [];

    /** P2 wave C — searchable employee pick; consumed then reset. */
    public ?int $pickEmployeeId = null;

    /** P2 wave C — choosing a committee bulk-adds its members. */
    public ?int $pickCommitteeId = null;

    /**
     * P2 wave C — external guests (no employee account) queued for this save.
     *
     * @var list<array{name: string, email: string}>
     */
    public array $guestRows = [];

    public ?int $open = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'open' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->authorize('meetings.view');

        if ($this->open) {
            $this->openView($this->open);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage('upcomingPage');
        $this->resetPage('pastPage');
    }

    public function openCreate(): void
    {
        $this->authorize('meetings.create');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $meeting = Meeting::with('attendees:id')->findOrFail($id);
        $this->authorize('update', $meeting);
        $this->fillForm($meeting);
        $this->viewOnly = false;
        $this->showModal = true;
    }

    public function openView(int $id): void
    {
        $meeting = Meeting::with('attendees:id')->findOrFail($id);
        $this->authorize('view', $meeting);
        $this->fillForm($meeting);
        $this->viewOnly = true;
        $this->showModal = true;
    }

    /**
     * P2 wave C — searchable employee combobox: pick, append, reset the picker.
     * Time: O(1) | Space: O(1)
     */
    public function updatedPickEmployeeId(): void
    {
        if ($this->pickEmployeeId && ! in_array($this->pickEmployeeId, $this->attendeeIds, true)) {
            $this->attendeeIds[] = $this->pickEmployeeId;
        }

        $this->pickEmployeeId = null;
    }

    /**
     * P2 wave C — choosing a committee bulk-adds its members to attendees.
     * Time: O(m) members | Space: O(m)
     */
    public function updatedPickCommitteeId(): void
    {
        if (! $this->pickCommitteeId) {
            return;
        }

        $memberIds = Committee::with('members:id')->find($this->pickCommitteeId)
            ?->members->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];

        $this->attendeeIds = array_values(array_unique(array_merge($this->attendeeIds, $memberIds)));
        $this->pickCommitteeId = null;
    }

    /** Allow removing individually — whether hand-picked or added via a committee. */
    public function removeAttendee(int $userId): void
    {
        $this->attendeeIds = array_values(array_filter(
            $this->attendeeIds,
            fn ($id) => $id !== $userId
        ));
    }

    public function addGuestRow(): void
    {
        $this->guestRows[] = ['name' => '', 'email' => ''];
    }

    public function removeGuestRow(int $index): void
    {
        unset($this->guestRows[$index]);
        $this->guestRows = array_values($this->guestRows);
    }

    /** Remove an already-persisted guest invite (blocked once they've confirmed). */
    public function removeGuest(int $guestId): void
    {
        $meeting = Meeting::findOrFail($this->meetingId);
        $this->authorize('update', $meeting);

        $guest = MeetingGuest::where('meeting_id', $meeting->id)->findOrFail($guestId);

        if ($guest->confirmed_at !== null) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكن حذف ضيف أكّد الاطلاع بالفعل');

            return;
        }

        $guest->delete();
        $this->dispatch('toast', type: 'success', message: 'أُزيل الضيف');
    }

    public function save(): void
    {
        if ($this->viewOnly) {
            return;
        }

        $isEdit = (bool) $this->meetingId;

        $this->validate([
            'title' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'agenda' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'remote_link' => 'nullable|string|max:500',
            'attendeeIds' => 'array',
            'attendeeIds.*' => 'integer|exists:users,id',
            'guestRows.*.name' => 'nullable|string|max:255|required_with:guestRows.*.email',
            'guestRows.*.email' => 'nullable|email|max:255|required_with:guestRows.*.name',
        ], [
            'title.required' => 'عنوان الاجتماع مطلوب',
            'scheduled_at.required' => 'تاريخ ووقت الاجتماع مطلوب',
            'guestRows.*.name.required_with' => 'اسم الضيف مطلوب عند إدخال البريد',
            'guestRows.*.email.required_with' => 'بريد الضيف مطلوب عند إدخال الاسم',
        ]);

        $payload = [
            'title' => $this->title,
            'scheduled_at' => $this->scheduled_at,
            'agenda' => $this->agenda ?: null,
            'location' => $this->location ?: null,
            'link' => $this->remote_link ?: null,
        ];

        $previousAttendeeIds = [];

        if ($isEdit) {
            $meeting = Meeting::with('attendees:id')->findOrFail($this->meetingId);
            $this->authorize('update', $meeting);
            $previousAttendeeIds = $meeting->attendees->pluck('id')->all();
            $meeting->update($payload);
        } else {
            $this->authorize('meetings.create');
            $meeting = Meeting::create($payload + [
                'status' => 'scheduled',
                'chair_id' => auth()->id(),
            ]);
        }

        if (auth()->user()->can('update', $meeting) || ! $isEdit) {
            $meeting->attendees()->sync($this->attendeeIds);
        }

        $meeting->refresh()->load('attendees');
        $this->notifyAttendees($meeting, $previousAttendeeIds, ! $isEdit);
        $this->persistGuests($meeting);

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث الاجتماع' : 'تم إنشاء الاجتماع');
    }

    /**
     * On create: invite all attendees. On update: invite newly added attendees.
     *
     * @param  list<int>  $previousAttendeeIds
     */
    private function notifyAttendees(Meeting $meeting, array $previousAttendeeIds, bool $isCreate): void
    {
        $targets = $isCreate
            ? $meeting->attendees
            : $meeting->attendees->whereNotIn('id', $previousAttendeeIds);

        foreach ($targets as $attendee) {
            $attendee->notify(new MeetingInvite($meeting));
        }
    }

    /**
     * P2 wave C — persist queued external guest rows and email each an
     * invite carrying their unique short link. Existing guests are left
     * untouched (cumulative — removal only via the explicit removeGuest()
     * action). Time: O(g) queued guests | Space: O(g)
     */
    private function persistGuests(Meeting $meeting): void
    {
        $rows = collect($this->guestRows)
            ->filter(fn ($row) => trim($row['name'] ?? '') !== '' && trim($row['email'] ?? '') !== '')
            ->values();

        foreach ($rows as $row) {
            $guest = MeetingGuest::create([
                'meeting_id' => $meeting->id,
                'name' => $row['name'],
                'email' => $row['email'],
                'token' => Str::random(48),
                'invited_by' => auth()->id(),
            ]);

            Notification::route('mail', $guest->email)->notify(new MeetingGuestInvite($meeting, $guest));
        }

        $this->guestRows = [];
    }

    public function delete(int $id): void
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorize('delete', $meeting);
        $meeting->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف الاجتماع');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function fillForm(Meeting $meeting): void
    {
        $this->meetingId = $meeting->id;
        $this->title = $meeting->title;
        $this->scheduled_at = $meeting->scheduled_at?->format('Y-m-d\TH:i');
        $this->agenda = $meeting->agenda ?? '';
        $this->location = $meeting->location ?? '';
        $this->remote_link = $meeting->link ?? '';
        $this->attendeeIds = $meeting->attendees->pluck('id')->all();
        $this->guestRows = [];
        $this->pickEmployeeId = null;
        $this->pickCommitteeId = null;
    }

    protected function resetForm(): void
    {
        $this->meetingId = null;
        $this->viewOnly = false;
        $this->title = '';
        $this->scheduled_at = null;
        $this->agenda = '';
        $this->location = '';
        $this->remote_link = '';
        $this->attendeeIds = [];
        $this->guestRows = [];
        $this->pickEmployeeId = null;
        $this->pickCommitteeId = null;
        $this->resetValidation();
    }

    protected function meetingQuery(bool $upcoming)
    {
        return Meeting::query()
            ->select(['id', 'title', 'scheduled_at', 'agenda', 'location', 'link', 'status'])
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when(
                $upcoming,
                fn ($q) => $q->where('scheduled_at', '>=', now())->orderBy('scheduled_at'),
                fn ($q) => $q->where('scheduled_at', '<', now())->orderByDesc('scheduled_at')
            );
    }

    public function render(): View
    {
        Meeting::query()
            ->where('scheduled_at', '<', now())
            ->whereNotIn('status', [Meeting::STATUS_COMPLETED, Meeting::STATUS_CANCELLED])
            ->update(['status' => Meeting::STATUS_COMPLETED]);

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('livewire.meetings.meetings-index', [
            'upcomingMeetings' => $this->meetingQuery(true)->paginate(6, pageName: 'upcomingPage'),
            'pastMeetings' => $this->meetingQuery(false)->paginate(6, pageName: 'pastPage'),
            'users' => $users,
            'pickableUsers' => $users->whereNotIn('id', $this->attendeeIds)->values(),
            'attendeeUsers' => $users->whereIn('id', $this->attendeeIds),
            'committees' => Committee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'existingGuests' => $this->meetingId
                ? MeetingGuest::where('meeting_id', $this->meetingId)->orderBy('id')->get()
                : collect(),
        ])->layout('layouts.app', ['title' => 'الاجتماعات']);
    }
}
