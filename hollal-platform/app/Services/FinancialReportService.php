<?php

namespace App\Services;

use App\Models\ExpenseRequest;
use App\Models\PayrollRunItem;
use App\Models\Revenue;
use App\Support\PdfArabic;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * 04-B6 — strictly-derived financial reports. Every total is a live DB aggregate;
 * nothing is stored. Line items always reconcile to their header total.
 */
class FinancialReportService
{
    /**
     * @return array<string, mixed>
     */
    public function monthly(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $expensesByCategory = ExpenseRequest::query()
            ->countedAsSpend()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->map(fn ($row) => ['category_id' => $row->category_id, 'total' => (float) $row->total]);

        $expensesTotal = (float) ExpenseRequest::query()
            ->countedAsSpend()
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $revenuesByCategory = Revenue::query()
            ->where('status', Revenue::STATUS_CONFIRMED)
            ->whereBetween('confirmed_at', [$start, $end])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->map(fn ($row) => ['category_id' => $row->category_id, 'total' => (float) $row->total]);

        $revenuesTotal = (float) Revenue::query()
            ->where('status', Revenue::STATUS_CONFIRMED)
            ->whereBetween('confirmed_at', [$start, $end])
            ->sum('amount');

        $payrollTotal = (float) PayrollRunItem::query()
            ->whereHas('run', fn ($q) => $q->where('month', $month))
            ->sum('net');

        return [
            'month' => $month,
            'expenses_by_category' => $expensesByCategory,
            'expenses_total' => $expensesTotal,
            'revenues_by_category' => $revenuesByCategory,
            'revenues_total' => $revenuesTotal,
            'payroll_total' => $payrollTotal,
            'net' => $revenuesTotal - $expensesTotal - $payrollTotal,
        ];
    }

    /**
     * True when every line-item block reconciles to its header total and the net
     * equals revenues − expenses − payroll. No stored figure is trusted.
     *
     * @param  array<string, mixed>  $report
     */
    public function reconciles(array $report): bool
    {
        $expenseLines = round(collect($report['expenses_by_category'])->sum('total'), 2);
        $revenueLines = round(collect($report['revenues_by_category'])->sum('total'), 2);

        $expectedNet = round(
            (float) $report['revenues_total'] - (float) $report['expenses_total'] - (float) $report['payroll_total'],
            2
        );

        return $expenseLines === round((float) $report['expenses_total'], 2)
            && $revenueLines === round((float) $report['revenues_total'], 2)
            && $expectedNet === round((float) $report['net'], 2);
    }

    public function exportMonthlyPdf(string $month): string
    {
        $report = $this->monthly($month);

        $html = PdfArabic::header('التقرير المالي الشهري — '.$month, includeCr: true)
            .'<div dir="rtl">'
            .'<table><thead><tr><th>البند</th><th>المبلغ</th></tr></thead><tbody>'
            .'<tr><td>إجمالي المصروفات</td><td>'.number_format($report['expenses_total'], 2).'</td></tr>'
            .'<tr><td>إجمالي الإيرادات</td><td>'.number_format($report['revenues_total'], 2).'</td></tr>'
            .'<tr><td>إجمالي الرواتب</td><td>'.number_format($report['payroll_total'], 2).'</td></tr>'
            .'<tr><td><strong>الصافي</strong></td><td><strong>'.number_format($report['net'], 2).'</strong></td></tr>'
            .'</tbody></table></div>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return PdfArabic::applyOptions($pdf)->output();
    }

    /**
     * UTF-8 CSV with BOM for Excel. Sheets simulated as sections.
     * Time: O(n) | Space: O(n)
     */
    public function exportMonthlyCsv(string $month): string
    {
        $report = $this->monthly($month);
        $lines = [];
        $lines[] = ['القسم', 'البند', 'المبلغ'];
        $lines[] = ['ملخص', 'إجمالي المصروفات', number_format((float) $report['expenses_total'], 2, '.', '')];
        $lines[] = ['ملخص', 'إجمالي الإيرادات', number_format((float) $report['revenues_total'], 2, '.', '')];
        $lines[] = ['ملخص', 'إجمالي الرواتب', number_format((float) $report['payroll_total'], 2, '.', '')];
        $lines[] = ['ملخص', 'الصافي', number_format((float) $report['net'], 2, '.', '')];

        foreach ($report['expenses_by_category'] as $line) {
            $lines[] = ['مصروفات حسب التصنيف', (string) ($line['category_id'] ?? 'غير مصنّف'), number_format((float) $line['total'], 2, '.', '')];
        }
        foreach ($report['revenues_by_category'] as $line) {
            $lines[] = ['إيرادات حسب التصنيف', (string) ($line['category_id'] ?? 'غير مصنّف'), number_format((float) $line['total'], 2, '.', '')];
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($lines as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }
}
