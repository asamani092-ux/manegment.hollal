<div dir="rtl" class="ds-page-rtl ds-portal-steps">
    <h1 class="ds-page-title">بوابة الجهة — {{ $partnership->organization?->name ?? $partnership->entity_name ?? '' }}</h1>
    <p class="ds-text-muted">المرحلة الحالية: {{ $partnership->stageLabel() }}</p>

    <section class="ds-section">
        <h2 class="ds-section-title">اختيار البرامج والكميات</h2>
        <x-ds-table>
            <x-slot:head>
                <tr><th>اختيار</th><th>البرنامج</th><th>الخدمة</th><th>الكمية</th><th>سعر الوحدة</th></tr>
            </x-slot:head>
            @forelse ($programs as $program)
                <tr wire:key="portal-program-{{ $program->id }}">
                    <td>
                        <input type="checkbox" value="{{ $program->id }}" wire:model.live="selectedProgramIds">
                    </td>
                    <td>
                        <strong>{{ $program->name }}</strong>
                        <small class="ds-text-muted">{{ $program->target_audience ?? '—' }}</small>
                    </td>
                    <td>
                        <select class="ds-input" wire:model.live="programServices.{{ $program->id }}">
                            @foreach ($program->prices as $price)
                                <option value="{{ $price->service_type }}">{{ $price->service_type }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" min="0.01" step="0.01" class="ds-input ds-ltr-num"
                               wire:model.live="programQuantities.{{ $program->id }}">
                    </td>
                    <td class="ds-ltr-num">
                        {{ number_format((float) ($program->prices->firstWhere('service_type', $programServices[$program->id] ?? null)?->unit_price ?? $program->prices->first()?->unit_price ?? 0), 2) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد برامج متاحة حاليًا</td></tr>
            @endforelse
        </x-ds-table>
        @if ($quotes->isNotEmpty())
            <p class="ds-text-muted">تُحتسب الضريبة من إعدادات المنصة بعد حفظ الاختيار.</p>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveProgramSelection({{ $quotes->first()->id }})">
                تحديث العرض والمعاينة
            </button>
        @endif
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
                @foreach ($quote->items as $item)
                    <p class="ds-text-muted">
                        {{ $item->description }} × <span class="ds-ltr-num">{{ number_format((float) $item->quantity, 2) }}</span>
                        = <span class="ds-ltr-num">{{ number_format((float) $item->line_total, 2) }}</span>
                    </p>
                @endforeach
                <p>الإجمالي شامل الضريبة:
                    <span class="ds-ltr-num">{{ number_format((float) $quote->total, 2) }}</span></p>
                @if ($quote->status !== \App\Models\Quote::STATUS_ACCEPTED)
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="acceptQuote({{ $quote->id }})">قبول العرض</button>
                @endif
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
                    <p class="ds-text-muted">
                        تم التوقيع ({{ $contract->signature_method ?? '—' }}) — الحالة: {{ $contract->status }}
                    </p>
                @else
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
                        <h3 class="ds-section-title">التوقيع الإلكتروني داخل الرابط</h3>
                        <canvas x-ref="pad" width="400" height="160" class="ds-input" style="touch-action: none; background: #E7EEF1; max-width: 100%;" wire:ignore></canvas>
                        @error('signaturePadData') <small class="ds-error">{{ $message }}</small> @enderror
                        <div class="ds-filter-bar">
                            <button type="button" class="ds-btn" @click="clear()">مسح اللوحة</button>
                            <button type="button" class="ds-btn ds-btn-primary" @click="submitEsign()">اعتماد التوقيع الإلكتروني</button>
                        </div>
                    </div>

                    <h3 class="ds-section-title">أو الرفع اليدوي</h3>
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
