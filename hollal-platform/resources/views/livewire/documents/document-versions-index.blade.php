<x-ds-page>
    <x-ds-page-header title="إدارة النسخ" :show-button="false" />
    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>المستند</th>
                <th>النسخة</th>
                <th>ملاحظة التغيير</th>
                <th>التاريخ</th>
            </tr>
        </x-slot:head>
        @forelse ($versions as $version)
            <tr wire:key="ver-{{ $version->id }}">
                <td>{{ $version->document?->title ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $version->version }}</td>
                <td>{{ $version->change_note ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $version->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="ds-table-empty">لا توجد نسخ مسجّلة</td></tr>
        @endforelse
    </x-ds-table>
    {{ $versions->links() }}
</x-ds-page>
