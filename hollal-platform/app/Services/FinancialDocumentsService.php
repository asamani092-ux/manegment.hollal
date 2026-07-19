<?php

namespace App\Services;

use App\Models\CustodySettlementItem;
use App\Models\ExpenseRequest;
use App\Models\PayrollRunItem;
use App\Models\Revenue;
use Illuminate\Support\Collection;

/**
 * 04-B4 — read-only aggregation of every financial attachment across the system
 * (expense invoices, revenue docs, custody invoices, payroll proofs). No upload
 * happens here; documents are attached from their own modules.
 */
class FinancialDocumentsService
{
    /**
     * @param  array{type?: string, month?: string, project_id?: int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function all(array $filters = []): Collection
    {
        $rows = collect();

        $rows = $rows->merge(
            ExpenseRequest::query()
                ->whereNotNull('official_document_path')
                ->get(['id', 'official_document_path', 'project_id', 'created_at'])
                ->map(fn ($e) => $this->row('expense_invoice', 'فاتورة مصروف', $e->official_document_path, $e->created_at, $e->project_id))
        );

        $rows = $rows->merge(
            Revenue::query()
                ->whereNotNull('external_document_path')
                ->get(['id', 'external_document_path', 'created_at'])
                ->map(fn ($r) => $this->row('revenue_document', 'مستند إيراد', $r->external_document_path, $r->created_at))
        );

        $rows = $rows->merge(
            CustodySettlementItem::query()
                ->whereNotNull('invoice_file')
                ->get(['id', 'invoice_file', 'created_at'])
                ->map(fn ($c) => $this->row('custody_invoice', 'فاتورة عهدة', $c->invoice_file, $c->created_at))
        );

        $rows = $rows->merge(
            PayrollRunItem::query()
                ->whereNotNull('proof_file')
                ->get(['id', 'proof_file', 'created_at'])
                ->map(fn ($p) => $this->row('payroll_proof', 'إثبات صرف راتب', $p->proof_file, $p->created_at))
        );

        if (! empty($filters['type'])) {
            $rows = $rows->where('type', $filters['type']);
        }

        if (! empty($filters['month'])) {
            $rows = $rows->where('month', $filters['month']);
        }

        if (! empty($filters['project_id'])) {
            $rows = $rows->where('project_id', (int) $filters['project_id']);
        }

        return $rows->sortByDesc('date')->values();
    }

    /** @return array<string, mixed> */
    private function row(string $type, string $label, string $path, $date, ?int $projectId = null): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'path' => $path,
            'date' => $date,
            'month' => $date?->format('Y-m'),
            'project_id' => $projectId,
        ];
    }
}
