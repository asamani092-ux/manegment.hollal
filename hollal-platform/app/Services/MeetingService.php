<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Meeting;
use App\Models\MeetingAmendment;
use App\Models\MeetingItem;
use App\Models\User;
use App\Notifications\MeetingMinutesReady;
use Illuminate\Support\Facades\Storage;

/**
 * 03-B1 — meeting minutes approval cycle and amendments.
 * After approval, minutes are frozen. Amendment path:
 * request → approve (unlock edit) → edit items → finalize (labeled DocumentVersion).
 */
class MeetingService
{
    public function approveMinutes(
        Meeting $meeting,
        User $chair,
        bool $allowMissingSignatures = false,
        ?string $missingReason = null,
    ): Meeting {
        if ($meeting->isApproved()) {
            throw new \RuntimeException('المحضر معتمد بالفعل.');
        }

        $meeting->load('attendees');
        $unsigned = $meeting->attendees->filter(fn (User $u) => blank($u->pivot->confirmed_at ?? null));

        if ($unsigned->isNotEmpty() && ! $allowMissingSignatures) {
            throw new \RuntimeException('يوجد حضور بلا توقيع. أكّد مع سبب النقص أو انتظر التأكيدات.');
        }

        if ($unsigned->isNotEmpty() && blank($missingReason)) {
            throw new \RuntimeException('يلزم ذكر سبب نقص التوقيع (غائب / لا يلزم توقيع…).');
        }

        $meeting->update([
            'approval_status' => Meeting::APPROVAL_APPROVED,
            'approved_by' => $chair->id,
            'approved_at' => now(),
            'minutes_missing_signatures_reason' => $unsigned->isNotEmpty() ? $missingReason : null,
        ]);

        $this->archiveMinutes($meeting, $chair);

        if ($meeting->partnership_id && $meeting->partnership) {
            app(PartnershipPipelineService::class)->advanceIfBefore(
                $meeting->partnership,
                \App\Models\Partnership::STAGE_MEETING,
                $chair,
                'اعتماد محضر لقاء',
            );
        }

        return $meeting;
    }

    /**
     * Attendee confirms minutes and stamps their electronic signature. If the
     * user has an uploaded signature image (profile-stored, ج٤), a copy of
     * its path is frozen on the pivot so later profile changes never alter
     * already-confirmed history. Falls back to the typed signature text.
     * Time: O(1) | Space: O(1)
     */
    public function confirmAttendance(Meeting $meeting, User $user): void
    {
        if ($meeting->isApproved()) {
            throw new \RuntimeException('المحضر معتمد ولا يمكن تأكيد الحضور.');
        }

        if (! $meeting->hasEnded()) {
            throw new \RuntimeException('لا يمكن تأكيد الاطلاع على المحضر قبل انتهاء الاجتماع.');
        }

        if (! $meeting->attendees()->where('users.id', $user->id)->exists()
            && (int) $meeting->chair_id !== (int) $user->id
            && (int) $meeting->secretary_id !== (int) $user->id) {
            throw new \RuntimeException('لست من حضور هذا الاجتماع.');
        }

        $pivot = [
            'confirmed_at' => now(),
            'signature_text' => $user->electronic_signature ?: $user->name,
            'signature_image_path' => $user->signature_image_path,
        ];

        if (! $meeting->attendees()->where('users.id', $user->id)->exists()) {
            $meeting->attendees()->attach($user->id, $pivot);

            return;
        }

        $meeting->attendees()->updateExistingPivot($user->id, $pivot);
    }

    /**
     * P2 wave C — notify attendees once the meeting has ended, with a direct
     * link to the minutes page. Idempotent via minutes_notified_at.
     * Time: O(a) attendees | Space: O(1)
     */
    public function notifyMinutesReadyIfDue(Meeting $meeting): void
    {
        if ($meeting->minutes_notified_at !== null) {
            return;
        }

        if (! $meeting->hasEnded()) {
            return;
        }

        $meeting->loadMissing('attendees:id,name,email');

        foreach ($meeting->attendees as $attendee) {
            $attendee->notify(new MeetingMinutesReady($meeting));
        }

        $meeting->forceFill(['minutes_notified_at' => now()])->save();
    }

    /**
     * 03-B2 — auto-generate the minutes PDF and store it as a read-only,
     * source-linked archived document.
     */
    private function archiveMinutes(Meeting $meeting, User $chair): void
    {
        $pdf = app(MeetingMinutesPdfService::class)->output($meeting);

        $path = 'meetings/'.now()->format('Y/m').'/'.$meeting->id.'-minutes.pdf';
        Storage::disk('local')->put($path, $pdf);

        $document = Document::create([
            'title' => 'محضر اجتماع: '.$meeting->title,
            'category' => 'محاضر_الاجتماعات',
            'source_type' => 'meeting',
            'source_id' => $meeting->id,
            'is_auto_archived' => true,
            'confidentiality' => 'department',
            'uploader_id' => $chair->id,
            'path' => $path,
            'current_version' => 1,
        ]);

        \App\Models\DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'path' => $path,
            'change_note' => 'النسخة الأصلية المعتمدة',
            'uploaded_by' => $chair->id,
        ]);

        $meeting->update(['archived_document_id' => $document->id]);
    }

    /**
     * Any attendee may propose a pre-agenda discussion item before approval.
     */
    public function proposeItem(Meeting $meeting, User $proposer, string $topic): MeetingItem
    {
        if ($meeting->isApproved()) {
            throw new \RuntimeException('لا يمكن اقتراح بنود بعد اعتماد المحضر.');
        }

        return MeetingItem::create([
            'meeting_id' => $meeting->id,
            'topic' => $topic,
            'item_kind' => 'نقاشي',
            'proposed_by' => $proposer->id,
            'status' => 'open',
        ]);
    }

    /**
     * Amend an approved meeting: bumps the version and records an amendment,
     * leaving the original minutes intact.
     */
    public function amend(Meeting $meeting, User $approver, string $note): MeetingAmendment
    {
        if (! $meeting->isApproved()) {
            throw new \RuntimeException('لا يمكن تعديل محضر غير معتمد؛ عدّله مباشرة.');
        }

        $newVersion = $meeting->version + 1;

        $amendment = MeetingAmendment::create([
            'meeting_id' => $meeting->id,
            'version' => $newVersion,
            'note' => $note,
            'approved_by' => $approver->id,
            'created_at' => now(),
        ]);

        $meeting->update(['version' => $newVersion]);

        return $amendment;
    }

    /**
     * Step 1 — submit amendment request. Time: O(1) | Space: O(1)
     */
    public function requestAmendment(Meeting $meeting, User $requester, string $note): MeetingAmendment
    {
        if (! $meeting->isApproved()) {
            throw new \RuntimeException('لا يمكن تعديل محضر غير معتمد؛ عدّله مباشرة.');
        }

        if ($meeting->amendments()->whereIn('status', [
            MeetingAmendment::STATUS_PENDING,
            MeetingAmendment::STATUS_EDITING,
        ])->exists()) {
            throw new \RuntimeException('يوجد طلب تعديل معلّق أو جارٍ على هذا المحضر.');
        }

        return MeetingAmendment::create([
            'meeting_id' => $meeting->id,
            'version' => $meeting->version + 1,
            'note' => $note,
            'status' => MeetingAmendment::STATUS_PENDING,
            'requested_by' => $requester->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Step 2 — approve request and unlock minutes for item edits (no new PDF yet).
     * Time: O(1) + archive ensure | Space: O(1)
     */
    public function approveAmendment(MeetingAmendment $amendment, User $approver): MeetingAmendment
    {
        if ($amendment->status !== MeetingAmendment::STATUS_PENDING) {
            throw new \RuntimeException('لا يمكن اعتماد طلب غير معلّق.');
        }

        if ($amendment->requested_by !== null && (int) $amendment->requested_by === (int) $approver->id) {
            throw new \RuntimeException('لا يمكن لمقدّم الطلب الموافقة على طلبه.');
        }

        $meeting = $amendment->meeting()->firstOrFail();
        $this->ensureOriginalMinutesVersionFrozen($meeting, $approver);

        $amendment->forceFill([
            'status' => MeetingAmendment::STATUS_EDITING,
            'approved_by' => $approver->id,
        ])->save();

        return $amendment->fresh();
    }

    /**
     * Step 4 — finalize after item edits: bump version + labeled DocumentVersion.
     * Time: O(pdf) | Space: O(pdf)
     */
    public function finalizeAmendment(MeetingAmendment $amendment, User $finalizer): MeetingAmendment
    {
        if ($amendment->status !== MeetingAmendment::STATUS_EDITING) {
            throw new \RuntimeException('لا يمكن اعتماد تغيير بلا مرحلة تعديل مفتوحة.');
        }

        $meeting = $amendment->meeting()->firstOrFail();
        $newVersion = (int) $meeting->version + 1;

        $amendment->forceFill([
            'status' => MeetingAmendment::STATUS_APPROVED,
            'version' => $newVersion,
        ])->save();

        $meeting->update(['version' => $newVersion]);

        $this->storeAmendedMinutesVersion($meeting, $finalizer, (string) $amendment->note);

        return $amendment->fresh();
    }

    /**
     * Ensure archived PDF exists as DocumentVersion v1 before any edits.
     * Time: O(1) or O(pdf) if missing archive | Space: O(1)
     */
    private function ensureOriginalMinutesVersionFrozen(Meeting $meeting, User $actor): void
    {
        $meeting->refresh();
        $document = $this->resolveMinutesDocument($meeting, $actor);
        if ($document === null) {
            return;
        }

        if ($document->versions()->count() === 0 && $document->path) {
            \App\Models\DocumentVersion::create([
                'document_id' => $document->id,
                'version' => max(1, (int) $document->current_version),
                'path' => $document->path,
                'change_note' => 'النسخة الأصلية المعتمدة',
                'uploaded_by' => $document->uploader_id,
            ]);
            if (! $document->current_version) {
                $document->forceFill(['current_version' => 1])->save();
            }
        }
    }

    /**
     * Persist amended minutes as a new DocumentVersion (original kept).
     * Time: O(pdf) | Space: O(pdf)
     */
    private function storeAmendedMinutesVersion(Meeting $meeting, User $approver, string $note): void
    {
        $meeting->refresh();
        $document = $this->resolveMinutesDocument($meeting, $approver);
        if ($document === null) {
            return;
        }

        $this->ensureOriginalMinutesVersionFrozen($meeting, $approver);
        $document->refresh();

        $pdf = app(MeetingMinutesPdfService::class)->output($meeting);
        $path = 'meetings/'.now()->format('Y/m').'/'.$meeting->id.'-minutes-v'.($meeting->version).'.pdf';
        Storage::disk('local')->put($path, $pdf);

        $label = 'معدَّل بتاريخ '.now()->format('Y-m-d').' بموافقة '.$approver->name;
        if (trim($note) !== '') {
            $label .= ' — '.$note;
        }

        app(DocumentLibraryService::class)->addVersion($document, $path, $label, $approver);
    }

    private function resolveMinutesDocument(Meeting $meeting, User $actor): ?Document
    {
        $document = null;
        if ($meeting->archived_document_id) {
            $document = Document::query()->find($meeting->archived_document_id);
        }
        if ($document === null && $meeting->signed_document_id) {
            $document = Document::query()->find($meeting->signed_document_id);
        }
        if ($document === null) {
            $this->archiveMinutes($meeting, $actor);
            $meeting->refresh();
            $document = Document::query()->find($meeting->archived_document_id);
        }

        return $document;
    }
}
