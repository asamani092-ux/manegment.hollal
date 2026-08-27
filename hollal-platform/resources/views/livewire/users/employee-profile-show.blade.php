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
        $typeLabels = [
            'دوام_كامل' => 'نظامي — دوام كامل',
            'دوام_جزئي' => 'دوام جزئي',
            'متعاون' => 'متعاون',
            'متطوع' => 'متطوع',
        ];
    @endphp

    <x-ds-page-header :title="'الملف الوظيفي — '.$user->name">
        <x-slot:actions>
            @if ($canUpdate)
                <button type="button" class="ds-btn ds-btn-primary" wire:click="openEdit">تعديل</button>
            @endif
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
                <article class="ds-card ds-mb-3">
                    <div class="ds-card-head">
                        <h3 class="ds-section-title">بطاقة البيانات</h3>
                        @if ($canUpdate)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openEdit">تعديل</button>
                        @endif
                    </div>
                    <dl class="ds-detail-grid">
                        <div><dt>الاسم</dt><dd>{{ $user->name }}</dd></div>
                        <div><dt>البريد</dt><dd dir="ltr">{{ $user->email }}</dd></div>
                        <div><dt>الجوال</dt><dd dir="ltr">{{ $user->phone ?? '—' }}</dd></div>
                        <div><dt>الهوية</dt><dd dir="ltr">{{ $user->profile?->national_id ?? '—' }}</dd></div>
                        <div><dt>المدير المباشر</dt><dd>{{ $user->manager?->name ?? '—' }}</dd></div>
                        <div><dt>الدور</dt><dd><x-ds-role-label :name="$user->roles->first()?->name ?? ''" /></dd></div>
                    </dl>
                </article>
            @elseif ($activeTab === 'job')
                <article class="ds-card ds-mb-3">
                    <div class="ds-card-head">
                        <h3 class="ds-section-title">بطاقة الوظيفة</h3>
                        @if ($canUpdate)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openEdit">تعديل</button>
                        @endif
                    </div>
                    <dl class="ds-detail-grid">
                        <div><dt>المسمى الوظيفي</dt><dd>{{ $user->profile?->job_title ?? '—' }}</dd></div>
                        <div><dt>نوع التوظيف</dt><dd>{{ $typeLabels[$user->profile?->employment_type] ?? ($user->profile?->employment_type ?? '—') }}</dd></div>
                        <div><dt>تاريخ المباشرة</dt><dd>{{ $user->profile?->hire_date?->format('Y-m-d') ?? '—' }}</dd></div>
                        <div><dt>القسم</dt><dd>{{ $user->department?->name ?? '—' }}</dd></div>
                        <div><dt>الساعات الأساسية أسبوعيًا</dt><dd class="ds-ltr-num">{{ $user->profile?->weekly_hours ?? '—' }}</dd></div>
                        <div><dt>برنامج الحضور</dt><dd>{{ $user->attendance_enabled ? 'مفعّل لهذا الموظف فقط' : 'متوقّف — التقييم على المهام' }}</dd></div>
                    </dl>
                </article>

                <article class="ds-card ds-mb-3">
                    <h3 class="ds-section-title">المسؤوليات الوظيفية</h3>
                    @forelse ($responsibilities as $item)
                        <p class="ds-mb-sm">{{ $item->order }}. {{ $item->body }}</p>
                    @empty
                        <p class="ds-text-muted">لا توجد بنود مسؤولية.</p>
                    @endforelse
                </article>

                @can('hr.employees.update')
                    <article class="ds-card ds-mb-3">
                        <h3 class="ds-section-title">إعدادات الحضور</h3>
                        <p class="ds-text-muted">يُفعَّل برنامج الحضور لكل موظف على حدة من هنا. من لم يُفعَّل له يبقى تقييمه على المهام.</p>
                        <label class="ds-checkbox">
                            <input type="checkbox" wire:model="attendanceEnabled">
                            تفعيل برنامج الحضور لهذا الموظف
                        </label>
                        <x-ds-form-group label="الساعات الأساسية الأسبوعية" :error="$errors->first('weeklyHours')">
                            <input type="number" class="ds-input ds-ltr-num" wire:model="weeklyHours" min="1" max="80">
                        </x-ds-form-group>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveAttendanceSettings">حفظ</button>
                    </article>
                @endcan
            @elseif ($activeTab === 'salary')
                <article class="ds-card ds-mb-3">
                    <div class="ds-card-head">
                        <h3 class="ds-section-title">بطاقة الراتب</h3>
                    </div>
                <dl class="ds-detail-grid">
                    <div><dt>السلم</dt><dd>{{ $user->profile?->payScale?->name_ar ?? '—' }}</dd></div>
                    <div><dt>الدرجة</dt><dd>{{ $user->profile?->grade_label ?? '—' }}</dd></div>
                    <div><dt>الأساسي</dt><dd class="ds-ltr-num">{{ number_format($salaryTotals['base'] ?? 0, 2) }}</dd></div>
                    <div><dt>البدلات</dt><dd class="ds-ltr-num">{{ number_format($salaryTotals['allowances'] ?? 0, 2) }}</dd></div>
                    <div><dt>الخصم الثابت</dt><dd class="ds-ltr-num">{{ number_format($salaryTotals['deductions'] ?? 0, 2) }}</dd></div>
                    <div><dt>الراتب الشهري المشتق</dt><dd class="ds-ltr-num"><strong>{{ number_format($salaryTotals['monthly'] ?? 0, 2) }}</strong></dd></div>
                    <div><dt>الساعات الإضافية</dt><dd>{{ $user->profile?->overtime_unlocked ? 'مفتوح' : 'مقفل' }}</dd></div>
                    <div><dt>قيمة ساعة الإضافي</dt><dd class="ds-ltr-num">{{ $user->profile?->overtime_hour_value ?? '0' }}</dd></div>
                </dl>
                </article>

                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>النوع</th>
                            <th>البند</th>
                            <th>المبلغ</th>
                            @if ($canManageOvertime)<th>إجراءات</th>@endif
                        </tr>
                    </x-slot:head>
                    @forelse ($salaryComponents as $component)
                        <tr wire:key="comp-{{ $component->id }}">
                            <td>{{ $component->type }}</td>
                            <td>
                                @if ($editingComponentId === $component->id)
                                    <input type="text" class="ds-input" wire:model="editComponentLabel">
                                @else
                                    {{ $component->label_ar }}
                                @endif
                            </td>
                            <td class="ds-ltr-num">
                                @if ($editingComponentId === $component->id)
                                    <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="editComponentAmount">
                                @else
                                    {{ number_format((float) $component->amount, 2) }}
                                @endif
                            </td>
                            @if ($canManageOvertime)
                                <td>
                                    @if ($editingComponentId === $component->id)
                                        <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="saveEditComponent">حفظ</button>
                                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="$set('editingComponentId', null)">إلغاء</button>
                                    @else
                                        <button type="button" class="ds-link" wire:click="openEditComponent({{ $component->id }})">تعديل</button>
                                        <button type="button" class="ds-link" wire:click="closeSalaryComponent({{ $component->id }})" wire:confirm="إيقاف سريان هذا المكوّن؟">إيقاف</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManageOvertime ? 4 : 3 }}" class="ds-text-muted">لا توجد مكوّنات سارية</td></tr>
                    @endforelse
                </x-ds-table>

                @if ($canManageOvertime)
                    <section class="ds-section">
                        <h3 class="ds-section-title">الراتب الأساسي (تعديل مباشر)</h3>
                        <p class="ds-text-muted">يُغلق المبلغ السابق ويُفتح مبلغ جديد من اليوم — المسيّرات السابقة لا تتأثر؛ المسيّر التالي يلتقط القيمة الجديدة.</p>
                        <x-ds-form-group label="الأساسي (ر.س)" :error="$errors->first('baseAmount')">
                            <input type="number" step="0.01" min="0" class="ds-input ds-ltr-num" wire:model="baseAmount">
                        </x-ds-form-group>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveBaseAmount">حفظ الأساسي</button>
                    </section>

                    <section class="ds-section">
                        <h3 class="ds-section-title">ربط السلم والدرجة</h3>
                        <x-ds-form-group label="سلم الرواتب" :error="$errors->first('payScaleId')">
                            <select class="ds-input" wire:model.live="payScaleId">
                                <option value="">—</option>
                                @foreach ($payScales as $scale)
                                    <option value="{{ $scale->id }}">{{ $scale->name_ar }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="الدرجة" :error="$errors->first('gradeLabel')">
                            <select class="ds-input" wire:model="gradeLabel">
                                <option value="">—</option>
                                @php
                                    $selectedScale = $payScales->firstWhere('id', (int) $payScaleId);
                                @endphp
                                @foreach ($selectedScale?->grades ?? [] as $grade)
                                    <option value="{{ $grade['label'] }}">{{ $grade['label'] }} ({{ $grade['base_amount'] }})</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="assignPayGrade">حفظ الربط</button>
                    </section>

                    <section class="ds-section">
                        <h3 class="ds-section-title">إضافة بدل أو خصم ثابت</h3>
                        <p class="ds-text-muted">الخصم الثابت للنظامي (دوام كامل) فقط. خصم الأداء/الغياب المتغير يُضاف من المسير الشهري بسبب إلزامي.</p>
                        <x-ds-form-group label="النوع" :error="$errors->first('newComponentType')">
                            <select class="ds-input" wire:model="newComponentType">
                                <option value="{{ \App\Models\SalaryComponent::TYPE_ALLOWANCE }}">بدل</option>
                                <option value="{{ \App\Models\SalaryComponent::TYPE_DEDUCTION }}">خصم ثابت</option>
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="البيان" :error="$errors->first('newComponentLabel')">
                            <input type="text" class="ds-input" wire:model="newComponentLabel">
                        </x-ds-form-group>
                        <x-ds-form-group label="المبلغ" :error="$errors->first('newComponentAmount')">
                            <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="newComponentAmount">
                        </x-ds-form-group>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="addSalaryComponent">إضافة</button>
                    </section>

                    <section class="ds-section">
                        <h3 class="ds-section-title">فتح الساعات الإضافية</h3>
                        <x-ds-form-group label="حالة الإضافي" :error="$errors->first('overtimeGate')">
                            <select class="ds-input" wire:model="overtimeGate">
                                <option value="مقفل">مقفل</option>
                                <option value="مفتوح">مفتوح</option>
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="قيمة ساعة الإضافي" :error="$errors->first('overtimeHourValue')">
                            <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="overtimeHourValue">
                        </x-ds-form-group>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveOvertimeGate">حفظ</button>
                    </section>
                @endif
            @elseif ($activeTab === 'contracts')
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>البداية</th>
                            <th>النهاية</th>
                            <th>الحالة</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($contracts as $contract)
                        <tr wire:key="c-{{ $contract->id }}">
                            <td class="ds-ltr-num">{{ $contract->start_date?->format('Y-m-d') }}</td>
                            <td class="ds-ltr-num">{{ $contract->end_date?->format('Y-m-d') }}</td>
                            <td>{{ $contract->statusLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ds-text-muted">لا توجد عقود</td></tr>
                    @endforelse
                </x-ds-table>
            @elseif ($activeTab === 'tasks')
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>المهمة</th>
                            <th>الحالة</th>
                            <th>الاستحقاق</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($tasks as $task)
                        <tr wire:key="t-{{ $task->id }}">
                            <td>{{ $task->title }}</td>
                            <td>{{ $task->status }}</td>
                            <td class="ds-ltr-num">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ds-text-muted">لا توجد مهام</td></tr>
                    @endforelse
                </x-ds-table>
            @elseif ($activeTab === 'evaluations')
                <p class="ds-text-muted ds-mb-3">
                    سجل التقييمات من أداة «التقييم الدوري» في الموارد البشرية. الأرشيف يحفظ التاريخ دون حذفه.
                </p>
                <h3 class="ds-section-title">الجاري</h3>
                @forelse ($evaluations as $evaluation)
                    <article class="ds-card ds-mb-3" wire:key="ev-{{ $evaluation->id }}">
                        <h3>{{ $evaluation->period }} — {{ $evaluation->status }}</h3>
                        <p class="ds-text-muted">المقيّم: {{ $evaluation->evaluator?->name ?? '—' }}</p>
                        @foreach ($evaluation->scores as $score)
                            <p>{{ $score->responsibility?->body ?? 'بند' }}: <strong class="ds-ltr-num">{{ $score->score }}</strong>/5
                                @if ($score->note) — {{ $score->note }} @endif
                            </p>
                        @endforeach
                        @if ($evaluation->employee_comment)
                            <p>تعليق الموظف: {{ $evaluation->employee_comment }}</p>
                        @elseif ($evaluation->isPublished() && $evaluation->employee_id === auth()->id())
                            <x-ds-form-group label="تعليقك" :error="$errors->first('employeeComment')">
                                <textarea class="ds-input" wire:model="employeeComment" rows="3"></textarea>
                            </x-ds-form-group>
                            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveEmployeeComment({{ $evaluation->id }})">إرسال التعليق</button>
                        @endif
                    </article>
                @empty
                    <p class="ds-text-muted ds-mb-3">لا توجد تقييمات جارية.</p>
                @endforelse

                <h3 class="ds-section-title">الأرشيف</h3>
                @forelse ($archivedEvaluations as $evaluation)
                    <article class="ds-card ds-mb-3" wire:key="ev-arch-{{ $evaluation->id }}">
                        <h3>{{ $evaluation->period }} — مؤرشف</h3>
                        <p class="ds-text-muted">المقيّم: {{ $evaluation->evaluator?->name ?? '—' }}</p>
                        @foreach ($evaluation->scores as $score)
                            <p>{{ $score->responsibility?->body ?? 'بند' }}: <strong class="ds-ltr-num">{{ $score->score }}</strong>/5
                                @if ($score->note) — {{ $score->note }} @endif
                            </p>
                        @endforeach
                        @if ($evaluation->employee_comment)
                            <p>تعليق الموظف: {{ $evaluation->employee_comment }}</p>
                        @endif
                    </article>
                @empty
                    <p class="ds-text-muted">لا يوجد أرشيف بعد.</p>
                @endforelse
            @elseif ($activeTab === 'leaves')
                <p class="ds-text-muted ds-mb-3">الرصيد السنوي: <strong class="ds-ltr-num">{{ $user->profile?->annual_leave_balance ?? '—' }}</strong></p>
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>النوع</th>
                            <th>من</th>
                            <th>إلى</th>
                            <th>الحالة</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($leaves as $leave)
                        <tr wire:key="lv-{{ $leave->id }}">
                            <td>{{ $leave->type }}</td>
                            <td class="ds-ltr-num">{{ $leave->from_date?->format('Y-m-d') }}</td>
                            <td class="ds-ltr-num">{{ $leave->to_date?->format('Y-m-d') }}</td>
                            <td><x-ds-status-badge :status="$leave->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ds-text-muted">لا توجد إجازات</td></tr>
                    @endforelse
                </x-ds-table>
            @elseif ($activeTab === 'documents')
                <article class="ds-card ds-mb-3">
                    <div class="ds-card-head">
                        <h3 class="ds-section-title">الوثائق الرسمية</h3>
                        @if ($canUpdate)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="openDocumentModal">إضافة وثيقة</button>
                        @endif
                    </div>
                    <p class="ds-text-muted ds-mb-3">هوية · إقامة · جواز · عقد عمل · أخرى — مع رقم الوثيقة وتاريخ الانتهاء للتنبيه قبل التجديد.</p>
                    <x-ds-table>
                        <x-slot:head>
                            <tr>
                                <th>النوع</th>
                                <th>الرقم</th>
                                <th>الإصدار</th>
                                <th>الانتهاء</th>
                                <th>الحالة</th>
                                <th>الملف</th>
                                @if ($canUpdate)<th>إجراءات</th>@endif
                            </tr>
                        </x-slot:head>
                        @forelse ($employeeDocuments as $doc)
                            <tr wire:key="edoc-{{ $doc->id }}">
                                <td>{{ $doc->type }}</td>
                                <td class="ds-ltr-num">{{ $doc->document_number ?? '—' }}</td>
                                <td class="ds-ltr-num">{{ $doc->issue_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="ds-ltr-num">{{ $doc->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>
                                    @if ($doc->isExpired())
                                        <span class="ds-badge ds-badge-danger">منتهية</span>
                                    @elseif ($doc->isExpiringSoon(30))
                                        <span class="ds-badge ds-badge-warning">تنتهي خلال {{ $doc->daysUntilExpiry() }} يوم</span>
                                    @elseif ($doc->expiry_date)
                                        <span class="ds-badge ds-badge-success">سارية</span>
                                    @else
                                        <span class="ds-text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($doc->file_path)
                                        <a class="ds-link" href="{{ route('employee-documents.files.download', $doc) }}?inline=1" target="_blank" rel="noopener">معاينة</a>
                                        ·
                                        <a class="ds-link" href="{{ route('employee-documents.files.download', $doc) }}">تنزيل</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                @if ($canUpdate)
                                    <td>
                                        <button type="button" class="ds-link" wire:click="openDocumentModal({{ $doc->id }})">تعديل</button>
                                        <button type="button" class="ds-link" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="حذف هذه الوثيقة؟">حذف</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canUpdate ? 7 : 6 }}" class="ds-text-muted">لا توجد وثائق رسمية</td></tr>
                        @endforelse
                    </x-ds-table>
                </article>
            @elseif ($activeTab === 'log')
                <p class="ds-text-muted">سجل التغييرات الوظيفية يُحفظ في سجل النشاط.</p>
            @endif
        </div>
    </section>

        <x-ds-modal :show="$showDocumentModal" title="وثيقة رسمية" close-action="$set('showDocumentModal', false)" size="lg">
            <x-ds-form-group label="نوع الوثيقة" :error="$errors->first('docType')">
                <select class="ds-input" wire:model="docType">
                    @foreach (\App\Models\EmployeeDocument::TYPES as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="رقم الوثيقة" :error="$errors->first('docNumber')">
                <input type="text" class="ds-input ds-ltr-num" wire:model="docNumber">
            </x-ds-form-group>
            <x-ds-form-group label="تاريخ الإصدار" :error="$errors->first('docIssueDate')">
                <input type="date" class="ds-input ds-ltr-num" wire:model="docIssueDate">
            </x-ds-form-group>
            <x-ds-form-group label="تاريخ الانتهاء" :error="$errors->first('docExpiryDate')">
                <input type="date" class="ds-input ds-ltr-num" wire:model="docExpiryDate">
            </x-ds-form-group>
            <x-ds-form-group label="ملاحظات" :error="$errors->first('docNotes')">
                <textarea class="ds-input" rows="2" wire:model="docNotes"></textarea>
            </x-ds-form-group>
            <x-ds-form-group label="المرفق" :error="$errors->first('docFile')">
                <input type="file" class="ds-input" wire:model="docFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            </x-ds-form-group>
            <x-slot:footer>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveDocument">حفظ</button>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showDocumentModal', false)">إلغاء</button>
            </x-slot:footer>
        </x-ds-modal>

        <x-ds-modal :show="$showEdit" title="تعديل الملف الوظيفي" close-action="$set('showEdit', false)" size="lg">
        <x-ds-form-group label="الاسم" :error="$errors->first('editName')">
            <input type="text" class="ds-input" wire:model="editName">
        </x-ds-form-group>
        <x-ds-form-group label="الجوال" :error="$errors->first('editPhone')">
            <input type="tel" class="ds-input" wire:model="editPhone">
        </x-ds-form-group>
        <x-ds-form-group label="البريد" :error="$errors->first('editEmail')">
            <input type="email" class="ds-input" wire:model="editEmail">
        </x-ds-form-group>
        <x-ds-form-group label="كلمة المرور الجديدة (اتركها فارغة للإبقاء)" :error="$errors->first('editPassword')">
            <input type="password" class="ds-input" wire:model="editPassword" autocomplete="new-password">
        </x-ds-form-group>
        <div class="ds-form-group">
            <label class="ds-checkbox-label">
                <input type="checkbox" wire:model="editIsActive">
                <span>الحساب نشط (إلغاء التفعيل يمنع تسجيل الدخول)</span>
            </label>
        </div>
        <x-ds-form-group label="المسمى الوظيفي" :error="$errors->first('editJobTitle')">
            <input type="text" class="ds-input" wire:model="editJobTitle">
        </x-ds-form-group>
        <x-ds-form-group label="نوع التوظيف" :error="$errors->first('editEmploymentType')">
            <select class="ds-input" wire:model="editEmploymentType">
                <option value="">—</option>
                <option value="دوام_كامل">نظامي — دوام كامل</option>
                <option value="دوام_جزئي">دوام جزئي</option>
                <option value="متعاون">متعاون</option>
                <option value="متطوع">متطوع</option>
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="تاريخ المباشرة" :error="$errors->first('editHireDate')">
            <input type="date" class="ds-input ds-ltr-num" wire:model="editHireDate">
        </x-ds-form-group>
        <x-ds-form-group label="رقم الهوية" :error="$errors->first('editNationalId')">
            <input type="text" class="ds-input ds-ltr-num" wire:model="editNationalId">
        </x-ds-form-group>
        <x-ds-form-group label="الدور" :error="$errors->first('editRoleName')">
            <select class="ds-input" wire:model="editRoleName">
                <option value="">— اختر دور —</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}">{{ hollal_role_label($role->name) }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="القسم">
            <select class="ds-input" wire:model="editDepartmentId">
                <option value="">—</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="المدير">
            <select class="ds-input" wire:model="editManagerId">
                <option value="">—</option>
                @foreach ($managers as $mgr)
                    @if ($mgr->id !== $userId)
                        <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                    @endif
                @endforeach
            </select>
        </x-ds-form-group>
        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveProfile">حفظ</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showEdit', false)">إلغاء</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
