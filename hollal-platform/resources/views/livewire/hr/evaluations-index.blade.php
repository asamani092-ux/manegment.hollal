<x-ds-page>
    <x-ds-page-header
        title="التقييم الدوري"
        :show-button="$canManage"
        button-label="تقييم جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <p class="ds-text-muted ds-mb-3">
        مسودة = للمقيّم/الموارد فقط · <strong>نشر</strong> = يظهر للموظف ليطّلع ويعلّق (خلال المهلة) · استخدم «معاينة قبل النشر» قبل الظهور للموظف.
        الإنشاء والتقييم يتطلب صلاحية التحديث.
    </p>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="eval-status">الحالة</label>
            <select id="eval-status" class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                <option value="مسودة">مسودة</option>
                <option value="منشور">منشور</option>
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="eval-period">الفترة</label>
            <select id="eval-period" class="ds-input" wire:model.live="periodFilter">
                <option value="">— الكل —</option>
                @foreach ($periods as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
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
                <th scope="col">الفترة</th>
                <th scope="col">المقيّم</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($evaluations as $evaluation)
            <tr wire:key="eval-{{ $evaluation->id }}">
                <td>{{ $evaluation->employee?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $evaluation->period }}</td>
                <td>{{ $evaluation->evaluator?->name ?? '—' }}</td>
                <td><x-ds-status-badge :status="$evaluation->status" /></td>
                <td>
                    <button type="button" class="ds-link" wire:click="openScoring({{ $evaluation->id }})">الدرجات</button>
                    @if ($canManage && $evaluation->status === \App\Models\PeriodicEvaluation::STATUS_DRAFT)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openPreview({{ $evaluation->id }})">معاينة قبل النشر</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ds-empty-state
                        message="لا توجد تقييمات بعد. أنشئ تقييمًا جديدًا بعد تعريف مسؤوليات الموظف من شاشة المسؤوليات؛ بدون مسؤوليات لن تظهر بنود للدرجات."
                        icon="fa-star-half-stroke"
                    />
                </td>
            </tr>
        @endforelse
    </x-ds-table>
    {{ $evaluations->links() }}

    <x-ds-modal :show="$showCreate" title="تقييم جديد" close-action="$set('showCreate', false)">
        <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
            <select class="ds-input" wire:model="employee_id">
                <option value="">—</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="الفترة" :error="$errors->first('period')">
            <input type="text" class="ds-input ds-ltr-num" wire:model="period" placeholder="2026-Q3">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="createEvaluation">حفظ</button>
    </x-ds-modal>

    <x-ds-modal :show="$previewId !== null" title="معاينة التقييم قبل النشر" close-action="closePreview">
        @if ($previewEvaluation)
            <p class="ds-text-muted ds-mb-3">
                بعد التأكيد سيظهر هذا التقييم للموظف المعني فقط (وليس للموارد البشرية وحدهم). الموظف يستطيع التعليق ضمن المهلة المحددة في الإعدادات.
            </p>
            <p><strong>الموظف:</strong> {{ $previewEvaluation->employee?->name ?? '—' }}</p>
            <p><strong>الفترة:</strong> <span class="ds-ltr-num">{{ $previewEvaluation->period }}</span></p>
            <p><strong>المقيّم:</strong> {{ $previewEvaluation->evaluator?->name ?? '—' }}</p>
            @if ($previewEvaluation->scores->isNotEmpty())
                <ul class="ds-mb-3">
                    @foreach ($previewEvaluation->scores as $score)
                        <li>
                            {{ $score->responsibility?->body ?? ('بند #'.$score->responsibility_id) }}
                            — <span class="ds-ltr-num">{{ $score->score }} / 5</span>
                            @if ($score->note) — {{ $score->note }} @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="ds-text-muted">لا توجد درجات مسجّلة بعد.</p>
            @endif
            <div class="ds-toolbar-actions">
                <button type="button" class="ds-btn ds-btn-outline" wire:click="closePreview">إلغاء</button>
                <button
                    type="button"
                    class="ds-btn ds-btn-primary"
                    wire:click="publish({{ $previewId }})"
                    wire:confirm="تأكيد النشر؟ سيظهر التقييم للموظف فوراً."
                >تأكيد النشر للموظف</button>
            </div>
        @endif
    </x-ds-modal>

    <x-ds-modal :show="$scoringId !== null" title="درجات المسؤوليات /5" close-action="$set('scoringId', null)" size="lg">
        @if ($scoringEvaluation)
            <p>{{ $scoringEvaluation->employee?->name }} — {{ $scoringEvaluation->period }} — {{ $scoringEvaluation->status }}</p>
            @forelse ($scoringResponsibilities as $item)
                <div class="ds-mb-3" wire:key="score-{{ $item->id }}">
                    <p>{{ $item->order }}. {{ $item->body }}</p>
                    @if ($scoringEvaluation->status === \App\Models\PeriodicEvaluation::STATUS_DRAFT && ($canManage || $scoringEvaluation->evaluator_id === auth()->id()))
                        <x-ds-form-group label="الدرجة من 5" :error="$errors->first('scoreInputs.'.$item->id.'.score')">
                            <input type="number" min="1" max="5" class="ds-input ds-ltr-num" wire:model="scoreInputs.{{ $item->id }}.score">
                        </x-ds-form-group>
                        <x-ds-form-group label="ملاحظة">
                            <input type="text" class="ds-input" wire:model="scoreInputs.{{ $item->id }}.note">
                        </x-ds-form-group>
                    @else
                        <p class="ds-ltr-num">{{ $scoreInputs[$item->id]['score'] ?? '—' }} / 5
                            @if (! empty($scoreInputs[$item->id]['note'])) — {{ $scoreInputs[$item->id]['note'] }} @endif
                        </p>
                    @endif
                </div>
            @empty
                <p class="ds-text-muted">
                    لا توجد مسؤوليات نشطة لهذا الموظف. أضف المسؤوليات من شاشة «المسؤوليات» أولًا، ثم أعد فتح الدرجات لتسجيل التقييم.
                </p>
            @endforelse
            @if ($scoringEvaluation->status === \App\Models\PeriodicEvaluation::STATUS_DRAFT && ($canManage || $scoringEvaluation->evaluator_id === auth()->id()))
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveScores">حفظ الدرجات</button>
            @endif
            @if ($scoringEvaluation->isPublished() && $scoringEvaluation->employee_id === auth()->id())
                <x-ds-form-group label="تعليق الموظف" :error="$errors->first('employeeComment')">
                    <textarea class="ds-input" wire:model="employeeComment" rows="3"></textarea>
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveComment">إرسال التعليق</button>
            @elseif ($scoringEvaluation->employee_comment)
                <p>تعليق الموظف: {{ $scoringEvaluation->employee_comment }}</p>
            @endif
        @endif
    </x-ds-modal>
</x-ds-page>
