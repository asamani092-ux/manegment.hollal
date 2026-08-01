<x-ds-page>
    <x-ds-page-header
        title="التقييم الدوري"
        :show-button="$canManage"
        button-label="تقييم جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

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
                    @if ($canManage && $evaluation->status === \App\Models\PeriodicEvaluation::STATUS_DRAFT)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="publish({{ $evaluation->id }})">نشر</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا توجد تقييمات" icon="fa-star-half-stroke" /></td></tr>
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
</x-ds-page>
