<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P2 wave C — streams the manually uploaded signed-PDF archive. Authorized
 * via MeetingPolicy::view so invited attendees (without meetings.view) can
 * still download it, same as the auto-generated PDF.
 */
class MeetingSignedMinutesController extends Controller
{
    public function __invoke(Meeting $meeting): StreamedResponse
    {
        $this->authorize('view', $meeting);

        abort_unless($meeting->signed_document_id, 404);

        $document = $meeting->signedDocument()->firstOrFail();

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $filename = 'minutes-signed-'.$meeting->id.'.pdf';

        return response()->streamDownload(
            fn () => print (Storage::disk('local')->get($document->path)),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
