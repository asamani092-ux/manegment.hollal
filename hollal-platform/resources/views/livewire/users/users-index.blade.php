<x-ds-page>
    @php
        $roleLabels = hollal_role_labels();
    @endphp
    <x-ds-page-header
        title="الفريق / المستخدمون"
        :show-button="auth()->user()->can('hr.employees.create')"
        button-label="إضافة مستخدم"
        button-icon="fa-user-plus"
        wire:click="openCreateModal"
    />

    @php
        $statusClasses = ['نشط' => 'ds-badge-success', 'مجمد' => 'ds-badge-warning', 'منتهية_علاقته' => 'ds-badge-danger'];
    @endphp

    <section class="ds-section ds-filter-bar">
        <input type="search" class="ds-input" placeholder="بحث بالاسم أو البريد"
               wire:model.live.debounce.300ms="search">
        <select class="ds-input" wire:model.live="filterDepartment">
            <option value="">كل الأقسام</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </select>
        <select class="ds-input" wire:model.live="filterStatus">
            <option value="">كل الحالات</option>
            <option value="نشط">نشط</option>
            <option value="مجمد">مجمد</option>
            <option value="منتهية_علاقته">منتهية علاقته</option>
        </select>
        <select class="ds-input" wire:model.live="filterType">
            <option value="">كل الأنواع</option>
            <option value="دوام_كامل">دوام كامل</option>
            <option value="دوام_جزئي">دوام جزئي</option>
            <option value="متعاون">متعاون</option>
            <option value="متطوع">متطوع</option>
        </select>
        <button type="button" class="ds-btn ds-btn-outline" wire:click="toggleView">
            <i class="fas fa-{{ $viewMode === 'cards' ? 'table' : 'th-large' }}" aria-hidden="true"></i>
            {{ $viewMode === 'cards' ? 'عرض جدول' : 'عرض بطاقات' }}
        </button>
    </section>

    @if ($viewMode === 'table')
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
                <tr wire:key="user-row-{{ $user->id }}">
                    <td><a href="{{ route('users.profile', $user->id) }}" class="ds-link">{{ $user->name }}</a></td>
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
                        <span class="ds-badge {{ $statusClasses[$user->employment_status] ?? 'ds-badge-pending' }}">
                            {{ $user->employment_status }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('users.profile', $user->id) }}" class="ds-link">الملف الوظيفي</a>
                        <x-ds-action-icons
                            :show-view="true"
                            :show-edit="false"
                            :show-delete="auth()->user()->can('hr.employees.delete')"
                            :view-action="'openViewModal('.$user->id.')'"
                            :delete-action="'delete('.$user->id.')'"
                            delete-confirm="حذف هذا المستخدم؟"
                        />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="ds-text-muted ds-table-empty">لا يوجد موظفون</td>
                </tr>
            @endforelse
        </x-ds-table>
    @else
        <div class="ds-cards-grid">
            @forelse ($users as $user)
                <div class="ds-stat-card" wire:key="user-card-{{ $user->id }}">
                    <div class="ds-card-head">
                        <a href="{{ route('users.profile', $user->id) }}" class="ds-link"><strong>{{ $user->name }}</strong></a>
                        <span class="ds-badge {{ $statusClasses[$user->employment_status] ?? 'ds-badge-pending' }}">
                            {{ $user->employment_status }}
                        </span>
                    </div>
                    <div class="ds-text-muted">{{ $user->profile?->job_title ?? '—' }}</div>
                    <div class="ds-text-muted">{{ $user->department?->name ?? 'بدون قسم' }}</div>
                    <div class="ds-card-actions">
                        <a href="{{ route('users.profile', $user->id) }}" class="ds-link">الملف الوظيفي</a>
                    </div>
                </div>
            @empty
                <p class="ds-text-muted">لا يوجد موظفون مطابقون</p>
            @endforelse
        </div>
    @endif

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
                        <select class="ds-input" wire:model.live="department_id" @disabled($viewOnly)>
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
                    <x-ds-form-group label="المسمى الوظيفي" :error="$errors->first('job_org_unit_id')">
                        <select class="ds-input" wire:model.live="job_org_unit_id" @disabled($viewOnly || ! $department_id)>
                            <option value="">{{ $department_id ? '— اختر من وظائف القسم —' : '— اختر القسم أولاً —' }}</option>
                            @foreach ($jobOptions as $job)
                                <option value="{{ $job['id'] }}">{{ $job['label'] }}</option>
                            @endforeach
                        </select>
                        @if ($department_id && count($jobOptions) === 0)
                            <p class="ds-text-muted ds-mt-sm">لا توجد بطاقات وظيفة مرتبطة بهذا القسم في الهيكل.</p>
                        @endif
                    </x-ds-form-group>
                    <x-ds-form-group label="نوع التوظيف" :error="$errors->first('employment_type')">
                        <select class="ds-input" wire:model="employment_type" @disabled($viewOnly)>
                            <option value="">—</option>
                            <option value="دوام_كامل">نظامي — دوام كامل</option>
                            <option value="دوام_جزئي">دوام جزئي</option>
                            <option value="متعاون">متعاون</option>
                            <option value="متطوع">متطوع</option>
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="تاريخ المباشرة" :error="$errors->first('hire_date')">
                        <input type="date" class="ds-input ds-ltr-num" wire:model="hire_date" @disabled($viewOnly)>
                    </x-ds-form-group>
                    @if (! $userId && ! $viewOnly)
                        <x-ds-form-group label="مسؤول مهام التهيئة" :error="$errors->first('onboarding_assignee_id')">
                            <select class="ds-input" wire:model="onboarding_assignee_id">
                                <option value="">— اختر مسؤولاً واحداً لكل المهام —</option>
                                @foreach ($managers as $mgr)
                                    <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    @endif
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
</x-ds-page>