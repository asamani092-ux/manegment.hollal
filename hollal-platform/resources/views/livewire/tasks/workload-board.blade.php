<x-ds-page>
    <x-ds-page-header title="عبء عمل الفريق" />

    <p class="ds-text-muted">حد التنبيه: أكثر من {{ $threshold }} مهمة مفتوحة</p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>مفتوحة</th>
                <th>متأخرة</th>
                <th>مستحقة هذا الأسبوع</th>
                <th>تقييمات 30 يومًا</th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr wire:key="workload-{{ $row['user']->id }}">
                <td>{{ $row['user']->name }}</td>
                <td>
                    {{ $row['open'] }}
                    @if ($row['overloaded'])
                        <span class="ds-badge ds-badge-warning">عبء مرتفع</span>
                    @endif
                </td>
                <td>{{ $row['overdue'] }}</td>
                <td>{{ $row['due_this_week'] }}</td>
                <td>
                    @forelse ($row['ratings'] as $label => $count)
                        <span class="ds-badge">{{ $label }}: {{ $count }}</span>
                    @empty
                        —
                    @endforelse
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="ds-text-muted ds-table-empty">لا يوجد أعضاء فريق</td>
            </tr>
        @endforelse
    </x-ds-table>
</x-ds-page>
