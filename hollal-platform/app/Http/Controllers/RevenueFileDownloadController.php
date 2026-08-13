<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\Revenue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure download / preview for revenue evidence on the local disk.
 */
class RevenueFileDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Revenue $revenue): StreamedResponse
    {
        abort_unless(
            auth()->user()->can('finance.revenues.view') || auth()->user()->can('finance.revenues.manage'),
            403
        );

        if (! $revenue->external_document_path || ! Storage::disk('local')->exists($revenue->external_document_path)) {
            abort(404);
        }

        $this->auditFileDownload('revenue_evidence', $revenue);

        $filename = basename($revenue->external_document_path);

        return Storage::disk('local')->download($revenue->external_document_path, $filename, [
            'Content-Disposition' => \App\Support\DownloadHeaders::contentDisposition($filename, 'inline'),
        ]);
    }
}
