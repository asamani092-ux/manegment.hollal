<x-ds-page>
    <x-ds-page-header title="الزيارات" :show-button="false" />
    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>المشروع</th>
                <th>الزائر</th>
                <th>التاريخ</th>
                <th>الغرض</th>
                <th>الحالة</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($visits as $visit)
            <tr wire:key="visit-{{ $visit->id }}">
                <td>{{ $visit->project?->name ?? '—' }}</td>
                <td>{{ $visit->visitor?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $visit->scheduled_on?->format('Y-m-d') }}</td>
                <td>{{ $visit->purpose }}</td>
                <td>{{ $visit->status }}</td>
                <td>
                    @if ($visit->project_id)
                        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('projects.execution', $visit->project_id) }}?tab=visits">فتح</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="ds-table-empty">لا توجد زيارات</td></tr>
        @endforelse
    </x-ds-table>
    {{ $visits->links() }}
</x-ds-page>
