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
        @error('links') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror
    </section>

    <div class="ds-workspace">
    <h2 class="ds-section-title">مساحة العمل</h2>
    <p class="ds-text-muted">أدوات الملف — ليست مراحل الرحلة. اختر خطوة للعمل عليها دون قفل.</p>
    <ol class="ds-journey-steps">
        <li class="{{ $workspaceStep === 1 ? 'is-current' : '' }}">
            <button type="button" class="ds-pill {{ $workspaceStep === 1 ? 'is-selected' : '' }}" wire:click="openWorkspace(1)">1 عروض الأسعار</button>
        </li>
        <li class="{{ $workspaceStep === 2 ? 'is-current' : '' }}">
            <button type="button" class="ds-pill {{ $workspaceStep === 2 ? 'is-selected' : '' }}" wire:click="openWorkspace(2)">2 عقد الشراكة</button>
        </li>
        <li class="{{ $workspaceStep === 3 ? 'is-current' : '' }}">
            <button type="button" class="ds-pill {{ $workspaceStep === 3 ? 'is-selected' : '' }}" wire:click="openWorkspace(3)">3 الدفعات</button>
        </li>
        <li class="{{ $workspaceStep === 4 ? 'is-current' : '' }}">
            <button type="button" class="ds-pill {{ $workspaceStep === 4 ? 'is-selected' : '' }}" wire:click="openWorkspace(4)">4 رابط الجهة</button>
        </li>
        <li class="{{ $workspaceStep === 5 ? 'is-current' : '' }}">
            <button type="button" class="ds-pill {{ $workspaceStep === 5 ? 'is-selected' : '' }}" wire:click="openWorkspace(5)">5 توليد مشروع</button>
        </li>
    </ol>

    <section id="step-quotes" class="ds-section {{ $workspaceStep === 1 ? 'ds-journey-active' : '' }}" @if ($workspaceStep !== 1) hidden @endif>
        <h2 class="ds-section-title">1. عروض الأسعار</h2>
        <p class="ds-text-muted">المسودة تُعتمد داخليًا ثم نهائيًا. إصدار الرابط هو الإرسال، والعرض يظهر للجهة بعد الاعتماد النهائي.</p>
        <p class="ds-text-muted">الفاتورة الضريبية ليست العرض: تُصدر من خطوة الدفعات بعد تأكيد المالية عبر زر إصدار فاتورة.</p>
        @can('partnerships.quotes.create')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openQuoteModal">عرض جديد</button>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>النسخة</th><th>الحالة</th><th>ملاحظات الجهة</th><th>الإجمالي</th><th>إجراءات</th>
                </tr>
            </x-slot:head>
            @php $supersededIds = $partnership->quotes->pluck('supersedes_id')->filter()->all(); @endphp
            @forelse ($partnership->quotes as $quote)
                <tr wire:key="quote-{{ $quote->id }}" class="{{ in_array($quote->id, $supersededIds, true) ? 'ds-quote-row-superseded' : '' }}">
                    <td class="ds-ltr-num">{{ $quote->version }}</td>
                    <td>
                        @if (in_array($quote->id, $supersededIds, true))
                            <span class="ds-badge">مسودة سابقة</span>
                        @endif
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
                            <a class="ds-btn ds-btn-sm" href="{{ route('quotes.pdf', $quote->id, false) }}?print=1" target="_blank" rel="noopener">معاينة</a>
                            <a class="ds-btn ds-btn-sm" href="{{ route('quotes.pdf', $quote->id, false) }}" download>تنزيل</a>
                            @can('partnerships.quotes.approve')
                                @if ($quote->status === \App\Models\Quote::STATUS_DRAFT || $quote->status === \App\Models\Quote::STATUS_WITH_NOTES)
                                    <button type="button" class="ds-btn ds-btn-sm {{ auth()->user()->can('partnerships.quotes.finalize') ? 'ds-btn-primary' : '' }}" wire:click="approveQuote({{ $quote->id }})">
                                        {{ auth()->user()->can('partnerships.quotes.finalize') ? 'اعتماد نهائي' : 'اعتماد داخلي' }}
                                    </button>
                                @endif
                                @if ($quote->status === \App\Models\Quote::STATUS_PENDING_FINAL && auth()->user()->can('partnerships.quotes.finalize'))
                                    <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="approveQuote({{ $quote->id }})">اعتماد نهائي</button>
                                    <input type="text" class="ds-input" wire:model="internalApprovalNotes" placeholder="ملاحظة الإرجاع">
                                    <button type="button" class="ds-btn ds-btn-sm" wire:click="returnQuote({{ $quote->id }})">إرجاع للمسودة</button>
                                @endif
                            @endcan
                            @if ($quote->status === \App\Models\Quote::STATUS_APPROVED)
                                <span class="ds-text-muted">ظاهر على رابط الجهة</span>
                            @endif
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

    @php $acceptedQuote = $partnership->quotes->firstWhere('status', \App\Models\Quote::STATUS_ACCEPTED); @endphp
    <section id="step-contract" class="ds-section {{ $workspaceStep === 2 ? 'ds-journey-active' : '' }}" @if ($workspaceStep !== 2) hidden @endif>
        <h2 class="ds-section-title">2. عقد الشراكة</h2>
        <p class="ds-text-muted">قبول الجهة للعرض ينشئ عقدًا بانتظار التوقيع تلقائيًا. يمكن إنشاء عقد يدوي من عرض مقبول وتحديد جدول الدفعات.</p>
        @can('partnerships.contracts.create')
            @if ($acceptedQuote)
                <button type="button" class="ds-btn ds-btn-primary" wire:click="openContractModal({{ $acceptedQuote->id }})">إنشاء عقد</button>
            @endif
        @endcan
        @foreach ($partnership->partnershipContracts as $contract)
            <div class="ds-kanban-card" wire:key="contract-{{ $contract->id }}">
                <p>عقد #{{ $contract->id }} — الحالة: <strong>{{ $contract->status }}</strong></p>
                <p>القيمة: <span class="ds-ltr-num">{{ number_format((float) $contract->total_value, 2) }}</span></p>
                <p>يشترط الدفعة الأولى: {{ $contract->requires_first_payment ? 'نعم' : 'لا' }}</p>
                @if ($contract->hasSignedCopy())
                    <p class="ds-text-muted">جدول الدفعات مقفل بعد التوقيع.</p>
                @endif
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
            <p class="ds-text-muted">{{ $acceptedQuote ? 'لا يوجد عقد بعد — استخدم إنشاء عقد أو اطلب من الجهة قبول العرض ليُنشأ تلقائيًا.' : 'لا يوجد عقد — اقبل عرضًا أولًا.' }}</p>
        @endif
    </section>

    <section id="step-payments" class="ds-section {{ $workspaceStep === 3 ? 'ds-journey-active' : '' }}" @if ($workspaceStep !== 3) hidden @endif>
        <h2 class="ds-section-title">3. الدفعات</h2>
        <p class="ds-text-muted">فريق حلل يحدد جدول الدفعات عند إنشاء العقد. الجهة ترفع الإثبات، والمالية تؤكد ثم تصدر الفاتورة.</p>
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

    <section id="step-link" class="ds-section {{ $workspaceStep === 4 ? 'ds-journey-active' : '' }}" @if ($workspaceStep !== 4) hidden @endif>
        <h2 class="ds-section-title">4. رابط الجهة الفريد</h2>
        @can('partnerships.links.manage')
            <div class="ds-form-row">
                <x-ds-form-group label="مدة الرابط بالأيام (الافتراضي {{ $linkDefaultDays }})" :error="$errors->first('linkExpiryDays')">
                    <input type="number" min="1" max="365" class="ds-input ds-ltr-num" wire:model="linkExpiryDays">
                </x-ds-form-group>
                <button type="button" class="ds-btn" wire:click="issueLink">إصدار رابط</button>
            </div>
            <div class="ds-section-spaced">
                <h3 class="ds-section-heading">ما يظهر للجهة على رابطها</h3>
                <p class="ds-text-muted">فعّل الأقسام المطلوبة ثم احفظ. لا يغيّر المرحلة؛ يتحكم فقط بما تراه الجهة.</p>
                <div class="ds-portal-feature-grid">
                    <label class="ds-portal-feature-card">
                        <span><input type="checkbox" wire:model="portalFeatures.programs"> <strong>البرامج</strong></span>
                        <small>اختيار البرامج والكميات من الكتالوج</small>
                    </label>
                    <label class="ds-portal-feature-card">
                        <span><input type="checkbox" wire:model="portalFeatures.diagnosis"> <strong>التشخيص</strong></span>
                        <small>استبانة الاحتياج</small>
                    </label>
                    <label class="ds-portal-feature-card">
                        <span><input type="checkbox" wire:model="portalFeatures.quotes"> <strong>عروض الأسعار</strong></span>
                        <small>المسودة والقبول</small>
                    </label>
                    <label class="ds-portal-feature-card">
                        <span><input type="checkbox" wire:model="portalFeatures.payments"> <strong>الدفعات</strong></span>
                        <small>المستحق وإثبات التحويل</small>
                    </label>
                    <label class="ds-portal-feature-card">
                        <span><input type="checkbox" wire:model="portalFeatures.contract"> <strong>العقد</strong></span>
                        <small>التنزيل والتوقيع الإلكتروني</small>
                    </label>
                </div>
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
                            @if (! $link->isUsable())
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="deleteLink({{ $link->id }})">حذف</button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد روابط</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    <section id="step-generate" class="ds-section {{ $workspaceStep === 5 ? 'ds-journey-active' : '' }}" @if ($workspaceStep !== 5) hidden @endif>
        <h2 class="ds-section-title">5. توليد مشروع</h2>
        <p class="ds-text-muted">التوليد يدوي بعد تأكيد العقد. لا يحدث تلقائيًا بعد الدفع. يمكن التأكيد دون دفعة إذا أُلغي شرط الدفعة الأولى.</p>
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
