<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Support\PdfArabic;
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
            'guests',
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

        $html = PdfArabic::shapeHtml(view('pdf.meeting-minutes', [
            'meeting' => $meeting,
            'openDecisions' => $openDecisions,
        ])->render());

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return PdfArabic::applyOptions($pdf)
            ->setOption('isRemoteEnabled', false);
    }

    public function output(Meeting $meeting): string
    {
        return $this->generate($meeting)->output();
    }
}
