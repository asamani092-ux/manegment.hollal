<x-ds-page>
    <x-ds-page-header title="الفواتير الضريبية">
        <x-slot:actions>
            @can('finance.tax_invoices.issue')
                <button type="button" class="ds-btn ds-btn-outline" wire:click="openTemplatesModal">
                    <i class="fas fa-file-image"></i> قوالب الفواتير
                </button>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="openIssueModal">
                    <i class="fas fa-plus"></i> إصدار فاتورة
                </button>
            @endcan
        </x-slot:actions>
    </x-ds-page-header>

    <div class="ds-card ds-mb-3" style="padding:.85rem 1rem">
        <p class="ds-text-muted" style="margin:0 0 .35rem">وضع الفوترة الحالي: <strong>{{ $mode }}</strong></p>
        <p style="margin:0"><strong>{{ $seller['name'] }}</strong> — الرقم الضريبي: <span class="ds-ltr-num">{{ $seller['vat_number'] ?: '—' }}</span>
            @if ($seller['commercial_register'])
                — السجل التجاري: <span class="ds-ltr-num">{{ $seller['commercial_register'] }}</span>
            @endif
        </p>
        @if ($seller['address'])
            <p class="ds-text-muted" style="margin:.25rem 0 0">{{ $seller['address'] }}</p>
        @endif
        <p class="ds-help-text" style="margin:.35rem 0 0">بيانات الشركة تُقرأ من إعدادات المنصة وتظهر تلقائيًا على كل فاتورة.</p>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الرقم</th>
                <th>النوع</th>
                <th>المشتري</th>
                <th>قبل الضريبة</th>
                <th>الضريبة</th>
                <th>الإجمالي</th>
                <th>تاريخ الإصدار</th>
                <th>الإشعارات</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($invoices as $invoice)
            <tr wire:key="tax-invoice-{{ $invoice->id }}">
                <td dir="ltr">{{ $invoice->number }}</td>
                <td>{{ $invoice->invoice_type }}</td>
                <td>{{ $invoice->buyer_name }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $invoice->vat_total, 2) }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $invoice->total, 2) }}</td>
                <td dir="ltr">{{ $invoice->issued_at?->format('Y-m-d') }}</td>
                <td class="ds-ltr-num">{{ $invoice->notes_count }}</td>
                <td>
                    <a class="ds-btn ds-btn-sm" href="{{ route('tax-invoices.pdf', ['taxInvoice' => $invoice->id, 'print' => 1]) }}" target="_blank" rel="noopener">طباعة</a>
                    @can('finance.tax_invoices.issue')
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="openNoteModal({{ $invoice->id }})">
                            إشعار دائن/مدين
                        </button>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="ds-text-muted ds-table-empty">لا توجد فواتير ضريبية</td></tr>
        @endforelse
    </x-ds-table>

    {{ $invoices->links() }}

    <x-ds-modal :show="$showIssueModal" title="إصدار فاتورة ضريبية" size="lg">
        <x-slot:header><h2>إصدار فاتورة ضريبية</h2></x-slot:header>

        <x-ds-form-group label="العميل" :error="$errors->first('buyerName')">
            <select class="ds-input" wire:model.live="buyerSource">
                <option value="جديد">جديد</option>
                <option value="جهة">من الجهات</option>
            </select>
        </x-ds-form-group>
        @if ($buyerSource === 'جهة')
            <x-ds-form-group label="الجهة">
                <select class="ds-input" wire:model.live="organizationId">
                    <option value="">— اختر جهة —</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}@if ($org->tax_number) — {{ $org->tax_number }}@endif</option>
                    @endforeach
                </select>
            </x-ds-form-group>
        @endif
        <x-ds-form-group label="اسم المشتري" :error="$errors->first('buyerName')">
            <input type="text" class="ds-input" wire:model="buyerName">
        </x-ds-form-group>

        <x-ds-form-group label="الرقم الضريبي للمشتري" :error="$errors->first('buyerVatNumber')">
            <input type="text" class="ds-input" wire:model="buyerVatNumber" dir="ltr">
        </x-ds-form-group>

        <x-ds-form-group label="نوع الفاتورة" :error="$errors->first('invoiceType')">
            <select class="ds-input" wire:model="invoiceType">
                @foreach ($invoiceTypes as $type)
                    <option value="{{ $type }}">{{ $type === 'مبسطة' ? 'مبسطة (نقاط بيع/أفراد)' : 'ضريبية كاملة (منشآت)' }}</option>
                @endforeach
            </select>
        </x-ds-form-group>

        @foreach ($lines as $index => $line)
            <div class="ds-form-row" wire:key="line-{{ $index }}">
                <x-ds-form-group label="الوصف" :error="$errors->first('lines.'.$index.'.description')">
                    <input type="text" class="ds-input" wire:model="lines.{{ $index }}.description">
                </x-ds-form-group>
                <x-ds-form-group label="الكمية" :error="$errors->first('lines.'.$index.'.quantity')">
                    <input type="number" step="0.01" class="ds-input" wire:model="lines.{{ $index }}.quantity">
                </x-ds-form-group>
                <x-ds-form-group label="سعر الوحدة" :error="$errors->first('lines.'.$index.'.unit_price')">
                    <input type="number" step="0.01" class="ds-input" wire:model="lines.{{ $index }}.unit_price">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-sm" wire:click="removeLine({{ $index }})">حذف</button>
            </div>
        @endforeach

        <button type="button" class="ds-btn ds-btn-sm" wire:click="addLine">إضافة بند</button>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showIssueModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="issue">إصدار</button>
        </x-slot:footer>
    </x-ds-modal>

    <x-ds-modal :show="$showNoteModal" title="إشعار دائن / مدين">
        <x-slot:header><h2>إشعار دائن / مدين</h2></x-slot:header>

        <x-ds-form-group label="النوع" :error="$errors->first('noteType')">
            <select class="ds-input" wire:model="noteType">
                <option value="دائن">دائن</option>
                <option value="مدين">مدين</option>
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="القيمة قبل الضريبة" :error="$errors->first('noteAmount')">
            <input type="number" step="0.01" class="ds-input" wire:model="noteAmount">
        </x-ds-form-group>

        <x-ds-form-group label="السبب" :error="$errors->first('noteReason')">
            <input type="text" class="ds-input" wire:model="noteReason">
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showNoteModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="issueNote">إصدار الإشعار</button>
        </x-slot:footer>
    </x-ds-modal>

    <x-ds-modal :show="$showTemplatesModal" title="قوالب الفواتير" size="lg" close-action="$set('showTemplatesModal', false)">
        <x-slot:header><h2>قوالب الفواتير</h2></x-slot:header>
        <p class="ds-text-muted">خلفية/ترويسة قابلة للرفع لكل نوع فاتورة. بيانات الشركة والعميل والبنود والضريبة تبقى مُعبَّأة تلقائيًا من المنصة — القالب يضبط الشكل فقط.</p>

        @foreach ($templates as $template)
            <div class="ds-card ds-mb-3" style="padding:.85rem 1rem" wire:key="template-{{ $template->type }}">
                <h3 class="ds-section-title" style="margin-top:0">{{ $template->type === 'مبسطة' ? 'القالب المبسّط' : 'القالب الضريبي الكامل' }}</h3>

                @if ($template->letterhead_path)
                    <p class="ds-badge ds-badge-success">يوجد خلفية مرفوعة</p>
                @else
                    <p class="ds-badge ds-badge-muted">بلا خلفية — يُستخدم الشكل الافتراضي</p>
                @endif

                @if ($template->type === \App\Models\TaxInvoice::TYPE_STANDARD)
                    <x-ds-form-group label="رفع خلفية جديدة" :error="$errors->first('standardLetterhead')">
                        <input type="file" class="ds-input" wire:model="standardLetterhead" accept="image/*">
                        <div wire:loading wire:target="standardLetterhead" class="ds-help-text">جاري الرفع…</div>
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="uploadStandardLetterhead" wire:loading.attr="disabled" wire:target="standardLetterhead,uploadStandardLetterhead">حفظ الخلفية</button>
                    @if ($template->letterhead_path)
                        <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="removeStandardLetterhead">إزالة الخلفية</button>
                    @endif
                @else
                    <x-ds-form-group label="رفع خلفية جديدة" :error="$errors->first('simplifiedLetterhead')">
                        <input type="file" class="ds-input" wire:model="simplifiedLetterhead" accept="image/*">
                        <div wire:loading wire:target="simplifiedLetterhead" class="ds-help-text">جاري الرفع…</div>
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="uploadSimplifiedLetterhead" wire:loading.attr="disabled" wire:target="simplifiedLetterhead,uploadSimplifiedLetterhead">حفظ الخلفية</button>
                    @if ($template->letterhead_path)
                        <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="removeSimplifiedLetterhead">إزالة الخلفية</button>
                    @endif
                @endif
            </div>
        @endforeach

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showTemplatesModal', false)">إغلاق</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
