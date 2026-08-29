<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\Document;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure download/preview for documents stored on the local disk.
 */
class DocumentDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        if (! Storage::disk('local')->exists($document->path)) {
            abort(404);
        }

        $this->auditFileDownload('document', $document);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);
        $filename = trim((string) $document->title) !== ''
            ? $document->title.($extension ? '.'.$extension : '')
            : basename($document->path);

        $disposition = ($request->boolean('inline') || $request->boolean('print')) ? 'inline' : 'attachment';

        return Storage::disk('local')->download($document->path, $filename, [
            'Content-Disposition' => DownloadHeaders::contentDisposition($filename, $disposition),
        ], $disposition);
    }
}
