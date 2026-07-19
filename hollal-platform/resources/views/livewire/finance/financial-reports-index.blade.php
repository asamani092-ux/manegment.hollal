<x-ds-page>
    <x-ds-page-header title="التقارير المالية (اشتقاق فقط)" />

    <section class="ds-section ds-filter-bar">
        <input type="month" class="ds-input" wire:model.live="month" dir="ltr">
        <a class="ds-btn ds-btn-secondary" href="{{ route('financial-reports.pdf', ['month' => $report['month']]) }}">
            <i class="fas fa-file-pdf"></i> تصدير PDF
        </a>
    </section>

    <section class="ds-section">
        @if ($reconciles)
            <p class="ds-badge ds-badge-success">مطابقة: بنود التقرير تساوي مجاميع الدفاتر المصدر</p>
        @else
            <p class="ds-badge ds-badge-danger">تحذير: التقرير غير مطابق للدفاتر المصدر</p>
        @endif
    </section>

    <section class="ds-section ds-stat-row">
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">الإيرادات المؤكدة</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($report['revenues_total'], 2) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">المصروفات</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($report['expenses_total'], 2) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">الرواتب المنفذة</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($report['payroll_total'], 2) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">الصافي</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($report['net'], 2) }}</span>
        </div>
    </section>

    <h2 class="ds-section-title">المصروفات حسب التصنيف</h2>
    <x-ds-table>
        <x-slot:head>
            <tr><th>التصنيف</th><th>الإجمالي</th></tr>
        </x-slot:head>
        @forelse ($report['expenses_by_category'] as $line)
            <tr wire:key="exp-line-{{ $line['category_id'] ?? 'none' }}">
                <td>{{ $expenseCategories[$line['category_id']] ?? 'غير مصنّف' }}</td>
                <td class="ds-ltr-num">{{ number_format($line['total'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="ds-text-muted ds-table-empty">لا توجد مصروفات في هذا الشهر</td></tr>
        @endforelse
    </x-ds-table>

    <h2 class="ds-section-title">الإيرادات حسب التصنيف</h2>
    <x-ds-table>
        <x-slot:head>
            <tr><th>التصنيف</th><th>الإجمالي</th></tr>
        </x-slot:head>
        @forelse ($report['revenues_by_category'] as $line)
            <tr wire:key="rev-line-{{ $line['category_id'] ?? 'none' }}">
                <td>{{ $revenueCategories[$line['category_id']] ?? 'غير مصنّف' }}</td>
                <td class="ds-ltr-num">{{ number_format($line['total'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="ds-text-muted ds-table-empty">لا توجد إيرادات مؤكدة في هذا الشهر</td></tr>
        @endforelse
    </x-ds-table>
</x-ds-page>
