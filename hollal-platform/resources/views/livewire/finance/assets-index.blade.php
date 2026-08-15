<x-ds-page>
    <x-ds-page-header
        title="الأصول"
        :show-button="$canManage"
        button-label="أصل جديد"
        button-icon="fa-plus"
        wire:click="openCreateModal"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="asset-search">بحث</label>
            <input id="asset-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="الاسم أو الرمز…">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="asset-condition">الحالة</label>
            <select id="asset-condition" class="ds-input" wire:model.live="conditionFilter">
                <option value="">— الكل —</option>
                @foreach ($conditionOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الرمز</th>
                <th scope="col">الاسم</th>
                <th scope="col">الفئة</th>
                <th scope="col">الموقع</th>
                <th scope="col">قيمة الشراء</th>
                <th scope="col">الحالة</th>
                <th scope="col">حامل العهدة</th>
                <th scope="col">منذ</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($assets as $asset)
            <tr wire:key="asset-{{ $asset->id }}">
                <td class="ds-ltr-num">{{ $asset->code }}</td>
                <td>{{ $asset->name_ar }}</td>
                <td>{{ $asset->category?->name_ar ?? '—' }}</td>
                <td>{{ $asset->location ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $asset->purchase_amount !== null ? number_format((float) $asset->purchase_amount, 2) : '—' }}</td>
                <td><x-ds-status-badge :status="$asset->condition" /></td>
                <td>{{ $asset->currentHolder?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $asset->holder_since?->format('Y-m-d') ?? '—' }}</td>
                <td>
                    @if ($canManage)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openHandoverModal({{ $asset->id }})">تسليم</button>
                        @if ($asset->current_holder_id)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="receiveAsset({{ $asset->id }})">استلام</button>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><x-ds-empty-state message="لا توجد أصول مسجّلة" icon="fa-boxes-stacked" /></td></tr>
        @endforelse
    </x-ds-table>

    {{ $assets->links() }}

    <x-ds-modal :show="$showCreateModal" title="أصل جديد" close-action="$set('showCreateModal', false)">
        <x-ds-form-group label="اسم الأصل" :error="$errors->first('name_ar')">
            <input type="text" class="ds-input" wire:model="name_ar">
        </x-ds-form-group>
        <x-ds-form-group label="الوصف" :error="$errors->first('description')">
            <textarea class="ds-input" rows="2" wire:model="description"></textarea>
        </x-ds-form-group>
        <x-ds-form-group label="الفئة">
            <select class="ds-input" wire:model="category_id">
                <option value="">— بدون —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="قيمة الشراء" :error="$errors->first('purchase_amount')">
            <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="purchase_amount">
        </x-ds-form-group>
        <x-ds-form-group label="الموقع" :error="$errors->first('location')">
            <input type="text" class="ds-input" wire:model="location">
        </x-ds-form-group>
        <x-ds-form-group label="الحالة" :error="$errors->first('condition')">
            <select class="ds-input" wire:model="condition">
                @foreach ($conditionOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="الحامل" :error="$errors->first('create_holder_id')">
            <select class="ds-input" wire:model="create_holder_id">
                <option value="">— بدون —</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveAsset">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>

    <x-ds-modal :show="$showHandoverModal" title="تسليم أصل" close-action="$set('showHandoverModal', false)">
        <x-ds-form-group label="المستلم" :error="$errors->first('holder_id')">
            <select class="ds-input" wire:model="holder_id">
                <option value="">— اختر —</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="السبب">
            <input type="text" class="ds-input" wire:model="handover_reason">
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="submitHandover">تسليم</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
