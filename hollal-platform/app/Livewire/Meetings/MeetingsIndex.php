<?php

namespace App\Livewire\Meetings;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Meeting;
use App\Models\User;
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

    public string $location = '';

    public string $link = '';

    public ?int $chair_id = null;

    public ?int $secretary_id = null;

    public string $status = 'scheduled';

    /** @var array<int> */
    public array $attendeeIds = [];

    protected $queryString = ['search' => ['except' => '']];

    public function mount(): void
    {
        $this->authorize('meetings.view');
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

        if ($isEdit) {
            $meeting = Meeting::findOrFail($this->meetingId);
            $this->authorize('update', $meeting);
        } else {
            $this->authorize('meetings.create');
        }

        $this->validate([
            'title' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'location' => 'nullable|string|max:255',
            'link' => 'nullable|url|max:500',
            'chair_id' => 'nullable|exists:users,id',
            'secretary_id' => 'nullable|exists:users,id',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'attendeeIds' => 'array',
            'attendeeIds.*' => 'integer|exists:users,id',
        ]);

        $meeting = Meeting::updateOrCreate(
            ['id' => $this->meetingId],
            [
                'title' => $this->title,
                'scheduled_at' => $this->scheduled_at,
                'location' => $this->location ?: null,
                'link' => $this->link ?: null,
                'chair_id' => $this->chair_id,
                'secretary_id' => $this->secretary_id,
                'status' => $this->status,
            ]
        );

        if (auth()->user()->can('update', $meeting)) {
            $meeting->attendees()->sync($this->attendeeIds);
        }

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث الاجتماع' : 'تم إنشاء الاجتماع');
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
        $this->location = $meeting->location ?? '';
        $this->link = $meeting->link ?? '';
        $this->chair_id = $meeting->chair_id;
        $this->secretary_id = $meeting->secretary_id;
        $this->status = $meeting->status;
        $this->attendeeIds = $meeting->attendees->pluck('id')->all();
    }

    protected function resetForm(): void
    {
        $this->meetingId = null;
        $this->viewOnly = false;
        $this->title = '';
        $this->scheduled_at = null;
        $this->location = '';
        $this->link = '';
        $this->chair_id = null;
        $this->secretary_id = null;
        $this->status = 'scheduled';
        $this->attendeeIds = [];
        $this->resetValidation();
    }

    protected function meetingQuery(bool $upcoming)
    {
        return Meeting::query()
            ->select(['id', 'title', 'scheduled_at', 'location', 'link', 'chair_id', 'secretary_id', 'status'])
            ->with(['chair:id,name', 'secretary:id,name'])
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
