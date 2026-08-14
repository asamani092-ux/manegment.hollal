<x-ds-page>
    <x-ds-page-header
        title="الأدوار والصلاحيات"
        :show-button="auth()->user()->can('roles.create') && $tab === 'entities'"
        button-label="إضافة دور"
        wire:click="openCreateRole"
    />

    <p class="ds-text-muted ds-mb-3">
        صفحة واحدة: إدارة الأدوار، منح صلاحيات الدور، الاستثناءات، ومصفوفة «من يملك ماذا».
    </p>

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-sm {{ $tab === 'entities' ? 'ds-btn-primary' : '' }}" wire:click="setTab('entities')">الأدوار</button>
        <button type="button" class="ds-btn ds-btn-sm {{ $tab === 'perms' ? 'ds-btn-primary' : '' }}" wire:click="setTab('perms')">صلاحيات الأدوار</button>
        <button type="button" class="ds-btn ds-btn-sm {{ $tab === 'exceptions' ? 'ds-btn-primary' : '' }}" wire:click="setTab('exceptions')">الاستثناءات</button>
        <button type="button" class="ds-btn ds-btn-sm {{ $tab === 'matrix' ? 'ds-btn-primary' : '' }}" wire:click="setTab('matrix')">من يملك ماذا</button>
    </section>

    @if ($tab === 'entities')
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>اسم الدور</th>
                    <th>عدد الصلاحيات</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($roleEntities as $role)
                <tr wire:key="role-entity-{{ $role->id }}">
                    <td><x-ds-role-label :name="$role->name" /></td>
                    <td class="ds-ltr-num">{{ $role->permissions_count }}</td>
                    <td>
                        @can('roles.update')
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openEditRole({{ $role->id }})">إعادة تسمية</button>
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="manageRolePermissions({{ $role->id }})">إدارة الصلاحيات</button>
                        @endcan
                        @can('roles.delete')
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deleteRole({{ $role->id }})" wire:confirm="حذف هذا الدور؟">حذف</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="ds-text-muted ds-table-empty">لا توجد أدوار</td>
                </tr>
            @endforelse
        </x-ds-table>
    @endif

    @if ($tab === 'perms')
        <section class="ds-section ds-filter-bar">
            <x-ds-form-group label="الدور">
                <input type="search" class="ds-input" wire:model.live.debounce.200ms="roleQuery" placeholder="بحث عن دور...">
                <select class="ds-input" wire:model.live="roleId">
                    @foreach ($roles as $role)
                        @php
                            $roleAr = [
                                'Super Admin' => 'مدير النظام',
                                'General Manager' => 'المدير العام',
                                'Executive Manager' => 'المدير التنفيذي',
                                'Project Manager' => 'مدير مشروع',
                                'Finance' => 'المالية',
                                'Employee' => 'موظف',
                                'Partnerships Manager' => 'مدير الشراكات',
                                'HR Manager' => 'مدير الموارد البشرية',
                            ][$role->name] ?? $role->name;
                        @endphp
                        <option value="{{ $role->id }}">{{ $roleAr }}</option>
                    @endforeach
                </select>
            </x-ds-form-group>
        </section>

        <section class="ds-section">
            <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleAll(true)">الكل</button>
            <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleAll(false)">إلغاء الكل</button>
        </section>

        @foreach ($permissions as $section => $sectionPermissions)
            <details class="ds-card ds-mb-3 uat-perm-section" wire:key="section-{{ $section }}" open>
                <summary class="ds-section-title" style="cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:.75rem">
                    <span>{{ $groups[$section] ?? $section }}</span>
                    <span class="ds-text-muted ds-ltr-num" style="font-size:.85rem">{{ count($sectionPermissions) }} صلاحية</span>
                </summary>
                <div style="padding:.75rem 0;display:grid;gap:.35rem">
                    <div class="ds-toolbar-actions">
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleSection('{{ $section }}', true)">تفعيل القسم</button>
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleSection('{{ $section }}', false)">تعطيل القسم</button>
                    </div>
                    @foreach ($sectionPermissions as $permission)
                        <label class="ds-checkbox" wire:key="perm-{{ $permission }}" style="display:flex;align-items:center;gap:.5rem;padding:.35rem .5rem;border-radius:8px;background:var(--ds-surface-2,#f5f7fa)">
                            <input type="checkbox" value="{{ $permission }}" wire:model="selected">
                            <span>{{ $labels[$permission] ?? $permission }}</span>
                        </label>
                    @endforeach
                </div>
            </details>
        @endforeach

        @can('roles.update')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveRole">حفظ صلاحيات الدور</button>
        @endcan
    @endif

    @if ($tab === 'exceptions')
        @can('roles.update')
            <section class="ds-section">
                <h2 class="ds-section-title">منح استثنائي لشخص</h2>
                <x-ds-form-group label="الموظف" :error="$errors->first('grantUserId')">
                    <input
                        type="search"
                        class="ds-input"
                        wire:model.live.debounce.200ms="userQuery"
                        placeholder="ابحث عن موظف بالاسم أو الجوال…"
                        autocomplete="off"
                    >
                    @if ($selectedGrantUser)
                        <div class="ds-toolbar-actions ds-mt-2" style="align-items:center;gap:.5rem;flex-wrap:wrap">
                            <span class="ds-badge ds-badge-info">
                                {{ $selectedGrantUser->name }}
                                @if ($selectedGrantUser->phone)
                                    <span class="ds-ltr-num">({{ $selectedGrantUser->phone }})</span>
                                @endif
                            </span>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="clearGrantUser">مسح الاختيار</button>
                        </div>
                    @endif
                    <div
                        class="ds-perm-picker"
                        style="margin-top:.5rem;max-height:220px;overflow:auto;border:1px solid var(--ds-border,#e8eef5);border-radius:8px;background:var(--ds-card,#fff)"
                        role="listbox"
                        aria-label="نتائج بحث الموظفين"
                    >
                        @forelse ($userChoices as $user)
                            <button
                                type="button"
                                class="ds-perm-picker__item"
                                style="display:block;width:100%;text-align:right;padding:.55rem .75rem;border:0;border-bottom:1px solid var(--ds-border,#e8eef5);background:{{ (int) $grantUserId === (int) $user->id ? 'var(--ds-accent-light,#e8f9f7)' : 'transparent' }};cursor:pointer;font:inherit;color:inherit"
                                wire:key="pick-user-{{ $user->id }}"
                                wire:click="selectGrantUser({{ $user->id }})"
                                role="option"
                                @if ((int) $grantUserId === (int) $user->id) aria-selected="true" @endif
                            >
                                <strong>{{ $user->name }}</strong>
                                @if ($user->phone)
                                    <span class="ds-text-muted ds-ltr-num" style="display:block;font-size:.8rem">{{ $user->phone }}</span>
                                @endif
                            </button>
                        @empty
                            <p class="ds-text-muted" style="padding:.75rem">لا يوجد موظف مطابق للبحث</p>
                        @endforelse
                    </div>
                </x-ds-form-group>

                <x-ds-form-group label="الصلاحية" :error="$errors->first('grantPermission')">
                    <input
                        type="search"
                        class="ds-input"
                        wire:model.live.debounce.200ms="permissionQuery"
                        placeholder="ابحث عن صلاحية بالاسم أو المفتاح…"
                        autocomplete="off"
                    >
                    @if ($grantPermission)
                        <div class="ds-toolbar-actions ds-mt-2" style="align-items:center;gap:.5rem;flex-wrap:wrap">
                            <span class="ds-badge ds-badge-info">{{ $labels[$grantPermission] ?? $grantPermission }}</span>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="clearGrantPermission">مسح الاختيار</button>
                        </div>
                    @endif
                    <div
                        class="ds-perm-picker"
                        style="margin-top:.5rem;max-height:220px;overflow:auto;border:1px solid var(--ds-border,#e8eef5);border-radius:8px;background:var(--ds-card,#fff)"
                        role="listbox"
                        aria-label="نتائج بحث الصلاحيات"
                    >
                        @forelse ($permissionChoices as $permission => $label)
                            <button
                                type="button"
                                class="ds-perm-picker__item"
                                style="display:block;width:100%;text-align:right;padding:.55rem .75rem;border:0;border-bottom:1px solid var(--ds-border,#e8eef5);background:{{ $grantPermission === $permission ? 'var(--ds-accent-light,#e8f9f7)' : 'transparent' }};cursor:pointer;font:inherit;color:inherit"
                                wire:key="pick-perm-{{ $permission }}"
                                wire:click="selectGrantPermission('{{ $permission }}')"
                                role="option"
                                @if ($grantPermission === $permission) aria-selected="true" @endif
                            >
                                <strong>{{ $label }}</strong>
                                <span class="ds-text-muted ds-ltr-num" style="display:block;font-size:.8rem">{{ $permission }}</span>
                            </button>
                        @empty
                            <p class="ds-text-muted" style="padding:.75rem">لا توجد صلاحية مطابقة للبحث</p>
                        @endforelse
                    </div>
                </x-ds-form-group>

                <x-ds-form-group label="سبب الاستثناء (إلزامي)" :error="$errors->first('grantReason')">
                    <input type="text" class="ds-input" wire:model="grantReason">
                </x-ds-form-group>

                <x-ds-form-group label="تاريخ الانتهاء (اختياري)" :error="$errors->first('grantExpiresOn')">
                    <input type="date" class="ds-input" wire:model="grantExpiresOn" dir="ltr">
                </x-ds-form-group>

                <button type="button" class="ds-btn ds-btn-primary" wire:click="grantException">منح</button>
            </section>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr><th>الموظف</th><th>الصلاحية</th><th>السبب</th><th>تاريخ المنح</th><th>الحالة</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($exceptions as $grant)
                <tr wire:key="grant-{{ $grant->id }}">
                    <td>{{ $grant->user?->name ?? '—' }}</td>
                    <td>{{ $labels[$grant->permission] ?? $grant->permission }}</td>
                    <td>{{ $grant->reason }}</td>
                    <td dir="ltr">{{ $grant->granted_on?->format('Y-m-d') }}</td>
                    <td>
                        @if ($grant->isActive())
                            <span class="ds-badge ds-badge-warning">استثناء فعّال</span>
                        @else
                            <span class="ds-badge ds-badge-info">منتهٍ/مسحوب</span>
                        @endif
                    </td>
                    <td>
                        @can('roles.update')
                            @if ($grant->isActive())
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="revokeException({{ $grant->id }})">سحب</button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد استثناءات</td></tr>
            @endforelse
        </x-ds-table>
    @endif

    @if ($tab === 'matrix')
        <section class="ds-section">
            <button type="button" class="ds-btn" wire:click="exportMatrix">تصدير المصفوفة CSV</button>
        </section>

        <x-ds-table>
            <x-slot:head><tr><th>الموظف</th><th>الصلاحيات</th></tr></x-slot:head>
            @forelse ($matrix as $row)
                <tr wire:key="matrix-{{ $row['user']->id }}">
                    <td>{{ $row['user']->name }}</td>
                    <td>
                        @foreach ($row['permissions'] as $permission => $source)
                            <span @class(['ds-badge', 'ds-badge-warning' => $source === 'استثناء', 'ds-badge-info' => $source !== 'استثناء'])>
                                {{ $labels[$permission] ?? $permission }} — {{ $source }}
                            </span>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="ds-text-muted ds-table-empty">لا توجد بيانات</td></tr>
            @endforelse
        </x-ds-table>
    @endif

    <x-ds-modal :show="$showRoleModal" title="{{ $editingRoleId ? 'إعادة تسمية الدور' : 'إضافة دور' }}" close-action="closeRoleModal">
        <x-ds-form-group label="اسم الدور" for="merged-role-name" :error="$errors->first('roleName')">
            <input type="text" id="merged-role-name" class="ds-input" wire:model="roleName" placeholder="مثال: مدير الموارد البشرية">
        </x-ds-form-group>
        <p class="ds-text-muted">الصلاحيات تُدار من تبويب «صلاحيات الأدوار» بعد الحفظ.</p>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="closeRoleModal">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveRoleEntity">حفظ</button>
        </div>
    </x-ds-modal>
</x-ds-page>
