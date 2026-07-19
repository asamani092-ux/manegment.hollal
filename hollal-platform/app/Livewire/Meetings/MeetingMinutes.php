<?php

namespace App\Livewire\Meetings;

use App\Mail\MeetingMinutesMailable;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class MeetingMinutes extends Component
{
    use AuthorizesRequests;

    public Meeting $meeting;

    public bool $showItemModal = false;

    public bool $itemViewOnly = false;

    public ?int $itemId = null;

    public string $topic = '';

    public string $discussion_summary = '';

    public string $decision = '';

    public ?int $responsible_id = null;

    public ?string $due_date = null;

    public string $status = 'open';

    public function mount(Meeting $meeting): void
    {
        $this->meeting = $meeting->load(['chair:id,name', 'secretary:id,name']);
        $this->authorize('view', $this->meeting);
    }

    public function openItemCreate(): void
    {
        $this->authorize('update', $this->meeting);
        $this->resetItemForm();
        $this->showItemModal = true;
    }

    public function openItemEdit(int $id): void
    {
        $this->authorize('update', $this->meeting);
        $item = MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($id);
        $this->fillItemForm($item);
        $this->itemViewOnly = false;
        $this->showItemModal = true;
    }

    public function openItemView(int $id): void
    {
        $this->authorize('view', $this->meeting);
        $item = MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($id);
        $this->fillItemForm($item);
        $this->itemViewOnly = true;
        $this->showItemModal = true;
    }

    public function approveMinutes(): void
    {
        $this->authorize('update', $this->meeting);

        try {
            app(\App\Services\MeetingService::class)->approveMinutes($this->meeting, auth()->user());
            $this->meeting->refresh();
            $this->dispatch('toast', type: 'success', message: 'تم اعتماد المحضر');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function saveItem(): void
    {
        if ($this->itemViewOnly) {
            return;
        }

        $this->authorize('update', $this->meeting);

        if ($this->meeting->isApproved()) {
            $this->dispatch('toast', type: 'error', message: 'المحضر معتمد ولا يمكن تعديله (استخدم مسار التعديل)');

            return;
        }

        $this->validate([
            'topic' => 'required|string|max:255',
            'discussion_summary' => 'nullable|string',
            'decision' => 'nullable|string',
            'responsible_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $isEdit = (bool) $this->itemId;
        $existingStatus = $isEdit
            ? MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($this->itemId)->status
            : 'open';

        MeetingItem::updateOrCreate(
            ['id' => $this->itemId, 'meeting_id' => $this->meeting->id],
            [
                'topic' => $this->topic,
                'discussion_summary' => $this->discussion_summary ?: null,
                'decision' => $this->decision ?: null,
                'responsible_id' => $this->responsible_id,
                'due_date' => $this->due_date,
                'status' => $existingStatus,
            ]
        );

        $this->closeItemModal();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث البند' : 'تم إضافة البند');
    }

    public function deleteItem(int $id): void
    {
        $this->authorize('update', $this->meeting);
        MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف البند');
    }

    public function convertToTask(int $itemId): void
    {
        $this->authorize('update', $this->meeting);
        $this->authorize('esnad.tasks.create');

        if (! $this->meeting->isApproved()) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكن تحويل القرارات إلى مهام قبل اعتماد المحضر');

            return;
        }

        $item = MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($itemId);

        if (! filled($item->decision)) {
            $this->dispatch('toast', type: 'error', message: 'لا يوجد قرار لتحويله إلى مهمة');

            return;
        }

        if ($item->task_id) {
            $this->dispatch('toast', type: 'error', message: 'تم تحويل هذا البند مسبقاً');

            return;
        }

        if (! $item->responsible_id) {
            $this->dispatch('toast', type: 'error', message: 'يجب تحديد المسؤول قبل التحويل');

            return;
        }

        $task = Task::create([
            'title' => $item->decision,
            'description' => $item->discussion_summary,
            'type' => 'single',
            'assigned_by' => auth()->id(),
            'assigned_to' => $item->responsible_id,
            'meeting_id' => $this->meeting->id,
            'priority' => 'medium',
            'status' => 'new',
            'due_date' => $item->due_date,
        ]);

        $item->update([
            'task_id' => $task->id,
            'status' => 'in_progress',
        ]);

        $this->dispatch('toast', type: 'success', message: 'تم تحويل القرار إلى مهمة');
    }

    public function sendMinutesByEmail(): void
    {
        $this->authorize('view', $this->meeting);

        $this->meeting->load('attendees:id,name,email');
        $recipients = $this->meeting->attendees->pluck('email')->filter()->unique()->values();

        if ($recipients->isEmpty()) {
            $this->dispatch('toast', type: 'error', message: 'لا يوجد بريد إلكتروني للحضور');

            return;
        }

        try {
            Mail::to($recipients->all())->queue(new MeetingMinutesMailable($this->meeting));
            $this->dispatch('toast', type: 'success', message: 'تم إدراج إرسال المحضر في قائمة الانتظار (يتطلب SMTP)');
        } catch (\Throwable $exception) {
            report($exception);
            $this->dispatch('toast', type: 'error', message: 'تعذّر الإرسال — تحقق من إعداد SMTP');
        }
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
        $this->resetItemForm();
    }

    protected function fillItemForm(MeetingItem $item): void
    {
        $this->itemId = $item->id;
        $this->topic = $item->topic;
        $this->discussion_summary = $item->discussion_summary ?? '';
        $this->decision = $item->decision ?? '';
        $this->responsible_id = $item->responsible_id;
        $this->due_date = $item->due_date?->format('Y-m-d');
        $this->status = $item->status;
    }

    protected function resetItemForm(): void
    {
        $this->itemId = null;
        $this->itemViewOnly = false;
        $this->topic = '';
        $this->discussion_summary = '';
        $this->decision = '';
        $this->responsible_id = null;
        $this->due_date = null;
        $this->status = 'open';
        $this->resetValidation();
    }

    public function render(): View
    {
        $items = MeetingItem::query()
            ->select(['id', 'meeting_id', 'topic', 'discussion_summary', 'decision', 'responsible_id', 'due_date', 'status', 'task_id'])
            ->where('meeting_id', $this->meeting->id)
            ->with(['responsible:id,name', 'task:id,title,status'])
            ->orderBy('id')
            ->get();

        return view('livewire.meetings.meeting-minutes', [
            'items' => $items,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'محضر — '.$this->meeting->title]);
    }
}
