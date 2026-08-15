<x-ds-page>
    <x-ds-page-header :title="'تقويم المهام — '.$monthLabel" />

    <section class="ds-cal-nav">
        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="previousMonth" aria-label="الشهر السابق">‹ السابق</button>
        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="goToday">اليوم</button>
        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="nextMonth" aria-label="الشهر التالي">التالي ›</button>
        <span class="ds-cal-nav-label">{{ $monthLabel }}</span>
        <input type="month" class="ds-input" wire:model.live="month" dir="ltr" style="max-width:10rem">
    </section>

    <div class="ds-cal-grid" role="grid" aria-label="تقويم شهري">
        @foreach ($dayHeaders as $dow)
            <div class="ds-cal-dow" role="columnheader">{{ $dow }}</div>
        @endforeach

        @foreach ($cells as $cell)
            <div
                class="ds-cal-cell {{ $cell['inMonth'] ? '' : 'is-outside' }} {{ $cell['isToday'] ? 'is-today' : '' }}"
                role="gridcell"
                wire:key="cal-cell-{{ $cell['date'] }}"
            >
                <div class="ds-cal-daynum ds-ltr-num">{{ $cell['day'] }}</div>

                @foreach ($cell['tasks'] as $task)
                    <button
                        type="button"
                        class="ds-cal-chip ds-cal-chip-task is-{{ $task->status }}"
                        wire:click="openTask({{ $task->id }})"
                        title="{{ $task->title }}"
                    >
                        {{ $task->title }}
                    </button>
                @endforeach

                @foreach ($cell['meetings'] as $meeting)
                    <button
                        type="button"
                        class="ds-cal-chip ds-cal-chip-meeting"
                        wire:click="openMeeting({{ $meeting->id }})"
                        title="{{ $meeting->title }}"
                    >
                        اجتماع: {{ $meeting->title }}
                    </button>
                @endforeach

                @foreach ($cell['leaves'] as $leave)
                    <button
                        type="button"
                        class="ds-cal-chip ds-cal-chip-leave"
                        wire:click="openLeave({{ $leave->id }})"
                        title="إجازة — {{ $leave->type }}"
                    >
                        إجازة: {{ $leave->employee?->name ?? $leave->type }}
                    </button>
                @endforeach
            </div>
        @endforeach
    </div>

    @if ($selectedTask)
        <div class="ds-modal-overlay" wire:click.self="closePeek">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>{{ $selectedTask->title }}</h3>
                    <button type="button" class="ds-modal-close" wire:click="closePeek">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-detail-row"><span class="ds-detail-label">المكلَّف:</span> {{ $selectedTask->assignee?->name ?? '—' }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">الحالة:</span> {{ $selectedTask->status }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">الاستحقاق:</span> <span class="ds-ltr-num">{{ $selectedTask->due_date?->format('Y-m-d H:i') ?? '—' }}</span></div>
                </div>
                <div class="ds-modal-footer">
                    <a class="ds-btn ds-btn-primary" href="{{ route('tasks.index', ['open' => $selectedTask->id]) }}">فتح المهمة</a>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closePeek">إغلاق</button>
                </div>
            </div>
        </div>
    @endif

    @if ($selectedMeeting)
        <div class="ds-modal-overlay" wire:click.self="closePeek">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>{{ $selectedMeeting->title }}</h3>
                    <button type="button" class="ds-modal-close" wire:click="closePeek">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-detail-row"><span class="ds-detail-label">الوقت:</span> <span class="ds-ltr-num">{{ $selectedMeeting->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                    <div class="ds-detail-row"><span class="ds-detail-label">المكان:</span> {{ $selectedMeeting->link ? 'عن بعد' : ($selectedMeeting->location ?: '—') }}</div>
                </div>
                <div class="ds-modal-footer">
                    <a class="ds-btn ds-btn-primary" href="{{ route('meetings.index') }}">الاجتماعات</a>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closePeek">إغلاق</button>
                </div>
            </div>
        </div>
    @endif

    @if ($selectedLeave)
        <div class="ds-modal-overlay" wire:click.self="closePeek">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>إجازة معتمدة</h3>
                    <button type="button" class="ds-modal-close" wire:click="closePeek">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-detail-row"><span class="ds-detail-label">الموظف:</span> {{ $selectedLeave->employee?->name ?? '—' }}</div>
                    <div class="ds-detail-row"><span class="ds-detail-label">النوع:</span> {{ $selectedLeave->type }}</div>
                    <div class="ds-detail-row">
                        <span class="ds-detail-label">الفترة:</span>
                        <span class="ds-ltr-num">{{ $selectedLeave->from_date?->format('Y-m-d') }} — {{ $selectedLeave->to_date?->format('Y-m-d') }}</span>
                    </div>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closePeek">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
