<x-ds-page class="ds-attendance-cycle-page">
    <x-ds-page-header title="الحضور الشهري والخصم" />

    <p class="ds-text-muted ds-mb-3">
        أداة مستقلة عن برنامج التحضير اليومي: رفع ملف حركات يستبدل الشهر، أو إدخال مؤشرات تأخير/غياب يدوياً ثم احتساب الخصم من معادلات الإعدادات واعتماده من الموارد البشرية.
    </p>

    <x-ds-collapsible-card title="1) رفع حركات الشهر (استبدال)" class="ds-mt-3" :open="$wizardStep !== 'done'">
        @if ($wizardStep === 'upload')
            <p class="ds-text-muted">ارفع ملفاً واحداً يستبدل حركات البصمة لذلك الشهر بالكامل (وليس دمجاً تراكمياً). عند التعارض مع حضور المنصة تغلب البصمة المستوردة حالياً.</p>
            <div class="ds-filters-row">
                <div class="ds-filter-field">
                    <label class="ds-label" for="att-source">المصدر / الجهة</label>
                    <input id="att-source" type="text" class="ds-input" wire:model="sourceLabel" placeholder="مثال: جهاز المقر الرئيسي">
                </div>
                <div class="ds-filter-field">
                    <label class="ds-label" for="att-import-month">شهر الاستبدال</label>
                    <input id="att-import-month" type="month" class="ds-input ds-ltr-num" wire:model="importMonth">
                </div>
            </div>
            <x-ds-form-group label="ملف الحركات">
                <input type="file" class="ds-input" wire:model="uploadFile" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                <p class="ds-text-muted ds-mt-sm">الصيغ المدعومة: جداول إكسل أو ملف مفصول بفواصل.</p>
            </x-ds-form-group>
            <div wire:loading wire:target="uploadFile" class="ds-text-muted">جاري قراءة العناوين…</div>
        @endif

        @if ($wizardStep === 'map')
            <p class="ds-text-muted">اختر أي عمود من الصف الأول يمثّل كل حقل. تُحفظ المطابقة الناجحة لهذا المصدر تلقائياً للمرات القادمة.</p>
            <div class="ds-filters-row">
                <div class="ds-filter-field">
                    <label class="ds-label" for="att-import-month-map">شهر الاستبدال</label>
                    <input id="att-import-month-map" type="month" class="ds-input ds-ltr-num" wire:model="importMonth">
                </div>
            </div>
            @foreach ($roleLabels as $role => $label)
                <x-ds-form-group :label="$label">
                    <select class="ds-input" wire:model="columnMap.{{ $role }}">
                        <option value="">— اختر العمود —</option>
                        @foreach ($fileHeaders as $idx => $header)
                            <option value="{{ $idx }}">{{ $header !== '' ? $header : ('عمود '.($idx + 1)) }}</option>
                        @endforeach
                    </select>
                </x-ds-form-group>
            @endforeach
            <div class="ds-btn-group ds-mt-3">
                <button type="button" class="ds-btn ds-btn-primary" wire:click="confirmColumnMap">متابعة</button>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelImportWizard">إلغاء</button>
            </div>
        @endif

        @if ($wizardStep === 'match' && $pendingImport)
            <p class="ds-text-muted">صفوف بلا بصمة معروفة في ملفات الموظفين — طابق كل صف بموظف قبل الاعتماد النهائي.</p>
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>معرّف البصمة</th>
                        <th>التاريخ</th>
                        <th>الحضور</th>
                        <th>الموظف</th>
                    </tr>
                </x-slot:head>
                @foreach ($pendingImport->unmatched_rows ?? [] as $idx => $urow)
                    <tr wire:key="unmatched-{{ $idx }}">
                        <td class="ds-ltr-num">{{ $urow['fingerprint'] ?? '—' }}</td>
                        <td class="ds-ltr-num">{{ $urow['date'] ?? '—' }}</td>
                        <td class="ds-ltr-num">{{ $urow['check_in'] ?? '—' }}</td>
                        <td>
                            <select class="ds-input" wire:model="manualMatches.{{ $idx }}">
                                <option value="">— اختر موظفاً —</option>
                                @foreach ($attendees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
            </x-ds-table>
            <div class="ds-btn-group ds-mt-3">
                <button type="button" class="ds-btn ds-btn-primary" wire:click="confirmManualMatches">اعتماد الاستيراد والاستبدال</button>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelImportWizard">إلغاء</button>
            </div>
        @endif
    </x-ds-collapsible-card>

    <x-ds-collapsible-card title="2) مؤشرات يدوية (بدون ملف)" class="ds-mt-3" :open="false">
        <p class="ds-text-muted">أدخل ساعات التأخير وأيام الغياب فقط. النظام يحسب مبلغ الخصم من معادلات الإعدادات — لا يُدخل المبلغ النهائي يدوياً.</p>
        <p class="ds-text-muted ds-ltr-num">{{ $hourValueHint }}</p>
        <x-ds-form-group label="الموظف">
            <select class="ds-input" wire:model="manualEmployeeId">
                <option value="">—</option>
                @foreach ($attendees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <label class="ds-label" for="manual-late">ساعات التأخير</label>
                <input id="manual-late" type="number" step="0.25" min="0" class="ds-input ds-ltr-num" wire:model="manualLateHours">
            </div>
            <div class="ds-filter-field">
                <label class="ds-label" for="manual-abs">أيام الغياب</label>
                <input id="manual-abs" type="number" min="0" class="ds-input ds-ltr-num" wire:model="manualAbsenceDays">
            </div>
        </div>
        <x-ds-form-group label="ملاحظة">
            <input type="text" class="ds-input" wire:model="manualNotes">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveManualIndicator">حفظ المؤشرات</button>

        @if ($manualRows->isNotEmpty())
            <div class="ds-table-wrap ds-mt-3">
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>الموظف</th>
                            <th>ساعات تأخير</th>
                            <th>أيام غياب</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($manualRows as $ind)
                        <tr>
                            <td>{{ $ind->employee?->name }}</td>
                            <td class="ds-ltr-num">{{ $ind->late_hours }}</td>
                            <td class="ds-ltr-num">{{ $ind->absence_days }}</td>
                        </tr>
                    @endforeach
                </x-ds-table>
            </div>
        @endif
    </x-ds-collapsible-card>

    <x-ds-collapsible-card title="3) تقرير الشهر" class="ds-mt-3" :open="$showMonthlyReport">
        <x-ds-form-group label="الشهر">
            <input type="month" class="ds-input ds-ltr-num" wire:model.live="reportMonth">
        </x-ds-form-group>
        <label class="ds-text-muted">
            <input type="checkbox" wire:model.live="showMonthlyReport"> عرض التقرير
        </label>
        @if ($monthlyReport)
            <p class="ds-text-muted">
                بداية الدوام: <span class="ds-ltr-num">{{ $monthlyReport['office_start'] }}</span>
                · السجلات: <span class="ds-ltr-num">{{ count($monthlyReport['rows']) }}</span>
                · العمل بعد نهاية الوردية للعرض فقط (لا يُضاف للمسير أو المكافأة تلقائياً)
            </p>
            <div class="ds-table-wrap">
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>التاريخ</th>
                            <th>الموظف</th>
                            <th>النوع</th>
                            <th>المصدر</th>
                            <th>حضور</th>
                            <th>انصراف</th>
                            <th>تأخير (د)</th>
                            <th>انصراف مبكر (د)</th>
                            <th>عمل إضافي</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($monthlyReport['rows'] as $row)
                        <tr>
                            <td class="ds-ltr-num">{{ $row['date'] }}</td>
                            <td>{{ $row['employee'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td>{{ $row['source'] ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $row['check_in'] ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $row['check_out'] ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $row['late_minutes'] > 0 ? $row['late_minutes'] : '—' }}</td>
                            <td class="ds-ltr-num">{{ ($row['early_leave_minutes'] ?? 0) > 0 ? $row['early_leave_minutes'] : '—' }}</td>
                            <td class="ds-ltr-num">{{ ($row['extra_work_minutes'] ?? 0) > 0 ? ($row['extra_work_label'] ?? $row['extra_work_minutes']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="ds-table-empty">لا سجلات لهذا الشهر</td></tr>
                    @endforelse
                </x-ds-table>
            </div>
        @endif
    </x-ds-collapsible-card>

    <x-ds-collapsible-card title="4) تقرير الخصومات والاعتماد" class="ds-mt-3" :open="true">
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
                    <th>المصدر</th>
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
                    <td>{{ $row['source'] ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $row['present_days'] }}</td>
                    <td class="ds-ltr-num">{{ $row['absence_days'] }}</td>
                    <td class="ds-ltr-num">{{ $row['chargeable_late_minutes'] }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['late_deduction'], 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format($row['absence_deduction'], 2) }}</td>
                    <td class="ds-ltr-num"><strong>{{ number_format($row['total_deduction'], 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="8" class="ds-table-empty">لا موظفون مفعّلو الحضور</td></tr>
            @endforelse
        </x-ds-table>

        <div class="ds-btn-group ds-mt-3">
            @if (! $approval || in_array($approval->status, ['مسودة'], true))
                <button type="button" class="ds-btn ds-btn-primary" wire:click="approve" wire:confirm="اعتماد خصم الدورة؟ الاعتماد للموارد البشرية فقط.">
                    اعتماد الخصم
                </button>
            @endif
        </div>

        @if ($approval && $approval->status === 'معتمد')
            <div class="ds-mt-3">
                <h3 class="ds-section-title">تصحيح بعد الاعتماد</h3>
                <x-ds-form-group label="سبب التصحيح">
                    <textarea class="ds-input" rows="2" wire:model="correctionReason"></textarea>
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="requestCorrection">طلب تصحيح</button>
            </div>
        @endif

        @if ($approval && $approval->status === 'بانتظار_تصحيح')
            <div class="ds-alert ds-alert-warning ds-mt-3">
                طلب تصحيح: {{ $approval->correction_reason }}
                <div class="ds-btn-group ds-mt-sm">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="approveCorrection" wire:confirm="الموافقة على التصحيح وإعادة فتح الدورة؟">
                        موافقة على التصحيح
                    </button>
                </div>
            </div>
        @endif
    </x-ds-collapsible-card>

    <x-ds-collapsible-card title="5) تطبيق على مسودة المسير" class="ds-mt-3" :open="false">
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
    </x-ds-collapsible-card>
</x-ds-page>
