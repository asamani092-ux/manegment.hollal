<x-ds-page>
    <x-ds-page-header title="المستندات المالية (عرض فقط)" />

    <section class="ds-section ds-filter-bar">
        <select class="ds-input" wire:model.live="typeFilter">
            <option value="">كل الأنواع</option>
            <option value="expense_invoice">فواتير المصروفات</option>
            <option value="revenue_document">مستندات الإيرادات</option>
            <option value="custody_invoice">فواتير العُهد</option>
            <option value="payroll_proof">إثباتات الرواتب</option>
        </select>
        <input type="month" class="ds-input" wire:model.live="monthFilter" dir="ltr">
        <select class="ds-input" wire:model.live="projectFilter">
            <option value="">كل المشاريع</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}">{{ $project->name }}</option>
            @endforeach
        </select>
    </section>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>النوع</th>
                <th>الشهر</th>
                <th>المستند</th>
            </tr>
        </x-slot:head>
        @forelse ($documents as $doc)
            <tr wire:key="fin-doc-{{ $loop->index }}">
                <td>{{ $doc['label'] }}</td>
                <td dir="ltr">{{ $doc['month'] ?? '—' }}</td>
                <td dir="ltr">{{ basename($doc['path']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="ds-text-muted ds-table-empty">لا توجد مستندات مالية</td></tr>
        @endforelse
    </x-ds-table>
</x-ds-page>
