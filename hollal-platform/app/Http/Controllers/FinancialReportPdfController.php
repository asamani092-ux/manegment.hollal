<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 04-B6 — monthly financial report PDF. Regenerated on every request from the
 * live ledgers; no snapshot is stored.
 */
class FinancialReportPdfController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $reports): Response
    {
        abort_unless($request->user()?->can('finance.reports.view'), 403);

        $month = (string) $request->query('month', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month) === 1, 404);

        return response($reports->exportMonthlyPdf($month), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="financial-report-'.$month.'.pdf"',
        ]);
    }
}
