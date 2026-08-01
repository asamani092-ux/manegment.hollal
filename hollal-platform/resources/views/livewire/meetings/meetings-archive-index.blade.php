<x-ds-page>
    <x-ds-page-header title="أرشيف المحاضر" :show-button="false" />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="arch-search">العنوان</label>
            <input id="arch-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بعنوان الاجتماع…">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="arch-month">الشهر</label>
            <input id="arch-month" type="month" class="ds-input ds-ltr-num" wire:model.live="month">
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الاجتماع</th>
                <th scope="col">التاريخ</th>
                <th scope="col">حالة الاعتماد</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($meetings as $meeting)
            <tr wire:key="arch-{{ $meeting->id }}">
                <td>{{ $meeting->title }}</td>
                <td class="ds-ltr-num">{{ $meeting->scheduled_at?->format('Y-m-d') }}</td>
                <td><x-ds-status-badge :status="$meeting->approval_status" /></td>
                <td>
                    @can('meetings.view')
                        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes', $meeting) }}">المحضر</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><x-ds-empty-state message="لا توجد محاضر معتمدة" icon="fa-box-archive" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $meetings->links() }}
</x-ds-page>
