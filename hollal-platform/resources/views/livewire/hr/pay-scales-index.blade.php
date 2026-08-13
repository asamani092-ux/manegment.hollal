<x-ds-page>
    <x-ds-page-header
        title="سلم الرواتب"
        :show-button="true"
        button-label="سلم جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <p class="ds-text-muted ds-mb-3">
        ربط الموظفين بالسلم يتم من تبويب الراتب في الملف الوظيفي لكل موظف (اختيار السلم والدرجة)، وليس من هذه الشاشة مباشرة.
    </p>

    <x-ds-table>
        <x-slot:head>
                <tr>
                    <th>الاسم</th>
                    <th>عدد الدرجات</th>
                    <th>عدد الموظفين</th>
                    <th>الموظفون المرتبطون</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
        </x-slot:head>
        @forelse ($scales as $scale)
            <tr wire:key="scale-{{ $scale->id }}">
                <td>{{ $scale->name_ar }}</td>
                <td>{{ count($scale->grades ?? []) }}</td>
                <td class="ds-ltr-num">{{ $scale->profiles_count }}</td>
                <td>
                    @forelse ($scale->profiles as $profile)
                        @if ($profile->user)
                            <a href="{{ route('users.profile', $profile->user_id) }}" class="ds-link">
                                {{ $profile->user->name }}
                                @if ($profile->grade_label)
                                    <span class="ds-text-muted">({{ $profile->grade_label }})</span>
                                @endif
                            </a>@if (! $loop->last)، @endif
                        @endif
                    @empty
                        <span class="ds-text-muted">لا موظفون على هذا السلم</span>
                    @endforelse
                </td>
                <td>
                    <span class="ds-badge {{ $scale->is_active ? 'ds-badge-success' : 'ds-badge-pending' }}">
                        {{ $scale->is_active ? 'مفعّل' : 'معطّل' }}
                    </span>
                </td>
                <td>
                    <button type="button" class="ds-link" wire:click="openEdit({{ $scale->id }})">تعديل</button>
                </td>
            </tr>
        @empty
            <tr>
                    <td colspan="6" class="ds-text-muted ds-table-empty">لا يوجد سلالم رواتب</td>
            </tr>
        @endforelse
    </x-ds-table>

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showModal', false)">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>{{ $scaleId ? 'تعديل سلم' : 'سلم جديد' }}</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="اسم السلم" :error="$errors->first('name_ar')">
                        <input type="text" class="ds-input" wire:model="name_ar">
                    </x-ds-form-group>

                    <h4 class="ds-section-heading">الدرجات</h4>
                    @foreach ($grades as $index => $grade)
                        <div class="ds-filter-bar" wire:key="grade-{{ $index }}">
                            <input type="text" class="ds-input" placeholder="اسم الدرجة"
                                   wire:model="grades.{{ $index }}.label">
                            <input type="number" step="0.01" class="ds-input" placeholder="الراتب الأساسي" dir="ltr"
                                   wire:model="grades.{{ $index }}.base_amount">
                            <button type="button" class="ds-btn ds-btn-outline" wire:click="removeGrade({{ $index }})">حذف</button>
                        </div>
                        @error("grades.{$index}.label") <small class="ds-error">{{ $message }}</small> @enderror
                        @error("grades.{$index}.base_amount") <small class="ds-error">{{ $message }}</small> @enderror
                    @endforeach
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="addGrade">
                        <i class="fas fa-plus" aria-hidden="true"></i> إضافة درجة
                    </button>

                    <div class="ds-form-group">
                        <label class="ds-checkbox-label">
                            <input type="checkbox" wire:model="is_active">
                            <span>مفعّل</span>
                        </label>
                    </div>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="save">
                        <i class="fas fa-save" aria-hidden="true"></i> حفظ
                    </button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showModal', false)">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
