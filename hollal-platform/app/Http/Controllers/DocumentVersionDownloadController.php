<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\DocumentVersion;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download/preview a historical document revision.
 */
class DocumentVersionDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Request $request, DocumentVersion $version): StreamedResponse
    {
        $version->loadMissing('document');
        abort_unless($version->document, 404);
        $this->authorize('download', $version->document);

        if (! Storage::disk('local')->exists($version->path)) {
            abort(404);
        }

        $this->auditFileDownload('document_version', $version);

        $extension = pathinfo($version->path, PATHINFO_EXTENSION);
        $base = trim((string) $version->document->title) !== ''
            ? $version->document->title.'-v'.$version->version
            : 'document-v'.$version->version;
        $filename = $base.($extension ? '.'.$extension : '');

        $disposition = ($request->boolean('inline') || $request->boolean('print')) ? 'inline' : 'attachment';

        return Storage::disk('local')->download($version->path, $filename, [
            'Content-Disposition' => DownloadHeaders::contentDisposition($filename, $disposition),
        ], $disposition);
    }
}
