<x-ds-page>
    <x-ds-page-header title="الحضور والإجازات" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">إقرار إداري ليوم العمل (حضور / عن بعد / تكليف / انقطاع). لا يُخصم الراتب آليًا منه. الساعات الإضافية تُسحب إلى المسير عند التوليد للموظفين المفعَّل لهم البرنامج من الملف الوظيفي.</p>

    @if (! $attendanceEnabled)
        <p class="ds-badge ds-badge-warning ds-mb-3">برنامج الحضور غير مفعّل لحسابك. يفعّله مسؤول الموارد من الملف الوظيفي لموظفين محددين.</p>
    @endif

    @if ($attendanceEnabled)
        <div class="ds-card ds-mb-3 ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="checkIn">تسجيل حضور</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="checkOut">تسجيل انصراف</button>
        </div>
        <div class="ds-card ds-mb-3">
            <x-ds-form-group label="نوع الإقرار">
                <select class="ds-input" wire:model="type">
                    <option value="حضور">حضور</option>
                    <option value="عن بعد">عن بعد</option>
                    <option value="تكليف خارجي">تكليف خارجي</option>
                    <option value="انقطاع">انقطاع</option>
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="ملاحظة" :error="$errors->first('notes')">
                <input type="text" class="ds-input" wire:model="notes">
            </x-ds-form-group>
            <button type="button" class="ds-btn ds-btn-teal" wire:click="declareType">حفظ الإقرار</button>
        </div>
    @endif

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
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <tr wire:key="att-{{ $record->id }}">
                <td>{{ $record->employee?->name ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $record->date?->format('Y-m-d') }}</td>
                <td>{{ $record->type }}</td>
                <td class="ds-ltr-num">{{ $record->check_in_at?->format('H:i') ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $record->check_out_at?->format('H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا توجد سجلات حضور" icon="fa-clock" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $records->links() }}
</x-ds-page>
