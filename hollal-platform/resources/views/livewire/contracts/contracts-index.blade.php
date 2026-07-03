<div>
    @php
        $statusLabels = [
            'active' => 'ساري',
            'expired' => 'منتهٍ',
            'terminated' => 'مُنهى',
            'pending' => 'قيد الانتظار',
        ];
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
                    <th>القيمة</th>
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
                            <a class="ds-link" href="{{ route('contracts.files.download', $contract) }}">
                                <i class="fas fa-download"></i> تحميل
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
                        <x-ds-form-group label="القيمة" :error="$errors->first('value')">
                            <input type="number" step="0.01" class="ds-input" wire:model="value" @disabled($viewOnly)>
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
</div>
