<x-ds-page>
    @php
        $statusLabels = [
            'نشط' => 'ds-badge-success',
            'مجمد' => 'ds-badge-warning',
            'منتهية_علاقته' => 'ds-badge-danger',
        ];
        $tabs = [
            'data' => 'البيانات',
            'job' => 'الوظيفة',
            'contracts' => 'العقود',
            'salary' => 'الراتب',
            'tasks' => 'المهام',
            'evaluations' => 'التقييمات',
            'leaves' => 'الإجازات',
            'documents' => 'المستندات',
            'log' => 'السجل',
        ];
    @endphp

    <x-ds-page-header :title="'الملف الوظيفي — '.$user->name">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="ds-btn ds-btn-outline">
                <i class="fas fa-arrow-right" aria-hidden="true"></i> رجوع للفريق
            </a>
        </x-slot:actions>
    </x-ds-page-header>

    <section class="ds-section">
        <div class="ds-profile-head">
            <h2>{{ $user->name }}</h2>
            <span class="ds-badge {{ $statusLabels[$user->employment_status] ?? '' }}">
                {{ $user->employment_status }}
            </span>
            <div class="ds-text-muted">{{ $user->profile?->job_title ?? '—' }} — {{ $user->department?->name ?? 'بدون قسم' }}</div>
        </div>

        <nav class="ds-tabs" role="tablist">
            @foreach ($tabs as $key => $label)
                @if ($key === 'salary' && ! $canViewSalary)
                    @continue
                @endif
                <button type="button" role="tab"
                        class="ds-tab {{ $activeTab === $key ? 'ds-tab-active' : '' }}"
                        wire:click="setTab('{{ $key }}')">
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        <div class="ds-tab-panel">
            @if ($activeTab === 'data')
                <dl class="ds-detail-grid">
                    <div><dt>الاسم</dt><dd>{{ $user->name }}</dd></div>
                    <div><dt>البريد</dt><dd dir="ltr">{{ $user->email }}</dd></div>
                    <div><dt>الجوال</dt><dd dir="ltr">{{ $user->phone ?? '—' }}</dd></div>
                    <div><dt>الهوية</dt><dd dir="ltr">{{ $user->profile?->national_id ?? '—' }}</dd></div>
                    <div><dt>المدير المباشر</dt><dd>{{ $user->manager?->name ?? '—' }}</dd></div>
                    <div><dt>الدور</dt><dd><x-ds-role-label :name="$user->roles->first()?->name ?? '—' " /></dd></div>
                </dl>
            @elseif ($activeTab === 'job')
                <dl class="ds-detail-grid">
                    <div><dt>المسمى الوظيفي</dt><dd>{{ $user->profile?->job_title ?? '—' }}</dd></div>
                    <div><dt>نوع التوظيف</dt><dd>{{ $user->profile?->employment_type ?? '—' }}</dd></div>
                    <div><dt>تاريخ المباشرة</dt><dd>{{ $user->profile?->hire_date?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div><dt>القسم</dt><dd>{{ $user->department?->name ?? '—' }}</dd></div>
                    <div><dt>الساعات الأساسية أسبوعيًا</dt><dd class="ds-ltr-num">{{ $user->profile?->weekly_hours ?? '—' }}</dd></div>
                    <div><dt>برنامج الحضور</dt><dd>{{ $user->attendance_enabled ? 'مفعّل' : 'متوقّف' }}</dd></div>
                </dl>

                @can('hr.employees.update')
                    <section class="ds-section">
                        <h3 class="ds-section-title">إعدادات الحضور</h3>
                        <label class="ds-checkbox">
                            <input type="checkbox" wire:model="attendanceEnabled">
                            تفعيل برنامج الحضور لهذا الموظف
                        </label>
                        <x-ds-form-group label="الساعات الأساسية الأسبوعية" :error="$errors->first('weeklyHours')">
                            <input type="number" class="ds-input ds-ltr-num" wire:model="weeklyHours" min="1" max="80">
                        </x-ds-form-group>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveAttendanceSettings">حفظ</button>
                    </section>
                @endcan
            @elseif ($activeTab === 'salary')
                <p class="ds-text-muted">مكوّنات الراتب وسلم الرواتب تُعرض هنا (تُبنى في 01-B2).</p>
            @elseif ($activeTab === 'contracts')
                <p class="ds-text-muted">عقود الموظف.</p>
            @elseif ($activeTab === 'tasks')
                <p class="ds-text-muted">مهام الموظف.</p>
            @elseif ($activeTab === 'documents')
                <p class="ds-text-muted">مستندات الموظف.</p>
            @elseif ($activeTab === 'log')
                <p class="ds-text-muted">سجل التغييرات على بيانات الموظف.</p>
            @else
                <p class="ds-text-muted">هذا القسم قيد الإنشاء ضمن مراحل لاحقة.</p>
            @endif
        </div>
    </section>
</x-ds-page>
