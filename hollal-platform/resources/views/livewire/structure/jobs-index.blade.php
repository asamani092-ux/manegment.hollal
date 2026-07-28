<x-ds-page>
    <x-ds-page-header title="الوظائف" :show-button="false" />
    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>المسمى</th>
                <th>المستوى</th>
                <th>الوحدة الأب</th>
                <th>الحالة</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($jobs as $job)
            <tr wire:key="job-{{ $job->id }}">
                <td>{{ $job->name }}</td>
                <td>{{ $job->level }}</td>
                <td>{{ $job->parent?->name ?? '—' }}</td>
                <td>{{ $job->is_active ? 'نشطة' : 'موقوفة' }}</td>
                <td><a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.org-tree') }}">الهيكل</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="ds-table-empty">لا توجد بطاقات وظيفية</td></tr>
        @endforelse
    </x-ds-table>
    {{ $jobs->links() }}
</x-ds-page>
