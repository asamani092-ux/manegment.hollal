<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\MeetingMinutesPdfService;
use Illuminate\Http\Response;

class MeetingMinutesPdfController extends Controller
{
    public function __invoke(Meeting $meeting, MeetingMinutesPdfService $pdfService): Response
    {
        $this->authorize('downloadPdf', $meeting);

        $pdf = $pdfService->generate($meeting);
        $filename = 'minutes-'.$meeting->id.'.pdf';

        return $pdf->download($filename);
    }
}
