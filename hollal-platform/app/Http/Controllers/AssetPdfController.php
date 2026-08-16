<?php

namespace App\Http\Controllers;

use App\Services\AssetService;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asset registry PDF for print/preview.
 * ?print=1 → inline; otherwise attachment.
 * Time: O(n) | Space: O(pdf)
 */
class AssetPdfController extends Controller
{
    public function __invoke(Request $request, AssetService $assets): Response
    {
        abort_unless(
            $request->user()?->can('finance.assets.view') || $request->user()?->can('finance.assets.manage'),
            403
        );

        $statusTab = $request->query('statusTab', 'active') === 'all' ? 'all' : 'active';
        $search = trim((string) $request->query('search', ''));
        $condition = trim((string) $request->query('condition', ''));
        $disposition = $request->boolean('print') ? 'inline' : 'attachment';
        $filename = 'assets-'.$statusTab.'-'.now()->format('Y-m-d').'.pdf';

        $bytes = $assets->exportRegistryPdf(
            $statusTab,
            $search !== '' ? $search : null,
            $condition !== '' ? $condition : null
        );

        return response($bytes, 200, DownloadHeaders::pdf($filename, $disposition));
    }
}
