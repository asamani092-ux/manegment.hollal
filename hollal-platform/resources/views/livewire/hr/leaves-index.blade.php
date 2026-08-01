<x-ds-page>
    <x-ds-page-header
        title="الإجازات"
        :show-button="$canRequest"
        button-label="طلب إجازة"
        button-icon="fa-plus"
        wire:click="openForm"
    />

    <p class="ds-text-muted ds-mb-3">الرصيد السنوي المتاح: <strong class="ds-ltr-num">{{ $balance }}</strong> يومًا</p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>النوع</th>
                <th>من</th>
                <th>إلى</th>
                <th>الأيام</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($leaves as $leave)
            <tr wire:key="leave-{{ $leave->id }}">
                <td>{{ $leave->employee?->name ?? '—' }}</td>
                <td>{{ $leave->type }}</td>
                <td class="ds-ltr-num">{{ $leave->from_date?->format('Y-m-d') }}</td>
                <td class="ds-ltr-num">{{ $leave->to_date?->format('Y-m-d') }}</td>
                <td class="ds-ltr-num">{{ $leave->days_count }}</td>
                <td>
                    <span class="ds-badge ds-badge-{{ match ($leave->status) {
                        \App\Models\LeaveRequest::STATUS_APPROVED => 'success',
                        \App\Models\LeaveRequest::STATUS_REJECTED => 'danger',
                        default => 'pending',
                    } }}">{{ $leave->status }}</span>
                </td>
                <td>
                    @if ($canApprove && $leave->employee_id !== auth()->id() && $leave->status === \App\Models\LeaveRequest::STATUS_SUBMITTED)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="approve({{ $leave->id }})">اعتماد</button>
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="reject({{ $leave->id }})">رفض</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="ds-table-empty">لا توجد طلبات إجازة</td></tr>
        @endforelse
    </x-ds-table>
    {{ $leaves->links() }}

    @if ($showForm)
        <div class="ds-modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>طلب إجازة</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showForm', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="النوع" :error="$errors->first('type')">
                        <select class="ds-input" wire:model="type">
                            <option value="سنوية">سنوية</option>
                            <option value="مرضية">مرضية</option>
                            <option value="استثنائية">استثنائية</option>
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="من" :error="$errors->first('from_date')">
                        <input type="date" class="ds-input" wire:model="from_date">
                    </x-ds-form-group>
                    <x-ds-form-group label="إلى" :error="$errors->first('to_date')">
                        <input type="date" class="ds-input" wire:model="to_date">
                    </x-ds-form-group>
                    <x-ds-form-group label="السبب" :error="$errors->first('reason')">
                        <textarea class="ds-input" wire:model="reason" rows="2"></textarea>
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="submitLeave">تقديم</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
