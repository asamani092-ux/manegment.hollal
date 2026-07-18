<x-ds-page>
    <x-ds-page-header
        title="الأقسام"
        :show-button="auth()->user()->can('structure.departments.create')"
        button-label="قسم جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="search" placeholder="اسم القسم...">
        </div>
    </div>

    <div class="ds-table-wrap">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الاسم</th>
                    <th>تاريخ الإنشاء</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($departments as $department)
                <tr wire:key="dept-{{ $department->id }}">
                    <td>{{ $department->name }}</td>
                    <td>{{ $department->created_at?->format('Y-m-d') }}</td>
                    <td>
                        <x-ds-action-icons
                            :show-view="true"
                            :show-edit="auth()->user()->can('structure.departments.update')"
                            :show-delete="auth()->user()->can('structure.departments.delete')"
                            :view-action="'openView('.$department->id.')'"
                            :edit-action="'openEdit('.$department->id.')'"
                            :delete-action="'delete('.$department->id.')'"
                            delete-confirm="حذف هذا القسم؟"
                        />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="ds-text-muted ds-table-empty">لا توجد أقسام</td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $departments->links() }}

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="closeModal">
            <div class="ds-modal" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($viewOnly)
                            عرض قسم
                        @elseif ($departmentId)
                            تعديل قسم
                        @else
                            قسم جديد
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="اسم القسم" :error="$errors->first('name')">
                        <input type="text" class="ds-input" wire:model="name" @disabled($viewOnly)>
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    @if (! $viewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="save">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>