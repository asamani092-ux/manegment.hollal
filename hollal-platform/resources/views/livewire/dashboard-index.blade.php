<x-ds-page>
    @php
        $statusLabels = [
            'new' => 'جديدة',
            'in_progress' => 'قيد التنفيذ',
            'pending_review' => 'بانتظار المراجعة',
            'completed' => 'مكتملة',
            'overdue' => 'متأخرة',
        ];
        $priorityLabels = ['low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع', 'urgent' => 'عاجل'];
        $actionIcons = [
            'overdue_task' => 'fa-tasks',
            'expense_pending' => 'fa-receipt',
            'meeting_decision' => 'fa-gavel',
            'partnership_expiring' => 'fa-handshake',
        ];
    @endphp

    <x-ds-page-header title="الرئيسية" />

    @if ($showActionSection)
        <section class="ds-section ds-alert-warning ds-alert-spaced">
            <h2 class="ds-section-title">
                <i class="fas fa-bell ds-section-icon"></i>
                يحتاج إجراءك
            </h2>
            <div>
                @foreach ($actionItems as $item)
                    <div class="ds-stat-mini" wire:key="action-{{ $item['kind'] }}-{{ $loop->index }}">
                        <div>
                            <i class="fas {{ $actionIcons[$item['kind']] ?? 'fa-circle' }} ds-section-icon"></i>
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" class="ds-link">{{ $item['label'] }}</a>
                            @else
                                <span>{{ $item['label'] }}</span>
                            @endif
                            @if ($item['meta'])
                                <div class="ds-text-muted">{{ $item['meta'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="ds-stats-grid">
        <div class="ds-stat-card">
            <div class="ds-stat-mini-label">المشاريع النشطة</div>
            <div class="ds-stat-mini-val">{{ $activeProjectsCount }}</div>
            <div class="ds-text-muted">نسبة الإنجاز {{ $averageCompletionPercent }}%</div>
        </div>
        <div class="ds-stat-card">
            <div class="ds-stat-mini-label">مهام متأخرة</div>
            <div class="ds-stat-mini-val">{{ $overdueTasksCount }}</div>
        </div>
        @if ($showFinanceMetric)
            <div class="ds-stat-card">
                <div class="ds-stat-mini-label">مصروفات الشهر</div>
                <div class="ds-stat-mini-val">{{ number_format($monthSpend, 2) }} ر.س</div>
            </div>
        @endif
        <div class="ds-stat-card">
            <div class="ds-stat-mini-label">اجتماعات قادمة</div>
            <div class="ds-stat-mini-val">{{ $upcomingMeetingsCount }}</div>
        </div>
    </div>

    <section class="ds-section ds-section-spaced">
        <h2 class="ds-section-title">
            <i class="fas fa-briefcase ds-section-icon"></i>
            مساحة عملي
        </h2>

        <div class="ds-cards-grid">
            <div class="ds-section">
                <h3 class="ds-section-heading">مهامي اليوم</h3>
                @forelse ($myTasksToday as $task)
                    <div class="ds-stat-mini" wire:key="today-task-{{ $task->id }}">
                        <div>
                            <strong>{{ $task->title }}</strong>
                            <div class="ds-text-muted">
                                {{ $task->project?->name ?? '—' }}
                                — {{ $statusLabels[$task->status] ?? $task->status }}
                            </div>
                        </div>
                        <span class="ds-badge">{{ $priorityLabels[$task->priority] ?? $task->priority }}</span>
                    </div>
                @empty
                    <p class="ds-text-muted">لا مهام مستحقة اليوم</p>
                @endforelse
                @can('tasks.view')
                    <a href="{{ route('tasks.index') }}" class="ds-link">عرض كل المهام</a>
                @endcan
            </div>

            <div class="ds-section">
                <h3 class="ds-section-heading">مهامي المفتوحة</h3>
                @forelse ($myOpenTasks as $task)
                    <div class="ds-stat-mini" wire:key="open-task-{{ $task->id }}">
                        <div>
                            <strong>{{ $task->title }}</strong>
                            <div class="ds-text-muted">
                                {{ $task->due_date?->format('Y-m-d H:i') ?? '—' }}
                                — {{ $statusLabels[$task->status] ?? $task->status }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="ds-text-muted">لا مهام مفتوحة</p>
                @endforelse
            </div>

            <div class="ds-section">
                <h3 class="ds-section-heading">اجتماعاتي القادمة</h3>
                @forelse ($myUpcomingMeetings as $meeting)
                    <div class="ds-stat-mini" wire:key="meeting-{{ $meeting->id }}">
                        <div>
                            <strong>{{ $meeting->title }}</strong>
                            <div class="ds-text-muted">{{ $meeting->scheduled_at->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>
                @empty
                    <p class="ds-text-muted">لا اجتماعات قادمة</p>
                @endforelse
                @can('meetings.view')
                    <a href="{{ route('meetings.index') }}" class="ds-link">عرض الاجتماعات</a>
                @endcan
            </div>
        </div>
    </section>
</x-ds-page>