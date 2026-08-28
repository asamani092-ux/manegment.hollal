<x-ds-page>
    <x-ds-page-header
        title="الإجازات"
        :show-button="$canRequest"
        button-label="طلب إجازة"
        button-icon="fa-plus"
        wire:click="openForm"
    />

    <x-ds-collapsible-card title="رصيد الإجازات المتاح" :open="true">
        <p class="ds-mb-0">رصيدك السنوي المتاح حالياً: <strong class="ds-ltr-num">{{ $balance }}</strong> يومًا (بعد خصم الأيام المعتمدة؛ الطلبات المقدَّمة تُحجز عند الإرسال).</p>
    </x-ds-collapsible-card>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="leaves-status">الحالة</label>
            <select id="leaves-status" class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                <option value="مقدم">مقدم</option>
                <option value="معتمد">معتمد</option>
                <option value="مرفوض">مرفوض</option>
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="leaves-type">النوع</label>
            <select id="leaves-type" class="ds-input" wire:model.live="typeFilter">
                <option value="">— الكل —</option>
                <option value="سنوية">سنوية</option>
                <option value="مرضية">مرضية</option>
                <option value="استثنائية">استثنائية</option>
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="leaves-search">الموظف</label>
            <input id="leaves-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم…">
        </div>
    </div>

    <div class="ds-task-cards ds-list-cards-mobile">
        @forelse ($leaves as $leave)
            <article class="ds-task-card {{ $open === $leave->id ? 'is-open-record' : '' }}" wire:key="leave-card-{{ $leave->id }}">
                <h3 class="ds-task-card-title">{{ $leave->employee?->name ?? '—' }} — {{ $leave->type }}</h3>
                <div class="ds-task-card-meta">
                    <span class="ds-ltr-num">{{ $leave->from_date?->format('Y-m-d') }}</span>
                    <span class="ds-ltr-num">{{ $leave->to_date?->format('Y-m-d') }}</span>
                    <span class="ds-ltr-num">{{ $leave->days_count }} يوم</span>
                    @if ($leave->type === 'سنوية')
                        <span>رصيد: <strong class="ds-ltr-num">{{ (int) ($leave->employee?->profile?->annual_leave_balance ?? 21) }}</strong></span>
                    @endif
                </div>
                <x-ds-status-badge :status="$leave->status" />
                @if ($canApprove && $leave->employee_id !== auth()->id() && $leave->status === \App\Models\LeaveRequest::STATUS_SUBMITTED)
                    <div class="ds-toolbar-actions">
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="approve({{ $leave->id }})">اعتماد</button>
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="reject({{ $leave->id }})">رفض</button>
                    </div>
                @endif
            </article>
        @empty
            <x-ds-empty-state message="لا توجد طلبات إجازة" icon="fa-umbrella-beach" />
        @endforelse
    </div>

    <div class="ds-list-table-desktop">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th scope="col">الموظف</th>
                    <th scope="col">النوع</th>
                    <th scope="col">من</th>
                    <th scope="col">إلى</th>
                    <th scope="col">الأيام</th>
                    <th scope="col">الرصيد المتاح</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($leaves as $leave)
                <tr wire:key="leave-{{ $leave->id }}" class="{{ $open === $leave->id ? 'is-open-record' : '' }}">
                    <td>{{ $leave->employee?->name ?? '—' }}</td>
                    <td>{{ $leave->type }}</td>
                    <td class="ds-ltr-num">{{ $leave->from_date?->format('Y-m-d') }}</td>
                    <td class="ds-ltr-num">{{ $leave->to_date?->format('Y-m-d') }}</td>
                    <td class="ds-ltr-num">{{ $leave->days_count }}</td>
                    <td class="ds-ltr-num">
                        @if ($leave->type === 'سنوية')
                            {{ (int) ($leave->employee?->profile?->annual_leave_balance ?? 21) }}
                        @else
                            —
                        @endif
                    </td>
                    <td><x-ds-status-badge :status="$leave->status" /></td>
                    <td>
                        @if ($canApprove && $leave->employee_id !== auth()->id() && $leave->status === \App\Models\LeaveRequest::STATUS_SUBMITTED)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="approve({{ $leave->id }})">اعتماد</button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="reject({{ $leave->id }})">رفض</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-ds-empty-state message="لا توجد طلبات إجازة" icon="fa-umbrella-beach" /></td></tr>
            @endforelse
        </x-ds-table>
    </div>
    {{ $leaves->links() }}

    <x-ds-modal :show="$showForm" title="طلب إجازة" close-action="$set('showForm', false)">
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
    </x-ds-modal>
</x-ds-page>
