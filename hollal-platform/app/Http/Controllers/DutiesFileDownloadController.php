<?php

namespace App\Http\Controllers;

use App\Models\OfficialDutiesDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 01-B5 — the latest published official duties file is viewable by every
 * authenticated employee (private disk, protected route).
 */
class DutiesFileDownloadController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $document = OfficialDutiesDocument::latestPublished();

        if (! $document || ! Storage::disk('local')->exists($document->file_path)) {
            abort(404);
        }

        return response()->streamDownload(
            fn () => print(Storage::disk('local')->get($document->file_path)),
            'official-duties-v'.$document->version.'.pdf',
        );
    }
}
