<x-ds-page>
    <x-ds-page-header title="الهيكل التنظيمي" />

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'tree')">الشجرة</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'jobs')">الوظائف</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'transfers')">النقل</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'committees')">اللجان</button>
        @can('structure.manage')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openUnitModal">إضافة إدارة</button>
        @endcan
    </section>

    @if ($tab === 'tree')
        <section class="ds-section">
            <x-ds-table>
                <x-slot:head>
                    <tr><th>الوحدة</th><th>المستوى</th><th>المسؤول</th><th>الأعضاء</th><th>إجراءات</th></tr>
                </x-slot:head>
                @forelse ($tree as $root)
                    @include('livewire.structure.partials.org-node', [
                        'node' => $root,
                        'depth' => 0,
                        'adminColor' => $adminColors[$root->id] ?? '#0F3446',
                    ])
                @empty
                    <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا يوجد هيكل بعد</td></tr>
                @endforelse
            </x-ds-table>
        </section>

        @if ($jobCard)
            <section class="ds-section">
                <h2 class="ds-section-title">بطاقة الوظيفة — {{ $jobCard->name }}</h2>
                <p>الغرض: {{ $jobCard->job_purpose ?? '—' }}</p>
                <p>المسؤوليات:</p>
                <ul>
                    @foreach ($jobCard->job_responsibilities ?? [] as $responsibility)
                        <li>{{ $responsibility }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif

    @if ($tab === 'jobs')
        <section class="ds-section">
            <x-ds-table>
                <x-slot:head>
                    <tr><th>الوظيفة</th><th>التابع لـ</th><th>المسؤول</th><th>الغرض</th><th>إجراءات</th></tr>
                </x-slot:head>
                @forelse ($jobs as $job)
                    <tr wire:key="job-{{ $job->id }}">
                        <td>{{ $job->name }}</td>
                        <td>{{ $job->parent?->name ?? '—' }}</td>
                        <td>{{ $job->manager?->name ?? '—' }}</td>
                        <td>{{ $job->job_purpose ?? '—' }}</td>
                        <td>
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="viewJobCard({{ $job->id }})">بطاقة الوظيفة</button>
                            @can('structure.positions.manage')
                                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.jobs', ['edit' => $job->id]) }}">تعديل البطاقة</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد وظائف</td></tr>
                @endforelse
            </x-ds-table>
        </section>
        @if ($jobCard)
            <section class="ds-section">
                <h2 class="ds-section-title">بطاقة الوظيفة — {{ $jobCard->name }}</h2>
                <p>الغرض: {{ $jobCard->job_purpose ?? '—' }}</p>
            </section>
        @endif
    @endif

    @if ($tab === 'transfers')
        @can('structure.manage')
            <section class="ds-section">
                <h2 class="ds-section-title">نقل موظف</h2>
                <x-ds-form-group label="الموظف" :error="$errors->first('transferUserId')">
                    <select class="ds-input" wire:model="transferUserId">
                        <option value="">—</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-ds-form-group>
                <x-ds-form-group label="الوحدة التنظيمية الجديدة">
                    <select class="ds-input" wire:model="transferUnitId">
                        <option value="">—</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->level }})</option>{{-- المستوى عربي من OrgUnit --}}
                        @endforeach
                    </select>
                </x-ds-form-group>
                <x-ds-form-group label="سبب النقل">
                    <input type="text" class="ds-input" wire:model="transferReason">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="transfer">نقل</button>
            </section>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr><th>الموظف</th><th>من</th><th>إلى</th><th>التاريخ</th><th>السبب</th></tr>
            </x-slot:head>
            @forelse ($transfers as $transfer)
                <tr wire:key="transfer-{{ $transfer->id }}">
                    <td>{{ $transfer->employee?->name ?? '—' }}</td>
                    <td>{{ $transfer->fromUnit?->name ?? '—' }}</td>
                    <td>{{ $transfer->toUnit?->name ?? '—' }}</td>
                    <td dir="ltr">{{ $transfer->effective_on?->format('Y-m-d') }}</td>
                    <td>{{ $transfer->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد عمليات نقل</td></tr>
            @endforelse
        </x-ds-table>
    @endif

    @if ($tab === 'committees')
        @can('structure.manage')
            <section class="ds-section">
                <x-ds-form-group label="اسم اللجنة" :error="$errors->first('committeeName')">
                    <input type="text" class="ds-input" wire:model="committeeName">
                </x-ds-form-group>
                <x-ds-form-group label="اختصاصها">
                    <textarea class="ds-input" wire:model="committeeMandate"></textarea>
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveCommittee">إنشاء لجنة</button>
            </section>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr><th>اللجنة</th><th>الرئيس</th><th>الأعضاء</th><th>الاجتماعات</th><th>الحالة</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($committees as $committee)
                <tr wire:key="committee-{{ $committee->id }}">
                    <td>{{ $committee->name }}</td>
                    <td>{{ $committee->chair?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $committee->members->count() }}</td>
                    <td class="ds-ltr-num">{{ $committee->meetings_count }}</td>
                    <td><x-ds-status-badge :status="$committee->is_active ? 'نشطة' : 'موقوفة'" /></td>
                    <td>
                        @can('structure.committees.manage')
                            <div class="ds-toolbar-actions" style="flex-wrap:wrap;gap:.35rem">
                                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.committees', ['manage' => $committee->id]) }}">الأعضاء والضيوف</a>
                                @if ($committee->is_active)
                                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deactivateCommittee({{ $committee->id }})">إيقاف</button>
                                @else
                                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="activateCommittee({{ $committee->id }})">تفعيل</button>
                                @endif
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askDeleteCommittee({{ $committee->id }})">حذف</button>
                            </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد لجان</td></tr>
            @endforelse
        </x-ds-table>

        @if ($committeeDeleteConfirmId && $committeeDeleteTarget)
            <div class="ds-modal-overlay" wire:key="org-committee-delete-{{ $committeeDeleteConfirmId }}" wire:click.self="cancelDeleteCommittee" wire:keydown.escape.window="cancelDeleteCommittee" style="z-index:1300">
                <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl" wire:click.stop>
                    <div class="ds-modal-header">
                        <h3>تأكيد حذف اللجنة</h3>
                        <button type="button" class="ds-modal-close" wire:click="cancelDeleteCommittee" aria-label="إغلاق">&times;</button>
                    </div>
                    <div class="ds-modal-body">
                        <p>حذف اللجنة «{{ $committeeDeleteTarget->name }}»؟ سيُزال الأعضاء والضيوف من السجل.</p>
                        @if (($committeeDeleteTarget->meetings_count ?? 0) > 0)
                            <p class="ds-badge ds-badge-warning">مرتبط بـ {{ $committeeDeleteTarget->meetings_count }} اجتماع — لن يُسمح بالحذف؛ استخدم الإيقاف بدلاً منه.</p>
                        @endif
                        <div class="ds-toolbar-actions">
                            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelDeleteCommittee">إلغاء</button>
                            <button
                                type="button"
                                class="ds-btn ds-btn-primary"
                                wire:click="deleteCommittee({{ $committeeDeleteConfirmId }})"
                                @disabled(($committeeDeleteTarget->meetings_count ?? 0) > 0)
                            >تأكيد الحذف</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <x-ds-modal :show="$showUnitModal">
        <x-slot:header><h2>وحدة تنظيمية جديدة</h2></x-slot:header>

        <x-ds-form-group label="الاسم" :error="$errors->first('unitName')">
            <input type="text" class="ds-input" wire:model="unitName">
        </x-ds-form-group>

        <x-ds-form-group label="المستوى" :error="$errors->first('unitLevel')">
            <select class="ds-input" wire:model="unitLevel">
                <option value="إدارة">إدارة</option>
                <option value="قسم">قسم</option>
                <option value="وظيفة">وظيفة</option>
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="غرض الوظيفة (لبطاقة الوظيفة)">
            <textarea class="ds-input" wire:model="jobPurpose"></textarea>
        </x-ds-form-group>

        <x-ds-form-group label="المسؤوليات (سطر لكل مسؤولية)">
            <textarea class="ds-input" wire:model="jobResponsibilities"></textarea>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showUnitModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveUnit">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
