<x-ds-page>
    @php
        $itemStatusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];
    @endphp

    <x-ds-page-header title="القرارات" :back-url="route('meetings.index')" back-label="رجوع" />

    <p class="ds-text-muted" style="max-width:40rem;margin-bottom:1rem">
        تُنشأ القرارات من بنود المحضر عند توثيق قرار في اجتماع، ثم تُتابع هنا حتى تُحوَّل إلى مهمة في إسناد أو تُغلق يدويًا مع سبب الإغلاق.
    </p>

    <nav class="ds-tabs" role="tablist">
        <button type="button" class="ds-tab {{ $tab === 'open' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','open')">
            مفتوحة
        </button>
        <button type="button" class="ds-tab {{ $tab === 'archived' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','archived')">
            مؤرشفة (مغلقة)
        </button>
    </nav>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="search" placeholder="موضوع أو قرار...">
        </div>
    </div>

    <div class="ds-table-wrap">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الموضوع</th>
                    <th>القرار</th>
                    <th>الاجتماع</th>
                    <th>المسؤول</th>
                    <th>تاريخ الاستحقاق</th>
                    <th>الحالة</th>
                    <th>المهمة</th>
                    @if ($archived)
                        <th>سبب الإغلاق</th>
                    @else
                        <th>إجراءات</th>
                    @endif
                </tr>
            </x-slot:head>
            @forelse ($decisions as $item)
                <tr wire:key="decision-{{ $item->id }}">
                    <td>{{ $item->topic }}</td>
                    <td>{{ $item->decision }}</td>
                    <td>
                        <a class="ds-link" href="{{ route('meetings.minutes', $item->meeting_id) }}">
                            {{ $item->meeting?->title ?? '—' }}
                        </a>
                    </td>
                    <td>{{ $item->responsible?->name ?? '—' }}</td>
                    <td>{{ $item->due_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $itemStatusLabels[$item->status] ?? $item->status }}</td>
                    <td>
                        @if ($item->task)
                            {{ $item->task->title }}
                        @else
                            <span class="ds-text-muted">لم تُحوَّل بعد</span>
                        @endif
                    </td>
                    @if ($archived)
                        <td>{{ $item->close_reason ?? '—' }}</td>
                    @else
                        <td>
                            @can('meetings.update')
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="openClose({{ $item->id }})">إغلاق</button>
                            @endcan
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="ds-text-muted ds-table-empty">
                        {{ $archived ? 'لا توجد قرارات مؤرشفة' : 'لا توجد قرارات مفتوحة' }}
                    </td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $decisions->links() }}

    <x-ds-modal :show="$showCloseModal">
        <x-slot:header><h2>إغلاق القرار</h2></x-slot:header>
        <x-ds-form-group label="سبب الإغلاق" :error="$errors->first('closeReason')">
            <textarea class="ds-input" wire:model="closeReason" rows="3"></textarea>
        </x-ds-form-group>
        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showCloseModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="closeDecision">إغلاق</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
