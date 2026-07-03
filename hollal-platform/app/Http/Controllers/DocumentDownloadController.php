<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure download for documents stored on the local disk.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        if (! Storage::disk('local')->exists($document->path)) {
            abort(404);
        }

        $filename = basename($document->path);

        return response()->streamDownload(
            fn () => print(Storage::disk('local')->get($document->path)),
            $filename
        );
    }
}
