<x-ds-page>
    @php
        $itemStatusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];
    @endphp

    <x-ds-page-header title="قرارات مفتوحة" />

    <div class="ds-page-toolbar">
        <a href="{{ route('meetings.index') }}" class="ds-link"><i class="fas fa-arrow-right"></i> العودة للاجتماعات</a>
    </div>

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
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="ds-text-muted ds-table-empty">لا توجد قرارات مفتوحة</td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $decisions->links() }}
</x-ds-page>