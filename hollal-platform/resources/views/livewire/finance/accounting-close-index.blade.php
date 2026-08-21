<x-ds-page>
    <x-ds-page-header title="مراكز التكلفة والإقفال" />

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">مراكز التكلفة</h2>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="syncCenters">مزامنة من الأقسام والمشاريع</button>
        <x-ds-table>
            <x-slot:head><tr><th>المركز</th><th>مصروفات</th><th>إيرادات</th></tr></x-slot:head>
            @forelse ($centers as $row)
                <tr>
                    <td>{{ $row['cost_center'] }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['expenses'], 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['revenues'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-table-empty">لا مراكز بعد — نفّذ المزامنة</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">تسوية بنكية</h2>
        <div class="ds-grid-2">
            <x-ds-form-group label="حساب الصندوق/البنك">
                <select class="ds-input" wire:model="bankAccountId">
                    @foreach ($bankAccounts as $a)
                        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name_ar }}</option>
                    @endforeach
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="رصيد الكشف">
                <input type="number" step="0.01" class="ds-input" wire:model="statementBalance">
            </x-ds-form-group>
            <x-ds-form-group label="من"><input type="date" class="ds-input" wire:model="from"></x-ds-form-group>
            <x-ds-form-group label="إلى"><input type="date" class="ds-input" wire:model="to"></x-ds-form-group>
        </div>
        <button type="button" class="ds-btn" wire:click="reconcile">مطابقة</button>
        <x-ds-table>
            <x-slot:head><tr><th>الفترة</th><th>الكشف</th><th>الدفتر</th><th>الفرق</th><th>الحالة</th></tr></x-slot:head>
            @foreach ($reconciliations as $r)
                <tr>
                    <td dir="ltr">{{ $r->period_from?->format('Y-m-d') }} → {{ $r->period_to?->format('Y-m-d') }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $r->statement_balance, 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $r->book_balance, 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $r->difference, 2) }}</td>
                    <td>{{ $r->status }}</td>
                </tr>
            @endforeach
        </x-ds-table>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">رصيد افتتاحي</h2>
        <x-ds-form-group label="مبلغ الصندوق الافتتاحي">
            <input type="number" step="0.01" class="ds-input" wire:model="openingAmount">
        </x-ds-form-group>
        <button type="button" class="ds-btn" wire:click="postOpening">ترحيل افتتاحي</button>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-heading">إقفال سنوي</h2>
        <x-ds-form-group label="السنة">
            <input type="number" class="ds-input" wire:model="closeYear">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="closeFiscalYearAction" wire:confirm="إقفال السنة؟">إقفال السنة</button>
        <ul>
            @foreach ($closes as $c)
                <li>سنة {{ $c->year }} — {{ $c->closed_at }}</li>
            @endforeach
        </ul>
    </section>
</x-ds-page>
