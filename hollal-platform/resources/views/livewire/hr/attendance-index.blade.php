<x-ds-page class="ds-attendance-page">
    <x-ds-page-header title="إدارة الحضور" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        <strong>دورة الحضور:</strong>
        (1) تفعيل البرنامج ←
        (2) تعريف الوردية وإسنادها ←
        (3) تسجيل يومي من زر «تسجيل الحضور» في الشريط العلوي ←
        (4) إقرار نوع اليوم (حضور / عن بعد / ميداني) ←
        (5) اعتماد المدير للعن بعد والميداني ←
        (6) طباعة شهرية ←
        (7) <a href="{{ route('attendance.cycle') }}">الحضور الشهري</a> (استيراد بصمة وخصم).
    </p>

    @if (! $attendanceEnabled)
        <p class="ds-badge ds-badge-warning ds-mb-3 ds-no-print">برنامج الحضور غير مفعّل لحسابك. يفعّله مسؤول الموارد من هذه الشاشة أو من الملف الوظيفي.</p>
    @elseif ($attendanceEnabled)
        <p class="ds-text-muted ds-mb-3 ds-no-print">
            لتسجيل الحضور/الانصراف استخدم زر «تسجيل الحضور» بجانب الإشعارات.
            @if ($userShift)
                ورديتك: {{ $userShift->name }} · بداية <span class="ds-ltr-num">{{ $officeStart }}</span>
                · مرونة <span class="ds-ltr-num">{{ $shiftGrace }}</span> د
            @else
                بداية الدوام الافتراضية: <span class="ds-ltr-num">{{ $officeStart }}</span>
            @endif
        </p>
    @endif

    <div class="ds-attendance-grid ds-no-print">
        @if ($canManage)
            <x-ds-collapsible-card title="1) تفعيل الحضور للموظفين" class="ds-attendance-grid-card" :open="false">
                <p class="ds-text-muted">بدون تفعيل لا يستطيع الموظف تسجيل الحضور.</p>
                <div class="ds-filters-row">
                    <div class="ds-filter-field">
                        <label class="ds-label" for="att-roster">بحث موظف</label>
                        <input id="att-roster" type="search" class="ds-input" wire:model.live.debounce.300ms="rosterSearch" placeholder="الاسم…">
                    </div>
                </div>
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th scope="col">الموظف</th>
                            <th scope="col">الوردية</th>
                            <th scope="col">البرنامج</th>
                            <th scope="col">إجراء</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($roster as $employee)
                        <tr wire:key="roster-{{ $employee->id }}">
                            <td>{{ $employee->name }}</td>
                            <td>{{ $employee->profile?->workShift?->name ?? '—' }}</td>
                            <td>
                                @if ($employee->attendance_enabled)
                                    <span class="ds-badge ds-badge-success">مفعّل</span>
                                @else
                                    <span class="ds-badge ds-badge-warning">متوقف</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="toggleAttendanceEnabled({{ $employee->id }})">
                                    {{ $employee->attendance_enabled ? 'إيقاف' : 'تفعيل' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ds-text-muted">لا يوجد موظفون مطابقون</td></tr>
                    @endforelse
                </x-ds-table>
            </x-ds-collapsible-card>

            <x-ds-collapsible-card title="2) الورديات" class="ds-attendance-grid-card" :open="false">
                <p class="ds-text-muted">بداية · نهاية · مرونة التأخير · أيام الأسبوع.</p>
                <div class="ds-toolbar-actions ds-mb-3">
                    <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="openShiftForm">وردية جديدة</button>
                </div>
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th scope="col">الاسم</th>
                            <th scope="col">البداية</th>
                            <th scope="col">النهاية</th>
                            <th scope="col">مرونة (د)</th>
                            <th scope="col">الأيام</th>
                            <th scope="col">إجراء</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($shifts as $shift)
                        <tr wire:key="shift-{{ $shift->id }}">
                            <td>{{ $shift->name }}</td>
                            <td class="ds-ltr-num">{{ $shift->startHm() }}</td>
                            <td class="ds-ltr-num">{{ $shift->endHm() }}</td>
                            <td class="ds-ltr-num">{{ $shift->grace_minutes }}</td>
                            <td>{{ $shift->weekdaysLabel() }}</td>
                            <td>
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openShiftForm({{ $shift->id }})">تعديل</button>
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deleteShift({{ $shift->id }})" wire:confirm="حذف الوردية؟">حذف</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ds-text-muted">لا توجد ورديات بعد</td></tr>
                    @endforelse
                </x-ds-table>
            </x-ds-collapsible-card>

            <x-ds-collapsible-card title="3) إسناد وردية لموظف" class="ds-attendance-grid-card" :open="false">
                <div class="ds-filters-row">
                    <div class="ds-filter-field">
                        <label class="ds-label" for="att-assign-emp">الموظف</label>
                        <select id="att-assign-emp" class="ds-input" wire:model="assignEmployeeId">
                            <option value="">— اختر —</option>
                            @foreach ($assignCandidates as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                            @endforeach
                        </select>
                        @error('assignEmployeeId') <span class="ds-field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="ds-filter-field">
                        <label class="ds-label" for="att-assign-shift">الوردية</label>
                        <select id="att-assign-shift" class="ds-input" wire:model="assignShiftId">
                            <option value="">— بدون —</option>
                            @foreach ($shifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ds-filter-field" style="align-self:end">
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="assignShift">حفظ الإسناد</button>
                    </div>
                </div>
            </x-ds-collapsible-card>
        @endif

        @if ($pendingApprovals->isNotEmpty() || $canManage)
            <x-ds-collapsible-card title="4) اعتماد عن بعد / ميداني" class="ds-attendance-grid-card" :open="false">
                <p class="ds-text-muted">يبقى معلّقاً حتى يعتمد المدير المباشر أو الموارد البشرية.</p>
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th scope="col">الموظف</th>
                            <th scope="col">التاريخ</th>
                            <th scope="col">النوع</th>
                            <th scope="col">إجراء</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($pendingApprovals as $pending)
                        <tr wire:key="pending-{{ $pending->id }}">
                            <td>{{ $pending->employee?->name ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $pending->date?->format('Y-m-d') }}</td>
                            <td>{{ $pending->type }}</td>
                            <td>
                                <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="approvePending({{ $pending->id }})">اعتماد</button>
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="rejectPending({{ $pending->id }})">رفض</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ds-text-muted">لا طلبات معلّقة</td></tr>
                    @endforelse
                </x-ds-table>
            </x-ds-collapsible-card>
        @endif

        <x-ds-collapsible-card title="5) طباعة السجل الشهري" class="ds-attendance-grid-card" :open="false">
            <p class="ds-text-muted">أيام الدوام: <span class="ds-ltr-num">{{ $monthlyWorkingDays }}</span> · بداية مرجعية: <span class="ds-ltr-num">{{ $officeStart }}</span></p>
            <div class="ds-filters-row">
                <div class="ds-filter-field">
                    <label class="ds-label" for="att-month">الشهر</label>
                    <input id="att-month" type="month" class="ds-input" wire:model="printMonth">
                </div>
                <div class="ds-filter-field" style="align-self:end">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="openMonthlyPrint">
                        <i class="fas fa-print" aria-hidden="true"></i> معاينة / طباعة
                    </button>
                </div>
            </div>
        </x-ds-collapsible-card>
    </div>

    <x-ds-collapsible-card title="6) سجل الحضور والانصراف" class="ds-no-print" :open="false">
        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <label class="ds-label" for="att-type">النوع</label>
                <select id="att-type" class="ds-input" wire:model.live="typeFilter">
                    <option value="">— الكل —</option>
                    <option value="حضور">حضور</option>
                    <option value="عن بعد">عن بعد</option>
                    <option value="ميداني">ميداني</option>
                </select>
            </div>
            <div class="ds-filter-field">
                <label class="ds-label" for="att-from">من تاريخ</label>
                <input id="att-from" type="date" class="ds-input" wire:model.live="dateFrom">
            </div>
            <div class="ds-filter-field">
                <label class="ds-label" for="att-to">إلى تاريخ</label>
                <input id="att-to" type="date" class="ds-input" wire:model.live="dateTo">
            </div>
            @if ($canViewAll)
                <div class="ds-filter-field">
                    <label class="ds-label" for="att-search">الموظف</label>
                    <input id="att-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم…">
                </div>
            @endif
        </div>

        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th scope="col">الموظف</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">النوع</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">حضور</th>
                    <th scope="col">انصراف</th>
                    <th scope="col">تأخر (د)</th>
                </tr>
            </x-slot:head>
            @forelse ($records as $record)
                <tr wire:key="att-{{ $record->id }}">
                    <td>{{ $record->employee?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $record->date?->format('Y-m-d') }}</td>
                    <td>{{ $record->type }}</td>
                    <td>{{ $record->approval_status ?: '—' }}</td>
                    <td class="ds-ltr-num">{{ hollal_time($record->check_in_at) }}</td>
                    <td class="ds-ltr-num">{{ hollal_time($record->check_out_at) }}</td>
                    <td class="ds-ltr-num">
                        @php $late = (int) ($lateById[$record->id] ?? 0); @endphp
                        {{ $late > 0 ? $late : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ds-empty-state message="لا توجد سجلات حضور" icon="fa-clock" /></td></tr>
            @endforelse
        </x-ds-table>
        <div>{{ $records->links() }}</div>
    </x-ds-collapsible-card>

    @if ($showShiftForm && $canManage)
        <x-ds-modal :show="true" title="{{ $editingShiftId ? 'تعديل وردية' : 'وردية جديدة' }}" close-action="closeShiftForm" size="md">
            <x-ds-form-group label="الاسم" :error="$errors->first('shiftName')">
                <input type="text" class="ds-input" wire:model="shiftName">
            </x-ds-form-group>
            <div class="ds-filters-row">
                <x-ds-form-group label="البداية" :error="$errors->first('shiftStart')">
                    <input type="time" class="ds-input" wire:model="shiftStart">
                </x-ds-form-group>
                <x-ds-form-group label="النهاية" :error="$errors->first('shiftEnd')">
                    <input type="time" class="ds-input" wire:model="shiftEnd">
                </x-ds-form-group>
                <x-ds-form-group label="مرونة التأخير (دقيقة)" :error="$errors->first('shiftGrace')">
                    <input type="number" min="0" max="240" class="ds-input" wire:model="shiftGrace">
                </x-ds-form-group>
            </div>
            <fieldset class="ds-mb-3">
                <legend class="ds-label">أيام الأسبوع</legend>
                @error('shiftWeekdays') <span class="ds-field-error">{{ $message }}</span> @enderror
                <div class="ds-filters-row">
                    @foreach ($weekdayLabels as $dow => $label)
                        <label class="ds-checkbox-label">
                            <input type="checkbox" value="{{ $dow }}" wire:model="shiftWeekdays">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="ds-toolbar-actions">
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveShift">حفظ</button>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="closeShiftForm">إلغاء</button>
            </div>
        </x-ds-modal>
    @endif

    @if ($showPrint && $printReport)
        <div class="ds-modal-overlay ds-no-print" wire:click.self="closePrint">
            <div class="ds-modal" role="dialog" aria-modal="true" style="max-width:48rem">
                <div class="ds-modal-header">
                    <h3>سجل الحضور الشهري — <span class="ds-ltr-num">{{ $printReport['month'] }}</span></h3>
                    <button type="button" class="ds-modal-close" wire:click="closePrint">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-toolbar-actions ds-mb-3">
                        <button type="button" class="ds-btn ds-btn-primary" onclick="window.print()">
                            <i class="fas fa-print" aria-hidden="true"></i> طباعة
                        </button>
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="closePrint">إغلاق</button>
                    </div>
                    @include('livewire.hr.partials.attendance-monthly-table', ['printReport' => $printReport])
                </div>
            </div>
        </div>

        <div class="ds-attendance-print-sheet" aria-hidden="true">
            <h1>سجل الحضور الشهري — {{ $printReport['month'] }}</h1>
            @include('livewire.hr.partials.attendance-monthly-table', ['printReport' => $printReport])
        </div>
    @endif
</x-ds-page>
