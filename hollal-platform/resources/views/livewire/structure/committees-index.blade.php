<x-ds-page>
    <x-ds-page-header title="اللجان" :show-button="false" />
    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الاسم</th>
                <th>الاختصاص</th>
                <th>الرئيس</th>
                <th>الحالة</th>
            </tr>
        </x-slot:head>
        @forelse ($committees as $committee)
            <tr wire:key="com-{{ $committee->id }}">
                <td>{{ $committee->name }}</td>
                <td>{{ $committee->mandate }}</td>
                <td>{{ $committee->chair?->name ?? '—' }}</td>
                <td>{{ $committee->is_active ? 'نشطة' : 'موقوفة' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="ds-table-empty">لا توجد لجان</td></tr>
        @endforelse
    </x-ds-table>
    {{ $committees->links() }}
    <p class="ds-text-muted" style="margin-top:1rem">إدارة الهيكل التفصيلية من <a href="{{ route('structure.org-tree') }}">الهيكل التنظيمي</a>.</p>
</x-ds-page>
