<x-ds-page>
    <x-ds-page-header
        title="المسؤوليات"
        :show-button="true"
        button-label="بند جديد"
        button-icon="fa-plus"
        wire:click="openForm"
    />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>البند</th>
                <th>الترتيب</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($items as $item)
            <tr wire:key="resp-{{ $item->id }}">
                <td>{{ $item->employee?->name ?? '—' }}</td>
                <td>{{ $item->body }}</td>
                <td class="ds-ltr-num">{{ $item->order }}</td>
                <td>{{ $item->is_active ? 'نشط' : 'موقوف' }}</td>
                <td>
                    @if ($item->is_active)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deactivate({{ $item->id }})">إيقاف</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="ds-table-empty">لا توجد مسؤوليات</td></tr>
        @endforelse
    </x-ds-table>
    {{ $items->links() }}

    @if ($showForm)
        <div class="ds-modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>بند مسؤولية</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showForm', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
                        <select class="ds-input" wire:model="employee_id">
                            <option value="">—</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="البند" :error="$errors->first('body')">
                        <textarea class="ds-input" wire:model="body" rows="3"></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="الترتيب" :error="$errors->first('order')">
                        <input type="number" class="ds-input" wire:model="order" min="1" max="20">
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
