<div>
    @if ($selectedReport)
        <x-ds-page-header title="تفاصيل التقرير الأسبوعي" />

        <div class="ds-page-toolbar">
            <button type="button" class="ds-link" wire:click="closeReport">
                <i class="fas fa-arrow-right"></i> العودة للقائمة
            </button>
        </div>

        <div class="ds-card ds-mb-lg">
            <p><strong>الفترة:</strong> {{ $selectedReport->week_start->format('Y-m-d') }} — {{ $selectedReport->week_end->format('Y-m-d') }}</p>
            <p><strong>تاريخ الإنشاء:</strong> {{ $selectedReport->generated_at->format('Y-m-d H:i') }}</p>
            <p><strong>إنفاق الأسبوع:</strong> {{ number_format((float) $selectedReport->week_spend, 2) }}</p>
        </div>

        <div class="ds-card ds-mb-lg">
            <h3 class="ds-card-title">المهام المنجزة</h3>
            @forelse ($selectedReport->done ?? [] as $task)
                <p wire:key="done-{{ $task['id'] ?? $loop->index }}">
                    {{ $task['title'] ?? '—' }}
                    @if (! empty($task['assignee']))
                        <span class="ds-text-muted">({{ $task['assignee'] }})</span>
                    @endif
                </p>
            @empty
                <p class="ds-text-muted">لا مهام منجزة هذا الأسبوع</p>
            @endforelse
        </div>

        <div class="ds-card ds-mb-lg">
            <h3 class="ds-card-title">المهام المتأخرة</h3>
            @forelse ($selectedReport->overdue ?? [] as $task)
                <p wire:key="overdue-{{ $task['id'] ?? $loop->index }}">
                    {{ $task['title'] ?? '—' }}
                    @if (! empty($task['due_date']))
                        <span class="ds-text-muted">({{ $task['due_date'] }})</span>
                    @endif
                </p>
            @empty
                <p class="ds-text-muted">لا مهام متأخرة</p>
            @endforelse
        </div>

        <div class="ds-card ds-mb-lg">
            <h3 class="ds-card-title">حالة المشاريع</h3>
            <div class="ds-table-wrap">
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>المشروع</th>
                            <th>نسبة الإنجاز</th>
                            <th>المهام</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($selectedReport->project_status ?? [] as $project)
                        <tr wire:key="project-{{ $project['id'] ?? $loop->index }}">
                            <td>{{ $project['name'] ?? '—' }}</td>
                            <td>{{ $project['completion_percent'] ?? 0 }}%</td>
                            <td>{{ $project['completed_tasks'] ?? 0 }} / {{ $project['total_tasks'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="ds-text-muted ds-table-empty">لا مشاريع نشطة</td>
                        </tr>
                    @endforelse
                </x-ds-table>
            </div>
        </div>

        <div class="ds-card">
            <h3 class="ds-card-title">قرارات اجتماعات متأخرة</h3>
            @forelse ($selectedReport->open_decisions ?? [] as $decision)
                <p wire:key="decision-{{ $decision['id'] ?? $loop->index }}">
                    {{ $decision['topic'] ?? '—' }}: {{ $decision['decision'] ?? '' }}
                    @if (! empty($decision['due_date']))
                        <span class="ds-text-muted">({{ $decision['due_date'] }})</span>
                    @endif
                </p>
            @empty
                <p class="ds-text-muted">لا قرارات متأخرة</p>
            @endforelse
        </div>
    @else
        <x-ds-page-header title="التقارير الأسبوعية" />

        <div class="ds-table-wrap">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الفترة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>إنفاق الأسبوع</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($reports as $report)
                    <tr wire:key="report-{{ $report->id }}">
                        <td>{{ $report->week_start->format('Y-m-d') }} — {{ $report->week_end->format('Y-m-d') }}</td>
                        <td>{{ $report->generated_at->format('Y-m-d H:i') }}</td>
                        <td>{{ number_format((float) $report->week_spend, 2) }}</td>
                        <td>
                            <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="openReport({{ $report->id }})">
                                <i class="fas fa-eye"></i> عرض
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="ds-text-muted ds-table-empty">لا توجد تقارير</td>
                    </tr>
                @endforelse
            </x-ds-table>
        </div>

        {{ $reports->links() }}
    @endif
</div>
