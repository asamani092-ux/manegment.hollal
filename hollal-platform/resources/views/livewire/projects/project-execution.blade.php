<x-ds-page>
    <x-ds-page-header :title="$project->name" />

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'plan')">الخطة والفريق</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'visits')">الزيارات والاستشارات</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'measurement')">القياس والأثر</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'closure')">الإغلاق</button>
    </section>

    <section class="ds-section ds-stat-row">
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">الإنجاز الموزون بالتقييم الثلاثي</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($summary['weighted_percent'], 2) }}%</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">المهام</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ $summary['total'] }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">المقيّمة نهائيًا</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ $summary['evaluated'] }}</span>
        </div>
        <div class="ds-stat-mini">
            <span class="ds-stat-mini-label">المتأخر</span>
            <span class="ds-stat-mini-val ds-ltr-num">{{ $summary['overdue'] }}</span>
        </div>
    </section>

    @if ($tab === 'plan')
        <section class="ds-section">
            <h2 class="ds-section-title">بطاقة الشراكة</h2>
            @if ($project->partnership)
                <p>الجهة: {{ $project->partnership->organization?->name ?? $project->partnership->entity_name ?? '—' }}</p>
                <p>مرحلة الشراكة: {{ $project->partnership->stageLabel() }}</p>
            @else
                <p class="ds-text-muted">مشروع داخلي بلا شراكة</p>
            @endif
        </section>

        <section class="ds-section">
            <h2 class="ds-section-title">بطاقة منصة البرنامج</h2>
            @if ($project->program?->platform_url)
                <p><a href="{{ $project->program->platform_url }}" dir="ltr" target="_blank" rel="noopener">{{ $project->program->platform_url }}</a></p>
                <p>{{ $project->program->platform_steps ?? '—' }}</p>
            @else
                <p class="ds-text-muted">لا توجد منصة مرتبطة</p>
            @endif
        </section>

        <section class="ds-section">
            <h2 class="ds-section-title">شجرة الخطة</h2>
            <x-ds-table>
                <x-slot:head>
                    <tr><th>البند</th><th>الدور</th><th>المكلّف</th><th>التسليم</th><th>الشاهد</th><th>التقييم النهائي</th></tr>
                </x-slot:head>
                @forelse ($planTree as $root)
                    @include('livewire.projects.partials.plan-node', ['node' => $root, 'depth' => 0])
                @empty
                    <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد بنود خطة</td></tr>
                @endforelse
            </x-ds-table>
        </section>

        <section class="ds-section">
            <h2 class="ds-section-title">الفريق</h2>
            <x-ds-table>
                <x-slot:head>
                    <tr><th>الاسم</th><th>الجهة/حلل</th><th>الدور</th></tr>
                </x-slot:head>
                @foreach ($project->team as $member)
                    <tr wire:key="team-{{ $member->id }}">
                        <td>{{ $member->name }}</td>
                        <td>حلل</td>
                        <td>{{ $member->profile?->job_title ?? '—' }}</td>
                    </tr>
                @endforeach
                @foreach ($project->entityMembers as $member)
                    <tr wire:key="entity-member-{{ $member->id }}">
                        <td>{{ $member->name }}</td>
                        <td>الجهة</td>
                        <td>{{ $member->role_label }}</td>
                    </tr>
                @endforeach
                @if ($project->team->isEmpty() && $project->entityMembers->isEmpty())
                    <tr><td colspan="3" class="ds-text-muted ds-table-empty">لا يوجد فريق بعد</td></tr>
                @endif
            </x-ds-table>
        </section>
    @endif

    @if ($tab === 'visits')
        <section class="ds-section ds-stat-row">
            @foreach ($quotas as $service => $quota)
                <div class="ds-stat-mini" wire:key="quota-{{ $service }}">
                    <span class="ds-stat-mini-label">{{ $service }} (المستهلك من المتعاقد)</span>
                    <span class="ds-stat-mini-val ds-ltr-num">{{ $quota['consumed'] }} / {{ $quota['contracted'] }}</span>
                </div>
            @endforeach
        </section>

        <section class="ds-section">
            <h2 class="ds-section-title">جدولة زيارة</h2>
            @can('projects.visits.manage')
                <x-ds-form-group label="التاريخ" :error="$errors->first('visitDate')">
                    <input type="date" class="ds-input" wire:model="visitDate" dir="ltr">
                </x-ds-form-group>
                <x-ds-form-group label="الغرض" :error="$errors->first('visitPurpose')">
                    <input type="text" class="ds-input" wire:model="visitPurpose">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="scheduleVisit">جدولة</button>
            @endcan
        </section>

        @foreach ($visits as $visit)
            <div class="ds-kanban-card" wire:key="visit-{{ $visit->id }}">
                <p>زيارة #{{ $visit->id }} — {{ $visit->scheduled_on->format('Y-m-d') }} — {{ $visit->status }}</p>
                <p class="ds-text-muted">{{ $visit->purpose ?? '—' }}</p>

                @if ($visit->recommendations)
                    <x-ds-table>
                        <x-slot:head><tr><th>التوصية</th><th>إجراء</th></tr></x-slot:head>
                        @foreach ($visit->recommendations as $index => $recommendation)
                            <tr wire:key="rec-{{ $visit->id }}-{{ $index }}">
                                <td>{{ $recommendation }}</td>
                                <td>
                                    @can('projects.visits.manage')
                                        <button type="button" class="ds-btn ds-btn-sm"
                                            wire:click="createCorrectiveTask({{ $visit->id }}, {{ $index }})">
                                            مهمة تصحيحية
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </x-ds-table>
                @endif

                @can('projects.visits.manage')
                    @if ($visit->status !== 'منفذة')
                        <x-ds-form-group label="الملاحظات">
                            <textarea class="ds-input" wire:model="visitNotes"></textarea>
                        </x-ds-form-group>
                        <x-ds-form-group label="الإيجابيات">
                            <textarea class="ds-input" wire:model="visitPositives"></textarea>
                        </x-ds-form-group>
                        <x-ds-form-group label="التحديات/المخالفات">
                            <textarea class="ds-input" wire:model="visitChallenges"></textarea>
                        </x-ds-form-group>
                        <x-ds-form-group label="التوصيات (سطر لكل توصية)">
                            <textarea class="ds-input" wire:model="visitRecommendations"></textarea>
                        </x-ds-form-group>
                        <button type="button" class="ds-btn" wire:click="submitVisitReport({{ $visit->id }})">رفع التقرير</button>
                    @endif
                @endcan
            </div>
        @endforeach

        <section class="ds-section">
            <h2 class="ds-section-title">الاستشارات</h2>
            @can('projects.visits.manage')
                <x-ds-form-group label="الموضوع" :error="$errors->first('consultationSubject')">
                    <input type="text" class="ds-input" wire:model="consultationSubject">
                </x-ds-form-group>
                <x-ds-form-group label="الطلب">
                    <textarea class="ds-input" wire:model="consultationRequest"></textarea>
                </x-ds-form-group>
                <button type="button" class="ds-btn" wire:click="openConsultation">فتح استشارة</button>
            @endcan

            <x-ds-table>
                <x-slot:head><tr><th>الموضوع</th><th>المصدر</th><th>المختص</th><th>الحالة</th></tr></x-slot:head>
                @forelse ($consultations as $consultation)
                    <tr wire:key="consultation-{{ $consultation->id }}">
                        <td>{{ $consultation->subject }}</td>
                        <td>{{ $consultation->requested_via }}</td>
                        <td>{{ $consultation->specialist?->name ?? '—' }}</td>
                        <td>{{ $consultation->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ds-text-muted ds-table-empty">لا توجد استشارات</td></tr>
                @endforelse
            </x-ds-table>
        </section>
    @endif

    @if ($tab === 'measurement')
        <section class="ds-section ds-stat-row">
            <div class="ds-stat-mini">
                <span class="ds-stat-mini-label">القياس القبلي</span>
                <span class="ds-stat-mini-val ds-ltr-num">
                    {{ $results['pre_percent'] !== null ? number_format($results['pre_percent'], 2).'%' : '—' }}
                </span>
            </div>
            <div class="ds-stat-mini">
                <span class="ds-stat-mini-label">القياس البعدي</span>
                <span class="ds-stat-mini-val ds-ltr-num">
                    {{ $results['post_percent'] !== null ? number_format($results['post_percent'], 2).'%' : '—' }}
                </span>
            </div>
            <div class="ds-stat-mini">
                <span class="ds-stat-mini-label">نسبة التحسن</span>
                <span class="ds-stat-mini-val ds-ltr-num">
                    {{ $results['improvement_percent'] !== null ? number_format($results['improvement_percent'], 2).'%' : '—' }}
                </span>
            </div>
            <div class="ds-stat-mini">
                <span class="ds-stat-mini-label">المستفيدون</span>
                <span class="ds-stat-mini-val ds-ltr-num">{{ number_format($results['beneficiaries']) }}</span>
            </div>
        </section>

        @can('projects.measurement.manage')
            <section class="ds-section">
                <h2 class="ds-section-title">مجموعات المستفيدين</h2>
                <x-ds-form-group label="اسم المجموعة" :error="$errors->first('groupName')">
                    <input type="text" class="ds-input" wire:model="groupName">
                </x-ds-form-group>
                <x-ds-form-group label="العدد" :error="$errors->first('groupSize')">
                    <input type="number" class="ds-input" wire:model="groupSize">
                </x-ds-form-group>
                <button type="button" class="ds-btn" wire:click="addBeneficiaryGroup">إضافة</button>

                <x-ds-table>
                    <x-slot:head><tr><th>المجموعة</th><th>العدد</th></tr></x-slot:head>
                    @forelse ($groups as $group)
                        <tr wire:key="group-{{ $group->id }}">
                            <td>{{ $group->name }}</td>
                            <td class="ds-ltr-num">{{ $group->size }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="ds-text-muted ds-table-empty">لا توجد مجموعات</td></tr>
                    @endforelse
                </x-ds-table>
            </section>

            <section class="ds-section">
                <h2 class="ds-section-title">تسجيل قياس</h2>
                <x-ds-form-group label="النموذج" :error="$errors->first('formId')">
                    <select class="ds-input" wire:model.live="formId">
                        <option value="">—</option>
                        @foreach ($forms as $form)
                            <option value="{{ $form->id }}">{{ $form->title }} ({{ $form->kind }})</option>
                        @endforeach
                    </select>
                </x-ds-form-group>

                <x-ds-form-group label="المرحلة" :error="$errors->first('phase')">
                    <select class="ds-input" wire:model="phase">
                        <option value="قبلي">قبلي</option>
                        <option value="بعدي">بعدي</option>
                    </select>
                </x-ds-form-group>

                @if ($selectedForm)
                    @foreach ($selectedForm->questions as $question)
                        <x-ds-form-group :label="$question->text.' (حتى '.$question->max_score.')'" wire:key="q-{{ $question->id }}">
                            <input type="number" step="0.01" class="ds-input" wire:model="answers.{{ $question->id }}">
                        </x-ds-form-group>
                    @endforeach
                @endif

                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveMeasurement">حفظ القياس</button>
            </section>
        @endcan
    @endif

    @if ($tab === 'closure')
        <section class="ds-section">
            <h2 class="ds-section-title">قائمة شروط الإغلاق</h2>
            @error('closure') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror

            <x-ds-table>
                <x-slot:head><tr><th>الشرط</th><th>الحالة</th></tr></x-slot:head>
                @foreach ($checklist as $key => $item)
                    <tr wire:key="checklist-{{ $key }}">
                        <td>{{ $item['label'] }}</td>
                        <td>
                            @if ($item['ok'])
                                <span class="ds-badge ds-badge-success">مستوفٍ</span>
                            @else
                                <span class="ds-badge ds-badge-danger">غير مستوفٍ</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ds-table>

            @can('projects.close')
                <x-ds-form-group label="الدرس المستفاد" :error="$errors->first('lessonLearned')">
                    <textarea class="ds-input" wire:model="lessonLearned"></textarea>
                </x-ds-form-group>
                <button type="button" class="ds-btn" wire:click="saveLesson">حفظ الدرس</button>
                <button type="button" class="ds-btn" wire:click="generateFinalReport">توليد التقرير الختامي</button>
                <button type="button" class="ds-btn" wire:click="approveFinalReport">اعتماد التقرير</button>
                <button type="button" class="ds-btn" wire:click="markDelivered">تسليم عبر رابط الجهة</button>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="closeProject">إغلاق المشروع</button>
            @endcan
        </section>
    @endif
</x-ds-page>
