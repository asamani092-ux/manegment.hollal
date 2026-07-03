<div>
    <x-ds-page-header
        title="الأدوار والصلاحيات"
        :show-button="auth()->user()->can('roles.create')"
        button-label="إضافة دور"
        wire:click="openCreateModal"
    />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>اسم الدور</th>
                <th>عدد الصلاحيات</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($roles as $role)
            <tr wire:key="role-{{ $role->id }}">
                <td><x-ds-role-label :name="$role->name" /></td>
                <td>{{ $role->permissions_count }}</td>
                <td>
                    <x-ds-action-icons
                        :show-edit="auth()->user()->can('roles.update')"
                        :show-delete="auth()->user()->can('roles.delete')"
                        :show-view="false"
                        :edit-action="'openEditModal('.$role->id.')'"
                        :delete-action="'delete('.$role->id.')'"
                        delete-confirm="حذف هذا الدور؟"
                    />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="ds-text-muted ds-table-empty">لا توجد أدوار</td>
            </tr>
        @endforelse
    </x-ds-table>

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="closeModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>{{ $roleId ? 'تعديل دور' : 'إضافة دور' }}</h3>
                    <button type="button" class="ds-modal-close" wire:click="closeModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="اسم الدور" for="role-name" :error="$errors->first('name')">
                        <input type="text" id="role-name" class="ds-input" wire:model="name"
                               placeholder="مثال: مدير الموارد البشرية">
                    </x-ds-form-group>
                    <x-ds-form-group label="الصلاحيات">
                        <div class="ds-permissions-grid">
                            @foreach ($allPermissions as $permission)
                                <label class="ds-checkbox-label">
                                    <input type="checkbox" value="{{ $permission->name }}"
                                           wire:model="selectedPermissions">
                                    <span><x-ds-permission-label :name="$permission->name" /></span>
                                </label>
                            @endforeach
                        </div>
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="save">
                        <i class="fas fa-save"></i> حفظ
                    </button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeModal">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</div>
