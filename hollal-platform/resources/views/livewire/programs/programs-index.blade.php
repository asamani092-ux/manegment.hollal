<x-ds-page>
    <x-ds-page-header
        title="برامج حلّل"
        :show-button="true"
        button-label="برنامج جديد"
        button-permission="projects.programs.manage"
        wire:click="openCreate"
    />

    <section class="ds-section ds-filter-bar">
        <input type="search" class="ds-input" placeholder="بحث باسم البرنامج" wire:model.live="search">
        <select class="ds-input" wire:model.live="stageFilter">
            <option value="">كل المراحل</option>
            <option value="تطوير">تطوير</option>
            <option value="نشط">نشط</option>
            <option value="موقوف">موقوف</option>
        </select>
    </section>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>البرنامج</th>
                <th>المرحلة</th>
                <th>الفئة المستهدفة</th>
                <th>اللقاءات</th>
                <th>الساعات</th>
                <th>المشاريع</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($programs as $program)
            <tr wire:key="program-{{ $program->id }}">
                <td><a href="{{ route('programs.show', $program->id) }}">{{ $program->name }}</a></td>
                <td>{{ $program->stage }}</td>
                <td>{{ $program->target_audience ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $program->sessions_count ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $program->hours_count ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $program->projects_count }}</td>
                <td>
                    @can('projects.programs.manage')
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="edit({{ $program->id }})">تعديل</button>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="ds-text-muted ds-table-empty">لا توجد برامج</td></tr>
        @endforelse
    </x-ds-table>

    {{ $programs->links() }}

    <x-ds-modal :show="$showModal" size="lg">
        <x-slot:header><h2>{{ $editingId ? 'تعديل برنامج' : 'برنامج جديد' }}</h2></x-slot:header>

        <x-ds-form-group label="اسم البرنامج" :error="$errors->first('name')">
            <input type="text" class="ds-input" wire:model="name">
        </x-ds-form-group>

        <x-ds-form-group label="الوصف" :error="$errors->first('description')">
            <textarea class="ds-input" wire:model="description"></textarea>
        </x-ds-form-group>

        <x-ds-form-group label="المرحلة" :error="$errors->first('stage')">
            <select class="ds-input" wire:model="stage">
                <option value="تطوير">تطوير</option>
                <option value="نشط">نشط</option>
                <option value="موقوف">موقوف</option>
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="الفئة المستهدفة" :error="$errors->first('target_audience')">
            <input type="text" class="ds-input" wire:model="target_audience">
        </x-ds-form-group>

        <x-ds-form-group label="عدد اللقاءات" :error="$errors->first('sessions_count')">
            <input type="number" class="ds-input" wire:model="sessions_count">
        </x-ds-form-group>

        <x-ds-form-group label="عدد الساعات" :error="$errors->first('hours_count')">
            <input type="number" class="ds-input" wire:model="hours_count">
        </x-ds-form-group>

        <x-ds-form-group label="متطلبات التنفيذ" :error="$errors->first('execution_requirements')">
            <textarea class="ds-input" wire:model="execution_requirements"></textarea>
        </x-ds-form-group>

        <x-ds-form-group label="رابط منصة البرنامج" :error="$errors->first('platform_url')">
            <input type="url" class="ds-input" wire:model="platform_url" dir="ltr">
        </x-ds-form-group>

        <x-ds-form-group label="خطوات الحسابات في المنصة" :error="$errors->first('platform_steps')">
            <textarea class="ds-input" wire:model="platform_steps"></textarea>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
