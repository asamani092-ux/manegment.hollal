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
                <option value="">— الكل (نشطة) —</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $statusLabels[$opt] ?? $opt }}</option>
                @endforeach
            </select>
        </div>
        @if ($canSeeAll)
            <div class="ds-filter-field">
                <label class="ds-label">النطاق</label>
                <select class="ds-input" wire:model.live="listScope">
                    <option value="my">مهامي وأسندتها</option>
                    <option value="all">كل المهام</option>
                </select>
            </div>
        @endif
        <div class="ds-filter-field">
            <label class="ds-label">العرض</label>
            <div class="ds-view-toggle" style="margin-bottom:0">
                <button type="button" class="ds-btn ds-btn-sm {{ $viewMode === 'cards' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setViewMode('cards')">بطاقات</button>
                <button type="button" class="ds-btn ds-btn-sm {{ $viewMode === 'table' ? 'ds-btn-primary' : 'ds-btn-outline' }}" wire:click="setViewMode('table')">جدول</button>
            </div>
        </div>
    </div>

    @if ($approvalQueue->isNotEmpty())
        <section class="ds-section-spaced">
            <h2 class="ds-section-heading">بانتظار اعتمادي ({{ $approvalQueue->count() }})</h2>
            @foreach ($approvalQueue as $task)
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
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="openTaskView({{ $task->id }})">تفاصيل</button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($listScope === 'all' && $canSeeAll)
        @if ($showActiveLists && $allTasks)
            <section class="ds-section-spaced">
                <h2 class="ds-section-heading">كل المهام (نشطة)</h2>
                @include('livewire.tasks.partials.task-list', [
                    'tasks' => $allTasks,
                    'keyPrefix' => 'all',
                    'showAssignee' => true,
                    'showAssigner' => true,
                    'viewMode' => $viewMode,
                    'statusLabels' => $statusLabels,
                    'priorityLabels' => $priorityLabels,
                ])
                {{ $allTasks->links() }}
            </section>
        @endif
        @if ($statusFilter === '' && ! $showCompleted)
            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="$set('showCompleted', true)">عرض المكتملة</button>
        @endif
        @if ($allCompleted)
            <section class="ds-section-spaced">
                <h2 class="ds-section-heading">كل المهام (مكتملة)</h2>
                @include('livewire.tasks.partials.task-list', [
                    'tasks' => $allCompleted,
                    'keyPrefix' => 'all-done',
                    'showAssignee' => true,
                    'showAssigner' => true,
                    'viewMode' => $viewMode,
                    'statusLabels' => $statusLabels,
                    'priorityLabels' => $priorityLabels,
                ])
                {{ $allCompleted->links() }}
            </section>
        @endif
    @else
        @if ($showActiveLists && $myTasks)
            <section class="ds-section-spaced">
                <h2 class="ds-section-heading">مهامي</h2>
                @include('livewire.tasks.partials.task-list', [
                    'tasks' => $myTasks,
                    'keyPrefix' => 'my',
                    'showAssignee' => false,
                    'showAssigner' => true,
                    'viewMode' => $viewMode,
                    'statusLabels' => $statusLabels,
                    'priorityLabels' => $priorityLabels,
                ])
                {{ $myTasks->links() }}
            </section>
        @endif

        @if ($showActiveLists && $assignedByMe)
            <section class="ds-section-spaced">
                <h2 class="ds-section-heading">مهام أسندتها</h2>
                @include('livewire.tasks.partials.task-list', [
                    'tasks' => $assignedByMe,
                    'keyPrefix' => 'delegated',
                    'showAssignee' => true,
                    'showAssigner' => false,
                    'allowDelete' => true,
                    'viewMode' => $viewMode,
                    'statusLabels' => $statusLabels,
                    'priorityLabels' => $priorityLabels,
                ])
                {{ $assignedByMe->links() }}
            </section>
        @endif

        @if ($statusFilter === '' && ! $showCompleted)
            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="$set('showCompleted', true)">عرض المكتملة</button>
        @endif

        @if ($myCompleted)
            <section class="ds-section-spaced">
                <h2 class="ds-section-heading">مهامي المكتملة</h2>
                @include('livewire.tasks.partials.task-list', [
                    'tasks' => $myCompleted,
                    'keyPrefix' => 'my-done',
                    'showAssignee' => false,
                    'showAssigner' => true,
                    'viewMode' => $viewMode,
                    'statusLabels' => $statusLabels,
                    'priorityLabels' => $priorityLabels,
                ])
                {{ $myCompleted->links() }}
            </section>
        @endif

        @if ($delegatedCompleted)
            <section class="ds-section-spaced">
                <h2 class="ds-section-heading">مكتملة أسندتها</h2>
                @include('livewire.tasks.partials.task-list', [
                    'tasks' => $delegatedCompleted,
                    'keyPrefix' => 'delegated-done',
                    'showAssignee' => true,
                    'showAssigner' => false,
                    'allowDelete' => true,
                    'viewMode' => $viewMode,
                    'statusLabels' => $statusLabels,
                    'priorityLabels' => $priorityLabels,
                ])
                {{ $delegatedCompleted->links() }}
            </section>
        @endif
    @endif

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
                        <x-ds-form-group label="شاهد الإنجاز" :error="$errors->first('submittedFile')">
                            <input type="file" class="ds-input" wire:model="submittedFile">
                            <div wire:loading wire:target="submittedFile" class="ds-text-muted">جاري الرفع...</div>
                        </x-ds-form-group>
                    @endif

                    @if ($taskId && $currentTask)
                        <div class="ds-task-files">
                            <h4 class="ds-section-heading">الملفات</h4>
                            @if ($existingAttachmentPath || $currentTask->attachment_path)
                                <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $currentTask->id, 'type' => 'attachment']) }}">تنزيل مرفق المهمة</a>
                            @else
                                <p class="ds-text-muted">لا مرفق للمهمة</p>
                            @endif
                            @if ($existingSubmittedPath || $currentTask->submitted_file)
                                <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $currentTask->id, 'type' => 'submitted']) }}">تنزيل شاهد الإنجاز</a>
                            @else
                                <p class="ds-text-muted">لا شاهد إنجاز</p>
                            @endif
                        </div>

                        <div class="ds-notes-timeline">
                            <h4 class="ds-section-heading">الملاحظات</h4>
                            @forelse ($taskNotes as $note)
                                <div class="ds-note-item" wire:key="note-{{ $note->id }}">
                                    <div class="ds-note-meta">{{ $note->author?->name }} — {{ hollal_dt($note->created_at) }}</div>
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
