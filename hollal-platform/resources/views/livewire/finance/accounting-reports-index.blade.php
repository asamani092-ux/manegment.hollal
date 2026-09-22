<x-ds-page>
    <x-ds-page-header title="الدفاتر والقوائم المالية" />

    <div class="ds-page-toolbar ds-mb-3">
        <div class="ds-btn-group">
            <button type="button" class="ds-btn {{ $tab === 'trial' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('trial')">ميزان المراجعة</button>
            <button type="button" class="ds-btn {{ $tab === 'ledger' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('ledger')">دفتر الأستاذ</button>
            <button type="button" class="ds-btn {{ $tab === 'income' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setTab('income')">قائمة الأنشطة</button>
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
        @if ($tab === 'cash')
            <button type="button" class="ds-btn ds-btn-outline" wire:click="downloadCashFlowPdf">تصدير PDF</button>
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
            <h3 class="ds-section-heading">قائمة الأنشطة</h3>
            <p>إيرادات غير مقيّدة: <strong class="ds-ltr-num">{{ number_format($income['unrestricted_revenue'], 2) }}</strong></p>
            <p>إيرادات مقيّدة: <strong class="ds-ltr-num">{{ number_format($income['restricted_revenue'], 2) }}</strong></p>
            <p>إجمالي الإيرادات: <strong class="ds-ltr-num">{{ number_format($income['total_revenue'], 2) }}</strong></p>
            <p>المصروفات: <strong class="ds-ltr-num">{{ number_format($income['expenses'], 2) }}</strong></p>
            <p>التغيّر في صافي الأصول: <strong class="ds-ltr-num">{{ number_format($income['change_in_net_assets'], 2) }}</strong></p>
        </div>
    @elseif ($tab === 'balance')
        <div class="ds-card">
            <h3 class="ds-section-heading">قائمة المركز المالي</h3>
            <p>الأصول: <strong class="ds-ltr-num">{{ number_format($balance['assets'], 2) }}</strong></p>
            <p>الخصوم: <strong class="ds-ltr-num">{{ number_format($balance['liabilities'], 2) }}</strong></p>
            <p>صافي أصول غير مقيّدة: <strong class="ds-ltr-num">{{ number_format($balance['unrestricted_net_assets'], 2) }}</strong></p>
            <p>صافي أصول مقيّدة: <strong class="ds-ltr-num">{{ number_format($balance['restricted_net_assets'], 2) }}</strong></p>
            <p>إجمالي صافي الأصول: <strong class="ds-ltr-num">{{ number_format($balance['total_net_assets'], 2) }}</strong></p>
            <p>متوازن: {{ $balance['balanced'] ? 'نعم' : 'لا' }}</p>
        </div>
    @else
        <div class="ds-card">
            <h3 class="ds-section-heading">قائمة التدفقات النقدية</h3>
            <p>صافي النقد من التشغيل: <strong class="ds-ltr-num">{{ number_format($cash['operating'], 2) }}</strong></p>
            <p>صافي النقد من الاستثمار: <strong class="ds-ltr-num">{{ number_format($cash['investing'], 2) }}</strong></p>
            <p>صافي النقد من التمويل: <strong class="ds-ltr-num">{{ number_format($cash['financing'], 2) }}</strong></p>
            <p>صافي التغيّر في النقد: <strong class="ds-ltr-num">{{ number_format($cash['net_change'], 2) }}</strong></p>
            <p>رصيد النقد — أول المدة: <strong class="ds-ltr-num">{{ number_format($cash['opening_cash'], 2) }}</strong></p>
            <p>رصيد النقد — آخر المدة: <strong class="ds-ltr-num">{{ number_format($cash['closing_cash'], 2) }}</strong></p>
        </div>
    @endif
</x-ds-page>
