<x-ds-page>
    <x-ds-page-header title="التهيئة وإنهاء العلاقة" :show-button="false" />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>الجوال</th>
                <th>الحالة الوظيفية</th>
                <th>موانع الإنهاء</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($users as $user)
            <tr wire:key="life-{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td class="ds-ltr-num">{{ $user->phone }}</td>
                <td>{{ $user->employment_status ?? 'نشط' }}</td>
                <td>
                    @if (($holds[$user->id] ?? []) === [])
                        لا يوجد
                    @else
                        {{ implode('، ', $holds[$user->id]) }}
                    @endif
                </td>
                <td>
                    @if ($user->id !== auth()->id())
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm"
                                wire:click="startOffboarding({{ $user->id }})"
                                wire:confirm="تأكيد بدء إنهاء العلاقة؟">
                            بدء إنهاء العلاقة
                        </button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="ds-table-empty">لا يوجد عاملون</td></tr>
        @endforelse
    </x-ds-table>
    {{ $users->links() }}
</x-ds-page>
