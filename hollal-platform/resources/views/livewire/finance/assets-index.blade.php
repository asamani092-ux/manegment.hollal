<x-ds-page>
    <x-ds-page-header
        title="الأصول"
        :show-button="$canManage"
        button-label="أصل جديد"
        button-icon="fa-plus"
        wire:click="openCreateModal"
    />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الرمز</th>
                <th>الاسم</th>
                <th>الحالة</th>
                <th>حامل العهدة</th>
                <th>منذ</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($assets as $asset)
            <tr wire:key="asset-{{ $asset->id }}">
                <td class="ds-ltr-num">{{ $asset->code }}</td>
                <td>{{ $asset->name_ar }}</td>
                <td>{{ $asset->condition }}</td>
                <td>{{ $asset->currentHolder?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $asset->holder_since?->format('Y-m-d') ?? '—' }}</td>
                <td>
                    @if ($canManage)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openHandoverModal({{ $asset->id }})">تسليم</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="ds-table-empty">لا توجد أصول مسجّلة</td></tr>
        @endforelse
    </x-ds-table>

    {{ $assets->links() }}

    @if ($showCreateModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showCreateModal', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>أصل جديد</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showCreateModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="اسم الأصل" :error="$errors->first('name_ar')">
                        <input type="text" class="ds-input" wire:model="name_ar">
                    </x-ds-form-group>
                    <x-ds-form-group label="الفئة">
                        <select class="ds-input" wire:model="category_id">
                            <option value="">— بدون —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="saveAsset">حفظ</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showHandoverModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showHandoverModal', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تسليم أصل</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showHandoverModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
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
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="submitHandover">تسليم</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
