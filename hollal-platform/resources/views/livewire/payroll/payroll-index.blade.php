<x-ds-page>
    @php
        $transferLabels = ['pending' => 'معلق', 'transferred' => 'محوّل', 'failed' => 'فشل'];
    @endphp

    <x-ds-page-header
        title="الرواتب"
        :show-button="auth()->user()->can('hr.salaries.manage')"
        button-label="راتب جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="search" placeholder="اسم الموظف...">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label">الشهر</label>
            <input type="month" class="ds-input" wire:model.live="monthFilter">
        </div>
    </div>

    <div class="ds-table-wrap">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الموظف</th>
                    <th>الشهر</th>
                    <th>الأساسي</th>
                    <th>الإضافات</th>
                    <th>الخصومات</th>
                    <th>الصافي</th>
                    <th>حالة التحويل</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($payrolls as $payroll)
                <tr wire:key="payroll-{{ $payroll->id }}">
                    <td>{{ $payroll->employee?->name ?? '—' }}</td>
                    <td>{{ $payroll->month?->format('Y-m') }}</td>
                    <td>{{ number_format((float) $payroll->base, 2) }}</td>
                    <td>{{ number_format((float) $payroll->additions, 2) }}</td>
                    <td>{{ number_format((float) $payroll->deductions, 2) }}</td>
                    <td>{{ number_format((float) $payroll->net, 2) }}</td>
                    <td>{{ $transferLabels[$payroll->transfer_status] ?? $payroll->transfer_status }}</td>
                    <td>
                        <x-ds-action-icons
                            :show-view="auth()->user()->can('hr.salaries.view')"
                            :show-edit="auth()->user()->can('hr.salaries.manage')"
                            :show-delete="auth()->user()->can('hr.salaries.manage')"
                            :view-action="'openView('.$payroll->id.')'"
                            :edit-action="'openEdit('.$payroll->id.')'"
                            :delete-action="'delete('.$payroll->id.')'"
                            delete-confirm="حذف هذا الراتب؟"
                        />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="ds-text-muted ds-table-empty">لا توجد رواتب</td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $payrolls->links() }}

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="closeModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($viewOnly)
                            عرض راتب
                        @elseif ($payrollId)
                            تعديل راتب
                        @else
                            راتب جديد
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-grid-2">
                        <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
                            <select class="ds-input" wire:model="employee_id" @disabled($viewOnly)>
                                <option value="">— اختر —</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="الشهر" :error="$errors->first('month')">
                            <input type="month" class="ds-input" wire:model="month" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الراتب الأساسي" :error="$errors->first('base')">
                            <input type="number" step="0.01" class="ds-input" wire:model="base" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الإضافات" :error="$errors->first('additions')">
                            <input type="number" step="0.01" class="ds-input" wire:model="additions" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الخصومات" :error="$errors->first('deductions')">
                            <input type="number" step="0.01" class="ds-input" wire:model="deductions" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="حالة التحويل" :error="$errors->first('transfer_status')">
                            <select class="ds-input" wire:model="transfer_status" @disabled($viewOnly)>
                                @foreach ($transferLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    </div>
                    @if ($viewOnly && $payrollId)
                        <p class="ds-text-muted">
                            الصافي: {{ number_format((float) \App\Models\Payroll::computeNet($base, $additions, $deductions), 2) }}
                        </p>
                    @endif
                </div>
                <div class="ds-modal-footer">
                    @if (! $viewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="save">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>