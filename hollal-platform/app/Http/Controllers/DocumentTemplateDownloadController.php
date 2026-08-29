<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\DocumentTemplate;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download/preview for approved document templates.
 */
class DocumentTemplateDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Request $request, DocumentTemplate $template): StreamedResponse
    {
        abort_unless(
            auth()->user()?->can('documents.view')
            || auth()->user()?->can('documents.templates.manage'),
            403
        );
        abort_unless($template->isVisibleTo(auth()->user()), 403);

        if (! Storage::disk('local')->exists($template->path)) {
            abort(404);
        }

        $this->auditFileDownload('document_template', $template);

        $extension = pathinfo($template->path, PATHINFO_EXTENSION);
        $filename = trim((string) $template->title) !== ''
            ? $template->title.($extension ? '.'.$extension : '')
            : basename($template->path);

        $disposition = ($request->boolean('inline') || $request->boolean('print')) ? 'inline' : 'attachment';

        return Storage::disk('local')->download($template->path, $filename, [
            'Content-Disposition' => DownloadHeaders::contentDisposition($filename, $disposition),
        ], $disposition);
    }
}
