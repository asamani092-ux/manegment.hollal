<x-ds-page>
    <x-ds-page-header title="الدفاتر والقوائم المالية" />

    <div class="ds-page-toolbar ds-mb-3">
        <div class="ds-btn-group">
            <button type="button" class="ds-btn {{ $tab === 'trial' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('trial')">ميزان المراجعة</button>
            <button type="button" class="ds-btn {{ $tab === 'ledger' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('ledger')">دفتر الأستاذ</button>
            <button type="button" class="ds-btn {{ $tab === 'income' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('income')">قائمة الدخل</button>
            <button type="button" class="ds-btn {{ $tab === 'balance' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('balance')">المركز المالي</button>
            <button type="button" class="ds-btn {{ $tab === 'cash' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('cash')">التدفقات النقدية</button>
        </div>
    </div>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">من</label>
            <input type="date" class="ds-input" wire:model.live="from">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label">إلى</label>
            <input type="date" class="ds-input" wire:model.live="to">
        </div>
        @if ($tab === 'ledger')
            <div class="ds-filter-field">
                <label class="ds-label">الحساب</label>
                <select class="ds-input" wire:model.live="accountId">
                    <option value="">— اختر —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name_ar }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($tab === 'trial')
            <button type="button" class="ds-btn ds-btn-outline" wire:click="downloadTrialPdf">تصدير PDF</button>
        @endif
    </div>

    @if ($tab === 'trial')
        <x-ds-table>
            <x-slot:head>
                <tr><th>الرقم</th><th>الحساب</th><th>مدين</th><th>دائن</th></tr>
            </x-slot:head>
            @foreach ($trial['rows'] as $row)
                <tr>
                    <td class="ds-ltr-num">{{ $row['code'] }}</td>
                    <td>{{ $row['name_ar'] }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['debit'], 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['credit'], 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="2">المجموع</th>
                <th class="ds-ltr-num">{{ number_format($trial['total_debit'], 2) }}</th>
                <th class="ds-ltr-num">{{ number_format($trial['total_credit'], 2) }}</th>
            </tr>
        </x-ds-table>
        <p class="ds-text-muted">متوازن: {{ $trial['balanced'] ? 'نعم' : 'لا' }}</p>
    @elseif ($tab === 'ledger')
        <x-ds-table>
            <x-slot:head>
                <tr><th>التاريخ</th><th>القيد</th><th>الوصف</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr>
            </x-slot:head>
            @forelse ($ledger as $row)
                <tr>
                    <td dir="ltr">{{ $row['date'] }}</td>
                    <td class="ds-ltr-num">{{ $row['number'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['debit'], 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['credit'], 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-table-empty">اختر حسابًا</td></tr>
            @endforelse
        </x-ds-table>
    @elseif ($tab === 'income')
        <div class="ds-card">
            <p>الإيرادات: <strong class="ds-ltr-num">{{ number_format($income['revenues'], 2) }}</strong></p>
            <p>المصروفات: <strong class="ds-ltr-num">{{ number_format($income['expenses'], 2) }}</strong></p>
            <p>الفائض / العجز: <strong class="ds-ltr-num">{{ number_format($income['surplus'], 2) }}</strong></p>
        </div>
    @elseif ($tab === 'balance')
        <div class="ds-card">
            <p>الأصول: <strong class="ds-ltr-num">{{ number_format($balance['assets'], 2) }}</strong></p>
            <p>الخصوم: <strong class="ds-ltr-num">{{ number_format($balance['liabilities'], 2) }}</strong></p>
            <p>حقوق الملكية (+ الفائض): <strong class="ds-ltr-num">{{ number_format($balance['equity'], 2) }}</strong></p>
            <p>متوازن: {{ $balance['balanced'] ? 'نعم' : 'لا' }}</p>
        </div>
    @else
        <div class="ds-card">
            <p>صافي التدفق التشغيلي (الصندوق/البنك): <strong class="ds-ltr-num">{{ number_format($cash['operating'], 2) }}</strong></p>
        </div>
    @endif
</x-ds-page>
