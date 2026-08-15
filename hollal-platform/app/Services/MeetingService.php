<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Meeting;
use App\Models\MeetingAmendment;
use App\Models\MeetingItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * 03-B1 — meeting minutes approval cycle and amendments. Once approved, minutes
 * are frozen; changes go through a versioned amendment that preserves the
 * original.
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

        return $meeting;
    }

    /**
     * Attendee confirms minutes and stamps electronic signature from profile.
     * Time: O(1) | Space: O(1)
     */
    public function confirmAttendance(Meeting $meeting, User $user): void
    {
        if ($meeting->isApproved()) {
            throw new \RuntimeException('المحضر معتمد ولا يمكن تأكيد الحضور.');
        }

        if (! $meeting->attendees()->where('users.id', $user->id)->exists()
            && (int) $meeting->chair_id !== (int) $user->id
            && (int) $meeting->secretary_id !== (int) $user->id) {
            throw new \RuntimeException('لست من حضور هذا الاجتماع.');
        }

        $signature = $user->electronic_signature ?: $user->name;

        if (! $meeting->attendees()->where('users.id', $user->id)->exists()) {
            $meeting->attendees()->attach($user->id, [
                'confirmed_at' => now(),
                'signature_text' => $signature,
            ]);

            return;
        }

        $meeting->attendees()->updateExistingPivot($user->id, [
            'confirmed_at' => now(),
            'signature_text' => $signature,
        ]);
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
}
