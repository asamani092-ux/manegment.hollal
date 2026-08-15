<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly financial report as CSV (Excel-friendly UTF-8 BOM).
 * Time: O(n) lines | Space: O(1) stream
 */
class FinancialReportExcelController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $reports): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.reports.view'), 403);

        $month = (string) $request->query('month', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month) === 1, 404);

        $csv = $reports->exportMonthlyCsv($month);
        $filename = 'financial-report-'.$month.'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => \App\Support\DownloadHeaders::contentDisposition($filename),
        ]);
    }
}
