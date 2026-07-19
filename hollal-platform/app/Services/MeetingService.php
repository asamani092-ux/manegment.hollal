<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingAmendment;
use App\Models\MeetingItem;
use App\Models\User;

/**
 * 03-B1 — meeting minutes approval cycle and amendments. Once approved, minutes
 * are frozen; changes go through a versioned amendment that preserves the
 * original.
 */
class MeetingService
{
    public function approveMinutes(Meeting $meeting, User $chair): Meeting
    {
        if ($meeting->isApproved()) {
            throw new \RuntimeException('المحضر معتمد بالفعل.');
        }

        $meeting->update([
            'approval_status' => Meeting::APPROVAL_APPROVED,
            'approved_by' => $chair->id,
            'approved_at' => now(),
        ]);

        return $meeting;
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
