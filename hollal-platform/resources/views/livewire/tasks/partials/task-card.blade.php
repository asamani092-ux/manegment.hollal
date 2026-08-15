@php
    $showAssignee = $showAssignee ?? false;
    $showAssigner = $showAssigner ?? false;
    $allowDelete = $allowDelete ?? false;
@endphp
<article class="ds-task-card {{ $task->status === 'completed' ? 'is-completed' : '' }}" wire:key="{{ $keyPrefix }}-card-{{ $task->id }}">
    @include('livewire.tasks.partials.status-badge', ['status' => $task->status])
    <h3 class="ds-task-card-title">{{ $task->title }}</h3>
    <div class="ds-task-card-meta">
        @if ($showAssignee)
            <span>إلى: {{ $task->assignee?->name ?? '—' }}</span>
        @endif
        @if ($showAssigner)
            <span>من: {{ $task->assigner?->name ?? '—' }}</span>
        @endif
        <span>{{ $task->project?->name ?? 'بدون مشروع' }}</span>
        <span>{{ $priorityLabels[$task->priority] ?? $task->priority }}</span>
        <span class="ds-ltr-num">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</span>
    </div>
    @if ($task->attachment_path || $task->submitted_file)
        <div class="ds-task-card-files">
            @if ($task->attachment_path)
                <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $task->id, 'type' => 'attachment']) }}">مرفق</a>
            @endif
            @if ($task->submitted_file)
                <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $task->id, 'type' => 'submitted']) }}">شاهد</a>
            @endif
        </div>
    @endif
    <div class="ds-task-card-actions">
        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openTaskView({{ $task->id }})">عرض</button>
        @can('update', $task)
            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="openTaskEdit({{ $task->id }})">تعديل</button>
            @if ($allowDelete)
                <button type="button" class="ds-btn ds-btn-danger ds-btn-sm" wire:click="deleteTask({{ $task->id }})" wire:confirm="حذف هذه المهمة؟">حذف</button>
            @endif
        @endcan
    </div>
</article>
