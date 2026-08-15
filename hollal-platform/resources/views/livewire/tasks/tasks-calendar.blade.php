<x-ds-page>
    <x-ds-page-header :title="'تقويم المهام — '.$monthLabel" />

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="previousMonth">الشهر السابق</button>
        <input type="month" class="ds-input" wire:model.live="month" dir="ltr">
        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="nextMonth">الشهر التالي</button>
    </section>

    @php
        $days = collect($tasksByDay->keys() ?? [])
            ->merge(array_keys($leavesByDay ?? []))
            ->merge(array_keys($meetingsByDay ?? []))
            ->unique()
            ->sort()
            ->values();
    @endphp

    @forelse ($days as $day)
        <section class="ds-section ds-section-spaced" wire:key="day-{{ $day }}">
            <h3 class="ds-section-heading">{{ $day }}</h3>
            @foreach (($tasksByDay[$day] ?? []) as $task)
                <div class="ds-stat-mini" wire:key="cal-task-{{ $task->id }}">
                    <strong>مهمة: {{ $task->title }}</strong>
                    <span class="ds-text-muted">{{ $task->assignee?->name ?? '—' }} — {{ $task->status }}</span>
                </div>
            @endforeach
            @foreach (($meetingsByDay[$day] ?? []) as $meeting)
                <div class="ds-stat-mini" wire:key="cal-meeting-{{ $meeting->id }}">
                    <strong>اجتماع: {{ $meeting->title }}</strong>
                    <span class="ds-text-muted ds-ltr-num">{{ $meeting->scheduled_at?->format('H:i') }}
                        — {{ $meeting->link ? 'عن بعد' : ($meeting->location ?: '—') }}</span>
                </div>
            @endforeach
            @foreach (($leavesByDay[$day] ?? []) as $leave)
                <div class="ds-stat-mini" wire:key="cal-leave-{{ $leave->id }}-{{ $day }}">
                    <strong>إجازة — {{ $leave->type }}</strong>
                    <span class="ds-text-muted">{{ $leave->employee?->name ?? '—' }}</span>
                </div>
            @endforeach
        </section>
    @empty
        <p class="ds-text-muted">لا مهام أو اجتماعات أو إجازات معتمدة في هذا الشهر</p>
    @endforelse
</x-ds-page>
