<x-ds-page>
    <x-ds-page-header title="التقييم الربعي" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        مسار واحد متسلسل: قالب ← فتح دورة ← فتح جماعي ← تعبئة ← اعتماد جماعي ← إغلاق/أرشفة.
        الاعتماد فردي معطّل — الاعتماد الجماعي فقط بعد اكتمال كل الدرجات.
    </p>

    @if ($canManage)
        <ol class="ds-journey-steps ds-mb-4">
            @foreach ($stepLabels as $key => $label)
                <li class="{{ $step === $key ? 'is-current' : '' }}">
                    <button type="button" class="ds-pill {{ $step === $key ? 'is-selected' : '' }}" wire:click="setStep('{{ $key }}')">
                        {{ $label }}
                    </button>
                </li>
            @endforeach
        </ol>
    @else
        <p class="ds-mb-3"><strong>تعبئة قسم المدير</strong> — فريقك فقط، بلا مجموع نهائي.</p>
    @endif

    {{-- ── Step: template ── --}}
    @if ($canManage && $step === 'template')
        <div class="ds-toolbar-actions ds-mb-3">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openTemplateCreate">
                <i class="fas fa-plus" aria-hidden="true"></i> قالب جديد
            </button>
        </div>
        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <label class="ds-label" for="tpl-search">بحث</label>
                <input id="tpl-search" type="search" class="ds-input" wire:model.live.debounce.400ms="templateSearch" placeholder="ابحث باسم القالب…">
            </div>
        </div>
        <x-ds-table class="ds-has-row-menus">
            <x-slot:head>
                <tr>
                    <th scope="col">الاسم</th>
                    <th scope="col">عدد البنود</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                <tr wire:key="tpl-{{ $template->id }}">
                    <td>{{ $template->name }}</td>
                    <td class="ds-ltr-num">{{ $template->items_count }}</td>
                    <td><x-ds-status-badge :status="$template->is_active ? 'نشط' : 'موقوف'" /></td>
                    <td>
                        <x-ds-row-menu align="end">
                            <button type="button" class="ds-dropdown-item" wire:click="openTemplateEdit({{ $template->id }})">تعديل</button>
                            <button type="button" class="ds-dropdown-item" wire:click="toggleTemplateActive({{ $template->id }})">
                                {{ $template->is_active ? 'إيقاف' : 'تفعيل' }}
                            </button>
                        </x-ds-row-menu>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-ds-empty-state message="لا توجد قوالب تقييم" icon="fa-clipboard-list" /></td></tr>
            @endforelse
        </x-ds-table>
        {{ $templates->links() }}
    @endif

    {{-- ── Step: cycle ── --}}
    @if ($canManage && $step === 'cycle')
        <div class="ds-toolbar-actions ds-mb-3">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openCycleCreate">
                <i class="fas fa-plus" aria-hidden="true"></i> دورة جديدة
            </button>
        </div>
        <p class="ds-text-muted ds-mb-3">عند فتح الدورة تُنسخ بنود القالب كلقطة ثابتة.</p>
        <x-ds-table class="ds-has-row-menus">
            <x-slot:head>
                <tr>
                    <th scope="col">الفترة</th>
                    <th scope="col">القالب</th>
                    <th scope="col">من–إلى</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">بنود</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($cycles as $row)
                <tr wire:key="cycle-open-{{ $row->id }}">
                    <td class="ds-ltr-num">{{ $row->periodLabel() }}</td>
                    <td>{{ $row->template?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $row->starts_at?->toDateString() }} – {{ $row->ends_at?->toDateString() }}</td>
                    <td><x-ds-status-badge :status="$row->status" /></td>
                    <td class="ds-ltr-num">{{ $row->items_count }}</td>
                    <td>
                        <x-ds-row-menu align="end">
                            @if ($row->status === \App\Models\EvaluationCycle::STATUS_DRAFT)
                                <button type="button" class="ds-dropdown-item" wire:click="askConfirm('open_cycle', {{ $row->id }})">فتح الدورة</button>
                            @endif
                            @if ($row->status === \App\Models\EvaluationCycle::STATUS_OPEN)
                                <button type="button" class="ds-dropdown-item" wire:click="setStep('bulk_open')">الخطوة التالية: فتح جماعي</button>
                            @endif
                        </x-ds-row-menu>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ds-empty-state message="لا توجد دورات تقييم" icon="fa-calendar-alt" /></td></tr>
            @endforelse
        </x-ds-table>
        {{ $cycles->links() }}
    @endif

    {{-- ── Step: bulk_open ── --}}
    @if ($canManage && $step === 'bulk_open')
        @if (! $cycle)
            <x-ds-empty-state message="لا توجد دورة مفتوحة. افتح دورة من خطوة «فتح دورة» أولاً." icon="fa-calendar-check" />
        @else
            <p class="ds-mb-3">
                الدورة المفتوحة: <strong>{{ $cycle->periodLabel() }}</strong>
                <x-ds-status-badge :status="$cycle->status" />
            </p>
            @if ($needsBulkOpen)
                <x-ds-empty-state
                    message="الدورة مفتوحة لكن لم يُنفَّذ الفتح الجماعي بعد — لا توجد صفوف تقييم. نفّذ الفتح الجماعي لإنشاء تقييم لكل موظف مؤهل."
                    icon="fa-users"
                />
                <div class="ds-mt-3">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="askConfirm('bulk_open', {{ $cycle->id }})">
                        فتح جماعي للمؤهلين
                    </button>
                </div>
            @else
                <p class="ds-mb-3">تم إنشاء صفوف التقييم. يمكنك إعادة الفتح الجماعي لإضافة مؤهلين جدد إن وُجدوا.</p>
                <button type="button" class="ds-btn ds-btn-secondary" wire:click="askConfirm('bulk_open', {{ $cycle->id }})">إعادة فتح جماعي</button>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="setStep('score')">الانتقال للتعبئة</button>
            @endif
        @endif
    @endif

    {{-- ── Step: score ── --}}
    @if ($step === 'score')
        @if (! $cycle)
            <x-ds-empty-state
                message="لا توجد دورة تقييم مفتوحة.{{ $canManage ? ' أنشئ قالباً وافتح دورة ثم نفّذ الفتح الجماعي من الخطوات أعلاه.' : '' }}"
                icon="fa-calendar-check"
            />
        @elseif ($needsBulkOpen)
            <x-ds-empty-state
                message="الدورة مفتوحة ({{ $cycle->periodLabel() }}) لكن لم يُنفَّذ الفتح الجماعي بعد — لا توجد صفوف تقييم.{{ $canManage ? ' انتقل إلى خطوة «فتح جماعي».' : ' راجع الموارد البشرية.' }}"
                icon="fa-star-half-stroke"
            />
            @if ($canManage)
                <p class="ds-mt-3">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="setStep('bulk_open')">الذهاب لفتح جماعي</button>
                </p>
            @endif
        @else
            <p class="ds-mb-3">
                الدورة الحالية:
                <strong>{{ $cycle->periodLabel() }}</strong>
                — {{ $cycle->starts_at?->toDateString() }} ← {{ $cycle->ends_at?->toDateString() }}
                <x-ds-status-badge :status="$cycle->status" />
            </p>

            <div class="ds-filters-row">
                <div class="ds-filter-field">
                    <label class="ds-label" for="eval-status">الحالة</label>
                    <select id="eval-status" class="ds-input" wire:model.live="statusFilter">
                        <option value="">— الكل —</option>
                        <option value="مسودة">مسودة</option>
                        <option value="قيد_التقييم">قيد التقييم</option>
                        <option value="معتمد">معتمد</option>
                        <option value="مؤرشف">مؤرشف</option>
                    </select>
                </div>
                <div class="ds-filter-field">
                    <label class="ds-label" for="eval-search">الموظف</label>
                    <input id="eval-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم…">
                </div>
            </div>

            <x-ds-table class="ds-has-row-menus">
                <x-slot:head>
                    <tr>
                        <th scope="col">الموظف</th>
                        <th scope="col">المقيّم</th>
                        <th scope="col">قسم المدير</th>
                        @if ($showTotals)
                            <th scope="col">قسم الموارد</th>
                            <th scope="col">المجموع</th>
                        @endif
                        <th scope="col">الحالة</th>
                        <th scope="col">إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($rows as $row)
                    @php
                        $mgrLabel = $service->sectionCompletionLabel($row, 'مدير');
                        $hrLabel = $service->sectionCompletionLabel($row, 'موارد');
                    @endphp
                    <tr wire:key="eval-row-{{ $row->id }}">
                        <td>{{ $row->employee?->name }}</td>
                        <td>{{ $row->evaluator?->name ?? '—' }}</td>
                        <td><x-ds-status-badge :status="$mgrLabel" /></td>
                        @if ($showTotals)
                            <td><x-ds-status-badge :status="$hrLabel" /></td>
                            <td class="ds-ltr-num">{{ $row->total_score !== null ? $row->total_score : '—' }}</td>
                        @endif
                        <td><x-ds-status-badge :status="$row->status" /></td>
                        <td>
                            <x-ds-row-menu align="end">
                                @if ($row->isEditableByScorers() || ($canManage && $row->isApproved()))
                                    <button type="button" class="ds-dropdown-item" wire:click="openScoring({{ $row->id }})">تعبئة / مراجعة</button>
                                @endif
                                <a class="ds-dropdown-item" href="{{ route('users.profile', $row->employee_id) }}?tab=log">الملف الوظيفي</a>
                            </x-ds-row-menu>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showTotals ? 7 : 5 }}">
                            <x-ds-empty-state message="لا تقييمات مطابقة للفلتر." icon="fa-star-half-stroke" />
                        </td>
                    </tr>
                @endforelse
            </x-ds-table>
            {{ $rows->links() }}
        @endif
    @endif

    {{-- ── Step: approve ── --}}
    @if ($canManage && $step === 'approve')
        @if (! $cycle)
            <x-ds-empty-state message="لا توجد دورة مفتوحة للاعتماد." icon="fa-check-double" />
        @elseif ($needsBulkOpen)
            <x-ds-empty-state
                message="الدورة مفتوحة لكن لم يُنفَّذ الفتح الجماعي بعد — لا توجد صفوف تقييم."
                icon="fa-users"
            />
        @else
            <p class="ds-mb-3">
                الدورة: <strong>{{ $cycle->periodLabel() }}</strong>
                — بانتظار الاعتماد: <span class="ds-ltr-num">{{ $pendingApproveCount }}</span>
            </p>
            @if ($pendingApproveCount === 0)
                <p class="ds-text-muted">لا تقييمات معلّقة — إما معتمدة مسبقاً أو لا صفوف.</p>
            @elseif ($allFullyScored)
                <p class="ds-mb-3">اكتملت درجات كل الموظفين لكل البنود. يمكنك الاعتماد الجماعي.</p>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="askConfirm('approve_all', {{ $cycle->id }})">
                    اعتماد جماعي
                </button>
            @else
                <x-ds-empty-state
                    message="لا يمكن الاعتماد الجماعي — لم تكتمل درجات كل الموظفين لكل البنود. أكمِل التعبئة أولاً."
                    icon="fa-exclamation-circle"
                />
                <p class="ds-mt-3">
                    <button type="button" class="ds-btn ds-btn-secondary" wire:click="setStep('score')">العودة للتعبئة</button>
                </p>
            @endif
        @endif
    @endif

    {{-- ── Step: close ── --}}
    @if ($canManage && $step === 'close')
        @if (! $cycle)
            <x-ds-empty-state message="لا توجد دورة مفتوحة لإغلاقها." icon="fa-box-archive" />
        @else
            <p class="ds-mb-3">
                إغلاق <strong>{{ $cycle->periodLabel() }}</strong>:
                التقييمات غير المعتمدة تُعتمد بصفر ثم تُؤرشف الكل.
            </p>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="askConfirm('close_cycle', {{ $cycle->id }})">
                إغلاق الدورة وأرشفة
            </button>
        @endif
    @endif

    {{-- Scoring modal --}}
    <x-ds-modal :show="$scoringId !== null" :title="'تقييم — '.($scoringEvaluation?->employee?->name ?? '')" close-action="closeScoring" size="lg">
        @if ($scoringEvaluation)
            <p class="ds-mb-2">
                {{ $scoringEvaluation->cycle?->periodLabel() }}
                — <x-ds-status-badge :status="$scoringEvaluation->status" />
                @if ($showTotals && $scoringEvaluation->total_score !== null)
                    — المجموع: <strong class="ds-ltr-num">{{ $scoringEvaluation->total_score }}</strong>
                @endif
            </p>
            <p class="ds-text-muted ds-mb-3">
                قسم المدير: <x-ds-status-badge :status="$managerComplete" />
                @if ($showTotals)
                    · قسم الموارد: <x-ds-status-badge :status="$hrComplete" />
                @endif
            </p>

            @if ($canManage)
                <button type="button" class="ds-btn ds-btn-outline ds-mb-3" wire:click="toggleReports">
                    {{ $showReports ? 'إخفاء التقارير المرجعية' : 'عرض تقارير الحضور والمهام (مرجعي)' }}
                </button>
            @endif

            @if ($showReports && $canManage)
                <h3 class="ds-section-title">حضور نافذة الربع (عرض فقط)</h3>
                <x-ds-table>
                    <x-slot:head>
                        <tr><th>التاريخ</th><th>النوع</th><th>تأخر</th></tr>
                    </x-slot:head>
                    @forelse ($attendanceRows as $att)
                        <tr wire:key="att-{{ $att->id }}">
                            <td class="ds-ltr-num">{{ $att->date?->toDateString() }}</td>
                            <td>{{ $att->type }}</td>
                            <td class="ds-ltr-num">{{ $att->late_minutes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ds-text-muted">لا سجلات حضور في النافذة</td></tr>
                    @endforelse
                </x-ds-table>

                <h3 class="ds-section-title ds-mt-3">مهام نافذة الربع (عرض فقط)</h3>
                <x-ds-table>
                    <x-slot:head>
                        <tr><th>المهمة</th><th>الحالة</th><th>الاستحقاق</th></tr>
                    </x-slot:head>
                    @forelse ($taskRows as $task)
                        <tr wire:key="task-ref-{{ $task->id }}">
                            <td>{{ $task->title }}</td>
                            <td>{{ $task->status }}</td>
                            <td class="ds-ltr-num">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ds-text-muted">لا مهام في النافذة</td></tr>
                    @endforelse
                </x-ds-table>
            @endif

            <h3 class="ds-section-title ds-mt-3">بنود المدير{{ $canManage ? ' (يمكن للموارد التعبئة نيابةً)' : '' }}</h3>
            @forelse ($managerItems as $item)
                @php
                    $inp = $scoreInputs[$item->id] ?? ['score' => '', 'note' => ''];
                    $scoreRow = $scoringEvaluation->scores->firstWhere('evaluation_cycle_item_id', $item->id);
                @endphp
                <div class="ds-mb-3" wire:key="mgr-item-{{ $item->id }}">
                    <p>{{ $item->question_text }} <span class="ds-text-muted ds-ltr-num">({{ $item->weight }}%)</span></p>
                    @if ($scoringEvaluation->isEditableByScorers() || ($canManage && $scoringEvaluation->isApproved()))
                        <x-ds-form-group label="الدرجة من 5" :error="$errors->first('scoreInputs.'.$item->id.'.score')">
                            <input type="number" min="1" max="5" class="ds-input ds-ltr-num" wire:model="scoreInputs.{{ $item->id }}.score">
                        </x-ds-form-group>
                        <x-ds-form-group label="ملاحظة">
                            <input type="text" class="ds-input" wire:model="scoreInputs.{{ $item->id }}.note">
                        </x-ds-form-group>
                    @else
                        <p class="ds-ltr-num">{{ $inp['score'] !== '' ? $inp['score'] : '—' }} / 5
                            @if ($inp['note'] !== '') — {{ $inp['note'] }} @endif
                        </p>
                    @endif
                    @if ($scoreRow?->scorer)
                        <p class="ds-text-muted ds-text-sm">أُدخل بواسطة: {{ $scoreRow->scorer->name }}</p>
                    @endif
                </div>
            @empty
                <p class="ds-text-muted">لا بنود مدير في اللقطة</p>
            @endforelse

            @if ($canManage)
                <h3 class="ds-section-title ds-mt-3">بنود الموارد</h3>
                @forelse ($hrItems as $item)
                    @php $scoreRow = $scoringEvaluation->scores->firstWhere('evaluation_cycle_item_id', $item->id); @endphp
                    <div class="ds-mb-3" wire:key="hr-item-{{ $item->id }}">
                        <p>{{ $item->question_text }} <span class="ds-text-muted ds-ltr-num">({{ $item->weight }}%)</span></p>
                        @if ($scoringEvaluation->isEditableByScorers() || $scoringEvaluation->isApproved())
                            <x-ds-form-group label="الدرجة من 5" :error="$errors->first('scoreInputs.'.$item->id.'.score')">
                                <input type="number" min="1" max="5" class="ds-input ds-ltr-num" wire:model="scoreInputs.{{ $item->id }}.score">
                            </x-ds-form-group>
                            <x-ds-form-group label="ملاحظة">
                                <input type="text" class="ds-input" wire:model="scoreInputs.{{ $item->id }}.note">
                            </x-ds-form-group>
                        @else
                            @php $inp = $scoreInputs[$item->id] ?? ['score' => '', 'note' => '']; @endphp
                            <p class="ds-ltr-num">{{ $inp['score'] !== '' ? $inp['score'] : '—' }} / 5
                                @if ($inp['note'] !== '') — {{ $inp['note'] }} @endif
                            </p>
                        @endif
                        @if ($scoreRow?->scorer)
                            <p class="ds-text-muted ds-text-sm">أُدخل بواسطة: {{ $scoreRow->scorer->name }}</p>
                        @endif
                    </div>
                @empty
                    <p class="ds-text-muted">لا بنود موارد في اللقطة</p>
                @endforelse
            @endif

            @if ($canManage && $scoringEvaluation->isApproved())
                <x-ds-form-group label="سبب التعديل (إلزامي بعد الاعتماد)" :error="$errors->first('amendReason')">
                    <textarea class="ds-input" rows="2" wire:model="amendReason" placeholder="اذكر سبب التعديل…"></textarea>
                </x-ds-form-group>
            @endif

            @if ($scoringEvaluation->isEditableByScorers() || ($canManage && $scoringEvaluation->isApproved()))
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveScores">
                    {{ $scoringEvaluation->isApproved() ? 'حفظ التعديل مع السبب' : ($canManage ? 'حفظ الدرجات' : 'حفظ بنود المدير') }}
                </button>
            @endif

            @if ($scoringEvaluation->editLogs->isNotEmpty())
                <h3 class="ds-section-title ds-mt-3">سجل التعديلات بعد الاعتماد</h3>
                <ul class="ds-list">
                    @foreach ($scoringEvaluation->editLogs as $log)
                        <li wire:key="elog-{{ $log->id }}">
                            {{ $log->created_at?->format('Y-m-d H:i') }} —
                            {{ $log->user?->name }}:
                            {{ $log->reason }}
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </x-ds-modal>

    {{-- Template form modal --}}
    <x-ds-modal :show="$showTemplateForm" :title="$editingTemplateId ? 'تعديل قالب تقييم' : 'قالب تقييم جديد'" close-action="$set('showTemplateForm', false)" size="lg">
        <x-ds-form-group label="اسم القالب" :error="$errors->first('templateName')">
            <input type="text" class="ds-input" wire:model="templateName">
        </x-ds-form-group>
        <label class="ds-checkbox ds-mb-3">
            <input type="checkbox" wire:model="templateIsActive">
            <span>نشط</span>
        </label>
        <p class="ds-text-muted ds-mb-2">
            مجموع الأوزان الحالي: <strong class="ds-ltr-num">{{ $weightsTotal }}</strong> / 100
            @if ($weightsTotal !== 100)
                <span class="ds-text-danger">— يجب أن يساوي 100 عند الحفظ</span>
            @endif
        </p>
        @error('templateItems') <p class="ds-field-error">{{ $message }}</p> @enderror
        <div class="ds-table-wrap ds-mb-3">
            <table class="ds-table">
                <thead>
                    <tr><th>القسم</th><th>نص السؤال</th><th>الوزن</th><th>الترتيب</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($templateItems as $index => $row)
                        <tr wire:key="tpl-item-{{ $index }}">
                            <td>
                                <select class="ds-input" wire:model="templateItems.{{ $index }}.section">
                                    @foreach ($sections as $section)
                                        <option value="{{ $section }}">{{ $section }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" class="ds-input" wire:model="templateItems.{{ $index }}.question_text">
                            </td>
                            <td>
                                <input type="number" class="ds-input ds-ltr-num" wire:model.live="templateItems.{{ $index }}.weight" min="1" max="100">
                            </td>
                            <td>
                                <input type="number" class="ds-input ds-ltr-num" wire:model="templateItems.{{ $index }}.sort_order" min="1" max="100">
                            </td>
                            <td>
                                <button type="button" class="ds-btn ds-btn-ghost" wire:click="removeTemplateItemRow({{ $index }})" @disabled(count($templateItems) <= 1)>حذف</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="ds-btn ds-btn-secondary ds-mb-3" wire:click="addTemplateItemRow">إضافة بند</button>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveTemplate">حفظ القالب</button>
    </x-ds-modal>

    {{-- Cycle create modal --}}
    <x-ds-modal :show="$showCycleForm" title="إنشاء دورة تقييم ربعية" close-action="$set('showCycleForm', false)" size="md">
        <x-ds-form-group label="السنة" :error="$errors->first('cycleYear')">
            <input type="number" class="ds-input ds-ltr-num" wire:model.live="cycleYear" min="2020" max="2100">
        </x-ds-form-group>
        <x-ds-form-group label="الربع" :error="$errors->first('cycleQuarter')">
            <select class="ds-input" wire:model.live="cycleQuarter">
                <option value="1">الربع الأول</option>
                <option value="2">الربع الثاني</option>
                <option value="3">الربع الثالث</option>
                <option value="4">الربع الرابع</option>
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="قالب التقييم" :error="$errors->first('cycleTemplateId')">
            <select class="ds-input" wire:model="cycleTemplateId">
                <option value="">— اختر قالباً —</option>
                @foreach ($activeTemplates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="من تاريخ" :error="$errors->first('cycleStartsAt')">
            <input type="date" class="ds-input ds-ltr-num" wire:model="cycleStartsAt">
        </x-ds-form-group>
        <x-ds-form-group label="إلى تاريخ" :error="$errors->first('cycleEndsAt')">
            <input type="date" class="ds-input ds-ltr-num" wire:model="cycleEndsAt">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="createCycle">حفظ كمسودة</button>
    </x-ds-modal>

    {{-- DS confirm modal (no wire:confirm) --}}
    <x-ds-modal :show="$confirmAction !== null" :title="$confirmTitle" close-action="cancelConfirm" size="md">
        <p class="ds-mb-3">{{ $confirmBody }}</p>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="executeConfirm">تأكيد</button>
        </div>
    </x-ds-modal>
</x-ds-page>
