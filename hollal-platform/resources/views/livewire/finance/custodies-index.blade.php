<x-ds-page>
    <x-ds-page-header
        title="العهد المالية"
        :show-button="true"
        button-label="طلب عهدة"
        button-icon="fa-plus"
        wire:click="openRequestModal"
    />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>المبلغ</th>
                <th>الغرض</th>
                <th>الحالة</th>
                <th>التاريخ</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($custodies as $custody)
            <tr wire:key="custody-{{ $custody->id }}">
                <td>{{ $custody->employee?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $custody->amount, 2) }} ر.س</td>
                <td>{{ $custody->purpose }}</td>
                <td><span class="ds-badge ds-badge-pending">{{ $custody->status }}</span></td>
                <td class="ds-ltr-num">{{ $custody->created_at?->format('Y-m-d') }}</td>
                <td>
                    @if ($canApprove && $custody->status === \App\Models\Custody::STATUS_REQUESTED)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="approveCustody({{ $custody->id }})">اعتماد</button>
                    @endif
                    @if ($canDisburse && $custody->status === \App\Models\Custody::STATUS_APPROVED)
                        <button type="button" class="ds-btn ds-btn-teal ds-btn-sm" wire:click="disburseCustody({{ $custody->id }})">صرف</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="ds-table-empty">لا توجد عهد مسجّلة</td></tr>
        @endforelse
    </x-ds-table>

    {{ $custodies->links() }}

    @if ($showRequestModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showRequestModal', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>طلب عهدة</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showRequestModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    @if ($canApprove)
                        <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
                            <select class="ds-input" wire:model="employee_id">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    @endif
                    <x-ds-form-group label="المبلغ" :error="$errors->first('amount')">
                        <input type="number" step="0.01" class="ds-input" wire:model="amount">
                    </x-ds-form-group>
                    <x-ds-form-group label="الغرض" :error="$errors->first('purpose')">
                        <textarea class="ds-input" rows="3" wire:model="purpose"></textarea>
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="submitRequest">إرسال الطلب</button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showRequestModal', false)">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
