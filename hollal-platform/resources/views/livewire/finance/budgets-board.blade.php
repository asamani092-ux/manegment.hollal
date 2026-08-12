<x-ds-page>
    <x-ds-page-header title="الموازنات (محسوبة آليًا)" />

    <p class="ds-text-muted ds-mb-3">مصدر الموازنة: القيمة المعتمدة على بطاقة المشروع، وتُزاد تراكميًا بعد اعتماد المدير التنفيذي.</p>

    <section class="ds-section ds-filter-bar">
        <select class="ds-input" wire:model.live="tierFilter">
            <option value="">كل المشاريع</option>
            <option value="warning">بلغت حد التنبيه ({{ $warningThreshold }}%) فأكثر</option>
            <option value="over">تجاوزت الموازنة (100%)</option>
        </select>
    </section>

    <section class="ds-section ds-stat-row">
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">إجمالي الموازنات</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format((float) $totals['budget'], 2) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">إجمالي المستهلك</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format((float) $totals['consumed'], 2) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">إجمالي المتبقي</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format((float) $totals['remaining'], 2) }}</span>
        </div>
    </section>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>المشروع</th>
                <th>مصدر الموازنة</th>
                <th>الموازنة</th>
                <th>المصروف فعليًا</th>
                <th>المرتبط (معتمد)</th>
                <th>المستهلك</th>
                <th>المتبقي</th>
                <th>نسبة الاستهلاك</th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr wire:key="budget-{{ $row['project']->id }}">
                <td>
                    <a href="{{ route('projects.show', $row['project']->id) }}">{{ $row['project']->name }}</a>
                </td>
                <td>بطاقة المشروع + إضافات معتمدة</td>
                <td class="ds-ltr-num">{{ number_format($row['budget'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['actual_spend'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['committed'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['consumed'], 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($row['remaining'], 2) }}</td>
                <td class="ds-ltr-num">
                    @if ($row['percent'] >= 100)
                        <span class="ds-badge ds-badge-danger">{{ $row['percent'] }}%</span>
                    @elseif ($row['percent'] >= $warningThreshold)
                        <span class="ds-badge ds-badge-warning">{{ $row['percent'] }}%</span>
                    @else
                        <span class="ds-badge ds-badge-success">{{ $row['percent'] }}%</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="ds-text-muted ds-table-empty">لا توجد مشاريع ذات موازنة</td></tr>
        @endforelse
    </x-ds-table>

    <section class="ds-section">
        <h3 class="ds-section-title">إضافة للموازنة</h3>
        <x-ds-form-group label="المشروع" :error="$errors->first('addProjectId')">
            <select class="ds-input" wire:model="addProjectId">
                <option value="">—</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="المبلغ" :error="$errors->first('addAmount')">
            <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="addAmount">
        </x-ds-form-group>
        <x-ds-form-group label="ملاحظة">
            <input type="text" class="ds-input" wire:model="addNote">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="requestAddition">إرسال للاعتماد التنفيذي</button>
    </section>

    @if ($pendingAdditions->isNotEmpty())
        <h3 class="ds-section-title">طلبات بانتظار المدير التنفيذي</h3>
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>المشروع</th>
                    <th>المبلغ</th>
                    <th>مقدّم الطلب</th>
                    <th>إجراء</th>
                </tr>
            </x-slot:head>
            @foreach ($pendingAdditions as $addition)
                <tr wire:key="add-{{ $addition->id }}">
                    <td>{{ $addition->project?->name }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $addition->amount, 2) }}</td>
                    <td>{{ $addition->requester?->name }}</td>
                    <td>
                        @if ($canApproveBudget)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="approveAddition({{ $addition->id }})">اعتماد</button>
                        @else
                            <span class="ds-text-muted">بانتظار الاعتماد</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ds-table>
    @endif
</x-ds-page>
