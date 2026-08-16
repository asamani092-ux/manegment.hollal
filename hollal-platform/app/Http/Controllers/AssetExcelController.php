<?php

namespace App\Http\Controllers;

use App\Services\AssetService;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Asset registry CSV (Excel-friendly UTF-8 BOM).
 * Query mirrors AssetsIndex filters: search, condition, statusTab.
 * Time: O(n) rows | Space: O(1) stream
 */
class AssetExcelController extends Controller
{
    public function __invoke(Request $request, AssetService $assets): StreamedResponse
    {
        abort_unless(
            $request->user()?->can('finance.assets.view') || $request->user()?->can('finance.assets.manage'),
            403
        );

        $statusTab = $request->query('statusTab', 'active') === 'all' ? 'all' : 'active';
        $search = trim((string) $request->query('search', ''));
        $condition = trim((string) $request->query('condition', ''));

        $csv = $assets->exportRegistryCsv($statusTab, $search !== '' ? $search : null, $condition !== '' ? $condition : null);
        $filename = 'assets-'.$statusTab.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => DownloadHeaders::contentDisposition($filename),
        ]);
    }
}
