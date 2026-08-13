<x-ds-page>
    <x-ds-page-header title="مسيّرات الرواتب" />

    @can('hr.salaries.manage')
        <section class="ds-section ds-filter-bar">
            <input type="month" class="ds-input" wire:model="month" dir="ltr" aria-label="شهر المسيّر">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="generate">
                <i class="fas fa-gears" aria-hidden="true"></i> توليد مسيّر الشهر
            </button>
            @error('month') <small class="ds-error">{{ $message }}</small> @enderror
        </section>
    @endcan

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="run-status">الحالة</label>
            <select id="run-status" class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="run-month">الشهر</label>
            <input id="run-month" type="month" class="ds-input" wire:model.live="monthFilter" dir="ltr">
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الشهر</th>
                <th scope="col">عدد الموظفين</th>
                <th scope="col">إجمالي الصافي</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($runs as $run)
            <tr wire:key="run-{{ $run->id }}">
                <td dir="ltr" class="ds-ltr-num">{{ $run->month }}</td>
                <td class="ds-ltr-num">{{ $run->items_count }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $run->items_sum_net, 2) }} ر.س</td>
                <td>
                    <x-ds-status-badge :status="$run->status" />
                </td>
                <td>
                    <button type="button" class="ds-link" wire:click="openRun({{ $run->id }})">التفاصيل</button>
                    @can('hr.salaries.manage')
                        @if (in_array($run->status, ['مسودة', 'معاد_للتصحيح'], true))
                            <button type="button" class="ds-link" wire:click="submit({{ $run->id }})">رفع للمالية</button>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5"><x-ds-empty-state message="لا توجد مسيّرات" icon="fa-money-check-dollar" /></td>
            </tr>
        @endforelse
    </x-ds-table>

    {{ $runs->links() }}

    <x-ds-modal :show="$viewingRun !== null" title="تفاصيل المسيّر" close-action="closeRun" size="lg">
        @if ($viewingRun)
            <p class="ds-text-muted">الشهر: <span class="ds-ltr-num">{{ $viewingRun->month }}</span> — الحالة: {{ $viewingRun->status }}</p>
            @if ($viewingRun->notes)
                <p class="ds-badge ds-badge-warning">سبب الإرجاع: {{ $viewingRun->notes }}</p>
            @endif
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الموظف</th>
                        <th>الراتب الأساسي</th>
                        <th>البدلات</th>
                        <th>الخصم الثابت</th>
                        <th>ساعات الإضافي</th>
                        <th>مبلغ الإضافي</th>
                        <th>الصافي</th>
                    </tr>
                </x-slot:head>
                @foreach ($viewingRun->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td>{{ $item->employee?->name }}</td>
                        <td class="ds-ltr-num">{{ number_format((float) $item->base, 2) }}</td>
                        <td class="ds-ltr-num">{{ number_format((float) $item->allowances, 2) }}</td>
                        <td class="ds-ltr-num">{{ number_format((float) $item->deductions, 2) }}</td>
                        <td class="ds-ltr-num">{{ number_format((float) $item->overtime_hours, 2) }}</td>
                        <td class="ds-ltr-num">{{ number_format((float) $item->overtime_amount, 2) }}</td>
                        <td class="ds-ltr-num">{{ number_format((float) $item->net, 2) }}</td>
                    </tr>
                    @foreach ($item->variables ?? [] as $vIndex => $variable)
                        <tr wire:key="var-{{ $item->id }}-{{ $vIndex }}">
                            <td colspan="7" class="ds-text-muted">
                                بند متغير — {{ ($variable['kind'] ?? '') === 'deduction' ? 'خصم' : 'إضافة' }}:
                                {{ $variable['label'] ?? '' }}
                                (<span class="ds-ltr-num">{{ number_format((float) ($variable['amount'] ?? 0), 2) }}</span>)
                                — السبب: {{ $variable['reason'] ?? '—' }}
                                @if ($viewingRun->isEditable())
                                    @can('hr.salaries.manage')
                                        <button type="button" class="ds-link" wire:click="startEditVariable({{ $item->id }}, {{ $vIndex }})">تعديل</button>
                                        <button type="button" class="ds-link" wire:click="deleteVariable({{ $item->id }}, {{ $vIndex }})"
                                                wire:confirm="حذف هذا البند المتغير؟">حذف</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </x-ds-table>

            @if ($viewingRun->isEditable())
                @can('hr.salaries.manage')
                    <h4 class="ds-section-title">
                        {{ $editingVariableIndex !== null ? 'تعديل بند متغير' : 'بند متغير (مكافأة / خصم أداء أو غياب) — السبب إلزامي' }}
                    </h4>
                    <x-ds-form-group label="الموظف (صف المسيّر)" :error="$errors->first('variableItemId')">
                        <select class="ds-input" wire:model="variableItemId" @disabled($editingVariableIndex !== null)>
                            <option value="">—</option>
                            @foreach ($viewingRun->items as $item)
                                <option value="{{ $item->id }}">{{ $item->employee?->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="نوع البند">
                        <select class="ds-input" wire:model="variableKind">
                            <option value="addition">إضافة / مكافأة</option>
                            <option value="deduction">خصم أداء أو غياب</option>
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="بيان البند" :error="$errors->first('variableLabel')">
                        <input type="text" class="ds-input" wire:model="variableLabel">
                    </x-ds-form-group>
                    <x-ds-form-group label="سبب البند" :error="$errors->first('variableReason')">
                        <input type="text" class="ds-input" wire:model="variableReason">
                    </x-ds-form-group>
                    <x-ds-form-group label="مبلغ البند" :error="$errors->first('variableAmount')">
                        <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="variableAmount">
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="addVariable">
                        {{ $editingVariableIndex !== null ? 'حفظ التعديل' : 'إضافة البند' }}
                    </button>
                    @if ($editingVariableIndex !== null)
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelEditVariable">إلغاء التعديل</button>
                    @endif
                @endcan
            @endif

            @if ($viewingRun->status === \App\Models\PayrollRun::STATUS_SUBMITTED)
                @can('finance.payroll.approve')
                    <h4 class="ds-section-title">قرار المالية</h4>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="financeApprove({{ $viewingRun->id }})">قبول</button>
                    <x-ds-form-group label="سبب الرفض / الإرجاع" :error="$errors->first('returnNote')">
                        <input type="text" class="ds-input" wire:model="returnNote">
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="financeReject({{ $viewingRun->id }})">رفض بسبب</button>
                @endcan
            @endif
        @endif
        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="closeRun">إغلاق</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
