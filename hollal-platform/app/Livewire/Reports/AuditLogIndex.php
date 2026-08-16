<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
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
        abort_unless(auth()->user()->can('reports.audit-log.view'), 403);
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

    /** CSV export — requires reports.export; every export is audited. */
    public function export()
    {
        abort_unless(
            auth()->user()->can('reports.audit-log.view') && auth()->user()->can('reports.export'),
            403
        );

        $rows = $this->query()->with('actor')->limit(5000)->get();

        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'audit_log.exported',
            'target_type' => AuditLog::class,
            'target_id' => null,
            'ip_address' => request()->ip(),
            'metadata' => ['rows' => $rows->count()],
            'created_at' => now(),
        ]);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($handle, ['التاريخ', 'الإجراء', 'الحالة', 'سبب الفشل', 'المنفذ']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at?->format('Y-m-d H:i:s'),
                    $row->actionLabel(),
                    $row->displayStatus(),
                    $row->statusReason() ?? '—',
                    $row->actor?->name ?? '—',
                ]);
            }

            fclose($handle);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Distinct action keys for the filter — cached / limited to avoid full-table scans.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function distinctActions()
    {
        return Cache::remember('audit_log.distinct_actions', 300, function () {
            $known = array_keys(AuditLog::ACTION_LABELS);
            $recent = AuditLog::query()
                ->orderByDesc('id')
                ->limit(200)
                ->pluck('action')
                ->unique()
                ->values()
                ->all();

            return collect(array_unique(array_merge($known, $recent)))->sort()->values();
        });
    }

    public function render(): View
    {
        return view('livewire.reports.audit-log-index', [
            'logs' => $this->query()->with('actor')->paginate(25),
            'actions' => $this->distinctActions(),
        ])->layout('layouts.app', ['title' => 'سجل النشاط']);
    }
}
