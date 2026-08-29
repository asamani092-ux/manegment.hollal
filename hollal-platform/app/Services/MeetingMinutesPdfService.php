<?php

namespace App\Services;

use App\Models\Meeting;
use App\Support\PdfArabic;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Arabic meeting minutes PDF via PdfArabic (Amiri + shaping + LTR/right-align).
 * Time: O(n) items | Space: O(n)
 */
class MeetingMinutesPdfService
{
    public function generate(Meeting $meeting): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadHTML(PdfArabic::shapeHtml($this->buildHtml($meeting)))->setPaper('a4');

        return PdfArabic::applyOptions($pdf);
    }

    public function output(Meeting $meeting): string
    {
        return PdfArabic::outputFromHtml($this->buildHtml($meeting));
    }

    /**
     * Full HTML document for Dompdf — same chrome as tax invoices.
     * Extracted for testability.
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
