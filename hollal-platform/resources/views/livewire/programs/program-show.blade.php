<x-ds-page>
    <x-ds-page-header :title="$program->name" />

    <section class="ds-section">
        <p class="ds-text-muted">{{ $program->description }}</p>
        <p>المرحلة: <strong>{{ $program->stage }}</strong> — الفئة المستهدفة: {{ $program->target_audience ?? '—' }}</p>
        <p>اللقاءات: <span class="ds-ltr-num">{{ $program->sessions_count ?? '—' }}</span> — الساعات: <span class="ds-ltr-num">{{ $program->hours_count ?? '—' }}</span></p>
        <p>متطلبات التنفيذ: {{ $program->execution_requirements ?? '—' }}</p>
        @can('projects.programs.manage')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="createDevelopmentProject">مشروع تطوير</button>
        @endcan
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">منصة البرنامج</h2>
        @if ($program->platform_url)
            <p><a href="{{ $program->platform_url }}" dir="ltr" target="_blank" rel="noopener">{{ $program->platform_url }}</a></p>
        @else
            <p class="ds-text-muted">لا يوجد رابط منصة</p>
        @endif
        <p>{{ $program->platform_steps ?? 'لم تُوثّق خطوات الحسابات بعد' }}</p>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">الأسعار</h2>
        @foreach ($services as $service)
            <x-ds-form-group :label="$service" :error="$errors->first('prices.'.$service)">
                <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="prices.{{ $service }}"
                    @cannot('projects.programs.manage') disabled @endcannot>
            </x-ds-form-group>
        @endforeach
        @can('projects.programs.manage')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="savePrices">حفظ الأسعار</button>
        @endcan
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">الإصدارات</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>الإصدار</th><th>الحالي</th><th>من عدّل</th><th>السبب</th><th>من اعتمد</th><th>التاريخ</th></tr>
            </x-slot:head>
            @forelse ($program->versions->sortByDesc('id') as $version)
                <tr wire:key="version-{{ $version->id }}">
                    <td dir="ltr">{{ $version->version_label }}</td>
                    <td>{{ $version->is_current ? 'نعم' : '—' }}</td>
                    <td>{{ $version->editor?->name ?? '—' }}</td>
                    <td>{{ $version->change_reason ?? '—' }}</td>
                    <td>{{ $version->approver?->name ?? '—' }}</td>
                    <td dir="ltr">{{ $version->created_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد إصدارات</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">الملفات</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>العنوان</th><th>النوع</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($program->files as $file)
                <tr wire:key="file-{{ $file->id }}">
                    <td>{{ $file->title }}</td>
                    <td>{{ $file->kind }}</td>
                    <td>
                        <a class="ds-btn ds-btn-sm" href="{{ route('programs.files.download', $file->id) }}">تنزيل</a>
                        @can('projects.programs.manage')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="deleteFile({{ $file->id }})">حذف</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-text-muted ds-table-empty">لا توجد ملفات</td></tr>
            @endforelse
        </x-ds-table>

        @can('projects.programs.manage')
            <x-ds-form-group label="عنوان الملف" :error="$errors->first('fileTitle')">
                <input type="text" class="ds-input" wire:model="fileTitle">
            </x-ds-form-group>
            <x-ds-form-group label="نوع الملف" :error="$errors->first('fileKind')">
                <select class="ds-input" wire:model="fileKind">
                    @foreach ($fileKinds as $kind)
                        <option value="{{ $kind }}">{{ $kind }}</option>
                    @endforeach
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="الملف" :error="$errors->first('upload')">
                <input type="file" class="ds-input" wire:model="upload">
            </x-ds-form-group>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="uploadFile">رفع</button>
        @endcan
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">الجهات التي نفّذت البرنامج</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>الجهة</th><th>المدينة</th></tr>
            </x-slot:head>
            @forelse ($executingOrganizations as $organization)
                <tr wire:key="org-{{ $organization->id }}">
                    <td>{{ $organization->name }}</td>
                    <td>{{ $organization->city ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="ds-text-muted ds-table-empty">لم ينفّذ البرنامج بعد</td></tr>
            @endforelse
        </x-ds-table>
    </section>
</x-ds-page>
