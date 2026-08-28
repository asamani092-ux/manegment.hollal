<div class="ds-attendance-print-block">
    <p>بداية الدوام: <span class="ds-ltr-num">{{ $printReport['office_start'] }}</span> · عدد السجلات: <span class="ds-ltr-num">{{ count($printReport['rows']) }}</span></p>
    <table class="ds-table">
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>الموظف</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>حضور</th>
                <th>انصراف</th>
                <th>تأخر (دقيقة)</th>
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
                </tr>
            @empty
                <tr><td colspan="7">لا توجد سجلات لهذا الشهر</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
