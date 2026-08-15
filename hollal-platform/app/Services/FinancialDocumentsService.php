<?php

namespace App\Services;

use App\Models\CustodySettlementItem;
use App\Models\ExpenseRequest;
use App\Models\PayrollRunItem;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read-only aggregation of financial attachments with uploader display.
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
                ->with('requester:id,name')
                ->get(['id', 'official_document_path', 'project_id', 'created_at', 'requester_id'])
                ->map(fn ($e) => $this->row(
                    'expense_invoice',
                    'فاتورة مصروف',
                    $e->official_document_path,
                    $e->created_at,
                    $e->project_id,
                    $e->requester?->name
                ))
        );

        $rows = $rows->merge(
            Revenue::query()
                ->whereNotNull('external_document_path')
                ->with('confirmer:id,name')
                ->get(['id', 'external_document_path', 'created_at', 'confirmed_by'])
                ->map(fn ($r) => $this->row(
                    'revenue_document',
                    'مستند إيراد',
                    $r->external_document_path,
                    $r->created_at,
                    null,
                    $r->confirmer?->name ?? '—'
                ))
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
    private function row(
        string $type,
        string $label,
        string $path,
        $date,
        ?int $projectId = null,
        ?string $uploader = null,
    ): array {
        return [
            'type' => $type,
            'label' => $label,
            'path' => $path,
            'date' => $date,
            'month' => $date?->format('Y-m'),
            'project_id' => $projectId,
            'uploader' => $uploader ?? '—',
        ];
    }
}
