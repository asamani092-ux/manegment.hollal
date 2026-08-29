<?php

namespace App\Services;

use App\Models\Meeting;
use App\Support\PdfArabic;

/**
 * Arabic RTL meeting minutes PDF using mPDF.
 * Time: O(n) items | Space: O(n).
 */
class MeetingMinutesPdfService
{
    public function generate(Meeting $meeting): string
    {
        return PdfArabic::outputFromHtml($this->buildHtml($meeting));
    }

    public function output(Meeting $meeting): string
    {
        return $this->generate($meeting);
    }

    /**
     * Full HTML document for minutes — extracted for testability.
     */
    public function buildHtml(Meeting $meeting): string
    {
        $meeting->load([
            'chair:id,name',
            'secretary:id,name',
            'attendees:id,name,email',
            'guests',
            'items' => fn ($q) => $q->with(['responsible:id,name'])->orderBy('id'),
        ]);

        return view('pdf.meeting-minutes', [
            'meeting' => $meeting,
            'fontFaceCss' => PdfArabic::fontFace(),
            'defaultFont' => PdfArabic::defaultFont(),
        ])->render();
    }
}
