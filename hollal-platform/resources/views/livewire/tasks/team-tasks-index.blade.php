<x-ds-page>
    <x-ds-page-header title="مهام الفريق" />

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
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="openDetail({{ $task->id }})">تفاصيل</button>
                        <select class="ds-input" wire:model="approveRating.{{ $task->id }}">
                            <option value="">اختر التقييم النهائي</option>
                            @foreach ($ratings as $rating)
                                <option value="{{ $rating }}">{{ $rating }}</option>
                            @endforeach
                        </select>
                        <input type="text" class="ds-input" placeholder="ملاحظة (اختياري)" wire:model="approveNote.{{ $task->id }}">
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="approveFromForm({{ $task->id }})">
                            اعتماد
                        </button>
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="returnFromForm({{ $task->id }})">
                            إرجاع للتعديل
                        </button>
                    </div>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام بانتظار اعتمادك</p>
            @endforelse
        @elseif ($tab === 'team')
            @forelse ($teamTasks as $task)
                <div class="ds-stat-mini" wire:key="team-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <span class="ds-text-muted">{{ $task->assignee?->name ?? '—' }} — {{ $statusLabels[$task->status] ?? $task->status }}</span>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openDetail({{ $task->id }})">تفاصيل</button>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام للفريق</p>
            @endforelse
        @else
            @forelse ($overdueTasks as $task)
                <div class="ds-stat-mini" wire:key="overdue-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <span class="ds-text-muted">
                        {{ $task->assignee?->name ?? '—' }} — استحقاق {{ $task->due_date?->format('Y-m-d') ?? '—' }}
                    </span>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openDetail({{ $task->id }})">تفاصيل</button>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام متأخرة</p>
            @endforelse
        @endif
    </div>

    @if ($showDetail && $detailTask)
        <div class="ds-modal-overlay" wire:click.self="closeDetail">
            <div class="ds-modal ds-modal-lg" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تفاصيل المهمة</h3>
                    <button type="button" class="ds-modal-close" wire:click="closeDetail">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-detail-row"><span class="ds-detail-label">العنوان:</span> {{ $detailTask->title }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">الحالة:</span> {{ $statusLabels[$detailTask->status] ?? $detailTask->status }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">المكلَّف:</span> {{ $detailTask->assignee?->name ?? '—' }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">المُسند:</span> {{ $detailTask->assigner?->name ?? '—' }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">المشروع:</span> {{ $detailTask->project?->name ?? '—' }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">الاستحقاق:</span> <span class="ds-ltr-num">{{ $detailTask->due_date?->format('Y-m-d H:i') ?? '—' }}</span></div>
                    @if ($detailTask->description)
                        <div class="ds-detail-row"><span class="ds-detail-label">الوصف:</span> {{ $detailTask->description }}</div>
                    @endif
                    @if ($detailTask->submission_note)
                        <div class="ds-detail-row"><span class="ds-detail-label">ملاحظة التسليم:</span> {{ $detailTask->submission_note }}</div>
                    @endif
                    @if ($detailTask->self_rating)
                        <div class="ds-detail-row"><span class="ds-detail-label">التقييم الذاتي:</span> {{ $detailTask->self_rating }}</div>
                    @endif

                    <h4 class="ds-section-heading">الملفات</h4>
                    <div class="ds-detail-row">
                        <span class="ds-detail-label">مرفق المهمة:</span>
                        @if ($detailTask->attachment_path)
                            <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $detailTask->id, 'type' => 'attachment']) }}">تنزيل المرفق</a>
                        @else
                            <span class="ds-text-muted">لا يوجد</span>
                        @endif
                    </div>
                    <div class="ds-detail-row">
                        <span class="ds-detail-label">ملف الإنجاز / الشاهد:</span>
                        @if ($detailTask->submitted_file)
                            <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $detailTask->id, 'type' => 'submitted']) }}">تنزيل الشاهد</a>
                        @else
                            <span class="ds-text-muted">لا يوجد</span>
                        @endif
                    </div>

                    <h4 class="ds-section-heading">الملاحظات</h4>
                    @forelse ($detailTask->notes as $note)
                        <div class="ds-note-item" wire:key="team-note-{{ $note->id }}">
                            <div class="ds-note-meta">{{ $note->author?->name }} — {{ $note->created_at?->format('Y-m-d H:i') }}</div>
                            <p>{{ $note->body }}</p>
                        </div>
                    @empty
                        <p class="ds-text-muted">لا توجد ملاحظات</p>
                    @endforelse

                    <h4 class="ds-section-heading">سجل الحالات</h4>
                    @forelse ($detailTask->statusLogs as $log)
                        <div class="ds-note-item" wire:key="team-log-{{ $log->id }}">
                            <div class="ds-note-meta">
                                {{ $statusLabels[$log->from_status] ?? $log->from_status ?? '—' }}
                                → {{ $statusLabels[$log->to_status] ?? $log->to_status }}
                                — {{ $log->created_at?->format('Y-m-d H:i') }}
                            </div>
                            @if ($log->note)
                                <p>{{ $log->note }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="ds-text-muted">لا يوجد سجل حالات</p>
                    @endforelse

                    @if ($detailTask->status === 'pending_review' && $detailTask->assigned_by === auth()->id())
                        <h4 class="ds-section-heading">اعتماد من التفاصيل</h4>
                        <div class="ds-filter-bar">
                            <select class="ds-input" wire:model="approveRating.{{ $detailTask->id }}">
                                <option value="">اختر التقييم النهائي</option>
                                @foreach ($ratings as $rating)
                                    <option value="{{ $rating }}">{{ $rating }}</option>
                                @endforeach
                            </select>
                            <input type="text" class="ds-input" placeholder="ملاحظة (اختياري)" wire:model="approveNote.{{ $detailTask->id }}">
                            <button type="button" class="ds-btn ds-btn-primary" wire:click="approveFromForm({{ $detailTask->id }})">اعتماد</button>
                            <button type="button" class="ds-btn ds-btn-outline" wire:click="returnFromForm({{ $detailTask->id }})">إرجاع للتعديل</button>
                        </div>
                    @endif
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeDetail">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
