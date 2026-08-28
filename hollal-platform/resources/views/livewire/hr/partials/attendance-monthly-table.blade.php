<div class="ds-attendance-print-block">
    <p>
        بداية الدوام الافتراضية: <span class="ds-ltr-num">{{ $printReport['office_start'] }}</span>
        · عدد السجلات: <span class="ds-ltr-num">{{ count($printReport['rows']) }}</span>
        · التأخر يُحسب من وردية كل موظف عند وجودها
        · العمل بعد نهاية الوردية للعرض فقط (لا يُضاف للمسير تلقائياً)
    </p>
    <table class="ds-table">
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>الموظف</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>حضور</th>
                <th>انصراف</th>
                <th>تأخر (د)</th>
                <th>انصراف مبكر (د)</th>
                <th>عمل إضافي</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($printReport['rows'] as $row)
                <tr>
                    <td class="ds-ltr-num">{{ $row['date'] }}</td>
                    <td>{{ $row['employee'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['approval_status'] ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $row['check_in'] ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $row['check_out'] ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $row['late_minutes'] > 0 ? $row['late_minutes'] : '—' }}</td>
                    <td class="ds-ltr-num">{{ ($row['early_leave_minutes'] ?? 0) > 0 ? $row['early_leave_minutes'] : '—' }}</td>
                    <td class="ds-ltr-num">{{ ($row['extra_work_minutes'] ?? 0) > 0 ? ($row['extra_work_label'] ?? $row['extra_work_minutes']) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9">لا توجد سجلات لهذا الشهر</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
