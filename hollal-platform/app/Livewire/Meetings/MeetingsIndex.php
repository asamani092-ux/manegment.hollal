<?php

namespace App\Livewire\Meetings;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
        ], [
            'title.required' => 'عنوان الاجتماع مطلوب',
            'scheduled_at.required' => 'تاريخ ووقت الاجتماع مطلوب',
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
        return view('livewire.meetings.meetings-index', [
            'upcomingMeetings' => $this->meetingQuery(true)->paginate(6, pageName: 'upcomingPage'),
            'pastMeetings' => $this->meetingQuery(false)->paginate(6, pageName: 'pastPage'),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'الاجتماعات']);
    }
}
