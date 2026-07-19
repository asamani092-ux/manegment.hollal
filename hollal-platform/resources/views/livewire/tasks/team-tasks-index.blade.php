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
                    <span class="ds-text-muted">{{ $task->assignee?->name ?? '—' }} — {{ $task->status }}</span>
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
                </div>
            @empty
                <p class="ds-text-muted">لا مهام متأخرة</p>
            @endforelse
        @endif
    </div>
</x-ds-page>
