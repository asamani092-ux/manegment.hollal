<x-ds-page>
    <x-ds-page-header title="التقييم الربعي" :show-button="false">
        <x-slot:actions>
            <a href="{{ route('evaluation-cycles.index') }}" class="ds-btn ds-btn-secondary">دورات التقييم</a>
            <a href="{{ route('evaluation-templates.index') }}" class="ds-btn ds-btn-secondary">قوالب التقييم</a>
            @if ($canManage && $cycle)
                <button
                    type="button"
                    class="ds-btn ds-btn-outline"
                    wire:click="closeCycle({{ $cycle->id }})"
                    wire:confirm="إغلاق الدورة؟ التقييمات غير المعتمدة تُعتمد بصفر ثم تُؤرشف الكل."
                >
                    إغلاق الدورة وأرشفة
                </button>
            @endif
        </x-slot:actions>
    </x-ds-page-header>

    <p class="ds-text-muted ds-mb-3">
        دورة التقييم: مسودة ← اعتماد ← أرشفة (عند إغلاق الربع). لا توجد حالة «نشر».
        لوحة الموارد للدورة المفتوحة الحالية — روابط القوالب والدورات أعلاه.
    </p>

    @if (! $cycle)
        <x-ds-empty-state
            message="لا توجد دورة تقييم مفتوحة. أنشئ دورة وافتحها من «دورات التقييم» ثم نفّذ الفتح الجماعي."
            icon="fa-calendar-check"
        />
        <p class="ds-mt-3">
            <a class="ds-link" href="{{ route('evaluation-cycles.index') }}">الذهاب إلى دورات التقييم</a>
        </p>
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

        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th scope="col">الموظف</th>
                    <th scope="col">المقيّم</th>
                    <th scope="col">قسم المدير</th>
                    <th scope="col">قسم الموارد</th>
                    <th scope="col">المجموع</th>
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
                    <td><x-ds-status-badge :status="$hrLabel" /></td>
                    <td class="ds-ltr-num">{{ $row->total_score !== null ? $row->total_score : '—' }}</td>
                    <td><x-ds-status-badge :status="$row->status" /></td>
                    <td>
                        <x-ds-row-menu align="end">
                            @if ($canManage)
                                <button type="button" class="ds-dropdown-item" wire:click="openScoring({{ $row->id }})">تعبئة / مراجعة</button>
                                @if ($row->isEditableByScorers())
                                    <button type="button" class="ds-dropdown-item" wire:click="approve({{ $row->id }})" wire:confirm="اعتماد التقييم وإظهاره للموظف؟">اعتماد</button>
                                @endif
                            @endif
                            <a class="ds-dropdown-item" href="{{ route('users.profile', $row->employee_id) }}?tab=evaluations">الملف الوظيفي</a>
                        </x-ds-row-menu>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-ds-empty-state message="لا تقييمات في هذه الدورة — نفّذ الفتح الجماعي من دورات التقييم." icon="fa-star-half-stroke" />
                    </td>
                </tr>
            @endforelse
        </x-ds-table>
        {{ $rows->links() }}
    @endif

    <x-ds-modal :show="$scoringId !== null" :title="'تقييم — '.($scoringEvaluation?->employee?->name ?? '')" close-action="closeScoring" size="lg">
        @if ($scoringEvaluation)
            <p class="ds-mb-2">
                {{ $scoringEvaluation->cycle?->periodLabel() }}
                — <x-ds-status-badge :status="$scoringEvaluation->status" />
                @if ($scoringEvaluation->total_score !== null)
                    — المجموع: <strong class="ds-ltr-num">{{ $scoringEvaluation->total_score }}</strong>
                @endif
            </p>
            <p class="ds-text-muted ds-mb-3">
                قسم المدير: <x-ds-status-badge :status="$managerComplete" />
                · قسم الموارد: <x-ds-status-badge :status="$hrComplete" />
            </p>

            <button type="button" class="ds-btn ds-btn-outline ds-mb-3" wire:click="toggleReports">
                {{ $showReports ? 'إخفاء التقارير المرجعية' : 'عرض تقارير الحضور والمهام (مرجعي)' }}
            </button>

            @if ($showReports)
                <h3 class="ds-section-title">حضور نافذة الربع (عرض فقط)</h3>
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>تأخر</th>
                        </tr>
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
                        <tr>
                            <th>المهمة</th>
                            <th>الحالة</th>
                            <th>الاستحقاق</th>
                        </tr>
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

            <h3 class="ds-section-title ds-mt-3">بنود المدير (قراءة)</h3>
            @forelse ($managerItems as $item)
                @php $inp = $scoreInputs[$item->id] ?? ['score' => '', 'note' => '']; @endphp
                <div class="ds-mb-2" wire:key="mgr-ro-{{ $item->id }}">
                    <p>{{ $item->question_text }} <span class="ds-text-muted ds-ltr-num">({{ $item->weight }}%)</span></p>
                    <p class="ds-ltr-num">{{ $inp['score'] !== '' ? $inp['score'] : '—' }} / 5
                        @if ($inp['note'] !== '') — {{ $inp['note'] }} @endif
                    </p>
                </div>
            @empty
                <p class="ds-text-muted">لا بنود مدير في اللقطة</p>
            @endforelse

            <h3 class="ds-section-title ds-mt-3">بنود الموارد</h3>
            @forelse ($hrItems as $item)
                <div class="ds-mb-3" wire:key="hr-item-{{ $item->id }}">
                    <p>{{ $item->question_text }} <span class="ds-text-muted ds-ltr-num">({{ $item->weight }}%)</span></p>
                    @if ($canManage && ($scoringEvaluation->isEditableByScorers() || $scoringEvaluation->isApproved()))
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
                </div>
            @empty
                <p class="ds-text-muted">لا بنود موارد في اللقطة</p>
            @endforelse

            @if ($canManage && $scoringEvaluation->isApproved())
                <x-ds-form-group label="سبب التعديل (إلزامي بعد الاعتماد)" :error="$errors->first('amendReason')">
                    <textarea class="ds-input" rows="2" wire:model="amendReason" placeholder="اذكر سبب التعديل…"></textarea>
                </x-ds-form-group>
            @endif

            @if ($canManage && ($scoringEvaluation->isEditableByScorers() || $scoringEvaluation->isApproved()))
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveHrScores">
                    {{ $scoringEvaluation->isApproved() ? 'حفظ التعديل مع السبب' : 'حفظ درجات الموارد' }}
                </button>
            @endif

            @if ($canManage && $scoringEvaluation->isEditableByScorers())
                <button type="button" class="ds-btn ds-btn-outline" wire:click="approve({{ $scoringEvaluation->id }})" wire:confirm="اعتماد وإظهار للموظف؟">اعتماد</button>
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
</x-ds-page>
