<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P2 — streams the manually uploaded signed-PDF archive.
 * ?inline=1 → browser preview; otherwise force download.
 * Time: O(file size) stream | Space: O(1)
 */
class MeetingSignedMinutesController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting): StreamedResponse
    {
        $this->authorize('view', $meeting);

        abort_unless($meeting->signed_document_id, 404);

        $document = $meeting->signedDocument()->firstOrFail();

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $filename = 'minutes-signed-'.$meeting->id.'.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return Storage::disk('local')->download($document->path, $filename, [
            'Content-Disposition' => DownloadHeaders::contentDisposition($filename, $disposition),
            'Content-Type' => 'application/pdf',
        ], $disposition);
    }
}
