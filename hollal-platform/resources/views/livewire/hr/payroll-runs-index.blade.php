<x-ds-page>
    <x-ds-page-header title="مسيّرات الرواتب" />

    @can('hr.salaries.manage')
        <section class="ds-section ds-filter-bar">
            <input type="month" class="ds-input" wire:model="month" dir="ltr" aria-label="شهر المسيّر">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="generate">
                <i class="fas fa-gears" aria-hidden="true"></i> توليد مسيّر الشهر
            </button>
            @error('month') <small class="ds-error">{{ $message }}</small> @enderror
        </section>
    @endcan

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="run-status">الحالة</label>
            <select id="run-status" class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="run-month">الشهر</label>
            <input id="run-month" type="month" class="ds-input" wire:model.live="monthFilter" dir="ltr">
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الشهر</th>
                <th scope="col">عدد الموظفين</th>
                <th scope="col">إجمالي الصافي</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($runs as $run)
            <tr wire:key="run-{{ $run->id }}">
                <td dir="ltr" class="ds-ltr-num">{{ $run->month }}</td>
                <td class="ds-ltr-num">{{ $run->items_count }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $run->items_sum_net, 2) }} ر.س</td>
                <td>
                    <x-ds-status-badge :status="$run->status" />
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
                <td colspan="5"><x-ds-empty-state message="لا توجد مسيّرات" icon="fa-money-check-dollar" /></td>
            </tr>
        @endforelse
    </x-ds-table>

    {{ $runs->links() }}
</x-ds-page>
