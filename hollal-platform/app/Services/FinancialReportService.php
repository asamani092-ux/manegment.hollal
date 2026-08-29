<?php

namespace App\Services;

use App\Models\ExpenseRequest;
use App\Models\PayrollRunItem;
use App\Models\Revenue;
use App\Support\PdfArabic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 04-B6 — strictly-derived financial reports. Every total is a live DB aggregate;
 * nothing is stored. Line items always reconcile to their header total.
 * Wave D-deep: detailed() adds a movement-by-movement view alongside the
 * existing monthly summary — same source ledgers, no new stored figures.
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
            .'<table class="pdf-meta"><thead><tr><th>البند</th><th class="num">المبلغ</th></tr></thead><tbody>'
            .'<tr><td class="pdf-label">إجمالي المصروفات</td><td class="num">'.number_format($report['expenses_total'], 2).'</td></tr>'
            .'<tr><td class="pdf-label">إجمالي الإيرادات</td><td class="num">'.number_format($report['revenues_total'], 2).'</td></tr>'
            .'<tr><td class="pdf-label">إجمالي الرواتب</td><td class="num">'.number_format($report['payroll_total'], 2).'</td></tr>'
            .'<tr><td class="pdf-label"><strong>الصافي</strong></td><td class="num"><strong>'.number_format($report['net'], 2).'</strong></td></tr>'
            .'</tbody></table>';

        return PdfArabic::outputFromHtml($html);
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

    /**
     * Every movement (مصروف/إيراد/رواتب) that fed the monthly summary,
     * sorted by date — for finance to trace a total back to its source
     * rows without leaving the platform.
     *
     * @return array<string, mixed>
     */
    public function detailed(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $expenses = ExpenseRequest::query()
            ->countedAsSpend()
            ->whereBetween('created_at', [$start, $end])
            ->select(['id', 'created_at', 'reason', 'category_id', 'amount', 'status', 'project_id'])
            ->with('project:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ExpenseRequest $e) => [
                'date' => $e->created_at?->format('Y-m-d'),
                'type' => 'مصروف',
                'description' => $e->reason,
                'category_id' => $e->category_id,
                'project' => $e->project?->name,
                'amount' => (float) $e->amount,
                'status' => $e->status,
            ]);

        $revenues = Revenue::query()
            ->where('status', Revenue::STATUS_CONFIRMED)
            ->whereBetween('confirmed_at', [$start, $end])
            ->select(['id', 'confirmed_at', 'source_type', 'category_id', 'amount', 'status'])
            ->orderBy('confirmed_at')
            ->get()
            ->map(fn (Revenue $r) => [
                'date' => $r->confirmed_at?->format('Y-m-d'),
                'type' => 'إيراد',
                'description' => $r->source_type,
                'category_id' => $r->category_id,
                'project' => null,
                'amount' => (float) $r->amount,
                'status' => $r->status,
            ]);

        $payroll = PayrollRunItem::query()
            ->whereHas('run', fn ($q) => $q->where('month', $month))
            ->select(['id', 'employee_id', 'net', 'executed_at', 'payroll_run_id'])
            ->with('employee:id,name')
            ->get()
            ->map(fn (PayrollRunItem $p) => [
                'date' => $p->executed_at?->format('Y-m-d') ?? $start->format('Y-m-d'),
                'type' => 'رواتب',
                'description' => 'صافي راتب — '.($p->employee?->name ?? '—'),
                'category_id' => null,
                'project' => null,
                'amount' => (float) $p->net,
                'status' => 'منفذ',
            ]);

        $movements = $expenses->concat($revenues)->concat($payroll)
            ->sortBy('date')
            ->values();

        return [
            'month' => $month,
            'movements' => $movements,
            'totals' => [
                'expenses' => (float) $expenses->sum('amount'),
                'revenues' => (float) $revenues->sum('amount'),
                'payroll' => (float) $payroll->sum('amount'),
            ],
        ];
    }

    /**
     * True when the detailed movement totals tie back to the monthly
     * summary totals for the same month.
     *
     * @param  array<string, mixed>  $detailed
     * @param  array<string, mixed>  $summary
     */
    public function detailedReconciles(array $detailed, array $summary): bool
    {
        return round($detailed['totals']['expenses'], 2) === round((float) $summary['expenses_total'], 2)
            && round($detailed['totals']['revenues'], 2) === round((float) $summary['revenues_total'], 2)
            && round($detailed['totals']['payroll'], 2) === round((float) $summary['payroll_total'], 2);
    }

    public function exportDetailedPdf(string $month): string
    {
        $detailed = $this->detailed($month);

        $rows = '';
        /** @var Collection<int, array<string, mixed>> $movements */
        $movements = $detailed['movements'];
        foreach ($movements as $movement) {
            $rows .= '<tr>'
                .'<td class="num">'.e((string) $movement['date']).'</td>'
                .'<td>'.e((string) $movement['type']).'</td>'
                .'<td>'.e((string) ($movement['description'] ?? '—')).'</td>'
                .'<td>'.e((string) ($movement['project'] ?? '—')).'</td>'
                .'<td class="num">'.number_format((float) $movement['amount'], 2).'</td>'
                .'</tr>';
        }

        $metaRow = static fn (string $label, string $value): string => '<tr>'
            .'<td class="pdf-label">'.e($label).'</td>'
            .'<td class="num">'.$value.'</td>'
            .'</tr>';

        $html = PdfArabic::header('التقرير المالي المفصّل — '.$month, includeCr: true)
            .'<table><thead><tr>'
            .'<th class="num">التاريخ</th><th>النوع</th><th>الوصف</th><th>المشروع</th><th class="num">المبلغ</th>'
            .'</tr></thead><tbody>'
            .($rows !== '' ? $rows : '<tr><td colspan="5">لا توجد حركات في هذا الشهر</td></tr>')
            .'</tbody></table>'
            .'<table class="pdf-meta" style="margin-top:12px;">'
            .$metaRow('إجمالي المصروفات', number_format($detailed['totals']['expenses'], 2))
            .$metaRow('إجمالي الإيرادات', number_format($detailed['totals']['revenues'], 2))
            .$metaRow('إجمالي الرواتب', number_format($detailed['totals']['payroll'], 2))
            .'</table>';

        return PdfArabic::outputFromHtml($html);
    }

    /** UTF-8 CSV with BOM for Excel — one row per movement. */
    public function exportDetailedCsv(string $month): string
    {
        $detailed = $this->detailed($month);
        $lines = [];
        $lines[] = ['التاريخ', 'النوع', 'الوصف', 'المشروع', 'المبلغ', 'الحالة'];

        foreach ($detailed['movements'] as $movement) {
            $lines[] = [
                (string) $movement['date'],
                (string) $movement['type'],
                (string) ($movement['description'] ?? ''),
                (string) ($movement['project'] ?? ''),
                number_format((float) $movement['amount'], 2, '.', ''),
                (string) ($movement['status'] ?? ''),
            ];
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
