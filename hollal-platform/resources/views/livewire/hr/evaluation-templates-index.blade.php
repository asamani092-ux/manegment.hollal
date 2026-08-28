<x-ds-page>
    <div class="ds-toolbar-actions ds-mb-3">
        <button type="button" class="ds-btn ds-btn-primary" wire:click="openCreate">
            <i class="fas fa-plus" aria-hidden="true"></i> قالب جديد
        </button>
        <a href="{{ route('evaluation-cycles.index') }}" class="ds-btn ds-btn-secondary">دورات التقييم</a>
    </div>

    <x-ds-page-header title="قوالب التقييم الربعي" :show-button="false" />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="tpl-search">بحث</label>
            <input id="tpl-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث باسم القالب…">
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الاسم</th>
                <th scope="col">عدد البنود</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $template)
            <tr wire:key="tpl-{{ $template->id }}">
                <td>{{ $template->name }}</td>
                <td class="ds-ltr-num">{{ $template->items_count }}</td>
                <td>
                    <x-ds-status-badge :status="$template->is_active ? 'نشط' : 'موقوف'" />
                </td>
                <td>
                    <x-ds-row-menu align="end">
                        <button type="button" class="ds-dropdown-item" wire:click="openEdit({{ $template->id }})">تعديل</button>
                        <button type="button" class="ds-dropdown-item" wire:click="toggleActive({{ $template->id }})">
                            {{ $template->is_active ? 'إيقاف' : 'تفعيل' }}
                        </button>
                    </x-ds-row-menu>
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><x-ds-empty-state message="لا توجد قوالب تقييم" icon="fa-clipboard-list" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $templates->links() }}

    <x-ds-modal :show="$showForm" :title="$editingId ? 'تعديل قالب تقييم' : 'قالب تقييم جديد'" close-action="$set('showForm', false)" size="lg">
        <x-ds-form-group label="اسم القالب" :error="$errors->first('name')">
            <input type="text" class="ds-input" wire:model="name">
        </x-ds-form-group>
        <label class="ds-checkbox ds-mb-3">
            <input type="checkbox" wire:model="is_active">
            <span>نشط</span>
        </label>

        <p class="ds-text-muted ds-mb-2">
            مجموع الأوزان الحالي:
            <strong class="ds-ltr-num">{{ $weightsTotal }}</strong>
            / 100
            @if ($weightsTotal !== 100)
                <span class="ds-text-danger">— يجب أن يساوي 100 عند الحفظ</span>
            @endif
        </p>
        @error('items') <p class="ds-field-error">{{ $message }}</p> @enderror

        <div class="ds-table-wrap ds-mb-3">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th>القسم</th>
                        <th>نص السؤال</th>
                        <th>الوزن</th>
                        <th>الترتيب</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $index => $row)
                        <tr wire:key="item-row-{{ $index }}">
                            <td>
                                <select class="ds-input" wire:model="items.{{ $index }}.section">
                                    @foreach ($sections as $section)
                                        <option value="{{ $section }}">{{ $section }}</option>
                                    @endforeach
                                </select>
                                @error("items.$index.section") <p class="ds-field-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input type="text" class="ds-input" wire:model="items.{{ $index }}.question_text">
                                @error("items.$index.question_text") <p class="ds-field-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input type="number" class="ds-input ds-ltr-num" wire:model.live="items.{{ $index }}.weight" min="1" max="100">
                                @error("items.$index.weight") <p class="ds-field-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input type="number" class="ds-input ds-ltr-num" wire:model="items.{{ $index }}.sort_order" min="1" max="100">
                            </td>
                            <td>
                                <button type="button" class="ds-btn ds-btn-ghost" wire:click="removeItemRow({{ $index }})" @disabled(count($items) <= 1)>حذف</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="button" class="ds-btn ds-btn-secondary ds-mb-3" wire:click="addItemRow">إضافة بند</button>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ القالب</button>
    </x-ds-modal>
</x-ds-page>
