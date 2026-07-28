<x-ds-page>
    <x-ds-page-header title="الحضور والإجازات" :show-button="false" />

    @if ($attendanceEnabled)
        <div class="ds-card ds-mb-3" style="padding:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="checkIn">تسجيل حضور</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="checkOut">تسجيل انصراف</button>
        </div>
        <div class="ds-card ds-mb-3" style="padding:1rem">
            <x-ds-form-group label="نوع الإقرار">
                <select class="ds-input" wire:model="type">
                    <option value="حضور">حضور</option>
                    <option value="عن بعد">عن بعد</option>
                    <option value="تكليف خارجي">تكليف خارجي</option>
                    <option value="انقطاع">انقطاع</option>
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="ملاحظة" :error="$errors->first('notes')">
                <input type="text" class="ds-input" wire:model="notes">
            </x-ds-form-group>
            <button type="button" class="ds-btn ds-btn-teal" wire:click="declareType">حفظ الإقرار</button>
        </div>
    @endif

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>التاريخ</th>
                <th>النوع</th>
                <th>حضور</th>
                <th>انصراف</th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <tr wire:key="att-{{ $record->id }}">
                <td>{{ $record->employee?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $record->date?->format('Y-m-d') }}</td>
                <td>{{ $record->type }}</td>
                <td class="ds-ltr-num">{{ $record->check_in_at?->format('H:i') ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $record->check_out_at?->format('H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="ds-table-empty">لا توجد سجلات حضور</td></tr>
        @endforelse
    </x-ds-table>
    {{ $records->links() }}
</x-ds-page>
