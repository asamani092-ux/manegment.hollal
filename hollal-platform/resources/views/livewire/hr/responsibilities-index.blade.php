<x-ds-page>
    <div class="ds-toolbar-actions ds-mb-3">
        <button type="button" class="ds-btn ds-btn-primary" wire:click="openForm">
            <i class="fas fa-plus" aria-hidden="true"></i> بند جديد
        </button>
    </div>

    <x-ds-page-header title="المسؤوليات" :show-button="false" />

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
                <th scope="col">اسم الموظف</th>
                <th scope="col">عدد المسؤوليات</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($employeeRows as $row)
            <tr wire:key="resp-emp-{{ $row->id }}">
                <td>{{ $row->name }}</td>
                <td class="ds-ltr-num">{{ (int) $row->responsibilities_count }}</td>
                <td>
                    <x-ds-status-badge :status="((int) $row->active_count) > 0 ? 'نشط' : 'موقوفة'" />
                </td>
                <td>
                    <x-ds-row-menu align="end">
                        <button type="button" class="ds-dropdown-item" wire:click="openEmployeePanel({{ $row->id }})">عرض المسؤوليات</button>
                        <button type="button" class="ds-dropdown-item" wire:click="deactivateAllForEmployee({{ $row->id }})" wire:confirm="إيقاف جميع مسؤوليات هذا الموظف؟">إيقاف مؤقت لجميع المسؤوليات</button>
                    </x-ds-row-menu>
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><x-ds-empty-state message="لا توجد مسؤوليات" icon="fa-list-check" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $employeeRows->links() }}

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
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>البند</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($panelItems as $item)
                    <tr wire:key="panel-{{ $item->id }}">
                        <td>{{ $item->body }}</td>
                        <td class="ds-ltr-num">{{ $item->order }}</td>
                        <td><x-ds-status-badge :status="$item->is_active ? 'نشط' : 'موقوفة'" /></td>
                        <td>
                            <x-ds-row-menu align="end">
                                <button type="button" class="ds-dropdown-item" wire:click="openEdit({{ $item->id }})">تعديل</button>
                                @if ($item->is_active)
                                    <button type="button" class="ds-dropdown-item" wire:click="deactivate({{ $item->id }})">إيقاف</button>
                                @endif
                                <button type="button" class="ds-dropdown-item" wire:click="delete({{ $item->id }})" wire:confirm="حذف هذا البند؟">حذف</button>
                            </x-ds-row-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ds-text-muted">لا بنود</td></tr>
                @endforelse
            </x-ds-table>
            <button type="button" class="ds-btn ds-btn-primary ds-mt-3" wire:click="openFormForEmployee({{ $panelEmployee->id }})">إضافة بند</button>
        @endif
    </x-ds-modal>
</x-ds-page>
