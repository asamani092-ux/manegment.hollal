<x-ds-page>
    <x-ds-page-header title="الموازنات (محسوبة آليًا)" />

    <section class="ds-section ds-filter-bar">
        <select class="ds-input" wire:model.live="tierFilter">
            <option value="">كل المشاريع</option>
            <option value="warning">بلغت حد التنبيه ({{ $warningThreshold }}%) فأكثر</option>
            <option value="over">تجاوزت الموازنة (100%)</option>
        </select>
    </section>

    <section class="ds-section ds-stat-row">
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">إجمالي الموازنات</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format((float) $totals['budget'], 2) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">إجمالي المستهلك</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format((float) $totals['consumed'], 2) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">إجمالي المتبقي</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format((float) $totals['remaining'], 2) }}</span>
        </div>
    </section>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>المشروع</th>
                <th>الموازنة</th>
                <th>المصروف فعليًا</th>
                <th>المرتبط (معتمد)</th>
                <th>المستهلك</th>
                <th>المتبقي</th>
                <th>نسبة الاستهلاك</th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr wire:key="budget-{{ $row['project']->id }}">
                <td>
                    <a href="{{ route('projects.show', $row['project']->id) }}">{{ $row['project']->name }}</a>
                </td>
                <td class="ds-ltr-num">{{ number_format($row['budget'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['actual_spend'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['committed'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['consumed'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['remaining'], 2) }}</td>
                <td class="ds-ltr-num">
                    @if ($row['percent'] >= 100)
                        <span class="ds-badge ds-badge-danger">{{ $row['percent'] }}%</span>
                    @elseif ($row['percent'] >= $warningThreshold)
                        <span class="ds-badge ds-badge-warning">{{ $row['percent'] }}%</span>
                    @else
                        <span class="ds-badge ds-badge-success">{{ $row['percent'] }}%</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="ds-text-muted ds-table-empty">لا توجد مشاريع ذات موازنة</td></tr>
        @endforelse
    </x-ds-table>
</x-ds-page>
