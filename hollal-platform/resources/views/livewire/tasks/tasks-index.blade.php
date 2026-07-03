<div>
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
        :show-button="auth()->user()->can('tasks.create')"
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

    {{-- My tasks --}}
    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">مهامي</h2>
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
                @forelse ($myTasks as $task)
                    <tr wire:key="my-task-{{ $task->id }}">
                        <td>{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td>{{ $priorityLabels[$task->priority] ?? $task->priority }}</td>
                        <td>
                            @can('tasks.update')
                                <select class="ds-input ds-status-select" wire:change="updateTaskStatus({{ $task->id }}, $event.target.value)">
                                    @foreach ($statusOptions as $opt)
                                        <option value="{{ $opt }}" @selected($task->status === $opt)>{{ $statusLabels[$opt] ?? $opt }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $statusLabels[$task->status] ?? $task->status }}
                            @endcan
                        </td>
                        <td>{{ $task->due_date?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            <x-ds-action-icons
                                :show-view="true"
                                :show-edit="auth()->user()->can('tasks.update')"
                                :show-delete="auth()->user()->can('tasks.delete')"
                                :view-action="'openTaskView('.$task->id.')'"
                                :edit-action="'openTaskEdit('.$task->id.')'"
                                :delete-action="'deleteTask('.$task->id.')'"
                                delete-confirm="حذف هذه المهمة؟"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="ds-text-muted ds-table-empty">لا توجد مهام</td>
                    </tr>
                @endforelse
            </x-ds-table>

        {{ $myTasks->links() }}
    </section>

    {{-- Delegated --}}
    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">مهام أسندتها</h2>
        <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>العنوان</th>
                        <th>المُسند إليه</th>
                        <th>المشروع</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($assignedByMe as $task)
                    <tr wire:key="delegated-task-{{ $task->id }}">
                        <td>{{ $task->title }}</td>
                        <td>{{ $task->assignee?->name ?? '—' }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td>
                            @can('tasks.update')
                                <select class="ds-input ds-status-select" wire:change="updateTaskStatus({{ $task->id }}, $event.target.value)">
                                    @foreach ($statusOptions as $opt)
                                        <option value="{{ $opt }}" @selected($task->status === $opt)>{{ $statusLabels[$opt] ?? $opt }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $statusLabels[$task->status] ?? $task->status }}
                            @endcan
                        </td>
                        <td>
                            <x-ds-action-icons
                                :show-view="true"
                                :show-edit="auth()->user()->can('tasks.update')"
                                :show-delete="auth()->user()->can('tasks.delete')"
                                :view-action="'openTaskView('.$task->id.')'"
                                :edit-action="'openTaskEdit('.$task->id.')'"
                                :delete-action="'deleteTask('.$task->id.')'"
                                delete-confirm="حذف هذه المهمة؟"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="ds-text-muted ds-table-empty">لا توجد مهام</td>
                    </tr>
                @endforelse
            </x-ds-table>

        {{ $assignedByMe->links() }}
    </section>

    @if ($showTaskModal)
        <div class="ds-modal-overlay" wire:click.self="closeTaskModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
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
                            <input type="file" class="ds-input" wire:model="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div wire:loading wire:target="attachment" class="ds-text-muted">جاري الرفع...</div>
                        </x-ds-form-group>
                        <x-ds-form-group label="ملف التسليم" :error="$errors->first('submittedFile')">
                            <input type="file" class="ds-input" wire:model="submittedFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div wire:loading wire:target="submittedFile" class="ds-text-muted">جاري الرفع...</div>
                        </x-ds-form-group>
                    @endif

                    @if ($taskViewOnly)
                        @if ($existingAttachmentPath)
                            <div class="ds-detail-row">
                                <span class="ds-detail-label">مرفق المهمة:</span>
                                <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $taskId, 'type' => 'attachment']) }}">تحميل</a>
                            </div>
                        @endif
                        @if ($existingSubmittedPath)
                            <div class="ds-detail-row">
                                <span class="ds-detail-label">ملف التسليم:</span>
                                <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $taskId, 'type' => 'submitted']) }}">تحميل</a>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="ds-modal-footer">
                    @if (! $taskViewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveTask" wire:loading.attr="disabled">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeTaskModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</div>
