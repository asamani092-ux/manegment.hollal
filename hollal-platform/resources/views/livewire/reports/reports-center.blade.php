<x-ds-page>
    <x-ds-page-header title="مركز التقارير الموحّد" />

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'monthly')">التقرير الشهري</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'project')">لوحة المشروع</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'impact')">الأثر</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'kpi')">مؤشرات الأداء</button>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="takeSnapshot">حفظ لقطة</button>
    </section>

    @if ($tab === 'monthly')
        <section class="ds-section ds-filter-bar">
            <input type="month" class="ds-input" wire:model.live="month" dir="ltr">
        </section>
        <x-ds-table>
            <x-slot:head><tr><th>المؤشر</th><th>القيمة</th></tr></x-slot:head>
            <tr><td>المهام المُنشأة</td><td class="ds-ltr-num">{{ $monthly['tasks_created'] }}</td></tr>
            <tr><td>المهام المكتملة</td><td class="ds-ltr-num">{{ $monthly['tasks_completed'] }}</td></tr>
            <tr><td>المهام المتأخرة</td><td class="ds-ltr-num">{{ $monthly['tasks_overdue'] }}</td></tr>
            <tr><td>المشاريع النشطة</td><td class="ds-ltr-num">{{ $monthly['projects_active'] }}</td></tr>
            <tr><td>المشاريع المغلقة</td><td class="ds-ltr-num">{{ $monthly['projects_closed'] }}</td></tr>
            <tr><td>المصروف</td><td class="ds-ltr-num">{{ number_format($monthly['spend'], 2) }}</td></tr>
            <tr><td>الزيارات المنفذة</td><td class="ds-ltr-num">{{ $monthly['visits_done'] }}</td></tr>
        </x-ds-table>

        <x-ds-table>
            <x-slot:head><tr><th>مرحلة الشراكة</th><th>العدد</th></tr></x-slot:head>
            @foreach ($monthly['partnerships_by_stage'] as $stage => $count)
                <tr wire:key="stage-count-{{ $loop->index }}">
                    <td>{{ $stage }}</td>
                    <td class="ds-ltr-num">{{ $count }}</td>
                </tr>
            @endforeach
        </x-ds-table>
    @endif

    @if ($tab === 'project')
        <section class="ds-section ds-filter-bar">
            <select class="ds-input" wire:model.live="projectId">
                <option value="">اختر مشروعًا</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </section>

        @if ($projectReport)
            <x-ds-table>
                <x-slot:head><tr><th>المؤشر</th><th>القيمة</th></tr></x-slot:head>
                <tr><td>الإنجاز الموزون</td><td class="ds-ltr-num">{{ number_format($projectReport['weighted_progress'], 2) }}%</td></tr>
                <tr><td>المهام</td><td class="ds-ltr-num">{{ $projectReport['tasks_total'] }}</td></tr>
                <tr><td>المقيّمة نهائيًا</td><td class="ds-ltr-num">{{ $projectReport['tasks_evaluated'] }}</td></tr>
                <tr><td>المتأخرة</td><td class="ds-ltr-num">{{ $projectReport['tasks_overdue'] }}</td></tr>
                <tr><td>الموازنة</td><td class="ds-ltr-num">{{ number_format($projectReport['budget'], 2) }}</td></tr>
                <tr><td>المستهلك</td><td class="ds-ltr-num">{{ number_format($projectReport['consumed'], 2) }}</td></tr>
                <tr><td>المستفيدون</td><td class="ds-ltr-num">{{ $projectReport['beneficiaries'] }}</td></tr>
                <tr><td>الزيارات المنفذة</td><td class="ds-ltr-num">{{ $projectReport['visits_done'] }}</td></tr>
            </x-ds-table>
        @else
            <p class="ds-text-muted">اختر مشروعًا لعرض لوحته</p>
        @endif
    @endif

    @if ($tab === 'impact')
        <section class="ds-section ds-filter-bar">
            <select class="ds-input" wire:model.live="organizationId">
                <option value="">كل الجهات</option>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                @endforeach
            </select>
        </section>

        <x-ds-table>
            <x-slot:head><tr><th>المؤشر</th><th>القيمة</th></tr></x-slot:head>
            <tr><td>عدد السجلات</td><td class="ds-ltr-num">{{ $impact['records'] }}</td></tr>
            <tr><td>المستفيدون</td><td class="ds-ltr-num">{{ number_format($impact['beneficiaries']) }}</td></tr>
            <tr>
                <td>متوسط التحسن</td>
                <td class="ds-ltr-num">
                    {{ $impact['avg_improvement_percent'] !== null ? number_format((float) $impact['avg_improvement_percent'], 2).'%' : '—' }}
                </td>
            </tr>
            <tr>
                <td>متوسط الرضا</td>
                <td class="ds-ltr-num">
                    {{ $impact['avg_satisfaction_percent'] !== null ? number_format((float) $impact['avg_satisfaction_percent'], 2).'%' : '—' }}
                </td>
            </tr>
        </x-ds-table>
    @endif

    @if ($tab === 'kpi')
        <x-ds-table>
            <x-slot:head><tr><th>المؤشر</th><th>القيمة</th></tr></x-slot:head>
            <tr><td>نسبة إنجاز المهام</td><td class="ds-ltr-num">{{ number_format($kpis['task_completion_percent'], 2) }}%</td></tr>
            <tr><td>المهام المتأخرة</td><td class="ds-ltr-num">{{ $kpis['overdue_tasks'] }}</td></tr>
            <tr><td>متوسط تقدم المشاريع</td><td class="ds-ltr-num">{{ number_format($kpis['avg_project_progress_percent'], 2) }}%</td></tr>
            <tr><td>المشاريع النشطة</td><td class="ds-ltr-num">{{ $kpis['active_projects'] }}</td></tr>
            <tr><td>الشراكات في الرحلة</td><td class="ds-ltr-num">{{ $kpis['active_partnerships'] }}</td></tr>
            <tr><td>الموظفون</td><td class="ds-ltr-num">{{ $kpis['employees'] }}</td></tr>
        </x-ds-table>
    @endif

    <section class="ds-section">
        <h2 class="ds-section-title">اللقطات المحفوظة (غير قابلة للتعديل)</h2>
        <x-ds-table>
            <x-slot:head><tr><th>النوع</th><th>العنوان</th><th>الفترة</th><th>التاريخ</th><th>سليمة</th></tr></x-slot:head>
            @forelse ($snapshots as $snapshot)
                <tr wire:key="snapshot-{{ $snapshot->id }}">
                    <td>{{ $snapshot->kind }}</td>
                    <td>{{ $snapshot->label }}</td>
                    <td dir="ltr">{{ $snapshot->period ?? '—' }}</td>
                    <td dir="ltr">{{ $snapshot->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $snapshot->isIntact() ? 'نعم' : 'لا' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد لقطات</td></tr>
            @endforelse
        </x-ds-table>
    </section>
</x-ds-page>
