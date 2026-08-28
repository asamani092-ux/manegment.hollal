<x-ds-page class="ds-attendance-page">
    <x-ds-page-header title="إدارة الحضور" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        <strong>دورة الحضور:</strong>
        (1) تفعيل البرنامج ←
        (2) تسجيل يومي من زر «تسجيل الحضور» في الشريط العلوي ←
        (3) إقرار نوع اليوم ←
        (4) سجل مع التأخر (<span class="ds-ltr-num">{{ $officeStart }}</span>) ←
        (5) طباعة شهرية ←
        (6) <a href="{{ route('attendance.cycle') }}">الحضور الشهري</a> (اعتماد وخصم).
    </p>

    @if (! $attendanceEnabled)
        <p class="ds-badge ds-badge-warning ds-mb-3 ds-no-print">برنامج الحضور غير مفعّل لحسابك. يفعّله مسؤول الموارد من هذه الشاشة أو من الملف الوظيفي.</p>
    @elseif ($attendanceEnabled)
        <p class="ds-text-muted ds-mb-3 ds-no-print">لتسجيل الحضور/الانصراف استخدم زر «تسجيل الحضور» بجانب الإشعارات في الأعلى.</p>
    @endif

    <div class="ds-attendance-grid ds-no-print">
        @if ($canManage)
            <x-ds-collapsible-card title="تفعيل الحضور للموظفين" class="ds-attendance-grid-card" :open="false">
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
                            <th scope="col">البرنامج</th>
                            <th scope="col">إجراء</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($roster as $employee)
                        <tr wire:key="roster-{{ $employee->id }}">
                            <td>{{ $employee->name }}</td>
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
                        <tr><td colspan="3" class="ds-text-muted">لا يوجد موظفون مطابقون</td></tr>
                    @endforelse
                </x-ds-table>
            </x-ds-collapsible-card>
        @endif

        <x-ds-collapsible-card title="طباعة السجل الشهري" class="ds-attendance-grid-card" :open="false">
            <p class="ds-text-muted">أيام الدوام: <span class="ds-ltr-num">{{ $monthlyWorkingDays }}</span> · بداية الدوام: <span class="ds-ltr-num">{{ $officeStart }}</span></p>
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

    <x-ds-collapsible-card title="سجل الحضور والانصراف" class="ds-no-print" :open="false">
        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <label class="ds-label" for="att-type">النوع</label>
                <select id="att-type" class="ds-input" wire:model.live="typeFilter">
                    <option value="">— الكل —</option>
                    <option value="حضور">حضور</option>
                    <option value="عن بعد">عن بعد</option>
                    <option value="تكليف خارجي">تكليف خارجي</option>
                    <option value="انقطاع">انقطاع</option>
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
                    <td class="ds-ltr-num">{{ hollal_time($record->check_in_at) }}</td>
                    <td class="ds-ltr-num">{{ hollal_time($record->check_out_at) }}</td>
                    <td class="ds-ltr-num">
                        @php $late = (int) ($lateById[$record->id] ?? 0); @endphp
                        {{ $late > 0 ? $late : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ds-empty-state message="لا توجد سجلات حضور" icon="fa-clock" /></td></tr>
            @endforelse
        </x-ds-table>
        <div>{{ $records->links() }}</div>
    </x-ds-collapsible-card>

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
