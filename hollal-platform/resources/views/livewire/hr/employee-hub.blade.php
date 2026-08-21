<x-ds-page>
    <x-ds-page-header title="مساحتي" />
    <p class="ds-text-muted ds-mb-3">بوابة الموظف: مهامي · إجازاتي · راتبي · تقييمي · حضوري · مستنداتي · مسؤولياتي.</p>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">مهامي</h2>
        <x-ds-table>
            <x-slot:head><tr><th>المهمة</th><th>الاستحقاق</th><th>الحالة</th></tr></x-slot:head>
            @forelse ($tasks as $t)
                <tr>
                    <td><a href="{{ \App\Support\RecordUrl::task($t->id) }}">{{ $t->title }}</a></td>
                    <td class="ds-ltr-num">{{ $t->due_date?->format('Y-m-d') }}</td>
                    <td>{{ $t->status }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-table-empty">لا مهام مفتوحة</td></tr>
            @endforelse
        </x-ds-table>
        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('tasks.index') }}">كل مهامي</a>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">إجازاتي <span class="ds-text-muted">(رصيد سنوي: {{ $leaveBalance }})</span></h2>
        <div class="ds-grid-2 ds-mb-2">
            <x-ds-form-group label="النوع">
                <select class="ds-input" wire:model="leaveType">
                    <option value="سنوية">سنوية</option>
                    <option value="مرضية">مرضية</option>
                    <option value="استثنائية">استثنائية</option>
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="السبب"><input class="ds-input" wire:model="leaveReason"></x-ds-form-group>
            <x-ds-form-group label="من"><input type="date" class="ds-input" wire:model="leaveFrom"></x-ds-form-group>
            <x-ds-form-group label="إلى"><input type="date" class="ds-input" wire:model="leaveTo"></x-ds-form-group>
        </div>
        <button type="button" class="ds-btn ds-btn-primary ds-mb-2" wire:click="submitLeave">تقديم إجازة</button>
        <x-ds-table>
            <x-slot:head><tr><th>النوع</th><th>الفترة</th><th>الحالة</th></tr></x-slot:head>
            @forelse ($leaves as $l)
                <tr>
                    <td>{{ $l->type }}</td>
                    <td class="ds-ltr-num">{{ $l->from_date?->format('Y-m-d') }} → {{ $l->to_date?->format('Y-m-d') }}</td>
                    <td>{{ $l->status }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-table-empty">لا طلبات</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">راتبي (منفّذ)</h2>
        <x-ds-table>
            <x-slot:head><tr><th>الشهر</th><th>الإجمالي</th><th>الصافي</th></tr></x-slot:head>
            @forelse ($payslips as $p)
                <tr>
                    <td class="ds-ltr-num">{{ $p->run?->month }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $p->gross, 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $p->net, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-table-empty">لا مسيرات منفّذة بعد</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">تقييمي</h2>
        <x-ds-table>
            <x-slot:head><tr><th>الفترة</th><th>الحالة</th><th>تعليق</th></tr></x-slot:head>
            @forelse ($evals as $e)
                <tr>
                    <td>{{ $e->period }}</td>
                    <td>{{ $e->status }}</td>
                    <td>{{ $e->employee_comment ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-table-empty">لا تقييمات</td></tr>
            @endforelse
        </x-ds-table>
        @php $published = $evals->firstWhere('status', 'منشور'); @endphp
        @if ($published)
            <x-ds-form-group label="تعليق على التقييم المنشور ({{ $published->period }})">
                <textarea class="ds-input" wire:model="evalComment" rows="2"></textarea>
            </x-ds-form-group>
            <button type="button" class="ds-btn" wire:click="saveEvalComment({{ $published->id }})">حفظ التعليق</button>
        @endif
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">حضوري</h2>
        <x-ds-table>
            <x-slot:head><tr><th>التاريخ</th><th>دخول</th><th>خروج</th><th>المصدر</th></tr></x-slot:head>
            @forelse ($attendance as $a)
                <tr>
                    <td class="ds-ltr-num">{{ $a->date?->format('Y-m-d') }}</td>
                    <td class="ds-ltr-num">{{ $a->check_in_at?->format('H:i') }}</td>
                    <td class="ds-ltr-num">{{ $a->check_out_at?->format('H:i') }}</td>
                    <td>{{ $a->source ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="ds-table-empty">لا سجلات</td></tr>
            @endforelse
        </x-ds-table>
        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('attendance.index') }}">شاشة الحضور</a>
    </section>

    <section class="ds-section ds-mb-3">
        <h2 class="ds-section-heading">مسؤولياتي</h2>
        <ul>
            @forelse ($responsibilities as $r)
                <li>{{ $r->body }}</li>
            @empty
                <li class="ds-text-muted">لا مسؤوليات مسجّلة</li>
            @endforelse
        </ul>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-heading">مستنداتي</h2>
        <x-ds-table>
            <x-slot:head><tr><th>المستند</th><th>التاريخ</th></tr></x-slot:head>
            @forelse ($docs as $d)
                <tr>
                    <td>{{ $d->title }}</td>
                    <td class="ds-ltr-num">{{ $d->created_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="ds-table-empty">لا مستندات مرفوعة</td></tr>
            @endforelse
        </x-ds-table>
        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.index') }}">المستندات</a>
    </section>
</x-ds-page>
