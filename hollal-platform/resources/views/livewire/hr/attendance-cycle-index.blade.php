<x-ds-page>
    <x-ds-page-header title="دورة الحضور والخصم" />

    <div class="ds-alert ds-alert-warning ds-mb-3">
        <strong>النوع الأول (المعتمد حالياً):</strong>
        ارفع ملف Excel/CSV لحركات الحضور والانصراف → يُصدر النظام تقرير الشهر وتقرير الخصومات والمبالغ → اعتمد الدورة → طبّق على مسودة المسير.
        <br>
        <strong>النوع الثاني (ورديات مباشرة + ميداني):</strong> قيد الدراسة — راجع <code>docs/plans/ATTENDANCE-SHIFTS-DESIGN.md</code>.
    </div>

    <section class="ds-card ds-mb-3">
        <h2 class="ds-section-title">1) رفع ملف الحضور (Excel / CSV)</h2>
        <p class="ds-text-muted">الأعمدة: <span class="ds-ltr-num">fingerprint_id, date, check_in, check_out</span> — يُطابق الرقم بملف الموظف (<code>fingerprint_id</code>).</p>
        <input type="file" class="ds-input" wire:model="csvFile" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
        <div wire:loading wire:target="csvFile" class="ds-text-muted">جاري الرفع…</div>
        <button type="button" class="ds-btn ds-btn-primary ds-mt-sm" wire:click="importCsv">رفع واستيراد</button>
    </section>

    <section class="ds-card ds-mb-3">
        <h2 class="ds-section-title">2) تقرير الحضور الشهري</h2>
        <x-ds-form-group label="الشهر">
            <input type="month" class="ds-input ds-ltr-num" wire:model.live="reportMonth">
        </x-ds-form-group>
        @if ($monthlyReport)
            <p class="ds-text-muted">بداية الدوام: <span class="ds-ltr-num">{{ $monthlyReport['office_start'] }}</span> · السجلات: <span class="ds-ltr-num">{{ count($monthlyReport['rows']) }}</span></p>
            <div class="ds-table-wrap">
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>التاريخ</th>
                            <th>الموظف</th>
                            <th>النوع</th>
                            <th>حضور</th>
                            <th>انصراف</th>
                            <th>تأخير (د)</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($monthlyReport['rows'] as $row)
                        <tr>
                            <td class="ds-ltr-num">{{ $row['date'] }}</td>
                            <td>{{ $row['employee'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td class="ds-ltr-num">{{ $row['check_in'] ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $row['check_out'] ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $row['late_minutes'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ds-table-empty">لا سجلات لهذا الشهر — ارفع ملف الحركات أولاً</td></tr>
                    @endforelse
                </x-ds-table>
            </div>
        @endif
    </section>

    <section class="ds-card ds-mb-3">
        <h2 class="ds-section-title">3) تقرير الخصومات والمبالغ (للموارد البشرية)</h2>
        <x-ds-form-group label="مرجع تاريخ الدورة">
            <input type="date" class="ds-input" wire:model.live="asOf">
        </x-ds-form-group>
        <p class="ds-text-muted">
            نافذة الدورة:
            من <span class="ds-ltr-num">{{ $cycle['from']->toDateString() }}</span>
            إلى <span class="ds-ltr-num">{{ $cycle['to']->toDateString() }}</span>
            @if ($approval)
                · الحالة: <strong>{{ $approval->status }}</strong>
            @endif
            · إجمالي الخصومات: <strong class="ds-ltr-num">{{ number_format($amountsTotal, 2) }}</strong> ر.س
        </p>
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الموظف</th>
                    <th>أيام حضور</th>
                    <th>أيام غياب</th>
                    <th>تأخير (د)</th>
                    <th>خصم تأخير</th>
                    <th>خصم غياب</th>
                    <th>إجمالي الخصم</th>
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
                    <td class="ds-ltr-num"><strong>{{ number_format($row['total_deduction'], 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7" class="ds-table-empty">لا موظفون مفعّلو الحضور</td></tr>
            @endforelse
        </x-ds-table>
        <div class="ds-btn-group ds-mt-3">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="approve" wire:confirm="اعتماد تقرير الدورة؟">4) اعتماد الدورة</button>
        </div>
    </section>

    <section class="ds-card ds-mb-3">
        <h2 class="ds-section-title">5) تطبيق الخصم على مسودة المسير</h2>
        <p class="ds-text-muted">بعد الاعتماد يُضاف بند متغيّر «خصم حضور الدورة» لكل موظف له خصم &gt; 0.</p>
        <x-ds-form-group label="مسير مسودة">
            <select class="ds-input" wire:model="applyRunId">
                <option value="">—</option>
                @foreach ($draftRuns as $run)
                    <option value="{{ $run->id }}">#{{ $run->id }} — {{ $run->month }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="applyToPayroll">إضافة بنود الخصم</button>
    </section>

    <details class="ds-card ds-mb-3">
        <summary class="ds-section-title" style="cursor:pointer">أدوات تجريبية (باركود / ميداني) — النوع الثاني لاحقاً</summary>
        <div class="ds-grid-2 ds-mt-3">
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
    </details>
</x-ds-page>
