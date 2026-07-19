<x-ds-page>
    <x-ds-page-header :title="'تقويم المهام — '.$monthLabel" />

    <section class="ds-section ds-filter-bar">
        <input type="month" class="ds-input" wire:model.live="month" dir="ltr">
    </section>

    @forelse ($tasksByDay as $day => $tasks)
        <section class="ds-section ds-section-spaced" wire:key="day-{{ $day }}">
            <h3 class="ds-section-heading">{{ $day }}</h3>
            @foreach ($tasks as $task)
                <div class="ds-stat-mini" wire:key="cal-task-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <span class="ds-text-muted">{{ $task->assignee?->name ?? '—' }} — {{ $task->status }}</span>
                </div>
            @endforeach
        </section>
    @empty
        <p class="ds-text-muted">لا مهام مجدولة في هذا الشهر</p>
    @endforelse
</x-ds-page>
