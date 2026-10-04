<div>
    <x-ds-page>
        <x-ds-page-header title="تسويات الرواتب" screen="hr.payroll-adjustments" />
        <label>الشهر
            <input class="ds-input" wire:model.live="month" placeholder="2026-08">
        </label>
        @foreach ($rows as $row)
            <p wire:key="adj-{{ $row->id }}">
                {{ $row->referenceItem?->name_ar ?? 'بند' }} — {{ $row->status }} — {{ $row->amount }}
                @if ($row->computed_amount !== null)
                    <span>المحسوب {{ $row->computed_amount }}</span>
                @endif
                @if ($canManage && $row->status === 'proposed')
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="approve({{ $row->id }})">اعتماد</button>
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="modifyAmount({{ $row->id }}, {{ (float) $row->amount }}, 'تعديل المبلغ')">تعديل</button>
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="cancel({{ $row->id }}, 'إلغاء التسوية')">إلغاء</button>
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="defer({{ $row->id }}, 'تأجيل للشهر التالي')">تأجيل</button>
                @endif
            </p>
        @endforeach
        <h2>الإجمالي حسب البند</h2>
        @foreach ($totals['by_item'] as $label => $amount)
            <p>{{ $label }}: {{ $amount }}</p>
        @endforeach
        @if ($canManage)
            <h2>إضافة مكتسبة</h2>
            <select class="ds-input" wire:model="manualEmployeeId">
                <option value="">الموظف</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
            <select class="ds-input" wire:model="manualItemId">
                <option value="">البند</option>
                @foreach ($earningItems as $item)
                    <option value="{{ $item->id }}">{{ $item->name_ar }}</option>
                @endforeach
            </select>
            <input class="ds-input" wire:model="manualAmount" placeholder="المبلغ">
            <input class="ds-input" wire:model="manualReference" placeholder="مرجع المشروع أو السبب">
            <button type="button" class="ds-btn" wire:click="addEarning">إضافة</button>
        @endif
    </x-ds-page>
</div>
