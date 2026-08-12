<x-ds-page>
    <x-ds-page-header
        title="المسؤوليات"
        :show-button="true"
        button-label="بند جديد"
        button-icon="fa-plus"
        wire:click="openForm"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="resp-search">الموظف</label>
            <input id="resp-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم…">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="resp-active">الحالة</label>
            <select id="resp-active" class="ds-input" wire:model.live="activeOnly">
                <option value="0">— الكل —</option>
                <option value="1">النشطة فقط</option>
            </select>
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الموظف</th>
                <th scope="col">البند</th>
                <th scope="col">الترتيب</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($items as $item)
            <tr wire:key="resp-{{ $item->id }}">
                <td>{{ $item->employee?->name ?? '—' }}</td>
                <td>{{ $item->body }}</td>
                <td class="ds-ltr-num">{{ $item->order }}</td>
                <td><x-ds-status-badge :status="$item->is_active ? 'نشط' : 'موقوفة'" /></td>
                <td>
                    @if ($item->is_active)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deactivate({{ $item->id }})">إيقاف</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا توجد مسؤوليات" icon="fa-list-check" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $items->links() }}

    <x-ds-modal :show="$showForm" title="بند مسؤولية" close-action="$set('showForm', false)">
        <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
            <select class="ds-input" wire:model="employee_id">
                <option value="">—</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="البند" :error="$errors->first('body')">
            <textarea class="ds-input" wire:model="body" rows="3"></textarea>
        </x-ds-form-group>
        <x-ds-form-group label="الترتيب" :error="$errors->first('order')">
            <input type="number" class="ds-input" wire:model="order" min="1" max="20">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
    </x-ds-modal>
</x-ds-page>
