<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\MeetingMinutesPdfService;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generated meeting minutes PDF.
 * ?print=1 or ?inline=1 → inline preview; otherwise attachment.
 * Time: O(n) items | Space: O(pdf)
 */
class MeetingMinutesPdfController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting, MeetingMinutesPdfService $pdfService): Response
    {
        $this->authorize('downloadPdf', $meeting);

        $filename = 'minutes-'.$meeting->id.'.pdf';
        $disposition = ($request->boolean('print') || $request->boolean('inline')) ? 'inline' : 'attachment';

        return response(
            $pdfService->output($meeting),
            200,
            DownloadHeaders::pdf($filename, $disposition)
        );
    }
}
