<x-ds-page>
    <x-ds-page-header title="مسيّرات الرواتب" />

    @php
        $statusClasses = [
            'مسودة' => 'ds-badge-pending',
            'مرفوع_للمالية' => 'ds-badge-info',
            'منفذ' => 'ds-badge-success',
            'معاد_للتصحيح' => 'ds-badge-warning',
        ];
    @endphp

    @can('hr.salaries.manage')
        <section class="ds-section ds-filter-bar">
            <input type="month" class="ds-input" wire:model="month" dir="ltr">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="generate">
                <i class="fas fa-gears" aria-hidden="true"></i> توليد مسيّر الشهر
            </button>
            @error('month') <small class="ds-error">{{ $message }}</small> @enderror
        </section>
    @endcan

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الشهر</th>
                <th>عدد الموظفين</th>
                <th>إجمالي الصافي</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($runs as $run)
            <tr wire:key="run-{{ $run->id }}">
                <td dir="ltr">{{ $run->month }}</td>
                <td>{{ $run->items_count }}</td>
                <td>{{ number_format((float) $run->items_sum_net, 2) }} ر.س</td>
                <td>
                    <span class="ds-badge {{ $statusClasses[$run->status] ?? '' }}">{{ $run->status }}</span>
                </td>
                <td>
                    @can('hr.salaries.manage')
                        @if (in_array($run->status, ['مسودة', 'معاد_للتصحيح'], true))
                            <button type="button" class="ds-link" wire:click="submit({{ $run->id }})">رفع للمالية</button>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="ds-text-muted ds-table-empty">لا توجد مسيّرات</td>
            </tr>
        @endforelse
    </x-ds-table>
</x-ds-page>
