<x-ds-page>
    <x-ds-page-header :title="$organization->name" :back-url="route('organizations.index')" back-label="رجوع" />

    <section class="ds-section">
        <p>النوع: {{ $organization->typeLabel() }} — المدينة: {{ $organization->city ?? '—' }}</p>
        <p>الأدوار: {{ $organization->roleLabels() ? implode('، ', $organization->roleLabels()) : '—' }}</p>
        <p class="ds-text-muted">{{ $organization->notes }}</p>
        @can('partnerships.organizations.manage')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openQuickPartnership">
                إنشاء شراكة من الكتالوج
            </button>
        @endcan
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">مسؤولو التواصل</h2>
        @can('partnerships.organizations.manage')
            <button type="button" class="ds-btn ds-btn-sm" wire:click="openContactCreate">إضافة مسؤول</button>
        @endcan
        <x-ds-table>
            <x-slot:head>
                <tr><th>الاسم</th><th>الصفة</th><th>الجوال</th><th>البريد</th><th>رئيسي</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($organization->contacts as $contact)
                <tr wire:key="contact-{{ $contact->id }}">
                    <td>{{ $contact->name }}</td>
                    <td>{{ $contact->position ?? '—' }}</td>
                    <td dir="ltr">{{ $contact->phone ?? '—' }}</td>
                    <td dir="ltr">{{ $contact->email ?? '—' }}</td>
                    <td>{{ $contact->is_primary ? 'نعم' : '—' }}</td>
                    <td>
                        @can('partnerships.organizations.manage')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="editContact({{ $contact->id }})">تعديل</button>
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="archiveContact({{ $contact->id }})">أرشفة</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا يوجد مسؤولو تواصل</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">رحلات الشراكة</h2>
        <p class="ds-text-muted">كل صف رحلة واحدة تحت الجهة. الشراكة لا تُغلق إلا بانتهاء مشروعها دون تجديد.</p>
        @error('renewal') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>رقم الرحلة</th>
                    <th>المرحلة الحالية</th>
                    <th>أيام في المرحلة</th>
                    <th>القيمة المتوقعة (ر.س)</th>
                    <th>فتح الملف</th>
                </tr>
            </x-slot:head>
            @forelse ($organization->partnerships as $partnership)
                <tr wire:key="org-partnership-{{ $partnership->id }}">
                    <td class="ds-ltr-num">
                        {{ $partnership->id }}
                        @if ($partnership->renewed_from_id)
                            <span class="ds-text-muted">← تجديد لرحلة {{ $partnership->renewed_from_id }}</span>
                        @endif
                    </td>
                    <td>{{ $partnership->stageLabel() }}</td>
                    <td class="ds-ltr-num">{{ $partnership->stageAgeDays() }}</td>
                    <td class="ds-ltr-num">
                        {{ $partnership->expected_value !== null ? number_format((float) $partnership->expected_value, 2) : '—' }}
                    </td>
                    <td>
                        <a class="ds-btn ds-btn-sm ds-btn-primary" href="{{ route('partnerships.show', $partnership->id) }}?from=organization">
                            فتح ملف الرحلة
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد شراكات</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">مشاريعها</h2>
        <p class="ds-text-muted">التجديد من هنا فقط: مشروع منتهٍ أو متوقف يفتح رحلة فرصة جديدة ويُغلق الرحلة الأم.</p
        <x-ds-table>
            <x-slot:head>
                <tr><th>المشروع</th><th>الحالة</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($projects as $project)
                <tr wire:key="org-project-{{ $project->id }}">
                    <td>
                        @can('projects.view')
                            <a class="ds-link" href="{{ route('projects.show', $project->id) }}">{{ $project->name }}</a>
                        @else
                            {{ $project->name }}
                        @endcan
                    </td>
                    <td>{{ $project->statusLabel() }}</td>
                    <td>
                        @can('partnerships.organizations.manage')
                            @if (\App\Models\Partnership::projectStatusAllowsRenewal($project->status))
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="renewFromProject({{ $project->id }})">
                                    تجديد
                                </button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-text-muted ds-table-empty">لا توجد مشاريع</td></tr>
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
                    <td dir="ltr">{{ hollal_dt($event['at'] ?? null) }}</td>
                    <td>{{ $event['kind'] }}</td>
                    <td>{{ $event['title'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-text-muted ds-table-empty">لا توجد أحداث</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <x-ds-modal :show="$showContactModal">
        <x-slot:header><h2>{{ $contactId ? 'تعديل مسؤول التواصل' : 'إضافة مسؤول التواصل' }}</h2></x-slot:header>
        <x-ds-form-group label="الاسم" :error="$errors->first('contactName')">
            <input type="text" class="ds-input" wire:model="contactName">
        </x-ds-form-group>
        <x-ds-form-group label="الصفة" :error="$errors->first('contactPosition')">
            <input type="text" class="ds-input" wire:model="contactPosition">
        </x-ds-form-group>
        <x-ds-form-group label="الجوال" :error="$errors->first('contactPhone')">
            <input type="text" class="ds-input" wire:model="contactPhone">
        </x-ds-form-group>
        <x-ds-form-group label="البريد" :error="$errors->first('contactEmail')">
            <input type="email" class="ds-input" wire:model="contactEmail">
        </x-ds-form-group>
        <label class="ds-checkbox"><input type="checkbox" wire:model="contactPrimary"> مسؤول رئيسي</label>
        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showContactModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveContact">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>

    <x-ds-modal :show="$showQuickPartnershipModal" size="lg">
        <x-slot:header><h2>إنشاء شراكة من الكتالوج</h2></x-slot:header>
        <x-ds-form-group label="المتابع" :error="$errors->first('quickOwnerId')">
            <select class="ds-input" wire:model="quickOwnerId">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="البرامج المسموحة" :error="$errors->first('quickProgramIds')">
            @foreach ($programs as $program)
                <label class="ds-checkbox">
                    <input type="checkbox" value="{{ $program->id }}" wire:model="quickProgramIds">
                    {{ $program->name }} ({{ $program->prices_count }} أسعار نشطة)
                </label>
            @endforeach
        </x-ds-form-group>
        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showQuickPartnershipModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="createQuickPartnership">إنشاء</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
