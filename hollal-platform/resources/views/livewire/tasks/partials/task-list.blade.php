@php
    $showAssignee = $showAssignee ?? false;
    $showAssigner = $showAssigner ?? false;
    $allowDelete = $allowDelete ?? false;
    $viewMode = $viewMode ?? 'cards';
@endphp

@if ($viewMode === 'table')
    <x-ds-table>
        <thead>
            <tr>
                <th>العنوان</th>
                @if ($showAssignee)<th>المكلَّف</th>@endif
                @if ($showAssigner)<th>المُسنِد</th>@endif
                <th>المشروع</th>
                <th>الأولوية</th>
                <th>الحالة</th>
                <th>الاستحقاق</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tasks as $task)
                <tr wire:key="{{ $keyPrefix }}-row-{{ $task->id }}">
                    <td>{{ $task->title }}</td>
                    @if ($showAssignee)<td>{{ $task->assignee?->name ?? '—' }}</td>@endif
                    @if ($showAssigner)<td>{{ $task->assigner?->name ?? '—' }}</td>@endif
                    <td>{{ $task->project?->name ?? '—' }}</td>
                    <td>{{ $priorityLabels[$task->priority] ?? $task->priority }}</td>
                    <td>@include('livewire.tasks.partials.status-badge', ['status' => $task->status])</td>
                    <td class="ds-ltr-num">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openTaskView({{ $task->id }})">عرض</button>
                        @can('update', $task)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="openTaskEdit({{ $task->id }})">تعديل</button>
                            @if ($allowDelete)
                                <button type="button" class="ds-btn ds-btn-danger ds-btn-sm" wire:click="deleteTask({{ $task->id }})" wire:confirm="حذف هذه المهمة؟">حذف</button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ds-text-muted ds-table-empty">لا توجد مهام</td></tr>
            @endforelse
        </tbody>
    </x-ds-table>
@else
    <div class="ds-task-cards">
        @forelse ($tasks as $task)
            @include('livewire.tasks.partials.task-card', [
                'task' => $task,
                'statusLabels' => $statusLabels,
                'priorityLabels' => $priorityLabels,
                'keyPrefix' => $keyPrefix,
                'showAssignee' => $showAssignee,
                'showAssigner' => $showAssigner,
                'allowDelete' => $allowDelete,
            ])
        @empty
            <x-ds-empty-state message="لا توجد مهام" icon="fa-tasks" />
        @endforelse
    </div>
@endif
