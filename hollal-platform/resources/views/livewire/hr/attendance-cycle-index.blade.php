<x-ds-page>
    <x-ds-page-header title="دورة الحضور والخصم" />

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">نافذة الدورة</h2>
        <x-ds-form-group label="مرجع التاريخ">
            <input type="date" class="ds-input" wire:model.live="asOf">
        </x-ds-form-group>
        <p class="ds-text-muted">
            من <span class="ds-ltr-num">{{ $cycle['from']->toDateString() }}</span>
            إلى <span class="ds-ltr-num">{{ $cycle['to']->toDateString() }}</span>
            @if ($approval)
                · الحالة: {{ $approval->status }}
            @endif
        </p>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="approve" wire:confirm="اعتماد التقرير وربط الخصم؟">اعتماد الدورة</button>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">تقرير الخصم (ATT-4)</h2>
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الموظف</th>
                    <th>حضور</th>
                    <th>غياب</th>
                    <th>تأخير (د)</th>
                    <th>خصم تأخير</th>
                    <th>خصم غياب</th>
                    <th>الإجمالي</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="ds-ltr-num">{{ $row['present_days'] }}</td>
                    <td class="ds-ltr-num">{{ $row['absence_days'] }}</td>
                    <td class="ds-ltr-num">{{ $row['chargeable_late_minutes'] }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['late_deduction'], 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['absence_deduction'], 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['total_deduction'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="ds-table-empty">لا موظفون مفعّلو الحضور</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">تطبيق على مسودة المسير</h2>
        <x-ds-form-group label="مسير مسودة">
            <select class="ds-input" wire:model="applyRunId">
                <option value="">—</option>
                @foreach ($draftRuns as $run)
                    <option value="{{ $run->id }}">#{{ $run->id }} — {{ $run->month }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <button type="button" class="ds-btn" wire:click="applyToPayroll">إضافة بنود الخصم</button>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">استيراد CSV (بصمة)</h2>
        <p class="ds-text-muted">الأعمدة: fingerprint_id,date,check_in,check_out</p>
        <input type="file" class="ds-input" wire:model="csvFile" accept=".csv,text/csv">
        <button type="button" class="ds-btn" wire:click="importCsv">رفع واستيراد</button>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">باركود المقر / عمل ميداني</h2>
        <div class="ds-grid-2">
            <x-ds-form-group label="رمز الباركود">
                <input type="text" class="ds-input" wire:model="barcodeToken">
            </x-ds-form-group>
            <div style="align-self:end"><button type="button" class="ds-btn" wire:click="scanBarcode">تسجيل بالباركود</button></div>
            <x-ds-form-group label="موقع ميداني">
                <input type="text" class="ds-input" wire:model="fieldLocation">
            </x-ds-form-group>
            <div style="align-self:end"><button type="button" class="ds-btn" wire:click="startField">بدء ميداني</button></div>
        </div>
        <x-ds-table>
            <x-slot:head><tr><th>الموظف</th><th>الموقع</th><th>إجراء</th></tr></x-slot:head>
            @forelse ($pendingField as $rec)
                <tr>
                    <td>{{ $rec->employee?->name }}</td>
                    <td>{{ $rec->field_location }}</td>
                    <td><button type="button" class="ds-btn ds-btn-sm" wire:click="approveField({{ $rec->id }})">اعتماد</button></td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-table-empty">لا طلبات ميدانية معلّقة</td></tr>
            @endforelse
        </x-ds-table>
    </section>
</x-ds-page>
