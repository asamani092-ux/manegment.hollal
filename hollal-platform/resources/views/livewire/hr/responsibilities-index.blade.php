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
                <td>
                    <button type="button" class="ds-link" wire:click="openEmployeePanel({{ $item->employee_id }})">
                        {{ $item->employee?->name ?? '—' }}
                    </button>
                </td>
                <td>{{ $item->body }}</td>
                <td class="ds-ltr-num">{{ $item->order }}</td>
                <td><x-ds-status-badge :status="$item->is_active ? 'نشط' : 'موقوفة'" /></td>
                <td>
                    <button type="button" class="ds-link" wire:click="openEdit({{ $item->id }})">تعديل</button>
                    @if ($item->is_active)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deactivate({{ $item->id }})">إيقاف</button>
                    @endif
                    <button type="button" class="ds-link" wire:click="delete({{ $item->id }})" wire:confirm="حذف هذا البند؟">حذف</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا توجد مسؤوليات" icon="fa-list-check" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $items->links() }}

    <x-ds-modal :show="$showForm" :title="$editingId ? 'تعديل مسؤولية' : 'بند مسؤولية'" close-action="$set('showForm', false)" size="lg">
        <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
            <x-ds-search-select
                :options="$employeeOptions"
                wire-model="employee_id"
                value-key="id"
                label-key="label"
                placeholder="ابحث عن الموظف…"
            />
        </x-ds-form-group>
        <x-ds-form-group label="البند" :error="$errors->first('body')">
            <textarea class="ds-input" wire:model="body" rows="3"></textarea>
        </x-ds-form-group>
        @if (! $editingId)
            <x-ds-form-group label="بنود إضافية (اختياري — سطر لكل بند)" :error="$errors->first('extraBodies')">
                <textarea class="ds-input" wire:model="extraBodies" rows="4" placeholder="بند ثانٍ&#10;بند ثالث"></textarea>
            </x-ds-form-group>
        @endif
        <x-ds-form-group label="الترتيب" :error="$errors->first('order')">
            <input type="number" class="ds-input" wire:model="order" min="1" max="20">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
    </x-ds-modal>

    <x-ds-modal :show="$showEmployeePanel" :title="'مسؤوليات — '.($panelEmployee?->name ?? '')" close-action="closeEmployeePanel" size="lg">
        @if ($panelEmployee)
            <p class="ds-text-muted ds-mb-3">كل مسؤوليات الموظف في مكان واحد.</p>
            <ul>
                @forelse ($panelItems as $item)
                    <li class="ds-mb-sm" wire:key="panel-{{ $item->id }}">
                        <strong class="ds-ltr-num">{{ $item->order }}.</strong>
                        {{ $item->body }}
                        — <x-ds-status-badge :status="$item->is_active ? 'نشط' : 'موقوفة'" />
                        <button type="button" class="ds-link" wire:click="openEdit({{ $item->id }})">تعديل</button>
                    </li>
                @empty
                    <li class="ds-text-muted">لا بنود</li>
                @endforelse
            </ul>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openForm">إضافة بند</button>
        @endif
    </x-ds-modal>
</x-ds-page>
