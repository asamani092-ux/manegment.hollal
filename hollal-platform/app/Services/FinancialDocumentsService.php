<?php

namespace App\Services;

use App\Models\Custody;
use App\Models\CustodySettlementItem;
use App\Models\ExpenseRequest;
use App\Models\PayrollRunItem;
use App\Models\Revenue;
use Illuminate\Support\Collection;

/**
 * 04-B4 — read-only aggregation of every financial attachment across the system
 * (expense invoices, payment proofs, revenue docs, custody invoices/proofs, payroll proofs).
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
                    $e->id,
                    $e->requester?->name
                ))
        );

        $rows = $rows->merge(
            ExpenseRequest::query()
                ->whereNotNull('payment_proof_path')
                ->get(['id', 'payment_proof_path', 'project_id', 'created_at'])
                ->map(fn ($e) => $this->row('expense_payment_proof', 'إثبات صرف', $e->payment_proof_path, $e->created_at, $e->project_id, $e->id))
        );

        $rows = $rows->merge(
            ExpenseRequest::query()
                ->whereNotNull('attachment')
                ->get(['id', 'attachment', 'project_id', 'created_at'])
                ->map(fn ($e) => $this->row('expense_witness', 'شاهد المصروف', $e->attachment, $e->created_at, $e->project_id, $e->id))
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
                    $r->id,
                    $r->confirmer?->name
                ))
        );

        $rows = $rows->merge(
            CustodySettlementItem::query()
                ->whereNotNull('invoice_file')
                ->with('custody.employee:id,name')
                ->get(['id', 'invoice_file', 'created_at', 'custody_id'])
                ->map(fn ($c) => $this->row(
                    'custody_invoice',
                    'فاتورة عهدة',
                    $c->invoice_file,
                    $c->created_at,
                    null,
                    $c->id,
                    $c->custody?->employee?->name
                ))
        );

        $rows = $rows->merge(
            Custody::query()
                ->whereNotNull('disbursement_proof_path')
                ->get(['id', 'disbursement_proof_path', 'created_at'])
                ->map(fn ($c) => $this->row('custody_disbursement_proof', 'إثبات صرف عهدة', $c->disbursement_proof_path, $c->created_at, null, $c->id))
        );

        $rows = $rows->merge(
            PayrollRunItem::query()
                ->whereNotNull('proof_file')
                ->with('employee:id,name')
                ->get(['id', 'proof_file', 'created_at', 'employee_id'])
                ->map(fn ($p) => $this->row(
                    'payroll_proof',
                    'إثبات صرف راتب',
                    $p->proof_file,
                    $p->created_at,
                    null,
                    $p->id,
                    $p->employee?->name
                ))
        );

        if (! empty($filters['type'])) {
            $rows = $rows->where('type', $filters['type']);
        }

        if (! empty($filters['month'])) {
            $rows = $rows->filter(fn (array $row) => ($row['month'] ?? null) === $filters['month']);
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
        ?int $sourceId = null,
        ?string $uploader = null,
    ): array {
        $downloadUrl = $sourceId
            ? route('financial-documents.files.download', ['type' => $type, 'id' => $sourceId])
            : null;

        return [
            'type' => $type,
            'label' => $label,
            'path' => $path,
            'date' => $date,
            'month' => $date?->format('Y-m'),
            'project_id' => $projectId,
            'source_id' => $sourceId,
            'uploader' => $uploader ?: '—',
            'download_url' => $downloadUrl,
            'preview_url' => $downloadUrl ? $downloadUrl.'?inline=1' : null,
        ];
    }
}
