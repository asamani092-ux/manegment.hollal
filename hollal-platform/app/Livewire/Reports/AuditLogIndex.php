<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 08-B2 — the audit-log screen. Strictly read-only: it offers filters and an
 * export, and deliberately exposes no create/update/delete action.
 */
class AuditLogIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $actionFilter = '';

    public string $actorFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        $this->authorize('reports.view');
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AuditLog> */
    public function query()
    {
        return AuditLog::query()
            ->when($this->actionFilter !== '', fn ($q) => $q->where('action', 'like', '%'.$this->actionFilter.'%'))
            ->when($this->actorFilter !== '', fn ($q) => $q->whereHas('actor', fn ($a) => $a->where('name', 'like', '%'.$this->actorFilter.'%')))
            ->when($this->fromDate !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->toDate))
            ->orderByDesc('id');
    }

    /** CSV export of the filtered view — a read, never a write. */
    public function export()
    {
        $this->authorize('reports.view');

        $rows = $this->query()->with('actor')->limit(5000)->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($handle, ['التاريخ', 'الإجراء', 'المنفذ', 'الهدف', 'العنوان IP']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at?->format('Y-m-d H:i:s'),
                    $row->action,
                    $row->actor?->name ?? '—',
                    trim(($row->target_type ?? '').' #'.($row->target_id ?? '')),
                    $row->ip_address ?? '—',
                ]);
            }

            fclose($handle);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        return view('livewire.reports.audit-log-index', [
            'logs' => $this->query()->with('actor')->paginate(25),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ])->layout('layouts.app', ['title' => 'سجل النشاط']);
    }
}
