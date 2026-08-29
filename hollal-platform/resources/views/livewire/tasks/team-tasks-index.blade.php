<x-ds-page>
    <x-ds-page-header
        title="متابعة الفريق"
        :show-button="auth()->user()->can('esnad.tasks.create')"
        button-label="قالب متكرر"
        button-icon="fa-repeat"
        wire:click="openCreate"
    />

    <nav class="ds-tabs" role="tablist">
        <button type="button" class="ds-tab {{ $tab === 'approval' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','approval')">
            بانتظار اعتمادي ({{ $approvalQueue->count() }})
        </button>
        <button type="button" class="ds-tab {{ $tab === 'team' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','team')">
            مهام الفريق ({{ $teamTasks->count() }})
        </button>
        <button type="button" class="ds-tab {{ $tab === 'overdue' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','overdue')">
            المتأخرة ({{ $overdueTasks->count() }})
        </button>
        <button type="button" class="ds-tab {{ $tab === 'loads' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','loads')">
            أحمال الفريق
        </button>
        <button type="button" class="ds-tab {{ $tab === 'recurring' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','recurring')">
            قوالب ومتابعة
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
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="approveFromForm({{ $task->id }})">اعتماد</button>
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="returnFromForm({{ $task->id }})">إرجاع للتعديل</button>
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

        @elseif ($tab === 'overdue')
            @forelse ($overdueTasks as $task)
                <div class="ds-stat-card" wire:key="overdue-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <div class="ds-text-muted">
                        {{ $task->assignee?->name ?? '—' }} — استحقاق {{ $task->due_date?->format('Y-m-d') ?? '—' }}
                    </div>
                    <div class="ds-filter-bar">
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="managerComplete({{ $task->id }})">إكمال</button>
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openDetail({{ $task->id }})">تفاصيل</button>
                    </div>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام متأخرة</p>
            @endforelse

        @elseif ($tab === 'loads')
            <p class="ds-text-muted">حد التنبيه: أكثر من {{ $threshold }} مهمة مفتوحة — المتأخرة مدمجة أسفل كل موظف مع رابط بطاقة المهمة.</p>
            <div class="ds-mb-3">
                <button type="button" class="ds-btn ds-btn-primary" wire:click="sendTeamReminder" wire:confirm="إرسال تذكير جماعي لكل من لديه مهام مفتوحة؟">تذكير جماعي</button>
            </div>
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الموظف</th>
                        <th>مفتوحة</th>
                        <th>متأخرة</th>
                        <th>مستحقة هذا الأسبوع</th>
                        <th>تقييمات 30 يومًا</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($rows as $row)
                    <tr wire:key="workload-{{ $row['user']->id }}">
                        <td>{{ $row['user']->name }}</td>
                        <td>
                            <a href="{{ route('team-tasks.index', ['tab' => 'team', 'assigneeId' => $row['user']->id]) }}">{{ $row['open'] }}</a>
                            @if ($row['overloaded'])
                                <span class="ds-badge ds-badge-warning">عبء مرتفع</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('team-tasks.index', ['tab' => 'overdue', 'assigneeId' => $row['user']->id]) }}">{{ $row['overdue'] }}</a>
                        </td>
                        <td>{{ $row['due_this_week'] }}</td>
                        <td>
                            @forelse ($row['ratings'] as $label => $count)
                                <span class="ds-badge">{{ $label }}: {{ $count }}</span>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $row['user']->id }})">تذكير</button>
                        </td>
                    </tr>
                    @if ($row['overdue_tasks']->isNotEmpty())
                        <tr wire:key="workload-od-{{ $row['user']->id }}">
                            <td colspan="6">
                                <div class="ds-text-muted" style="margin-bottom:.35rem">مهام متأخرة — {{ $row['user']->name }}</div>
                                @foreach ($row['overdue_tasks'] as $task)
                                    <div class="ds-stat-mini" wire:key="wl-od-task-{{ $task->id }}">
                                        <a class="ds-link" href="{{ route('tasks.index', ['open' => $task->id]) }}">{{ $task->title }}</a>
                                        <span class="ds-text-muted ds-ltr-num">{{ $task->due_date?->format('Y-m-d') }}</span>
                                    </div>
                                @endforeach
                                @if ($row['overdue'] > $row['overdue_tasks']->count())
                                    <p class="ds-text-muted">و{{ $row['overdue'] - $row['overdue_tasks']->count() }} أخرى…</p>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="ds-text-muted ds-table-empty">لا يوجد أعضاء فريق</td>
                    </tr>
                @endforelse
            </x-ds-table>

        @elseif ($tab === 'recurring')
            <div class="ds-view-toggle">
                <button type="button" class="ds-btn ds-btn-sm {{ $recurringPanel === 'templates' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="$set('recurringPanel','templates')">قوالب متكررة</button>
                <button type="button" class="ds-btn ds-btn-sm {{ $recurringPanel === 'reminders' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="$set('recurringPanel','reminders')">متابعة / تذكيرات</button>
            </div>

            @if ($recurringPanel === 'templates')
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>القالب</th>
                            <th>الموظف</th>
                            <th>النمط</th>
                            <th>من</th>
                            <th>إلى</th>
                            <th>مفتوحة</th>
                            <th>مكتملة</th>
                            <th>الحالة</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($recurringTemplates as $template)
                        <tr wire:key="wl-tpl-{{ $template->id }}">
                            <td>{{ $template->title }}</td>
                            <td>{{ $template->assignee?->name ?? '—' }}</td>
                            <td>{{ $template->pattern }}</td>
                            <td class="ds-ltr-num">{{ $template->starts_on?->format('Y-m-d') ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $template->ends_on?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $template->open_instances_count }}</td>
                            <td>{{ $template->completed_instances_count }}</td>
                            <td>
                                <span class="ds-badge {{ $template->is_active ? 'ds-badge-success' : 'ds-badge-danger' }}">
                                    {{ $template->is_active ? 'مفعّل' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="ds-toolbar-actions" style="gap:.35rem">
                                @can('esnad.tasks.update')
                                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="toggleActive({{ $template->id }})">
                                        {{ $template->is_active ? 'إيقاف' : 'تفعيل' }}
                                    </button>
                                @endcan
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $template->assigned_to_id }}, {{ $template->id }})">تذكير</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="ds-text-muted">لا قوالب متكررة</td></tr>
                    @endforelse
                </x-ds-table>
            @else
                <section class="ds-section-spaced">
                    <h3 class="ds-section-heading">متابعة المهام المتكررة القائمة</h3>
                    <p class="ds-text-muted">تظهر القائمة فقط للموظفين الذين لديهم مهمة متكررة غير مكتملة.</p>
                    <div class="ds-filters-row">
                        <div class="ds-filter-field">
                            <label class="ds-label">الموظف</label>
                            <select class="ds-input" wire:model.live="followUpUserId">
                                <option value="">— اختر —</option>
                                @foreach ($followUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if ($followUsers->isEmpty())
                        <p class="ds-text-muted">لا موظفون لديهم مهام متكررة قائمة حالياً.</p>
                    @elseif ($followUpUserId)
                        <x-ds-table>
                            <x-slot:head>
                                <tr>
                                    <th>المهمة</th>
                                    <th>الحالة</th>
                                    <th>الاستحقاق</th>
                                    <th></th>
                                </tr>
                            </x-slot:head>
                            @forelse ($followUp as $task)
                                <tr wire:key="fu-{{ $task->id }}">
                                    <td>
                                        <a class="ds-link" href="{{ route('tasks.index', ['open' => $task->id]) }}">{{ $task->title }}</a>
                                    </td>
                                    <td>{{ $statusLabels[$task->status] ?? $task->status }}</td>
                                    <td class="ds-ltr-num">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $task->assigned_to }})">تذكير</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="ds-text-muted">لا مهام قائمة لهذا الموظف</td></tr>
                            @endforelse
                        </x-ds-table>
                    @endif
                </section>
            @endif
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
                    <div class="ds-detail-row"><span class="ds-detail-label">الاستحقاق:</span> <span class="ds-ltr-num">{{ hollal_dt($detailTask->due_date) }}</span></div>
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
                            <div class="ds-note-meta">{{ $note->author?->name }} — {{ hollal_dt($note->created_at) }}</div>
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
                                — {{ hollal_dt($log->created_at) }}
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

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showModal', false)">
            <div class="ds-modal" role="dialog">
                <div class="ds-modal-header">
                    <h3>قالب متكرر جديد</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                        <input type="text" class="ds-input" wire:model="title">
                    </x-ds-form-group>
                    <x-ds-form-group label="المكلَّف" :error="$errors->first('assigned_to_id')">
                        <select class="ds-input" wire:model="assigned_to_id">
                            <option value="">— اختر —</option>
                            @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="المشروع">
                        <select class="ds-input" wire:model="project_id">
                            <option value="">— بدون —</option>
                            @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="النمط">
                        <select class="ds-input" wire:model.live="pattern">
                            <option value="أسبوعي">أسبوعي</option>
                            <option value="شهري">شهري</option>
                        </select>
                    </x-ds-form-group>
                    @if ($pattern === 'أسبوعي')
                        <x-ds-form-group label="يوم الأسبوع (0=الأحد)" :error="$errors->first('day_of_week')">
                            <input type="number" min="0" max="6" class="ds-input" wire:model="day_of_week" dir="ltr">
                        </x-ds-form-group>
                    @else
                        <x-ds-form-group label="يوم الشهر" :error="$errors->first('day_of_month')">
                            <input type="number" min="1" max="31" class="ds-input" wire:model="day_of_month" dir="ltr">
                        </x-ds-form-group>
                    @endif
                    <x-ds-form-group label="تاريخ البداية" :error="$errors->first('starts_on')">
                        <input type="date" class="ds-input" wire:model="starts_on" dir="ltr">
                    </x-ds-form-group>
                    <x-ds-form-group label="تاريخ النهاية" :error="$errors->first('ends_on')">
                        <input type="date" class="ds-input" wire:model="ends_on" dir="ltr">
                    </x-ds-form-group>
                    <x-ds-form-group label="الدليل المطلوب (اختياري)">
                        <input type="text" class="ds-input" wire:model="required_evidence">
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showModal', false)">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
