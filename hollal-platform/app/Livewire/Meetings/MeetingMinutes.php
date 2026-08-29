<?php

namespace App\Livewire\Meetings;

use App\Mail\MeetingMinutesMailable;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Task;
use App\Models\User;
use App\Services\MeetingService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;

class MeetingMinutes extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

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

    public bool $showApproveModal = false;

    public bool $allowMissingSignatures = false;

    public string $missingSignaturesReason = '';

    /** P2 wave C — first confirm without a saved signature prompts to save one. */
    public bool $showSignatureModal = false;

    public $signatureFile = null;

    /** P2 wave C — chair/secretary uploads a manually signed PDF for archive. */
    public bool $showSignedUploadModal = false;

    public $signedPdfFile = null;

    public function mount(Meeting $meeting): void
    {
        $this->meeting = $meeting->load(['chair:id,name', 'secretary:id,name', 'attendees:id,name,electronic_signature', 'guests']);
        $this->authorize('view', $this->meeting);

        $this->meeting->syncRuntimeStatus();
        $this->meeting->refresh();

        app(MeetingService::class)->notifyMinutesReadyIfDue($this->meeting);
    }

    public function openItemCreate(): void
    {
        $this->authorize('update', $this->meeting);
        if (! $this->meeting->allowsItemEdit()) {
            $this->dispatch('toast', type: 'error', message: 'المحضر معتمد — التعديل عبر مسار التعديل فقط');

            return;
        }
        $this->resetItemForm();
        $this->showItemModal = true;
    }

    /**
     * Open item form prefilled from an agenda line (قرار وتوصية).
     * Time: O(1) | Space: O(1)
     */
    public function openDecisionFromAgenda(string $topic): void
    {
        $this->authorize('update', $this->meeting);
        if (! $this->meeting->allowsItemEdit()) {
            $this->dispatch('toast', type: 'error', message: 'المحضر معتمد — التعديل عبر مسار التعديل فقط');

            return;
        }

        $topic = trim($topic);
        if ($topic === '') {
            $this->dispatch('toast', type: 'error', message: 'سطر جدول الأعمال فارغ');

            return;
        }

        $this->resetItemForm();
        $this->topic = \Illuminate\Support\Str::limit($topic, 255, '');
        $this->showItemModal = true;
    }

    public function openItemEdit(int $id): void
    {
        $this->authorize('update', $this->meeting);
        if (! $this->meeting->allowsItemEdit()) {
            $this->dispatch('toast', type: 'error', message: 'المحضر معتمد — التعديل عبر مسار التعديل فقط');

            return;
        }
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

    public function openApproveModal(): void
    {
        $this->authorize('update', $this->meeting);
        $this->allowMissingSignatures = false;
        $this->missingSignaturesReason = '';
        $this->showApproveModal = true;
    }

    public function approveMinutes(): void
    {
        $this->authorize('update', $this->meeting);

        try {
            app(MeetingService::class)->approveMinutes(
                $this->meeting,
                auth()->user(),
                $this->allowMissingSignatures,
                $this->missingSignaturesReason ?: null,
            );
            $this->meeting->refresh();
            $this->showApproveModal = false;
            $this->dispatch('toast', type: 'success', message: 'تم اعتماد المحضر');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * P2 wave C — single confirm action, gated to after the meeting ends.
     * When the user has no saved profile signature yet, prompts to save one
     * first, then stamps + confirms in the same flow.
     */
    public function confirmMyAttendance(): void
    {
        $this->authorize('view', $this->meeting);

        if (! $this->meeting->hasEnded()) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكن تأكيد الاطلاع قبل انتهاء الاجتماع');

            return;
        }

        if (blank(auth()->user()->signature_image_path)) {
            $this->showSignatureModal = true;

            return;
        }

        $this->performConfirm();
    }

    public function saveSignatureAndConfirm(): void
    {
        $this->authorize('view', $this->meeting);

        $this->validate(['signatureFile' => 'required|image|max:2048'], [], ['signatureFile' => 'صورة التوقيع']);

        $path = $this->signatureFile->store('signatures', 'local');
        auth()->user()->forceFill(['signature_image_path' => $path])->save();

        $this->signatureFile = null;
        $this->showSignatureModal = false;
        $this->performConfirm();
    }

    private function performConfirm(): void
    {
        try {
            app(MeetingService::class)->confirmAttendance($this->meeting, auth()->user());
            $this->meeting->refresh()->load('attendees');
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل اطّلاعك وتوقيعك على المحضر');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function openSignedUploadModal(): void
    {
        $this->authorize('update', $this->meeting);
        $this->signedPdfFile = null;
        $this->showSignedUploadModal = true;
    }

    /**
     * P2 wave C — chair uploads the physically signed PDF, archived alongside
     * the auto-generated electronic minutes (not replacing it).
     */
    public function uploadSignedMinutes(): void
    {
        $this->authorize('update', $this->meeting);

        $this->validate(['signedPdfFile' => 'required|file|mimes:pdf|max:10240'], [], ['signedPdfFile' => 'ملف PDF الموقع']);

        $path = $this->signedPdfFile->store('meetings/signed', 'local');

        $document = Document::create([
            'title' => 'نسخة موقّعة يدويًا: '.$this->meeting->title,
            'category' => 'محاضر_الاجتماعات',
            'source_type' => 'meeting_signed',
            'source_id' => $this->meeting->id,
            'is_auto_archived' => false,
            'confidentiality' => 'department',
            'uploader_id' => auth()->id(),
            'path' => $path,
        ]);

        $this->meeting->update(['signed_document_id' => $document->id]);

        $this->signedPdfFile = null;
        $this->showSignedUploadModal = false;
        $this->dispatch('toast', type: 'success', message: 'تم رفع النسخة الموقعة وربطها بأرشيف الاجتماع');
    }

    public function saveItem(): void
    {
        if ($this->itemViewOnly) {
            return;
        }

        $this->authorize('update', $this->meeting);

        if (! $this->meeting->allowsItemEdit()) {
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
        if (! $this->meeting->allowsItemEdit()) {
            $this->dispatch('toast', type: 'error', message: 'المحضر معتمد ولا يمكن حذف البنود');

            return;
        }
        MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف البند');
    }

    /**
     * Step 4 — finalize open amendment after item edits.
     * Time: O(pdf) | Space: O(pdf)
     */
    public function finalizeAmendment(): void
    {
        $this->authorize('update', $this->meeting);
        $amendment = $this->meeting->editingAmendment();
        if ($amendment === null) {
            $this->dispatch('toast', type: 'error', message: 'لا يوجد تعديل مفتوح للاعتماد');

            return;
        }

        try {
            app(MeetingService::class)->finalizeAmendment($amendment, auth()->user());
            $this->meeting->refresh();
            $this->dispatch('toast', type: 'success', message: 'اعتُمد التغيير ونُشرت نسخة موسومة');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
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
        $this->meeting->loadMissing(['chair:id,name', 'secretary:id,name', 'attendees', 'guests']);

        $items = MeetingItem::query()
            ->select(['id', 'meeting_id', 'topic', 'discussion_summary', 'decision', 'responsible_id', 'due_date', 'status', 'task_id'])
            ->where('meeting_id', $this->meeting->id)
            ->with(['responsible:id,name', 'task:id,title,status'])
            ->orderBy('id')
            ->get();

        $canEditItems = $this->meeting->allowsItemEdit();
        $editingAmendment = $this->meeting->isApproved() ? $this->meeting->editingAmendment() : null;

        return view('livewire.meetings.meeting-minutes', [
            'items' => $items,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'unsignedCount' => $this->meeting->attendees->filter(fn ($u) => blank($u->pivot->confirmed_at ?? null))->count(),
            'canEditItems' => $canEditItems,
            'editingAmendment' => $editingAmendment,
        ])->layout('layouts.app', ['title' => 'محضر — '.$this->meeting->title]);
    }
}
