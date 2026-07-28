<x-ds-page>
    <x-ds-page-header
        title="التقييم الدوري"
        :show-button="$canManage"
        button-label="تقييم جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>الفترة</th>
                <th>المقيّم</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($evaluations as $evaluation)
            <tr wire:key="eval-{{ $evaluation->id }}">
                <td>{{ $evaluation->employee?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $evaluation->period }}</td>
                <td>{{ $evaluation->evaluator?->name ?? '—' }}</td>
                <td><span class="ds-badge">{{ $evaluation->status }}</span></td>
                <td>
                    @if ($canManage && $evaluation->status === \App\Models\PeriodicEvaluation::STATUS_DRAFT)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="publish({{ $evaluation->id }})">نشر</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="ds-table-empty">لا توجد تقييمات</td></tr>
        @endforelse
    </x-ds-table>
    {{ $evaluations->links() }}

    @if ($showCreate)
        <div class="ds-modal-overlay" wire:click.self="$set('showCreate', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تقييم جديد</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showCreate', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
                        <select class="ds-input" wire:model="employee_id">
                            <option value="">—</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="الفترة" :error="$errors->first('period')">
                        <input type="text" class="ds-input" wire:model="period">
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="createEvaluation">حفظ</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
