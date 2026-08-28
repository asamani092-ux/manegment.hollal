<x-ds-page>
    <x-ds-page-header
        title="التقييم الدوري"
        :show-button="$canManage"
        button-label="تقييم جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    >
        @if ($canManage)
            <x-slot:actions>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="openBulkCreate">
                    <i class="fas fa-users" aria-hidden="true"></i> تقييم جماعي
                </button>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="openCreate">
                    <i class="fas fa-plus" aria-hidden="true"></i> تقييم جديد
                </button>
            </x-slot:actions>
        @endif
    </x-ds-page-header>

    <p class="ds-text-muted ds-mb-3">
        أداة الموارد البشرية: مسودة → درجات → <strong>أرشفة</strong> لظهور التقييم في سجل الملف الوظيفي (بعد اكتمال الدرجات).
    </p>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="eval-status">الحالة</label>
            <select id="eval-status" class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                <option value="مسودة">مسودة</option>
                <option value="منشور">منشور</option>
                <option value="مؤرشف">مؤرشف</option>
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
                <th scope="col">آخر فترة</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($employeeRows as $employee)
            @php $latest = $latestEvaluations->get($employee->id); @endphp
            <tr wire:key="eval-emp-{{ $employee->id }}">
                <td>{{ $employee->name }}</td>
                <td class="ds-ltr-num">{{ $latest?->period ?? '—' }}</td>
                <td>
                    @if ($latest)
                        <x-ds-status-badge :status="$latest->status" />
                    @else
                        —
                    @endif
                </td>
                <td>
                    <x-ds-row-menu align="end">
                        <button type="button" class="ds-dropdown-item" wire:click="openEmployeeEvaluations({{ $employee->id }})">عرض جميع التقييمات</button>
                        @if ($canManage)
                            <button type="button" class="ds-dropdown-item" wire:click="openLatestScoring({{ $employee->id }})">تقييم الفترة الجديدة</button>
                        @endif
                        <a class="ds-dropdown-item" href="{{ route('users.profile', $employee->id) }}?tab=evaluations">الملف الوظيفي</a>
                        @if ($canManage && $latest && $latest->status !== \App\Models\PeriodicEvaluation::STATUS_ARCHIVED)
                            <button type="button" class="ds-dropdown-item" wire:click="archiveForEmployee({{ $employee->id }})" wire:confirm="أرشفة آخر تقييم لهذا الموظف؟">أرشفة</button>
                        @endif
                    </x-ds-row-menu>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">
                    <x-ds-empty-state
                        message="لا توجد تقييمات بعد. أنشئ تقييمًا جديدًا بعد تعريف مسؤوليات الموظف."
                        icon="fa-star-half-stroke"
                    />
                </td>
            </tr>
        @endforelse
    </x-ds-table>
    {{ $employeeRows->links() }}

    <x-ds-modal :show="$showCreate" title="تقييم جديد" close-action="$set('showCreate', false)">
        <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
            <x-ds-search-select
                :options="$employeeOptions"
                wire-model="employee_id"
                value-key="id"
                label-key="label"
                placeholder="ابحث عن الموظف…"
            />
        </x-ds-form-group>
        <x-ds-form-group label="الفترة" :error="$errors->first('period')">
            <input type="text" class="ds-input ds-ltr-num" wire:model="period" placeholder="2026-Q3">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="createEvaluation">حفظ</button>
    </x-ds-modal>

    <x-ds-modal :show="$showBulkCreate" title="تقييم جماعي" close-action="$set('showBulkCreate', false)" size="lg">
        <p class="ds-text-muted ds-mb-3">يُنشأ تقييم لكل موظف مختار لنفس الفترة، مع بنود مشتركة.</p>
        <x-ds-form-group label="الفترة" :error="$errors->first('period')">
            <input type="text" class="ds-input ds-ltr-num" wire:model="period" placeholder="2026-Q3">
        </x-ds-form-group>
        <x-ds-form-group label="الموظفون" :error="$errors->first('bulkEmployeeIds')">
            <div class="ds-checkbox-list" style="max-height:12rem;overflow:auto">
                @foreach ($employees as $employee)
                    <label class="ds-checkbox-label" wire:key="bulk-emp-{{ $employee->id }}">
                        <input type="checkbox" value="{{ $employee->id }}" wire:model="bulkEmployeeIds">
                        <span>{{ $employee->name }}</span>
                    </label>
                @endforeach
            </div>
        </x-ds-form-group>
        <x-ds-form-group label="البنود المشتركة (سطر لكل بند)" :error="$errors->first('bulkCriteria')">
            <textarea class="ds-input" rows="6" wire:model="bulkCriteria"></textarea>
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="createBulkEvaluations">إنشاء التقييمات</button>
    </x-ds-modal>

    <x-ds-modal :show="$listEmployeeId !== null" :title="'تقييمات — '.($listEmployee?->name ?? '')" close-action="closeEmployeeEvaluations" size="lg">
        @if ($listEmployee)
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الفترة</th>
                        <th>المقيّم</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($employeeEvaluations as $evaluation)
                    <tr wire:key="emp-eval-{{ $evaluation->id }}">
                        <td class="ds-ltr-num">{{ $evaluation->period }}</td>
                        <td>{{ $evaluation->evaluator?->name ?? '—' }}</td>
                        <td><x-ds-status-badge :status="$evaluation->status" /></td>
                        <td>
                            @if ($evaluation->status === \App\Models\PeriodicEvaluation::STATUS_DRAFT)
                                <button type="button" class="ds-link" wire:click="openScoring({{ $evaluation->id }})">الدرجات</button>
                            @endif
                            @if ($canManage && $evaluation->status !== \App\Models\PeriodicEvaluation::STATUS_ARCHIVED)
                                <button type="button" class="ds-link" wire:click="archive({{ $evaluation->id }})" wire:confirm="أرشفة هذا التقييم؟">أرشفة</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ds-text-muted">لا توجد تقييمات</td></tr>
                @endforelse
            </x-ds-table>
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
                <p class="ds-text-muted">لا توجد مسؤوليات نشطة — عرّفها من شاشة المسؤوليات.</p>
            @endforelse
            @if ($scoringEvaluation->status === \App\Models\PeriodicEvaluation::STATUS_DRAFT && ($canManage || $scoringEvaluation->evaluator_id === auth()->id()))
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveScores">حفظ الدرجات</button>
            @endif
        @endif
    </x-ds-modal>
</x-ds-page>
