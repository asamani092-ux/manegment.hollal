<x-ds-page>
    <x-ds-page-header
        :title="'ملف الشراكة — '.($partnership->organization?->name ?? $partnership->entity_name ?? '#'.$partnership->id)"
        :back-url="$returnTo === 'organization' && $partnership->organization_id
            ? route('organizations.show', $partnership->organization_id)
            : route('partnerships.pipeline')"
        back-label="{{ $returnTo === 'organization' ? 'رجوع لملف الجهة' : 'رجوع لرحلة الشراكات' }}" />

    <section class="ds-section">
        <h2 class="ds-section-title">دورة حياة الرحلة</h2>
        <p>المرحلة الحالية: <strong>{{ $partnership->stageLabel() }}</strong> — منذ
            <span class="ds-ltr-num">{{ $partnership->stageAgeDays() }}</span> يومًا</p>
        @php
            $lifeStage = (int) $partnership->stage;
            $lifeInPipeline = in_array($lifeStage, \App\Models\Partnership::PIPELINE_STAGES, true);
        @endphp
        <ol class="ds-journey-steps" aria-label="دورة حياة الشراكة">
            @foreach (\App\Models\Partnership::PIPELINE_STAGES as $code)
                <li class="{{ $lifeStage === $code ? 'is-current' : ($lifeInPipeline && $lifeStage > $code ? 'is-done' : '') }}">
                    {{ \App\Models\Partnership::STAGE_LABELS[$code] }}
                </li>
            @endforeach
            @if (! $lifeInPipeline)
                <li class="is-current">{{ $partnership->stageLabel() }}</li>
            @endif
        </ol>
        @if ($partnership->awaiting_internal_approval)
            <p class="ds-badge ds-badge-warning">بانتظار الاعتماد النهائي</p>
        @elseif ($partnership->internal_approval_notes)
            <p class="ds-badge ds-badge-info">ملاحظات الجهة: {{ $partnership->internal_approval_notes }}</p>
        @endif
        @error('contract') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror
        @error('quote') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror
    </section>

    <div class="ds-workspace"
         x-data="{ step: {{ (int) $workspaceStep }} }"
         @open-workspace.window="step = Number(($event.detail && $event.detail.step) || $event.detail[0] || step)">
    <h2 class="ds-section-title">مساحة العمل</h2>
    <p class="ds-text-muted">أدوات الملف — ليست مراحل الرحلة. اختر خطوة للعمل عليها دون قفل.</p>
    <ol class="ds-journey-steps">
        <li :class="step === 1 ? 'is-current' : ''">
            <button type="button" class="ds-pill" :class="step === 1 ? 'is-selected' : ''" @click="step = 1">1 عروض الأسعار</button>
        </li>
        <li :class="step === 2 ? 'is-current' : ''">
            <button type="button" class="ds-pill" :class="step === 2 ? 'is-selected' : ''" @click="step = 2">2 عقد الشراكة</button>
        </li>
        <li :class="step === 3 ? 'is-current' : ''">
            <button type="button" class="ds-pill" :class="step === 3 ? 'is-selected' : ''" @click="step = 3">3 الدفعات</button>
        </li>
        <li :class="step === 4 ? 'is-current' : ''">
            <button type="button" class="ds-pill" :class="step === 4 ? 'is-selected' : ''" @click="step = 4">4 رابط الجهة</button>
        </li>
        <li :class="step === 5 ? 'is-current' : ''">
            <button type="button" class="ds-pill" :class="step === 5 ? 'is-selected' : ''" @click="step = 5">5 توليد مشروع</button>
        </li>
    </ol>

    <section id="step-quotes" class="ds-section ds-journey-active" x-show="step === 1" x-cloak>
        <h2 class="ds-section-title">1. عروض الأسعار</h2>
        <p class="ds-text-muted">الجهة تبني المسودة من البوابة حسب أسعار البرامج وتقبلها دون إرسال. التعديل الداخلي يصدر نسخة جديدة ويبقي القديمة.</p>
        @can('partnerships.quotes.create')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openQuoteModal">عرض جديد</button>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>النسخة</th><th>الحالة</th><th>ملاحظات الجهة</th><th>الإجمالي</th><th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($partnership->quotes as $quote)
                <tr wire:key="quote-{{ $quote->id }}">
                    <td class="ds-ltr-num">{{ $quote->version }}</td>
                    <td>
                        @if ($quote->status === \App\Models\Quote::STATUS_WITH_NOTES)
                            <span class="ds-badge ds-badge-warning">بملاحظات</span>
                        @else
                            {{ $quote->status }}
                        @endif
                    </td>
                    <td>{{ $quote->entity_notes ?: '—' }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $quote->total, 2) }}</td>
                    <td>
                        <div class="ds-quote-actions">
                            <a class="ds-btn ds-btn-sm" href="{{ route('quotes.pdf', $quote->id) }}?print=1" target="_blank" rel="noopener">معاينة</a>
                            <a class="ds-btn ds-btn-sm" href="{{ route('quotes.pdf', $quote->id) }}">تنزيل</a>
                            @can('partnerships.quotes.approve')
                                @if ($quote->status === \App\Models\Quote::STATUS_DRAFT || $quote->status === \App\Models\Quote::STATUS_WITH_NOTES)
                                    <button type="button" class="ds-btn ds-btn-sm" wire:click="approveQuote({{ $quote->id }})">اعتماد داخلي</button>
                                @endif
                                @if ($quote->status === \App\Models\Quote::STATUS_APPROVED)
                                    <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="sendQuote({{ $quote->id }})">إرسال للجهة</button>
                                @endif
                            @endcan
                            @can('partnerships.quotes.create')
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="openQuoteModal({{ $quote->id }})">تعديل وإصدار نسخة</button>
                            @endcan
                            @can('partnerships.contracts.create')
                                @if ($quote->status === \App\Models\Quote::STATUS_ACCEPTED)
                                    <button type="button" class="ds-btn ds-btn-sm" wire:click="openContractModal({{ $quote->id }})">إنشاء عقد</button>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد عروض</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section id="step-contract" class="ds-section ds-journey-active" x-show="step === 2" x-cloak>
        <h2 class="ds-section-title">2. عقد الشراكة</h2>
        <p class="ds-text-muted">التوقيع والتأكيد هنا. تسجيل الدفعات في خطوة الدفعات.</p>
        @foreach ($partnership->partnershipContracts as $contract)
            <div class="ds-kanban-card" wire:key="contract-{{ $contract->id }}">
                <p>عقد #{{ $contract->id }} — الحالة: <strong>{{ $contract->status }}</strong></p>
                <p>القيمة: <span class="ds-ltr-num">{{ number_format((float) $contract->total_value, 2) }}</span></p>
                <p>يشترط الدفعة الأولى: {{ $contract->requires_first_payment ? 'نعم' : 'لا' }}</p>
                @if ($contract->signed_pdf_hash)
                    <p class="ds-text-muted" dir="ltr">بصمة الملف: {{ Str::limit($contract->signed_pdf_hash, 24) }}</p>
                @endif
                @if ($partnership->awaiting_internal_approval)
                    <p class="ds-badge ds-badge-warning">بانتظار الاعتماد النهائي من PM/EM</p>
                @endif

                <x-ds-table>
                    <x-slot:head>
                        <tr><th>الدفعة</th><th>المبلغ</th><th>الاستحقاق</th><th>المؤكد</th></tr>
                    </x-slot:head>
                    @foreach ($contract->schedule as $row)
                        <tr wire:key="schedule-{{ $row->id }}">
                            <td>{{ $row->label }}</td>
                            <td class="ds-ltr-num">{{ number_format((float) $row->amount, 2) }}</td>
                            <td dir="ltr">{{ $row->due_on->format('Y-m-d') }}</td>
                            <td class="ds-ltr-num">
                                {{ number_format($row->confirmedAmount(), 2) }}
                                @if ($row->isLate())
                                    <span class="ds-badge ds-badge-danger">متأخرة</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ds-table>

                @can('partnerships.contracts.manage')
                    <x-ds-form-group label="اسم الموقّع" :error="$errors->first('signatureName')">
                        <input type="text" class="ds-input" wire:model="signatureName">
                    </x-ds-form-group>
                    <x-ds-form-group label="النسخة الموقعة (PDF)" :error="$errors->first('signedCopy')">
                        <input type="file" class="ds-input" wire:model="signedCopy">
                    </x-ds-form-group>
                    <button type="button" class="ds-btn" wire:click="uploadSignedCopy({{ $contract->id }})">رفع النسخة الموقعة</button>
                @endcan

                @if (auth()->user()->can('partnerships.contracts.confirm') || auth()->user()->can('projects.update') || auth()->user()->hasAnyRole(['Super Admin', 'Executive Manager']))
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="confirmContract({{ $contract->id }})">
                        تأكيد التعاقد
                    </button>
                    @if ($partnership->awaiting_internal_approval)
                        <x-ds-form-group label="ملاحظات الإرجاع" :error="$errors->first('internalApprovalNotes')">
                            <textarea class="ds-input" wire:model="internalApprovalNotes"></textarea>
                        </x-ds-form-group>
                        <button type="button" class="ds-btn" wire:click="returnContract({{ $contract->id }})">
                            إعادة للجهة بملاحظات
                        </button>
                    @endif
                @endif
            </div>
        @endforeach
        @if ($partnership->partnershipContracts->isEmpty())
            <p class="ds-text-muted">لا يوجد عقد شراكة — أنشئه من عرض مقبول في خطوة العروض.</p>
        @endif
    </section>

    <section id="step-payments" class="ds-section ds-journey-active" x-show="step === 3" x-cloak>
        <h2 class="ds-section-title">3. الدفعات</h2>
        <p class="ds-text-muted">جدول الاستحقاق وتسجيل الدفعة ثم تأكيد المالية.</p>
        <x-ds-table>
            <x-slot:head>
                <tr><th>الدفعة</th><th>المبلغ</th><th>الاستحقاق</th><th>المؤكد</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($partnership->partnershipContracts->flatMap->schedule as $row)
                <tr wire:key="pay-schedule-{{ $row->id }}">
                    <td>{{ $row->label }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $row->amount, 2) }}</td>
                    <td dir="ltr">{{ $row->due_on->format('Y-m-d') }}</td>
                    <td class="ds-ltr-num">
                        {{ number_format($row->confirmedAmount(), 2) }}
                        @if ($row->isLate())
                            <span class="ds-badge ds-badge-danger">متأخرة</span>
                        @endif
                    </td>
                    <td>
                        @can('partnerships.payments.record')
                            <div class="ds-quote-actions">
                                <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="paymentAmount">
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="recordPayment({{ $row->id }})">تسجيل دفعة</button>
                            </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا يوجد جدول دفعات</td></tr>
            @endforelse
        </x-ds-table>
        <h3 class="ds-section-heading">المسجّلة</h3>
        <x-ds-table>
            <x-slot:head>
                <tr><th>المبلغ</th><th>التاريخ</th><th>الحالة</th><th>المصدر</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($partnership->payments as $payment)
                <tr wire:key="payment-{{ $payment->id }}">
                    <td class="ds-ltr-num">{{ number_format((float) $payment->amount, 2) }}</td>
                    <td dir="ltr">{{ $payment->paid_on?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $payment->status }}</td>
                    <td>{{ $payment->recorded_via }}</td>
                    <td>
                        @can('partnerships.payments.confirm')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="confirmPayment({{ $payment->id }})">تأكيد المالية</button>
                        @endcan
                        @can('finance.tax_invoices.issue')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="issueInvoice({{ $payment->id }})">إصدار فاتورة</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد دفعات</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section id="step-link" class="ds-section ds-journey-active" x-show="step === 4" x-cloak>
        <h2 class="ds-section-title">4. رابط الجهة الفريد</h2>
        @can('partnerships.links.manage')
            <button type="button" class="ds-btn" wire:click="issueLink">إصدار رابط</button>
            <div class="ds-section-spaced">
                <h3 class="ds-section-heading">ما يظهر للجهة على رابطها</h3>
                <p class="ds-text-muted">أخفِ قسمًا إن لم يحن وقته. لا يغيّر المرحلة؛ يتحكم فقط بما تراه الجهة.</p>
                <label class="ds-checkbox"><input type="checkbox" wire:model="portalFeatures.programs"> البرامج</label>
                <label class="ds-checkbox"><input type="checkbox" wire:model="portalFeatures.diagnosis"> التشخيص</label>
                <label class="ds-checkbox"><input type="checkbox" wire:model="portalFeatures.quotes"> عروض الأسعار</label>
                <label class="ds-checkbox"><input type="checkbox" wire:model="portalFeatures.payments"> الدفعات</label>
                <label class="ds-checkbox"><input type="checkbox" wire:model="portalFeatures.contract"> العقد</label>
                <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="savePortalFeatures">حفظ التحكم</button>
            </div>
        @endcan
        <div class="ds-section-spaced">
            <h3 class="ds-section-heading">ما حفظته الجهة من البوابة</h3>
            <p class="ds-text-muted">البرامج المختارة: {{ $partnership->allowedPrograms->pluck('name')->join('، ') ?: '—' }}</p>
            @forelse ($diagnosisSnapshot as $answer)
                <p>{{ $answer['label'] }}: {{ $answer['value'] }}</p>
            @empty
                <p class="ds-text-muted">لم تُرسل استبانة التشخيص بعد.</p>
            @endforelse
        </div>
        <x-ds-table>
            <x-slot:head>
                <tr><th>الرابط</th><th>الصلاحية</th><th>الحالة</th><th>آخر استخدام</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($partnership->links as $link)
                <tr wire:key="link-{{ $link->id }}">
                    <td dir="ltr">
                        <span x-data="{ copied: false }">
                            <input class="ds-input" readonly value="{{ app(\App\Services\PartnerPortalService::class)->portalUrl($link->token) }}"
                                   x-ref="url">
                            <button type="button" class="ds-btn ds-btn-sm"
                                    @click="navigator.clipboard.writeText($refs.url.value); copied = true">
                                <span x-text="copied ? 'تم النسخ' : 'نسخ الرابط الكامل'"></span>
                            </button>
                        </span>
                    </td>
                    <td dir="ltr">{{ $link->expires_at?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $link->isUsable() ? 'فعّال' : 'منتهٍ/مُبطل' }}</td>
                    <td dir="ltr">{{ hollal_dt($link->last_used_at) }}</td>
                    <td>
                        @can('partnerships.links.manage')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="sendLinkEmail({{ $link->id }})">إرسال بالبريد</button>
                            <button type="button" class="ds-btn ds-btn-sm"
                                    x-data
                                    @click="navigator.clipboard.writeText(@js(app(\App\Services\PartnerPortalService::class)->whatsappText($link)))">
                                نسخ واتساب
                            </button>
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="revokeLink({{ $link->id }})">إبطال</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد روابط</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section id="step-generate" class="ds-section ds-journey-active" x-show="step === 5" x-cloak>
        <h2 class="ds-section-title">5. توليد مشروع</h2>
        @can('partnerships.generate')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openGenerateModal">توليد مشروع</button>
        @endcan
        @error('generateProgramId') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror

        <x-ds-table>
            <x-slot:head>
                <tr><th>البرنامج</th><th>الخدمات المشمولة</th><th>تاريخ الانطلاق</th><th>الحالة</th></tr>
            </x-slot:head>
            @forelse ($partnership->generationRequests as $request)
                <tr wire:key="gen-{{ $request->id }}">
                    <td>{{ $request->program?->name ?? '—' }}</td>
                    <td>{{ implode('، ', $request->included_services ?: ['—']) }}</td>
                    <td dir="ltr">{{ $request->launch_date?->format('Y-m-d') }}</td>
                    <td>{{ $request->status }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="ds-text-muted ds-table-empty">لا توجد طلبات توليد</td></tr>
            @endforelse
        </x-ds-table>
    </section>
    </div>

    <x-ds-modal :show="$showQuoteModal" size="lg">
        <x-slot:header><h2>{{ $revisingQuoteId ? 'نسخة معدّلة من العرض' : 'عرض سعر جديد' }}</h2></x-slot:header>

        @foreach ($quoteLines as $index => $line)
            <div class="ds-form-row" wire:key="quote-line-{{ $index }}">
                <x-ds-form-group label="البرنامج">
                    <select class="ds-input" wire:model="quoteLines.{{ $index }}.program_id">
                        <option value="">—</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </select>
                </x-ds-form-group>
                <x-ds-form-group label="الخدمة" :error="$errors->first('quoteLines.'.$index.'.service_type')">
                    <select class="ds-input" wire:model="quoteLines.{{ $index }}.service_type">
                        @foreach ($services as $service)
                            <option value="{{ $service }}">{{ $service }}</option>
                        @endforeach
                    </select>
                </x-ds-form-group>
                <x-ds-form-group label="الكمية" :error="$errors->first('quoteLines.'.$index.'.quantity')">
                    <input type="number" step="0.01" class="ds-input" wire:model="quoteLines.{{ $index }}.quantity">
                </x-ds-form-group>
                <x-ds-form-group label="سعر الوحدة (فارغ = من بطاقة البرنامج)">
                    <input type="number" step="0.01" class="ds-input" wire:model="quoteLines.{{ $index }}.unit_price">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-sm" wire:click="removeQuoteLine({{ $index }})">حذف</button>
            </div>
        @endforeach

        <button type="button" class="ds-btn ds-btn-sm" wire:click="addQuoteLine">إضافة بند</button>

        <x-ds-form-group label="الخصم" :error="$errors->first('quoteDiscount')">
            <input type="number" step="0.01" class="ds-input" wire:model="quoteDiscount">
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showQuoteModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveQuote">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>

    <x-ds-modal :show="$showContractModal" size="lg">
        <x-slot:header><h2>إنشاء عقد شراكة</h2></x-slot:header>

        @foreach ($scheduleRows as $index => $row)
            <div class="ds-form-row" wire:key="schedule-row-{{ $index }}">
                <x-ds-form-group label="اسم الدفعة">
                    <input type="text" class="ds-input" wire:model="scheduleRows.{{ $index }}.label">
                </x-ds-form-group>
                <x-ds-form-group label="المبلغ" :error="$errors->first('scheduleRows.'.$index.'.amount')">
                    <input type="number" step="0.01" class="ds-input" wire:model="scheduleRows.{{ $index }}.amount">
                </x-ds-form-group>
                <x-ds-form-group label="تاريخ الاستحقاق" :error="$errors->first('scheduleRows.'.$index.'.due_on')">
                    <input type="date" class="ds-input" wire:model="scheduleRows.{{ $index }}.due_on" dir="ltr">
                </x-ds-form-group>
            </div>
        @endforeach

        <button type="button" class="ds-btn ds-btn-sm" wire:click="addScheduleRow">إضافة دفعة</button>

        <x-ds-form-group label="يشترط تأكيد الدفعة الأولى للتعاقد">
            <input type="checkbox" wire:model="requiresFirstPayment">
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showContractModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveContract">إنشاء</button>
        </x-slot:footer>
    </x-ds-modal>

    <x-ds-modal :show="$showGenerateModal">
        <x-slot:header><h2>توليد مشروع من الشراكة</h2></x-slot:header>

        <x-ds-form-group label="البرنامج" :error="$errors->first('generateProgramId')">
            <select class="ds-input" wire:model="generateProgramId">
                <option value="">—</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}">{{ $program->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="تاريخ الانطلاق" :error="$errors->first('generateLaunchDate')">
            <input type="date" class="ds-input" wire:model="generateLaunchDate" dir="ltr">
        </x-ds-form-group>

        <x-ds-form-group label="مدير المشروع المقترح" :error="$errors->first('generateManagerId')">
            <select class="ds-input" wire:model="generateManagerId">
                <option value="">—</option>
                @foreach ($managers as $manager)
                    <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showGenerateModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="generateProject">توليد</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
