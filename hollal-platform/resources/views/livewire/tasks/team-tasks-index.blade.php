<x-ds-page>
    <x-ds-page-header title="مهام الفريق" />

    @if ($overdueCount > 0)
        <div class="ds-badge ds-badge-warning" style="display:block;margin-bottom:1rem;padding:.75rem 1rem">
            تذكير: لديك {{ $overdueCount }} مهمة متأخرة — راجع تبويب المتأخرة.
            <button type="button" class="ds-link" wire:click="$set('tab','overdue')">عرض المتأخرة</button>
        </div>
    @endif

    <nav class="ds-tabs" role="tablist">
        <button type="button" class="ds-tab {{ $tab === 'approval' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','approval')">
            بانتظار اعتمادي ({{ $approvalQueue->count() }})
        </button>
        @can('esnad.tasks.team.view')
            <button type="button" class="ds-tab {{ $tab === 'team' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','team')">
                مهام الفريق ({{ $teamTasks->count() }})
            </button>
        @endcan
        <button type="button" class="ds-tab {{ $tab === 'overdue' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','overdue')">
            المتأخرة ({{ $overdueTasks->count() }})
        </button>
    </nav>

    <div class="ds-tab-panel">
        @if ($tab === 'approval')
            @forelse ($approvalQueue as $task)
                <div class="ds-stat-card" wire:key="approve-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <div class="ds-text-muted">
                        المكلَّف: {{ $task->assignee?->name ?? '—' }}
                        @if ($task->project) — {{ $task->project->name }} @endif
                        — تقييمه الذاتي: {{ $task->self_rating ?? '—' }}
                    </div>
                    <div class="ds-filter-bar">
                        <select class="ds-input" wire:model="approveRating.{{ $task->id }}">
                            <option value="">اختر التقييم النهائي</option>
                            @foreach ($ratings as $rating)
                                <option value="{{ $rating }}">{{ $rating }}</option>
                            @endforeach
                        </select>
                        <input type="text" class="ds-input" placeholder="ملاحظة (اختياري)" wire:model="approveNote.{{ $task->id }}">
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="approveFromForm({{ $task->id }})">اعتماد</button>
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="returnFromForm({{ $task->id }})">إرجاع للتعديل</button>
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="openDetail({{ $task->id }})">تفاصيل</button>
                    </div>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام بانتظار اعتمادك</p>
            @endforelse
        @elseif ($tab === 'team')
            @forelse ($teamTasks as $task)
                <div class="ds-stat-card" wire:key="team-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <div class="ds-text-muted">
                        {{ $task->assignee?->name ?? '—' }} — {{ $statusLabels[$task->status] ?? $task->status }}
                        @if ($task->due_date) — استحقاق {{ $task->due_date->format('Y-m-d') }} @endif
                    </div>
                    <div class="ds-filter-bar">
                        <select class="ds-input" wire:model="managerStatus.{{ $task->id }}">
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($managerStatus[$task->id] ?? $task->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="managerUpdateStatus({{ $task->id }})">تغيير الحالة</button>
                        @if ($task->status !== 'completed')
                            <button type="button" class="ds-btn ds-btn-primary" wire:click="managerComplete({{ $task->id }})">إكمال</button>
                        @endif
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="openDetail({{ $task->id }})">تفاصيل</button>
                    </div>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام للفريق</p>
            @endforelse
        @else
            @forelse ($overdueTasks as $task)
                <div class="ds-stat-card" wire:key="overdue-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <div class="ds-text-muted">
                        {{ $task->assignee?->name ?? '—' }} — استحقاق {{ $task->due_date?->format('Y-m-d') ?? '—' }}
                    </div>
                    <div class="ds-filter-bar">
                        @can('esnad.tasks.team.view')
                            <button type="button" class="ds-btn ds-btn-primary" wire:click="managerComplete({{ $task->id }})">إكمال</button>
                        @endcan
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="openDetail({{ $task->id }})">تفاصيل</button>
                    </div>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام متأخرة</p>
            @endforelse
        @endif
    </div>

    @if ($showDetail && $detailTask)
        <div class="ds-modal-overlay" wire:click.self="closeDetail" style="z-index:1300">
            <div class="ds-modal" role="dialog" dir="rtl" wire:click.stop>
                <div class="ds-modal-header">
                    <h3>{{ $detailTask->title }}</h3>
                    <button type="button" class="ds-modal-close" wire:click="closeDetail">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p>المكلَّف: {{ $detailTask->assignee?->name ?? '—' }}</p>
                    <p>المُسنِد: {{ $detailTask->assigner?->name ?? '—' }}</p>
                    <p>الحالة: {{ $statusLabels[$detailTask->status] ?? $detailTask->status }}</p>
                    @if ($detailTask->attachment_path)
                        <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $detailTask->id, 'type' => 'attachment']) }}">مرفق</a>
                    @endif
                    @if ($detailTask->submitted_file)
                        <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $detailTask->id, 'type' => 'submitted']) }}">شاهد</a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
