<x-ds-page>
    <x-ds-page-header title="التقارير المالية (اشتقاق فقط)" />

    <section class="ds-section ds-filter-bar">
        <input type="month" class="ds-input" wire:model.live="month" dir="ltr">
        <a class="ds-btn ds-btn-secondary" href="{{ route('financial-reports.pdf', ['month' => $report['month'], 'type' => $reportTab, 'print' => 1]) }}" target="_blank" rel="noopener">
            <i class="fas fa-print"></i> طباعة / PDF
        </a>
        <a class="ds-btn ds-btn-secondary" href="{{ route('financial-reports.excel', ['month' => $report['month'], 'type' => $reportTab]) }}">
            <i class="fas fa-file-excel"></i> تصدير Excel
        </a>
    </section>

    <nav class="ds-tabs" role="tablist">
        <button type="button" class="ds-tab {{ $reportTab === 'summary' ? 'ds-tab-active' : '' }}" wire:click="$set('reportTab', 'summary')">ملخص</button>
        <button type="button" class="ds-tab {{ $reportTab === 'detailed' ? 'ds-tab-active' : '' }}" wire:click="$set('reportTab', 'detailed')">مفصّل (الحركات)</button>
    </nav>

    <div class="ds-tab-panel">
        @if ($reportTab === 'summary')
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
        @else
            <section class="ds-section">
                @if ($detailedReconciles)
                    <p class="ds-badge ds-badge-success">مطابقة: إجمالي الحركات يساوي ملخص الشهر</p>
                @else
                    <p class="ds-badge ds-badge-danger">تحذير: إجمالي الحركات لا يطابق ملخص الشهر</p>
                @endif
            </section>

            <section class="ds-section ds-stat-row">
                <div class="ds-stat-mini">
                    <span class="ds-stat-mini-label">إجمالي المصروفات</span>
                    <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($detailed['totals']['expenses'], 2) }}</span>
                </div>
                <div class="ds-stat-mini">
                    <span class="ds-stat-mini-label">إجمالي الإيرادات</span>
                    <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($detailed['totals']['revenues'], 2) }}</span>
                </div>
                <div class="ds-stat-mini">
                    <span class="ds-stat-mini-label">إجمالي الرواتب</span>
                    <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($detailed['totals']['payroll'], 2) }}</span>
                </div>
            </section>

            <h2 class="ds-section-title">حركات الشهر</h2>
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>التاريخ</th>
                        <th>النوع</th>
                        <th>الوصف</th>
                        <th>التصنيف</th>
                        <th>المشروع</th>
                        <th>المبلغ</th>
                    </tr>
                </x-slot:head>
                @forelse ($detailed['movements'] as $index => $movement)
                    @php
                        $categoryLabel = match ($movement['type']) {
                            'مصروف' => $expenseCategories[$movement['category_id']] ?? '—',
                            'إيراد' => $revenueCategories[$movement['category_id']] ?? '—',
                            default => '—',
                        };
                    @endphp
                    <tr wire:key="movement-{{ $index }}">
                        <td class="ds-ltr-num">{{ $movement['date'] }}</td>
                        <td><x-ds-status-badge :status="$movement['type']" /></td>
                        <td>{{ $movement['description'] ?? '—' }}</td>
                        <td>{{ $categoryLabel }}</td>
                        <td>{{ $movement['project'] ?? '—' }}</td>
                        <td class="ds-ltr-num">{{ number_format($movement['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد حركات في هذا الشهر</td></tr>
                @endforelse
            </x-ds-table>
        @endif
    </div>
</x-ds-page>
