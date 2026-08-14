<x-ds-page>
    @php
        $statusLabels = \App\Models\Contract::STATUS_LABELS;
    @endphp

    <x-ds-page-header
        title="العقود"
        :show-button="auth()->user()->can('create', App\Models\Contract::class)"
        button-label="عقد جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="search" placeholder="اسم الموظف...">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label">الحالة</label>
            <select class="ds-input" wire:model.live="statusFilter">
                <option value="">الكل</option>
                @foreach ($statusOptions as $option)
                    <option value="{{ $option }}">{{ $statusLabels[$option] ?? $option }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="ds-table-wrap">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الموظف</th>
                    <th>تاريخ البداية</th>
                    <th>تاريخ النهاية</th>
                    <th>قيمة العقد</th>
                    <th>الحالة</th>
                    <th>الملف</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($contracts as $contract)
                <tr wire:key="contract-{{ $contract->id }}">
                    <td>{{ $contract->employee?->name ?? '—' }}</td>
                    <td>{{ $contract->start_date?->format('Y-m-d') }}</td>
                    <td>{{ $contract->end_date?->format('Y-m-d') }}</td>
                    <td>{{ $this->maskedValue($contract) }}</td>
                    <td>{{ $statusLabels[$contract->status] ?? $contract->status }}</td>
                    <td>
                        @if ($contract->contract_file)
                            <a class="ds-link" href="{{ route('contracts.files.download', $contract) }}?inline=1" target="_blank" rel="noopener">
                                معاينة PDF
                            </a>
                            <a class="ds-link" href="{{ route('contracts.files.download', $contract) }}">
                                تحميل
                            </a>
                        @else
                            <span class="ds-text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <x-ds-action-icons
                            :show-view="true"
                            :show-edit="auth()->user()->can('update', $contract)"
                            :show-delete="auth()->user()->can('delete', $contract)"
                            :view-action="'openView('.$contract->id.')'"
                            :edit-action="'openEdit('.$contract->id.')'"
                            :delete-action="'delete('.$contract->id.')'"
                            delete-confirm="حذف هذا العقد؟"
                        />
                        @can('update', $contract)
                            @if ($contract->isRenewable())
                                <button type="button" class="ds-link" wire:click="openRenew({{ $contract->id }})">تجديد</button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="ds-text-muted ds-table-empty">لا توجد عقود</td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $contracts->links() }}

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="closeModal">
            <div class="ds-modal" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($viewOnly)
                            عرض عقد
                        @elseif ($contractId)
                            تعديل عقد
                        @else
                            عقد جديد
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
                        <select class="ds-input" wire:model="employee_id" @disabled($viewOnly)>
                            <option value="">— اختر —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="تاريخ البداية" :error="$errors->first('start_date')">
                        <input type="date" class="ds-input" wire:model="start_date" @disabled($viewOnly)>
                    </x-ds-form-group>
                    <x-ds-form-group label="تاريخ النهاية" :error="$errors->first('end_date')">
                        <input type="date" class="ds-input" wire:model="end_date" @disabled($viewOnly)>
                    </x-ds-form-group>
                    @if ($canViewValue)
                        <x-ds-form-group label="قيمة العقد (إجمالي العقد وليس الراتب الشهري)" :error="$errors->first('value')">
                            <input type="number" step="0.01" class="ds-input" wire:model="value" @disabled($viewOnly) aria-describedby="contract-value-help">
                            <p id="contract-value-help" class="ds-help-text">قيمة العقد كاملة طوال مدته. الراتب الشهري يُدار من سلم الرواتب / الملف الوظيفي / المسيّرات.</p>
                        </x-ds-form-group>
                    @endif
                    <x-ds-form-group label="الحالة" :error="$errors->first('status')">
                        <select class="ds-input" wire:model="status" @disabled($viewOnly)>
                            @foreach ($statusOptions as $option)
                                <option value="{{ $option }}">{{ $statusLabels[$option] ?? $option }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    @if (! $viewOnly)
                        <x-ds-form-group label="ملف العقد" :error="$errors->first('contractFile')">
                            <input type="file" class="ds-input" wire:model="contractFile" accept=".pdf,.doc,.docx">
                            <div wire:loading wire:target="contractFile" class="ds-text-muted">جاري رفع الملف…</div>
                            @if ($existingContractFile)
                                <p class="ds-text-muted ds-mt-sm">ملف محفوظ — رفع ملف جديد لاستبداله</p>
                            @endif
                        </x-ds-form-group>
                    @elseif ($existingContractFile)
                        <p class="ds-text-muted">
                            <a class="ds-link" href="{{ route('contracts.files.download', $contractId) }}">تحميل ملف العقد</a>
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

    @if ($showRenewModal)
        <div class="ds-modal-overlay" wire:click.self="closeRenewModal">
            <div class="ds-modal" role="dialog" aria-modal="true">
                <div class="ds-modal-header">
                    <h3>تجديد العقد</h3>
                    <button type="button" class="ds-modal-close" wire:click="closeRenewModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p class="ds-text-muted">أدخل تاريخ نهاية الفترة الجديدة. يُمدَّد نفس سجل العقد وتُسجَّل فترة التجديد في السجل.</p>
                    <x-ds-form-group label="تاريخ نهاية التجديد" :error="$errors->first('renewEndDate')">
                        <input type="date" class="ds-input" wire:model="renewEndDate" aria-label="تاريخ نهاية التجديد">
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="renew">
                        <i class="fas fa-rotate" aria-hidden="true"></i> تأكيد التجديد
                    </button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeRenewModal">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>