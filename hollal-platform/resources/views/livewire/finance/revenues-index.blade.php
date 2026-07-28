<x-ds-page>
    <x-ds-page-header
        title="الإيرادات"
        :show-button="$canManage"
        button-label="تسجيل إيراد"
        button-icon="fa-plus"
        wire:click="openCreateModal"
    />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>المصدر</th>
                <th>المبلغ</th>
                <th>تاريخ الاستلام</th>
                <th>الحالة</th>
            </tr>
        </x-slot:head>
        @forelse ($revenues as $revenue)
            <tr wire:key="revenue-{{ $revenue->id }}">
                <td>{{ $revenue->source_type }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $revenue->amount, 2) }} ر.س</td>
                <td class="ds-ltr-num">{{ $revenue->received_at?->format('Y-m-d') ?? '—' }}</td>
                <td>{{ $revenue->status }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="ds-table-empty">لا توجد إيرادات مسجّلة</td></tr>
        @endforelse
    </x-ds-table>

    {{ $revenues->links() }}

    @if ($showCreateModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showCreateModal', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تسجيل إيراد</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showCreateModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="المبلغ" :error="$errors->first('amount')">
                        <input type="number" step="0.01" class="ds-input" wire:model="amount">
                    </x-ds-form-group>
                    <x-ds-form-group label="التصنيف">
                        <select class="ds-input" wire:model="category_id">
                            <option value="">— بدون —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="تاريخ الاستلام" :error="$errors->first('received_at')">
                        <input type="date" class="ds-input" wire:model="received_at">
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="saveRevenue">حفظ</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
