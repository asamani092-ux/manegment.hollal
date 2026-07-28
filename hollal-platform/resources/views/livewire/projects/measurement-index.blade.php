<x-ds-page>
    <x-ds-page-header title="القياس والأثر" :show-button="false" />
    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>النموذج</th>
                <th>البرنامج</th>
                <th>النوع</th>
                <th>التاريخ</th>
            </tr>
        </x-slot:head>
        @forelse ($forms as $form)
            <tr wire:key="mf-{{ $form->id }}">
                <td>{{ $form->title }}</td>
                <td>{{ $form->program?->name ?? '—' }}</td>
                <td>{{ $form->kind }}</td>
                <td class="ds-ltr-num">{{ $form->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="ds-table-empty">لا توجد نماذج قياس</td></tr>
        @endforelse
    </x-ds-table>
    {{ $forms->links() }}
</x-ds-page>
