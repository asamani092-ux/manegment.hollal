<div>
    @php
        $roleLabels = ['Super Admin' => 'مدير النظام'];
    @endphp
    <x-ds-page-header
        title="الفريق / المستخدمون"
        :show-button="auth()->user()->can('users.create')"
        button-label="إضافة مستخدم"
        button-icon="fa-user-plus"
        wire:click="openCreateModal"
    />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الاسم</th>
                <th>البريد</th>
                <th>الدور</th>
                <th>القسم</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($users as $user)
            <tr wire:key="user-{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    @if ($user->roles->first())
                        <x-ds-role-label :name="$user->roles->first()->name" />
                    @else
                        —
                    @endif
                </td>
                <td>{{ $user->department?->name ?? '—' }}</td>
                <td>
                    @if ($user->is_active)
                        <span class="ds-badge ds-badge-success">نشط</span>
                    @else
                        <span class="ds-badge ds-badge-pending">معطل</span>
                    @endif
                </td>
                <td>
                    <x-ds-action-icons
                        :show-view="true"
                        :show-edit="auth()->user()->can('users.update')"
                        :show-delete="auth()->user()->can('users.delete')"
                        :view-action="'openViewModal('.$user->id.')'"
                        :edit-action="'openEditModal('.$user->id.')'"
                        :delete-action="'delete('.$user->id.')'"
                        delete-confirm="حذف هذا المستخدم؟"
                    />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="ds-text-muted ds-table-empty">لا يوجد مستخدمون</td>
            </tr>
        @endforelse
    </x-ds-table>

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="closeModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($viewOnly)
                            عرض مستخدم
                        @elseif ($userId)
                            تعديل مستخدم
                        @else
                            إضافة مستخدم
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="الاسم" :error="$errors->first('name')">
                        <input type="text" class="ds-input" wire:model="name" @disabled($viewOnly)>
                    </x-ds-form-group>
                    <x-ds-form-group label="رقم الجوال" :error="$errors->first('phone')">
                        <input type="tel" class="ds-input" wire:model="phone" @disabled($viewOnly)>
                    </x-ds-form-group>
                    <x-ds-form-group label="البريد الإلكتروني" :error="$errors->first('email')">
                        <input type="email" class="ds-input" wire:model="email" @disabled($viewOnly)>
                    </x-ds-form-group>
                    @if (! $viewOnly)
                        <x-ds-form-group label="كلمة المرور {{ $userId ? '(اتركها فارغة للإبقاء)' : '' }}" :error="$errors->first('password')">
                            <input type="password" class="ds-input" wire:model="password">
                        </x-ds-form-group>
                    @endif
                    <x-ds-form-group label="القسم">
                        <select class="ds-input" wire:model="department_id" @disabled($viewOnly)>
                            <option value="">— بدون قسم —</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="المدير">
                        <select class="ds-input" wire:model="manager_id" @disabled($viewOnly)>
                            <option value="">— بدون مدير —</option>
                            @foreach ($managers as $mgr)
                                @if ($mgr->id !== $userId)
                                    <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="الدور" :error="$errors->first('roleName')">
                        <select class="ds-input" wire:model="roleName" @disabled($viewOnly)>
                            <option value="">— اختر دور —</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->name }}">{{ $roleLabels[$role->name] ?? $role->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <div class="ds-form-group">
                        <label class="ds-checkbox-label">
                            <input type="checkbox" wire:model="is_active" @disabled($viewOnly)>
                            <span>نشط</span>
                        </label>
                    </div>
                </div>
                <div class="ds-modal-footer">
                    @if (! $viewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="save">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeModal">
                        {{ $viewOnly ? 'إغلاق' : 'إلغاء' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
