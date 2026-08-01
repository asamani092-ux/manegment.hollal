<x-ds-page>
    <x-ds-page-header title="أرشيف المحاضر" :show-button="false" />
    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الاجتماع</th>
                <th>التاريخ</th>
                <th>حالة الاعتماد</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($meetings as $meeting)
            <tr wire:key="arch-{{ $meeting->id }}">
                <td>{{ $meeting->title }}</td>
                <td class="ds-ltr-num">{{ $meeting->scheduled_at?->format('Y-m-d') }}</td>
                <td><span class="ds-badge ds-badge-success">{{ $meeting->approval_status }}</span></td>
                <td>
                    @can('meetings.view')
                        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes', $meeting) }}">المحضر</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="ds-table-empty">لا توجد محاضر معتمدة</td></tr>
        @endforelse
    </x-ds-table>
    {{ $meetings->links() }}
</x-ds-page>
