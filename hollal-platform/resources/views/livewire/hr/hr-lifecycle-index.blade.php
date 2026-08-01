<x-ds-page>
    <x-ds-page-header title="التهيئة وإنهاء العلاقة" :show-button="false" />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الموظف</th>
                <th scope="col">الجوال</th>
                <th scope="col">الحالة الوظيفية</th>
                <th scope="col">موانع الإنهاء</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($users as $user)
            <tr wire:key="life-{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td class="ds-ltr-num">{{ $user->phone }}</td>
                <td><x-ds-status-badge :status="$user->employment_status ?? 'نشط'" /></td>
                <td>
                    @if (($holds[$user->id] ?? []) === [])
                        <span class="ds-text-muted">لا يوجد</span>
                    @else
                        <span class="ds-badge ds-badge-warning">{{ implode('، ', $holds[$user->id]) }}</span>
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
            <tr><td colspan="5"><x-ds-empty-state message="لا يوجد عاملون" icon="fa-user-gear" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $users->links() }}
</x-ds-page>
