<div>
    <x-ds-page-header title="سلاسل الطلبات" screen="settings.approval-chains" :show-button="false" />

    <p class="ds-text-muted" style="max-width:42rem">
        لكل نوع طلب سلسلة واحدة تُقرأ من الأعلى. مثال: صرف بـ 500 ريال يقف عند المدير المباشر، وصرف بـ 15,000 ريال يكمل لمن تجاوز شرطه.
    </p>

    <div style="display:grid;grid-template-columns:minmax(14rem,18rem) minmax(0,1fr);gap:1rem;align-items:start">
        <aside class="ds-card" style="padding:0.75rem">
            @foreach ($typeLabels as $key => $label)
                @php
                    $chain = $chains->get($key);
                    $count = $chain?->steps?->count() ?? 0;
                    $warn = $chain?->steps?->contains(fn ($step) => $step->isUnresolved()) ?? false;
                @endphp
                <button type="button" class="ds-btn ds-btn-sm {{ $selectedType === $key ? 'ds-btn-primary' : 'ds-btn-outline' }}" style="width:100%;margin-bottom:0.4rem" wire:click="selectType('{{ $key }}')">
                    {{ $label }} — {{ $count }}
                </button>
                @if ($warn)
                    <p class="ds-badge ds-badge-warning" style="margin:-0.2rem 0 0.4rem">خطوة بلا معتمد</p>
                @endif
            @endforeach
        </aside>

        <section>
            <div class="ds-card" style="padding:0.75rem;margin-bottom:0.75rem">
                <strong>المعاينة</strong>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin:0.5rem 0">
                    <label>مقدّم الطلب
                        <select class="ds-input" wire:model.live="previewRequesterId">
                            <option value="">—</option>
                            @foreach ($allEmployees as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($valueLabel !== '')
                        <label>{{ $valueLabel }}
                            <input class="ds-input" type="number" wire:model.live="previewValue" min="0">
                        </label>
                    @endif
                </div>
                <p>{{ $previewLine }}</p>
            </div>

            @foreach ($steps as $index => $step)
                @php
                    $n = $index + 1;
                    $selectedIds = ($step['approver_type'] ?? '') === 'user'
                        ? array_values(array_filter([(int) ($step['user_id'] ?? 0)]))
                        : array_map('intval', $step['user_ids'] ?? []);
                    $selectedNames = collect($selectedIds)
                        ->map(fn (int $id) => optional($allEmployees->firstWhere('id', $id))->name)
                        ->filter()
                        ->values();
                    $who = match ($step['approver_type']) {
                        'direct_manager' => 'المدير المباشر',
                        'department_head' => 'رئيس القسم',
                        'user' => $selectedNames->first() ?: 'موظف غير محدد',
                        default => $selectedNames->isNotEmpty() ? $selectedNames->implode(' أو ') : 'خطوة بلا معتمد',
                    };
                    $formattedLimit = number_format((float) ($step['condition_value'] ?: 0), 0);
                    $cond = match ($step['condition_operator'] ?? '') {
                        'gt' => 'إذا تجاوز '.$valueLabel.' '.$formattedLimit.' '.$unit,
                        'gte' => 'إذا بلغ '.$valueLabel.' '.$formattedLimit.' '.$unit,
                        'lt' => 'إذا قل '.$valueLabel.' عن '.$formattedLimit.' '.$unit,
                        default => 'دائمًا',
                    };
                @endphp
                <article class="ds-card" style="padding:0.75rem;margin-bottom:0.6rem" draggable="true"
                    ondragstart="event.dataTransfer.setData('text/plain', '{{ $index }}')"
                    ondragover="event.preventDefault()"
                    ondrop="event.preventDefault(); $wire.moveStep(parseInt(event.dataTransfer.getData('text/plain'), 10), {{ $index }})">
                    <header style="display:flex;justify-content:space-between;gap:0.5rem;align-items:center">
                        <strong>{{ $who }} — {{ $cond }}</strong>
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="removeStep({{ $index }})">حذف</button>
                    </header>
                    <p class="ds-text-muted" style="margin:0.35rem 0">الخطوة {{ $n }} — اسحب البطاقة لإعادة الترتيب</p>
                    <div>
                        <span class="ds-text-muted">بديل لوحة المفاتيح</span>
                        <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="moveStep({{ $index }}, {{ max(0, $index - 1) }})">أعلى</button>
                        <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="moveStep({{ $index }}, {{ min(count($steps) - 1, $index + 1) }})">أسفل</button>
                    </div>
                    <div style="display:grid;gap:0.4rem;margin-top:0.6rem">
                        <label>المعتمد
                            <select class="ds-input" wire:model.live="steps.{{ $index }}.approver_type">
                                <option value="direct_manager">المدير المباشر</option>
                                <option value="department_head">رئيس القسم</option>
                                <option value="user">موظف بالاسم</option>
                                <option value="any_of_users">عدة موظفين محددين</option>
                            </select>
                        </label>
                        @if (($step['approver_type'] ?? '') === 'user' || ($step['approver_type'] ?? '') === 'any_of_users')
                            <div style="display:flex;flex-wrap:wrap;gap:0.3rem">
                                @foreach ($selectedIds as $sid)
                                    @php $person = $allEmployees->firstWhere('id', (int) $sid); @endphp
                                    @if ($person)
                                        <span style="display:inline-flex;align-items:center;gap:0.35rem;background:#0f4c5c;color:#fff;border-radius:999px;padding:0.15rem 0.55rem">
                                            {{ $person->name }}
                                            <button type="button" style="background:transparent;color:#fff;border:0;cursor:pointer" wire:click="removeEmployee({{ $index }}, {{ $person->id }})">إزالة</button>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                            <label>بحث بالاسم
                                <input class="ds-input" wire:model.live="picker" placeholder="اسم الموظف">
                            </label>
                            @if ($picker !== '')
                                <div style="display:flex;flex-wrap:wrap;gap:0.3rem">
                                    @foreach ($employees as $person)
                                        @if (! in_array($person->id, $selectedIds, true))
                                            @if (($step['approver_type'] ?? '') === 'user')
                                                <button type="button" class="ds-btn ds-btn-sm" wire:click="chooseEmployee({{ $index }}, {{ $person->id }})">{{ $person->name }}</button>
                                            @else
                                                <button type="button" class="ds-btn ds-btn-sm" wire:click="addEmployee({{ $index }}, {{ $person->id }})">{{ $person->name }}</button>
                                            @endif
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        <label>الشرط
                            <select class="ds-input" wire:model.live="steps.{{ $index }}.condition_operator">
                                <option value="">دائمًا</option>
                                <option value="gt">أكبر من</option>
                                <option value="gte">أكبر أو يساوي</option>
                                <option value="lt">أقل من</option>
                            </select>
                        </label>
                        @if (($step['condition_operator'] ?? '') !== '')
                            <label>الحد {{ $unit }}
                                <input class="ds-input" type="text" inputmode="numeric"
                                    value="{{ ($step['condition_value'] ?? '') !== '' ? number_format((float) $step['condition_value'], 0) : '' }}"
                                    wire:change="setThreshold({{ $index }}, $event.target.value)">
                            </label>
                        @endif
                        <label>إذا تعذّر تحديد المعتمد
                            <select class="ds-input" wire:model.live="steps.{{ $index }}.on_unresolved">
                                <option value="skip">تخطّي الخطوة</option>
                                <option value="block">إيقاف الطلب</option>
                            </select>
                        </label>
                    </div>
                </article>
            @endforeach

            <button type="button" class="ds-btn" wire:click="addStep">إضافة خطوة</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ السلسلة</button>
        </section>
    </div>
    <style>
        @media (max-width: 800px) {
            div[style*="grid-template-columns:minmax(14rem,18rem)"] { grid-template-columns: 1fr !important; }
        }
    </style>
</div>
