<x-ds-page>
    <x-ds-page-header
        title="العهد المالية"
        :show-button="true"
        button-label="طلب عهدة"
        button-icon="fa-plus"
        wire:click="openRequestModal"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="custody-status">الحالة</label>
            <select id="custody-status" class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="custody-search">الموظف</label>
            <input id="custody-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم…">
        </div>
    </div>

    <div class="ds-task-cards ds-list-cards-mobile">
        @forelse ($custodies as $custody)
            <article class="ds-task-card" wire:key="custody-card-{{ $custody->id }}">
                <h3 class="ds-task-card-title">{{ $custody->employee?->name ?? '—' }}</h3>
                <div class="ds-task-card-meta">
                    <span class="ds-ltr-num">{{ number_format((float) $custody->amount, 2) }} ر.س</span>
                    <span class="ds-ltr-num">{{ $custody->created_at?->format('Y-m-d') }}</span>
                </div>
                <x-ds-status-badge :status="$custody->status" />
                <p class="ds-text-muted">{{ $custody->purpose }}</p>
                <div class="ds-task-card-actions">
                    @if ($canApprove && $custody->status === \App\Models\Custody::STATUS_REQUESTED)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="approveCustody({{ $custody->id }})">اعتماد</button>
                    @endif
                    @if ($canDisburse && $custody->status === \App\Models\Custody::STATUS_APPROVED)
                        <button type="button" class="ds-btn ds-btn-teal ds-btn-sm" wire:click="disburseCustody({{ $custody->id }})">صرف</button>
                    @endif
                </div>
            </article>
        @empty
            <x-ds-empty-state message="لا توجد عهد مسجّلة" icon="fa-wallet" />
        @endforelse
    </div>

    <div class="ds-list-table-desktop">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th scope="col">الموظف</th>
                    <th scope="col">المبلغ</th>
                    <th scope="col">الغرض</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($custodies as $custody)
                <tr wire:key="custody-{{ $custody->id }}">
                    <td>{{ $custody->employee?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $custody->amount, 2) }} ر.س</td>
                    <td>{{ $custody->purpose }}</td>
                    <td><x-ds-status-badge :status="$custody->status" /></td>
                    <td class="ds-ltr-num">{{ $custody->created_at?->format('Y-m-d') }}</td>
                    <td>
                        @if ($canApprove && $custody->status === \App\Models\Custody::STATUS_REQUESTED)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="approveCustody({{ $custody->id }})">اعتماد</button>
                        @endif
                        @if ($canDisburse && $custody->status === \App\Models\Custody::STATUS_APPROVED)
                            <button type="button" class="ds-btn ds-btn-teal ds-btn-sm" wire:click="disburseCustody({{ $custody->id }})">صرف</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ds-empty-state message="لا توجد عهد مسجّلة" icon="fa-wallet" /></td></tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $custodies->links() }}

    <x-ds-modal :show="$showRequestModal" title="طلب عهدة" close-action="$set('showRequestModal', false)">
        @if ($canApprove)
            <x-ds-form-group label="الموظف" :error="$errors->first('employee_id')">
                <select class="ds-input" wire:model="employee_id">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
            </x-ds-form-group>
        @endif
        <x-ds-form-group label="المبلغ" :error="$errors->first('amount')">
            <input type="number" step="0.01" class="ds-input" wire:model="amount">
        </x-ds-form-group>
        <x-ds-form-group label="الغرض" :error="$errors->first('purpose')">
            <textarea class="ds-input" rows="3" wire:model="purpose"></textarea>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="submitRequest">إرسال الطلب</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showRequestModal', false)">إلغاء</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
