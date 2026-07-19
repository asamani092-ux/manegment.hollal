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
    @endphp

    <x-ds-page-header
        title="إسناد المهام"
        :show-button="auth()->user()->can('esnad.tasks.create')"
        button-label="إسناد مهمة"
        button-icon="fa-plus"
        wire:click="openTaskCreate"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="taskSearch" placeholder="عنوان المهمة...">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label">الحالة</label>
            <select class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $statusLabels[$opt] ?? $opt }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">مهامي</h2>

        <div class="ds-task-cards ds-task-cards-mobile">
            @forelse ($myTasks as $task)
                <article class="ds-task-card" wire:key="my-card-{{ $task->id }}">
                    <h3 class="ds-task-card-title">{{ $task->title }}</h3>
                    <div class="ds-task-card-meta">
                        <span>{{ $task->project?->name ?? 'بدون مشروع' }}</span>
                        <span>{{ $priorityLabels[$task->priority] ?? $task->priority }}</span>
                        <span>{{ $task->due_date?->format('Y-m-d') ?? '—' }}</span>
                    </div>
                    @include('livewire.tasks.partials.status-badge', ['status' => $task->status])
                    <p class="ds-text-muted">من: {{ $task->assigner?->name ?? '—' }}</p>
                    <div class="ds-task-card-actions">
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openTaskView({{ $task->id }})">عرض</button>
                        @can('update', $task)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="openTaskEdit({{ $task->id }})">تعديل</button>
                        @endcan
                    </div>
                </article>
            @empty
                <x-ds-empty-state message="لا توجد مهام" icon="fa-tasks" />
            @endforelse
        </div>

        <div class="ds-task-table-desktop">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>العنوان</th>
                        <th>المشروع</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>الاستحقاق</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @foreach ($myTasks as $task)
                    <tr wire:key="my-row-{{ $task->id }}">
                        <td>{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td>{{ $priorityLabels[$task->priority] ?? $task->priority }}</td>
                        <td>{{ $statusLabels[$task->status] ?? $task->status }}</td>
                        <td class="ds-ltr-num">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openTaskView({{ $task->id }})">عرض</button>
                        </td>
                    </tr>
                @endforeach
            </x-ds-table>
        </div>
        {{ $myTasks->links() }}
    </section>

    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">مهام أسندتها</h2>
        <div class="ds-task-cards ds-task-cards-mobile">
            @forelse ($assignedByMe as $task)
                <article class="ds-task-card" wire:key="delegated-card-{{ $task->id }}">
                    <h3 class="ds-task-card-title">{{ $task->title }}</h3>
                    <div class="ds-task-card-meta">
                        <span>إلى: {{ $task->assignee?->name ?? '—' }}</span>
                        <span>{{ $task->project?->name ?? '—' }}</span>
                    </div>
                    @include('livewire.tasks.partials.status-badge', ['status' => $task->status])
                    <div class="ds-task-card-actions">
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openTaskView({{ $task->id }})">عرض</button>
                        @can('update', $task)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="openTaskEdit({{ $task->id }})">تعديل</button>
                            <button type="button" class="ds-btn ds-btn-danger ds-btn-sm" wire:click="deleteTask({{ $task->id }})" wire:confirm="حذف هذه المهمة؟">حذف</button>
                        @endcan
                    </div>
                </article>
            @empty
                <x-ds-empty-state message="لا توجد مهام مسندة" icon="fa-tasks" />
            @endforelse
        </div>
        {{ $assignedByMe->links() }}
    </section>

    @if ($showTaskModal)
        <div class="ds-modal-overlay" wire:click.self="closeTaskModal">
            <div class="ds-modal ds-modal-lg" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>
                        @if ($taskViewOnly)
                            عرض مهمة
                        @elseif ($taskId)
                            تعديل مهمة
                        @else
                            إسناد مهمة
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeTaskModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    @if ($currentTask && $taskViewOnly)
                        <div class="ds-detail-row"><span class="ds-detail-label">المُسند:</span> {{ $currentTask->assigner?->name ?? '—' }}</div>
                        <div class="ds-detail-row"><span class="ds-detail-label">المُسند إليه:</span> {{ $currentTask->assignee?->name ?? '—' }}</div>
                    @endif

                    <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                        <input type="text" class="ds-input" wire:model="title" @disabled($taskViewOnly)>
                    </x-ds-form-group>
                    <x-ds-form-group label="الوصف" :error="$errors->first('description')">
                        <textarea class="ds-input" rows="3" wire:model="description" @disabled($taskViewOnly)></textarea>
                    </x-ds-form-group>
                    <div class="ds-grid-2">
                        <x-ds-form-group label="المُسند إليه" :error="$errors->first('assigned_to')">
                            <select class="ds-input" wire:model="assigned_to" @disabled($taskViewOnly)>
                                <option value="">— اختر —</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="المشروع" :error="$errors->first('project_id')">
                            <select class="ds-input" wire:model="project_id" @disabled($taskViewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="الأولوية" :error="$errors->first('priority')">
                            <select class="ds-input" wire:model="priority" @disabled($taskViewOnly)>
                                @foreach ($priorityLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="الحالة" :error="$errors->first('status')">
                            <select class="ds-input" wire:model="status" @disabled($taskViewOnly)>
                                @foreach ($statusOptions as $opt)
                                    <option value="{{ $opt }}">{{ $statusLabels[$opt] ?? $opt }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="تاريخ الاستحقاق" :error="$errors->first('due_date')">
                            <input type="datetime-local" class="ds-input" wire:model="due_date" @disabled($taskViewOnly)>
                        </x-ds-form-group>
                    </div>

                    @if (! $taskViewOnly)
                        <x-ds-form-group label="مرفق المهمة" :error="$errors->first('attachment')">
                            <input type="file" class="ds-input" wire:model="attachment">
                            <div wire:loading wire:target="attachment" class="ds-text-muted">جاري الرفع...</div>
                        </x-ds-form-group>
                    @endif

                    @if ($taskId && $currentTask)
                        <div class="ds-notes-timeline">
                            <h4 class="ds-section-heading">الملاحظات</h4>
                            @forelse ($taskNotes as $note)
                                <div class="ds-note-item" wire:key="note-{{ $note->id }}">
                                    <div class="ds-note-meta">{{ $note->author?->name }} — {{ $note->created_at?->format('Y-m-d H:i') }}</div>
                                    <p>{{ $note->body }}</p>
                                </div>
                            @empty
                                <p class="ds-text-muted">لا توجد ملاحظات بعد</p>
                            @endforelse

                            @can('addNote', $currentTask)
                                <x-ds-form-group label="إضافة ملاحظة" :error="$errors->first('noteBody')">
                                    <textarea class="ds-input" rows="2" wire:model="noteBody" placeholder="اكتب ملاحظتك..."></textarea>
                                </x-ds-form-group>
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="addTaskNote" wire:loading.attr="disabled">
                                    إضافة ملاحظة
                                </button>
                            @endcan
                        </div>
                    @endif
                </div>
                <div class="ds-modal-footer">
                    @if (! $taskViewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveTask" wire:loading.attr="disabled" wire:target="saveTask,attachment,submittedFile">
                            <i class="fas fa-save" aria-hidden="true"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeTaskModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
