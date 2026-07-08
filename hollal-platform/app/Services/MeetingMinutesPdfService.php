<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingItem;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Arabic RTL meeting minutes PDF.
 * Time: O(n) items | Space: O(n).
 */
class MeetingMinutesPdfService
{
    public function generate(Meeting $meeting): \Barryvdh\DomPDF\PDF
    {
        $meeting->load([
            'chair:id,name',
            'secretary:id,name',
            'attendees:id,name,email',
            'items' => fn ($q) => $q->with(['responsible:id,name'])->orderBy('id'),
        ]);

        $openDecisions = MeetingItem::query()
            ->whereNotNull('decision')
            ->where('decision', '!=', '')
            ->where('status', '!=', 'done')
            ->with(['meeting:id,title', 'responsible:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        return Pdf::loadView('pdf.meeting-minutes', [
            'meeting' => $meeting,
            'openDecisions' => $openDecisions,
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'dejavu sans');
    }

    public function output(Meeting $meeting): string
    {
        return $this->generate($meeting)->output();
    }
}
