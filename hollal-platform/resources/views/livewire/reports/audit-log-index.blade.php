<x-ds-page>
    <x-ds-page-header title="سجل النشاط (عرض فقط)" />

    <section class="ds-section ds-filter-bar">
        <select class="ds-input" wire:model.live="actionFilter">
            <option value="">كل الإجراءات</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}">{{ \App\Models\AuditLog::labelFor($action) }}</option>
            @endforeach
        </select>
        <input type="search" class="ds-input" placeholder="المنفّذ" wire:model.live="actorFilter">
        <input type="date" class="ds-input" wire:model.live="fromDate" dir="ltr">
        <input type="date" class="ds-input" wire:model.live="toDate" dir="ltr">
        <button type="button" class="ds-btn" wire:click="export">تصدير CSV</button>
    </section>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>التاريخ</th>
                <th>الإجراء</th>
                <th>الحالة</th>
                <th>سبب الفشل</th>
                <th>المنفّذ</th>
            </tr>
        </x-slot:head>
        @forelse ($logs as $log)
            <tr wire:key="audit-{{ $log->id }}">
                <td dir="ltr">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                <td>{{ $log->actionLabel() }}</td>
                <td>
                    <span class="ds-badge {{ $log->displayStatus() === 'فشل' ? 'ds-badge-danger' : 'ds-badge-success' }}">
                        {{ $log->displayStatus() }}
                    </span>
                </td>
                <td>{{ $log->statusReason() ?? '—' }}</td>
                <td>{{ $log->actor?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد سجلات</td></tr>
        @endforelse
    </x-ds-table>

    {{ $logs->links() }}
</x-ds-page>
