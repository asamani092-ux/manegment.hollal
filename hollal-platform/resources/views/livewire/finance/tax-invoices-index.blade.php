<x-ds-page>
    <x-ds-page-header
        title="الفواتير الضريبية"
        :show-button="true"
        button-label="إصدار فاتورة"
        button-permission="finance.tax_invoices.issue"
        wire:click="openIssueModal"
    />

    <p class="ds-text-muted">وضع الفوترة الحالي: <strong>{{ $mode }}</strong></p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الرقم</th>
                <th>المشتري</th>
                <th>قبل الضريبة</th>
                <th>الضريبة</th>
                <th>الإجمالي</th>
                <th>تاريخ الإصدار</th>
                <th>الإشعارات</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($invoices as $invoice)
            <tr wire:key="tax-invoice-{{ $invoice->id }}">
                <td dir="ltr">{{ $invoice->number }}</td>
                <td>{{ $invoice->buyer_name }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $invoice->vat_total, 2) }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $invoice->total, 2) }}</td>
                <td dir="ltr">{{ $invoice->issued_at?->format('Y-m-d') }}</td>
                <td class="ds-ltr-num">{{ $invoice->notes_count }}</td>
                <td>
                    <a class="ds-btn ds-btn-sm" href="{{ route('tax-invoices.pdf', $invoice->id) }}">PDF</a>
                    @can('finance.tax_invoices.issue')
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="openNoteModal({{ $invoice->id }})">
                            إشعار دائن/مدين
                        </button>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="ds-text-muted ds-table-empty">لا توجد فواتير ضريبية</td></tr>
        @endforelse
    </x-ds-table>

    {{ $invoices->links() }}

    <x-ds-modal :show="$showIssueModal" title="إصدار فاتورة ضريبية" size="lg">
        <x-slot:header><h2>إصدار فاتورة ضريبية</h2></x-slot:header>

        <x-ds-form-group label="اسم المشتري" :error="$errors->first('buyerName')">
            <input type="text" class="ds-input" wire:model="buyerName">
        </x-ds-form-group>

        <x-ds-form-group label="الرقم الضريبي للمشتري" :error="$errors->first('buyerVatNumber')">
            <input type="text" class="ds-input" wire:model="buyerVatNumber" dir="ltr">
        </x-ds-form-group>

        @foreach ($lines as $index => $line)
            <div class="ds-form-row" wire:key="line-{{ $index }}">
                <x-ds-form-group label="الوصف" :error="$errors->first('lines.'.$index.'.description')">
                    <input type="text" class="ds-input" wire:model="lines.{{ $index }}.description">
                </x-ds-form-group>
                <x-ds-form-group label="الكمية" :error="$errors->first('lines.'.$index.'.quantity')">
                    <input type="number" step="0.01" class="ds-input" wire:model="lines.{{ $index }}.quantity">
                </x-ds-form-group>
                <x-ds-form-group label="سعر الوحدة" :error="$errors->first('lines.'.$index.'.unit_price')">
                    <input type="number" step="0.01" class="ds-input" wire:model="lines.{{ $index }}.unit_price">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-sm" wire:click="removeLine({{ $index }})">حذف</button>
            </div>
        @endforeach

        <button type="button" class="ds-btn ds-btn-sm" wire:click="addLine">إضافة بند</button>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showIssueModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="issue">إصدار</button>
        </x-slot:footer>
    </x-ds-modal>

    <x-ds-modal :show="$showNoteModal" title="إشعار دائن / مدين">
        <x-slot:header><h2>إشعار دائن / مدين</h2></x-slot:header>

        <x-ds-form-group label="النوع" :error="$errors->first('noteType')">
            <select class="ds-input" wire:model="noteType">
                <option value="دائن">دائن</option>
                <option value="مدين">مدين</option>
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="القيمة قبل الضريبة" :error="$errors->first('noteAmount')">
            <input type="number" step="0.01" class="ds-input" wire:model="noteAmount">
        </x-ds-form-group>

        <x-ds-form-group label="السبب" :error="$errors->first('noteReason')">
            <input type="text" class="ds-input" wire:model="noteReason">
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showNoteModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="issueNote">إصدار الإشعار</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
