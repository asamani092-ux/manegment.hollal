<x-ds-page>
    <x-ds-page-header title="منح الصلاحيات" />

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'roles')">صلاحيات الأدوار</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'exceptions')">الاستثناءات</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'matrix')">من يملك ماذا</button>
    </section>

    @if ($tab === 'roles')
        <section class="ds-section ds-filter-bar">
            @foreach ($roles as $role)
                <button type="button" class="ds-btn ds-btn-sm" wire:click="selectRole({{ $role->id }})">
                    <x-ds-role-label :name="$role->name" />
                </button>
            @endforeach
        </section>

        <section class="ds-section">
            <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleAll(true)">الكل</button>
            <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleAll(false)">إلغاء الكل</button>
        </section>

        @foreach ($permissions as $section => $sectionPermissions)
            <section class="ds-section" wire:key="section-{{ $section }}">
                <h2 class="ds-section-title">{{ $groups[$section] ?? $section }}</h2>
                <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleSection('{{ $section }}', true)">تفعيل القسم</button>
                <button type="button" class="ds-btn ds-btn-sm" wire:click="toggleSection('{{ $section }}', false)">تعطيل القسم</button>

                @foreach ($sectionPermissions as $permission)
                    <label class="ds-checkbox" wire:key="perm-{{ $permission }}">
                        <input type="checkbox" value="{{ $permission }}" wire:model="selected">
                        {{ $labels[$permission] ?? $permission }}
                    </label>
                @endforeach
            </section>
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
                    <select class="ds-input" wire:model="grantUserId">
                        <option value="">—</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-ds-form-group>

                <x-ds-form-group label="الصلاحية" :error="$errors->first('grantPermission')">
                    <select class="ds-input" wire:model="grantPermission">
                        <option value="">—</option>
                        @foreach ($permissions as $section => $sectionPermissions)
                            @foreach ($sectionPermissions as $permission)
                                <option value="{{ $permission }}">{{ $labels[$permission] ?? $permission }}</option>
                            @endforeach
                        @endforeach
                    </select>
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
</x-ds-page>
