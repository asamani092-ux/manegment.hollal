<x-ds-page>
    <x-ds-page-header :title="$organization->name" />

    <section class="ds-section">
        <p>النوع: {{ $organization->type ?? '—' }} — المدينة: {{ $organization->city ?? '—' }}</p>
        <p>الأدوار: {{ $organization->roles ? implode('، ', $organization->roles) : '—' }}</p>
        <p class="ds-text-muted">{{ $organization->notes }}</p>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">مسؤولو التواصل</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>الاسم</th><th>الصفة</th><th>الجوال</th><th>البريد</th><th>رئيسي</th></tr>
            </x-slot:head>
            @forelse ($organization->contacts as $contact)
                <tr wire:key="contact-{{ $contact->id }}">
                    <td>{{ $contact->name }}</td>
                    <td>{{ $contact->position ?? '—' }}</td>
                    <td dir="ltr">{{ $contact->phone ?? '—' }}</td>
                    <td dir="ltr">{{ $contact->email ?? '—' }}</td>
                    <td>{{ $contact->is_primary ? 'نعم' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا يوجد مسؤولو تواصل</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">رحلات الشراكة</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>#</th><th>المرحلة</th><th>عمر المرحلة</th><th>القيمة المتوقعة</th></tr>
            </x-slot:head>
            @forelse ($organization->partnerships as $partnership)
                <tr wire:key="org-partnership-{{ $partnership->id }}">
                    <td class="ds-ltr-num">{{ $partnership->id }}</td>
                    <td>{{ $partnership->stageLabel() }}</td>
                    <td class="ds-ltr-num">{{ $partnership->stageAgeDays() }}</td>
                    <td class="ds-ltr-num">
                        {{ $partnership->expected_value !== null ? number_format((float) $partnership->expected_value, 2) : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="ds-text-muted ds-table-empty">لا توجد شراكات</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">مشاريعها</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>المشروع</th><th>الحالة</th></tr>
            </x-slot:head>
            @forelse ($projects as $project)
                <tr wire:key="org-project-{{ $project->id }}">
                    <td>{{ $project->name }}</td>
                    <td>{{ $project->status ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="ds-text-muted ds-table-empty">لا توجد مشاريع</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section ds-stat-row">
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">المستفيدون (سجل الأثر)</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($impact['beneficiaries']) }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">متوسط التحسن</span>
            <span class="ds-stat-mini-val ds-ltr-num">
                {{ $impact['improvement_percent'] !== null ? number_format((float) $impact['improvement_percent'], 2).'%' : '—' }}
            </span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">متوسط الرضا</span>
            <span class="ds-stat-mini-val ds-ltr-num">
                {{ $impact['satisfaction_percent'] !== null ? number_format((float) $impact['satisfaction_percent'], 2).'%' : '—' }}
            </span>
        </div>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">الخط الزمني للتواصل</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>التاريخ</th><th>النوع</th><th>الحدث</th></tr>
            </x-slot:head>
            @forelse ($timeline as $event)
                <tr wire:key="timeline-{{ $loop->index }}">
                    <td dir="ltr">{{ $event['at']?->format('Y-m-d H:i') }}</td>
                    <td>{{ $event['kind'] }}</td>
                    <td>{{ $event['title'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-text-muted ds-table-empty">لا توجد أحداث</td></tr>
            @endforelse
        </x-ds-table>
    </section>
</x-ds-page>
