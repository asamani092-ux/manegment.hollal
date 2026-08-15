<x-ds-page>
    <x-ds-page-header
        title="الإيرادات"
        :show-button="$canManage"
        button-label="تسجيل إيراد"
        button-icon="fa-plus"
        wire:click="openCreateModal"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="revenue-source">المصدر</label>
            <select id="revenue-source" class="ds-input" wire:model.live="sourceFilter">
                <option value="">— الكل —</option>
                @foreach ($sourceOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="revenue-from">من تاريخ</label>
            <input id="revenue-from" type="date" class="ds-input" wire:model.live="dateFrom">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="revenue-to">إلى تاريخ</label>
            <input id="revenue-to" type="date" class="ds-input" wire:model.live="dateTo">
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">المصدر</th>
                <th scope="col">المبلغ</th>
                <th scope="col">تاريخ الاستلام</th>
                <th scope="col">الحالة</th>
                <th scope="col">الشاهد</th>
            </tr>
        </x-slot:head>
        @forelse ($revenues as $revenue)
            <tr wire:key="revenue-{{ $revenue->id }}">
                <td>{{ $revenue->source_type }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $revenue->amount, 2) }} ر.س</td>
                <td class="ds-ltr-num">{{ $revenue->received_at?->format('Y-m-d') ?? '—' }}</td>
                <td><x-ds-status-badge :status="$revenue->status" /></td>
                <td>
                    @if ($revenue->external_document_path)
                        <a class="ds-btn ds-btn-outline ds-btn-sm" title="معاينة" target="_blank" rel="noopener" href="{{ route('revenues.files.download', ['revenue' => $revenue->id, 'inline' => 1]) }}">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </a>
                        <a class="ds-btn ds-btn-outline ds-btn-sm" title="تحميل" href="{{ route('revenues.files.download', $revenue) }}">
                            <i class="fas fa-download" aria-hidden="true"></i>
                        </a>
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا توجد إيرادات مسجّلة" icon="fa-coins" /></td></tr>
        @endforelse
    </x-ds-table>

    {{ $revenues->links() }}

    <x-ds-modal :show="$showCreateModal" title="تسجيل إيراد" close-action="$set('showCreateModal', false)">
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
        <x-ds-form-group label="مرفق الشاهد (إلزامي)" :error="$errors->first('evidence')">
            <input type="file" class="ds-input" wire:model="evidence" accept=".pdf,.jpg,.jpeg,.png">
            <div wire:loading wire:target="evidence" class="ds-text-muted">جاري الرفع…</div>
            @if ($evidence)
                <p class="ds-badge ds-badge-success">{{ $evidence->getClientOriginalName() }}</p>
            @endif
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveRevenue" wire:loading.attr="disabled" wire:target="evidence,saveRevenue">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
