<div dir="rtl" class="ds-page-rtl ds-portal-steps">
    <h1 class="ds-page-title">بوابة الجهة — {{ $partnership->organization?->name ?? $partnership->entity_name ?? '' }}</h1>
    <p class="ds-text-muted">المرحلة الحالية: {{ $partnership->stageLabel() }}</p>

    <section class="ds-section">
        <h2 class="ds-section-title">البرامج المتاحة</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>البرنامج</th><th>الفئة المستهدفة</th><th>اللقاءات</th><th>الساعات</th></tr>
            </x-slot:head>
            @forelse ($programs as $program)
                <tr wire:key="portal-program-{{ $program->id }}">
                    <td>{{ $program->name }}</td>
                    <td>{{ $program->target_audience ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $program->sessions_count ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $program->hours_count ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="ds-text-muted ds-table-empty">لا توجد برامج متاحة حاليًا</td></tr>
            @endforelse
        </x-ds-table>

        <x-ds-form-group label="البرامج محل اهتمامكم" :error="$errors->first('interestedPrograms')">
            <textarea class="ds-input" wire:model="interestedPrograms"></textarea>
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="submitInterest">إرسال الاهتمام</button>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">استبانة التشخيص</h2>
        <x-ds-form-group label="الفئة" :error="$errors->first('diagnosisAudience')">
            <input type="text" class="ds-input" wire:model="diagnosisAudience">
        </x-ds-form-group>
        <x-ds-form-group label="الأعداد" :error="$errors->first('diagnosisCount')">
            <input type="number" class="ds-input" wire:model="diagnosisCount">
        </x-ds-form-group>
        <x-ds-form-group label="البيئة" :error="$errors->first('diagnosisEnvironment')">
            <textarea class="ds-input" wire:model="diagnosisEnvironment"></textarea>
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="submitDiagnosis">إرسال الاستبانة</button>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">عروض الأسعار</h2>
        @forelse ($quotes as $quote)
            <div class="ds-kanban-card" wire:key="portal-quote-{{ $quote->id }}">
                <p>نسخة <span class="ds-ltr-num">{{ $quote->version }}</span> — الحالة: {{ $quote->status }}</p>
                <p>الإجمالي شامل الضريبة:
                    <span class="ds-ltr-num">{{ number_format((float) $quote->total, 2) }}</span></p>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="acceptQuote({{ $quote->id }})">قبول العرض</button>
                <x-ds-form-group label="ملاحظات على العرض" :error="$errors->first('quoteNotes')">
                    <textarea class="ds-input" wire:model="quoteNotes"></textarea>
                </x-ds-form-group>
                <button type="button" class="ds-btn" wire:click="noteQuote({{ $quote->id }})">إرسال ملاحظات</button>
            </div>
        @empty
            <p class="ds-text-muted">لا توجد عروض مرسلة</p>
        @endforelse
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">العقد والدفعات</h2>
        @foreach ($partnership->partnershipContracts as $contract)
            <div class="ds-kanban-card" wire:key="portal-contract-{{ $contract->id }}">
                <p>عقد #{{ $contract->id }} — الحالة: {{ $contract->status }}</p>
                <a class="ds-btn ds-btn-sm" href="{{ route('partner.portal.contract.pdf', ['token' => $link->token, 'contract' => $contract->id]) }}">
                    تنزيل العقد
                </a>

                @if ($contract->signed_pdf_path)
                    <p class="ds-text-muted">تم رفع نسخة موقعة — الحالة: {{ $contract->status }}</p>
                @else
                    <x-ds-form-group label="اسم الموقّع" :error="$errors->first('signatureName')">
                        <input type="text" class="ds-input" wire:model="signatureName">
                    </x-ds-form-group>
                    <x-ds-form-group label="رفع النسخة الموقعة (PDF)" :error="$errors->first('signedContract')">
                        <input type="file" class="ds-input" wire:model="signedContract" accept="application/pdf">
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="uploadSignedContract({{ $contract->id }})">
                        رفع العقد الموقع
                    </button>
                @endif

                <x-ds-table>
                    <x-slot:head>
                        <tr><th>الدفعة</th><th>المبلغ</th><th>الاستحقاق</th><th>تسجيل</th></tr>
                    </x-slot:head>
                    @foreach ($contract->schedule as $row)
                        <tr wire:key="portal-schedule-{{ $row->id }}">
                            <td>{{ $row->label }}</td>
                            <td class="ds-ltr-num">{{ number_format((float) $row->amount, 2) }}</td>
                            <td dir="ltr">{{ $row->due_on->format('Y-m-d') }}</td>
                            <td>
                                <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="paymentAmount">
                                <input type="file" class="ds-input" wire:model="paymentProof">
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="recordPayment({{ $row->id }})">
                                    تسجيل الدفعة
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </x-ds-table>
            </div>
        @endforeach
        @if ($partnership->partnershipContracts->isEmpty())
            <p class="ds-text-muted">لا يوجد عقد بعد</p>
        @endif
    </section>
</div>
