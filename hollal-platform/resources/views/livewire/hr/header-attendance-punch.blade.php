<div wire:key="header-att-punch">
    @if ($enabled)
        <button
            type="button"
            class="ds-btn ds-btn-primary ds-btn-sm ds-navbar-attendance-trigger"
            wire:click="openPanel"
            title="تسجيل الحضور"
        >
            <i class="fas fa-fingerprint" aria-hidden="true"></i>
            <span class="ds-navbar-attendance-label">تسجيل الحضور</span>
        </button>

        <x-ds-modal :show="$showPanel" title="تسجيل الحضور والانصراف" close-action="closePanel" size="lg">
            <div class="ds-punch-panel-status">
                <p><strong>اليوم:</strong> <span class="ds-ltr-num">{{ now()->format('Y-m-d') }}</span></p>
                <p>
                    <strong>بداية الوردية:</strong>
                    <span class="ds-ltr-num">{{ $officeStart }}</span>
                    @if ($userShift)
                        ({{ $userShift->name }})
                        · مرونة <span class="ds-ltr-num">{{ $shiftGrace }}</span> د
                    @endif
                </p>
                <p>
                    <strong>حضور:</strong>
                    <span class="ds-ltr-num">{{ $todayRecord?->check_in_at ? hollal_time($todayRecord->check_in_at) : '—' }}</span>
                    ·
                    <strong>انصراف:</strong>
                    <span class="ds-ltr-num">{{ $todayRecord?->check_out_at ? hollal_time($todayRecord->check_out_at) : '—' }}</span>
                    @if ($todayLate > 0)
                        · <strong>تأخر:</strong> <span class="ds-ltr-num">{{ $todayLate }}</span> د
                    @endif
                </p>
                @if ($todayRecord?->approval_status)
                    <p><strong>حالة الاعتماد:</strong> {{ $todayRecord->approval_status }}</p>
                @endif
            </div>

            <div class="ds-toolbar-actions ds-mb-3">
                <button type="button" class="ds-btn ds-btn-primary" wire:click="checkIn">تسجيل حضور</button>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="checkOut">تسجيل انصراف</button>
            </div>

            <section class="ds-section ds-mb-3">
                <h3 class="ds-section-title">إقرار نوع اليوم</h3>
                <x-ds-form-group label="النوع">
                    <select class="ds-input" wire:model="declareType">
                        <option value="حضور">حضور</option>
                        <option value="عن بعد">عن بعد</option>
                        <option value="ميداني">ميداني</option>
                    </select>
                </x-ds-form-group>
                <x-ds-form-group label="ملاحظة" :error="$errors->first('declareNotes')">
                    <input type="text" class="ds-input" wire:model="declareNotes">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-teal" wire:click="saveDeclaration">حفظ الإقرار</button>
            </section>

            <section class="ds-section">
                <h3 class="ds-section-title">آخر 7 أيام</h3>
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>الحالة</th>
                            <th>حضور</th>
                            <th>انصراف</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($recentRecords as $record)
                        <tr wire:key="punch-rec-{{ $record->id }}">
                            <td class="ds-ltr-num">{{ $record->date?->format('Y-m-d') }}</td>
                            <td>{{ $record->type }}</td>
                            <td>{{ $record->approval_status ?: '—' }}</td>
                            <td class="ds-ltr-num">{{ hollal_time($record->check_in_at) }}</td>
                            <td class="ds-ltr-num">{{ hollal_time($record->check_out_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ds-text-muted">لا سجلات بعد</td></tr>
                    @endforelse
                </x-ds-table>
            </section>

            @if ($canManageAttendance)
                <p class="ds-mt-3">
                    <a class="ds-link" href="{{ route('attendance.index') }}" wire:navigate>إدارة الحضور (مسؤول الموارد)</a>
                </p>
            @endif
        </x-ds-modal>
    @endif
</div>
