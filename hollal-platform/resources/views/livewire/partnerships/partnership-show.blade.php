<x-ds-page>
    <x-ds-page-header :title="'ملف الشراكة — '.($partnership->organization?->name ?? $partnership->entity_name ?? '#'.$partnership->id)" />

    <section class="ds-section">
        <p>المرحلة الحالية: <strong>{{ $partnership->stageLabel() }}</strong> — منذ
            <span class="ds-ltr-num">{{ $partnership->stageAgeDays() }}</span> يومًا</p>
        @error('contract') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror
    </section>

    {{-- 05-B3 عروض الأسعار --}}
    <section class="ds-section">
        <h2 class="ds-section-title">عروض الأسعار</h2>
        @can('partnerships.quotes.create')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openQuoteModal">عرض جديد</button>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>النسخة</th><th>الحالة</th><th>المجموع</th><th>الضريبة</th><th>الإجمالي</th><th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($partnership->quotes as $quote)
                <tr wire:key="quote-{{ $quote->id }}">
                    <td class="ds-ltr-num">{{ $quote->version }}</td>
                    <td>{{ $quote->status }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $quote->subtotal, 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $quote->tax_total, 2) }}</td>
                    <td class="ds-ltr-num">{{ number_format((float) $quote->total, 2) }}</td>
                    <td>
                        <a class="ds-btn ds-btn-sm" href="{{ route('quotes.pdf', $quote->id) }}">PDF</a>
                        @can('partnerships.quotes.approve')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="approveQuote({{ $quote->id }})">اعتماد</button>
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="sendQuote({{ $quote->id }})">إرسال</button>
                        @endcan
                        @can('partnerships.quotes.create')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="openQuoteModal({{ $quote->id }})">نسخة معدّلة</button>
                        @endcan
                        @can('partnerships.contracts.create')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="openContractModal({{ $quote->id }})">إنشاء عقد</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد عروض</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    {{-- 05-B4 العقود --}}
    <section class="ds-section">
        <h2 class="ds-section-title">العقود</h2>
        @foreach ($partnership->partnershipContracts as $contract)
            <div class="ds-kanban-card" wire:key="contract-{{ $contract->id }}">
                <p>عقد #{{ $contract->id }} — الحالة: <strong>{{ $contract->status }}</strong></p>
                <p>القيمة: <span class="ds-ltr-num">{{ number_format((float) $contract->total_value, 2) }}</span></p>
                <p>يشترط الدفعة الأولى: {{ $contract->requires_first_payment ? 'نعم' : 'لا' }}</p>
                @if ($contract->signed_pdf_hash)
                    <p class="ds-text-muted" dir="ltr">hash: {{ Str::limit($contract->signed_pdf_hash, 24) }}</p>
                @endif

                <x-ds-table>
                    <x-slot:head>
                        <tr><th>الدفعة</th><th>المبلغ</th><th>الاستحقاق</th><th>المؤكد</th><th>إجراءات</th></tr>
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
                            <td>
                                @can('partnerships.payments.record')
                                    <input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="paymentAmount">
                                    <button type="button" class="ds-btn ds-btn-sm" wire:click="recordPayment({{ $row->id }})">تسجيل دفعة</button>
                                @endcan
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

                @can('partnerships.contracts.confirm')
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="confirmContract({{ $contract->id }})">
                        تأكيد التعاقد
                    </button>
                @endcan
            </div>
        @endforeach
        @if ($partnership->partnershipContracts->isEmpty())
            <p class="ds-text-muted">لا توجد عقود</p>
        @endif
    </section>

    {{-- 05-B6 الدفعات --}}
    <section class="ds-section">
        <h2 class="ds-section-title">الدفعات المسجّلة</h2>
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

    {{-- 05-B5 رابط الجهة --}}
    <section class="ds-section">
        <h2 class="ds-section-title">رابط الجهة الفريد</h2>
        @can('partnerships.links.manage')
            <button type="button" class="ds-btn" wire:click="issueLink">إصدار رابط</button>
        @endcan
        <x-ds-table>
            <x-slot:head>
                <tr><th>الرابط</th><th>الصلاحية</th><th>الحالة</th><th>آخر استخدام</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($partnership->links as $link)
                <tr wire:key="link-{{ $link->id }}">
                    <td dir="ltr">{{ Str::limit($link->token, 16) }}</td>
                    <td dir="ltr">{{ $link->expires_at?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $link->isUsable() ? 'فعّال' : 'منتهٍ/مُبطل' }}</td>
                    <td dir="ltr">{{ $link->last_used_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        @can('partnerships.links.manage')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="revokeLink({{ $link->id }})">إبطال</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد روابط</td></tr>
            @endforelse
        </x-ds-table>
    </section>

    {{-- 05-B7 توليد مشروع --}}
    <section class="ds-section">
        <h2 class="ds-section-title">توليد مشروع</h2>
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
        <x-slot:header><h2>إنشاء عقد</h2></x-slot:header>

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
