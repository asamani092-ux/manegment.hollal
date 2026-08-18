@php
    $current = $wizard['current'];
    $focus = $wizard['focus'] ?? $current;
    $quoteAccepted = $quotes->contains(fn ($quote) => $quote->status === \App\Models\Quote::STATUS_ACCEPTED);
    $hasContract = $partnership->partnershipContracts->isNotEmpty();
    $can = fn (int $id) => $id <= 3 || $quoteAccepted || $hasContract;
    $enabled = collect($wizard['steps'])->contains(fn ($step) => $step['id'] === $focus);
    $pageUrl = fn (string $key) => route('partner.portal.page', ['token' => $link->token, 'page' => $key], false);
    $stepIds = collect($wizard['steps'])->pluck('id');
    $prevId = $stepIds->filter(fn ($id) => $id < $focus)->last();
    $nextId = $stepIds->first(fn ($id) => $id > $focus);
    $pageKeys = \App\Livewire\Partnerships\PartnerPortal::PAGE_KEYS;
@endphp
<div dir="rtl" class="ds-page-rtl ds-portal-steps">
    <h1 class="ds-page-title">بوابة الجهة — {{ $partnership->organization?->name ?? $partnership->entity_name ?? '' }}</h1>
    <p class="ds-text-muted">المرحلة الحالية: {{ $partnership->stageLabel() }}</p>
    @if (session('success') || ($flashNotice !== '' && $flashType !== 'error'))
        <p class="ds-badge ds-badge-success">{{ session('success') ?: $flashNotice }}</p>
    @endif
    @if (session('error') || ($flashNotice !== '' && $flashType === 'error'))
        <p class="ds-badge ds-badge-danger">{{ session('error') ?: $flashNotice }}</p>
    @endif
    @error('selectedProgramIds') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror
    @error('diagnosisAudience') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror

    <ol class="ds-journey-steps">
        @foreach ($wizard['steps'] as $step)
            <li class="{{ $focus === $step['id'] ? 'is-current' : ($step['state'] === 'done' ? 'is-done' : '') }}">
                @if ($step['state'] !== 'locked')
                    <a class="ds-pill {{ $focus === $step['id'] ? 'is-selected' : '' }}"
                       href="{{ $pageUrl($step['key']) }}">
                        {{ $step['id'] }}. {{ $step['label'] }}
                    </a>
                @else
                    {{ $step['id'] }}. {{ $step['label'] }}
                @endif
            </li>
        @endforeach
    </ol>

    @unless ($enabled)
        <section class="ds-portal-page">
            <p class="ds-text-muted">هذا القسم غير مفعّل على رابط الجهة.</p>
        </section>
    @else
        @if ($focus === 1)
            <section class="ds-portal-page ds-journey-active">
                <header class="ds-portal-page-head">
                    <h2 class="ds-section-title">1. البرامج</h2>
                    <p class="ds-text-muted">اختر البرامج والخدمة. الأعداد تُحسب من التشخيص وليست إدخالًا حرًا هنا.</p>
                </header>
                <form method="POST" action="{{ route('partner.portal.programs.save', ['token' => $link->token], false) }}">
                    @csrf
                    <x-ds-table>
                        <x-slot:head>
                            <tr><th>اختيار</th><th>البرنامج</th><th>الخدمة</th></tr>
                        </x-slot:head>
                        @forelse ($programs as $program)
                            <tr wire:key="portal-program-{{ $program->id }}">
                                <td>
                                    <input type="checkbox" name="selectedProgramIds[]" value="{{ $program->id }}"
                                           @checked(in_array((int) $program->id, array_map('intval', $selectedProgramIds), true))>
                                </td>
                                <td>
                                    <strong>{{ $program->name }}</strong>
                                    <small class="ds-text-muted">{{ $program->target_audience ?? '—' }}</small>
                                </td>
                                <td>
                                    <select class="ds-input ds-portal-service" name="programServices[{{ $program->id }}]">
                                        @foreach ($program->prices as $price)
                                            <option value="{{ $price->service_type }}" @selected(($programServices[$program->id] ?? '') === $price->service_type)>{{ $price->service_type }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="ds-text-muted ds-table-empty">لا توجد برامج متاحة حاليًا</td></tr>
                        @endforelse
                    </x-ds-table>
                    <button type="submit" class="ds-btn ds-btn-primary">حفظ الاختيار وبناء العرض</button>
                </form>
            </section>
        @elseif ($focus === 2)
            <section class="ds-portal-page ds-journey-active">
                <header class="ds-portal-page-head">
                    <h2 class="ds-section-title">2. التشخيص</h2>
                    <p class="ds-text-muted">أجب عن الاستبانة. الأعداد تُستخدم لاحقًا لحسبة العرض من الخدمات المسعّرة.</p>
                </header>
                <form method="POST" action="{{ route('partner.portal.diagnosis.save', ['token' => $link->token], false) }}">
                    @csrf
                    @forelse ($diagnosisQuestions as $question)
                        <x-ds-form-group :label="$question->label" :error="$errors->first('diagnosisAnswers.'.$question->id)">
                            @if ($question->key === 'audience')
                                <input type="text" class="ds-input" name="diagnosisAudience" value="{{ old('diagnosisAudience', $diagnosisAudience) }}">
                            @elseif ($question->key === 'count')
                                <input type="number" class="ds-input" name="diagnosisCount" value="{{ old('diagnosisCount', $diagnosisCount) }}">
                            @elseif ($question->key === 'environment')
                                <textarea class="ds-input" name="diagnosisEnvironment">{{ old('diagnosisEnvironment', $diagnosisEnvironment) }}</textarea>
                            @elseif ($question->type === 'number')
                                <input type="number" class="ds-input" name="diagnosisAnswers[{{ $question->id }}]" value="{{ old('diagnosisAnswers.'.$question->id, $diagnosisAnswers[$question->id] ?? '') }}">
                            @elseif ($question->type === 'textarea')
                                <textarea class="ds-input" name="diagnosisAnswers[{{ $question->id }}]">{{ old('diagnosisAnswers.'.$question->id, $diagnosisAnswers[$question->id] ?? '') }}</textarea>
                            @else
                                <input type="text" class="ds-input" name="diagnosisAnswers[{{ $question->id }}]" value="{{ old('diagnosisAnswers.'.$question->id, $diagnosisAnswers[$question->id] ?? '') }}">
                            @endif
                        </x-ds-form-group>
                    @empty
                        <x-ds-form-group label="الفئة" :error="$errors->first('diagnosisAudience')">
                            <input type="text" class="ds-input" name="diagnosisAudience" value="{{ old('diagnosisAudience', $diagnosisAudience) }}">
                        </x-ds-form-group>
                        <x-ds-form-group label="الأعداد" :error="$errors->first('diagnosisCount')">
                            <input type="number" class="ds-input" name="diagnosisCount" value="{{ old('diagnosisCount', $diagnosisCount) }}">
                        </x-ds-form-group>
                        <x-ds-form-group label="البيئة" :error="$errors->first('diagnosisEnvironment')">
                            <textarea class="ds-input" name="diagnosisEnvironment">{{ old('diagnosisEnvironment', $diagnosisEnvironment) }}</textarea>
                        </x-ds-form-group>
                    @endforelse
                    <button type="submit" class="ds-btn ds-btn-primary">إرسال الاستبانة</button>
                </form>
            </section>
        @elseif ($focus === 3)
            <section class="ds-portal-page ds-portal-page-solo ds-journey-active">
                <header class="ds-portal-page-head">
                    <h2 class="ds-section-title">قبول العرض</h2>
                    <p class="ds-text-muted">3. عروض الأسعار — راجع البنود والإجمالي ثم اقبل من هذه الصفحة فقط.</p>
                </header>
                @forelse ($quotes as $quote)
                    <article class="ds-portal-page-card" wire:key="portal-quote-{{ $quote->id }}">
                        <p>نسخة <span class="ds-ltr-num">{{ $quote->version }}</span> — الحالة: {{ $quote->status }}</p>
                        @foreach ($quote->items as $item)
                            <p class="ds-text-muted">
                                {{ $item->description }} × <span class="ds-ltr-num">{{ number_format((float) $item->quantity, 2) }}</span>
                            </p>
                        @endforeach
                        <p class="ds-portal-total">الإجمالي شامل الضريبة:
                            <span class="ds-ltr-num">{{ number_format((float) $quote->total, 2) }}</span></p>
                        @if ($can(3) && $quote->status === \App\Models\Quote::STATUS_APPROVED)
                            <form method="POST" action="{{ route('partner.portal.quotes.accept', ['token' => $link->token, 'quote' => $quote->id], false) }}">
                                @csrf
                                <button type="submit" class="ds-btn ds-btn-primary">قبول العرض</button>
                            </form>
                        @elseif ($quote->status === \App\Models\Quote::STATUS_ACCEPTED)
                            <p class="ds-text-muted">قُبل هذا العرض. انتقل لصفحة العقد عند صدوره.</p>
                        @endif
                    </article>
                @empty
                    <p class="ds-text-muted">احفظ اختيار البرامج ليُبنى العرض تلقائيًا من أسعار الخدمات.</p>
                @endforelse
            </section>
        @elseif ($focus === 4)
            <section class="ds-portal-page ds-journey-active">
                <header class="ds-portal-page-head">
                    <h2 class="ds-section-title">4. الدفعات</h2>
                    <p class="ds-text-muted">المبالغ المستحقة من العرض المقبول أو من جدول العقد.</p>
                </header>
                @php $acceptedQuote = $quotes->firstWhere('status', \App\Models\Quote::STATUS_ACCEPTED); @endphp
                @forelse ($partnership->partnershipContracts as $contract)
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
                                    @if ($can(4))
                                        <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="paymentAmount">
                                        <input type="file" class="ds-input" wire:model="paymentProof" accept=".pdf,image/*">
                                        @error('paymentProof') <small class="ds-error">{{ $message }}</small> @enderror
                                        <button type="button" class="ds-btn ds-btn-sm" wire:click="recordPayment({{ $row->id }})">
                                            إرسال إثبات التحويل
                                        </button>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-ds-table>
                @empty
                    @if ($acceptedQuote)
                        <article class="ds-portal-page-card">
                            <p>المبلغ المستحق من العرض المقبول:
                                <span class="ds-ltr-num">{{ number_format((float) $acceptedQuote->total, 2) }}</span>
                            </p>
                            <p class="ds-text-muted">إرسال إثبات التحويل يُفعَّل بعد إصدار جدول الدفعات مع العقد.</p>
                        </article>
                    @else
                        <p class="ds-text-muted">أتمّ حفظ البرامج وقبول العرض لتظهر قيمة الدفعات هنا.</p>
                    @endif
                @endforelse
            </section>
        @elseif ($focus === 5)
            <section class="ds-portal-page ds-portal-page-solo ds-journey-active">
                <header class="ds-portal-page-head">
                    <h2 class="ds-section-title">5. العقد</h2>
                    <p class="ds-text-muted">تنزيل العقد والتوقيع الإلكتروني من هذه الصفحة فقط.</p>
                </header>
                @forelse ($partnership->partnershipContracts as $contract)
                    <article class="ds-portal-page-card" wire:key="portal-contract-{{ $contract->id }}">
                        <p>عقد #{{ $contract->id }} — الحالة: {{ $contract->status }}</p>
                        <p>القيمة: <span class="ds-ltr-num">{{ number_format((float) $contract->total_value, 2) }}</span></p>
                        <p>يشترط الدفعة الأولى: {{ $contract->requires_first_payment ? 'نعم' : 'لا' }}</p>
                        <p>
                            <a class="ds-link" href="{{ route('partner.portal.contract.pdf', ['token' => $link->token, 'contract' => $contract->id]) }}" target="_blank" rel="noopener">تنزيل العقد</a>
                        </p>

                        @if ($contract->signed_pdf_path)
                            <p class="ds-text-muted">
                                تم التوقيع ({{ $contract->signature_method ?? '—' }}) — الحالة: {{ $contract->status }}
                            </p>
                        @elseif ($can(5))
                            <x-ds-form-group label="اسم الموقّع" :error="$errors->first('signatureName')">
                                <input type="text" class="ds-input" wire:model="signatureName">
                            </x-ds-form-group>
                            <x-ds-form-group label="الصفة" :error="$errors->first('signaturePosition')">
                                <input type="text" class="ds-input" wire:model="signaturePosition">
                            </x-ds-form-group>

                            <div class="ds-section"
                                 x-data="{
                                    drawing: false,
                                    initPad() {
                                        const c = this.$refs.pad;
                                        const ctx = c.getContext('2d');
                                        ctx.strokeStyle = '#0F3446';
                                        ctx.lineWidth = 2;
                                        ctx.lineCap = 'round';
                                        const pos = (e) => {
                                            const r = c.getBoundingClientRect();
                                            const src = e.touches ? e.touches[0] : e;
                                            return { x: src.clientX - r.left, y: src.clientY - r.top };
                                        };
                                        const start = (e) => { e.preventDefault(); this.drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); };
                                        const move = (e) => { if (!this.drawing) return; e.preventDefault(); const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); };
                                        const end = () => { this.drawing = false; };
                                        c.addEventListener('mousedown', start);
                                        c.addEventListener('mousemove', move);
                                        c.addEventListener('mouseup', end);
                                        c.addEventListener('mouseleave', end);
                                        c.addEventListener('touchstart', start, { passive: false });
                                        c.addEventListener('touchmove', move, { passive: false });
                                        c.addEventListener('touchend', end);
                                    },
                                    clear() {
                                        const c = this.$refs.pad;
                                        c.getContext('2d').clearRect(0, 0, c.width, c.height);
                                        $wire.set('signaturePadData', '');
                                    },
                                    submitEsign() {
                                        $wire.set('signaturePadData', this.$refs.pad.toDataURL('image/png'));
                                        $wire.signElectronically({{ $contract->id }});
                                    }
                                 }"
                                 x-init="initPad()">
                                <h3 class="ds-section-title">التوقيع داخل خانة العقد</h3>
                                <canvas x-ref="pad" width="400" height="160" class="ds-input" style="touch-action: none; background: #E7EEF1; max-width: 100%;" wire:ignore></canvas>
                                @error('signaturePadData') <small class="ds-error">{{ $message }}</small> @enderror
                                <div class="ds-filter-bar">
                                    <button type="button" class="ds-btn" @click="clear()">مسح اللوحة</button>
                                    <button type="button" class="ds-btn ds-btn-primary" @click="submitEsign()">اعتماد التوقيع</button>
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="ds-text-muted">سيظهر العقد بعد تأكيد الجهة للعرض، جاهزًا للتنزيل والتوقيع.</p>
                @endforelse
            </section>
        @endif
    @endunless

    <nav class="ds-portal-page-nav" aria-label="تنقل صفحات البوابة">
        @if ($prevId)
            <a class="ds-btn" href="{{ $pageUrl($pageKeys[$prevId]) }}">السابق</a>
        @endif
        @if ($nextId)
            <a class="ds-btn ds-btn-primary" href="{{ $pageUrl($pageKeys[$nextId]) }}">التالي</a>
        @endif
    </nav>
</div>
