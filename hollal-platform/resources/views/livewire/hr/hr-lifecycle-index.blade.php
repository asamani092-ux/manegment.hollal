<x-ds-page>
    <x-ds-page-header title="التهيئة وإنهاء العلاقة" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">مهام الإنهاء تُنشأ في إسناد. الموانع تُعرض صراحة وتمنع إغلاق الإنهاء. تعطيل الحساب هو آخر خطوة بعد اكتمال المهام.</p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الموظف</th>
                <th scope="col">الجوال</th>
                <th scope="col">الحالة الوظيفية</th>
                <th scope="col">مهام الإنهاء</th>
                <th scope="col">موانع الإغلاق</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($users as $user)
            @php
                $rowHolds = $holds[$user->id] ?? [];
                $counts = $taskCounts[$user->id] ?? null;
            @endphp
            <tr wire:key="life-{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td class="ds-ltr-num">{{ $user->phone }}</td>
                <td>
                    <x-ds-status-badge :status="$user->employment_status ?? 'نشط'" />
                    @if ($user->offboarding_started_at)
                        <span class="ds-badge ds-badge-warning">قيد الإنهاء</span>
                    @endif
                </td>
                <td class="ds-ltr-num">
                    @if ($counts)
                        {{ (int) $counts->done }} / {{ (int) $counts->total }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($rowHolds === [])
                        <span class="ds-text-muted">لا يوجد</span>
                    @else
                        <span class="ds-badge ds-badge-warning">{{ implode('، ', $rowHolds) }}</span>
                    @endif
                </td>
                <td>
                    @if ($user->id !== auth()->id())
                        @if (! $user->offboarding_started_at)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm"
                                    wire:click="startOffboarding({{ $user->id }})"
                                    wire:confirm="تأكيد بدء إنهاء العلاقة وإنشاء مهام التسليم؟">
                                بدء إنهاء العلاقة
                            </button>
                        @else
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm"
                                    wire:click="completeOffboarding({{ $user->id }})"
                                    wire:confirm="تعطيل الحساب خطوة أخيرة. تأكيد الإغلاق؟"
                                    @disabled($rowHolds !== [])>
                                إغلاق وتعطيل الحساب
                            </button>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ds-empty-state message="لا يوجد عاملون" icon="fa-user-gear" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $users->links() }}
</x-ds-page>
