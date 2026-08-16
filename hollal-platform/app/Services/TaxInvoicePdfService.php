<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\TaxInvoice;
use App\Models\TaxInvoiceTemplate;
use App\Support\PdfArabic;
use Illuminate\Support\Facades\Storage;

/**
 * 04-B7 — Arabic invoice PDF, including the TLV QR payload.
 * Labels and amounts are separated so glyph shaping never mangles numbers.
 * Time: O(n) line items | Space: O(n)
 */
class TaxInvoicePdfService
{
    public function render(TaxInvoice $invoice): string
    {
        return PdfArabic::outputFromHtml($this->buildHtml($invoice));
    }

    /**
     * Extracted for testability: assert the letterhead/company data end up
     * in the HTML without needing to parse the rendered PDF binary.
     */
    public function buildHtml(TaxInvoice $invoice): string
    {
        $invoice->loadMissing('items');
        $company = CompanyProfile::current();
        $seller = app(TaxInvoiceService::class)->sellerFromCompanyProfile();
        $template = TaxInvoiceTemplate::forType($invoice->invoice_type);

        $rows = '';
        foreach ($invoice->items as $item) {
            // LTR Dompdf: first cell = left. Put totals on the left so الوصف sits on the right (Arabic invoice).
            $rows .= '<tr>'
                .'<td class="num">'.number_format((float) $item->line_total, 2).'</td>'
                .'<td class="num">'.number_format((float) $item->vat_rate * 100, 2).'%</td>'
                .'<td class="num">'.number_format((float) $item->unit_price, 2).'</td>'
                .'<td class="num">'.number_format((float) $item->quantity, 2).'</td>'
                .'<td>'.e($item->description).'</td>'
                .'</tr>';
        }

        $cr = $seller['commercial_register'] ?: $company->commercial_register;
        $address = $seller['address'] ?: $company->address;
        $qr = (string) $invoice->qr_payload;
        $typeLabel = $invoice->invoice_type === TaxInvoice::TYPE_SIMPLIFIED
            ? 'فاتورة ضريبية مبسطة'
            : 'فاتورة ضريبية';

        $letterhead = $this->letterheadBackground($template);

        // value | label  → label appears on the right in LTR layout
        $metaRow = static fn (string $label, string $value, bool $numeric = false): string => '<tr>'
            .'<td'.($numeric ? ' class="num"' : '').'>'.$value.'</td>'
            .'<td class="pdf-label">'.e($label).'</td>'
            .'</tr>';

        $meta = '<table class="pdf-meta">'
            .$metaRow('رقم الفاتورة', e($invoice->number), true)
            .$metaRow('النوع', e($typeLabel))
            .$metaRow('تاريخ الإصدار', e(hollal_dt($invoice->issued_at)), true)
            .$metaRow('الوضع', e($invoice->mode))
            .$metaRow('البائع', e($invoice->seller_name))
            .$metaRow('الرقم الضريبي للبائع', e((string) $invoice->seller_vat_number), true)
            .($cr ? $metaRow('السجل التجاري', e((string) $cr), true) : '')
            .($address ? $metaRow('عنوان البائع', e((string) $address)) : '')
            .$metaRow('المشتري', e($invoice->buyer_name))
            .$metaRow('الرقم الضريبي للمشتري', e((string) $invoice->buyer_vat_number), true)
            .'</table>';

        $html = $letterhead
            .'<div class="tax-invoice-content">'
            .PdfArabic::header($typeLabel, includeCr: false)
            .$meta
            .'<h3 style="margin-top:16px; text-align:right;">بنود الفاتورة</h3>'
            .'<table><thead><tr>'
            .'<th class="num">الإجمالي</th><th class="num">الضريبة</th><th class="num">سعر الوحدة</th>'
            .'<th class="num">الكمية</th><th>الوصف</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<table class="pdf-meta" style="margin-top:12px;">'
            .$metaRow('المجموع قبل الضريبة', number_format((float) $invoice->subtotal, 2), true)
            .$metaRow('إجمالي الضريبة', number_format((float) $invoice->vat_total, 2), true)
            .$metaRow(
                'الإجمالي شامل الضريبة',
                '<strong>'.number_format((float) $invoice->total, 2).' '.e($invoice->currency).'</strong>',
                true
            )
            .'</table>'
            .'<p style="margin-top:12px; text-align:right;"><strong>رمز QR (ZATCA Phase A — TLV base64)</strong></p>'
            .'<p class="pdf-num" style="font-size:9px; word-break: break-all; text-align:left;">'.e($qr).'</p>'
            .'</div>';

        return $html;
    }

    /**
     * Full-page fixed background image behind the invoice content — the
     * standard DomPDF trick for a repeating letterhead. Empty when the
     * template has no uploaded letterhead.
     */
    private function letterheadBackground(?TaxInvoiceTemplate $template): string
    {
        if (! $template?->letterhead_path || ! Storage::disk('public')->exists($template->letterhead_path)) {
            return '';
        }

        $full = str_replace('\\', '/', Storage::disk('public')->path($template->letterhead_path));

        return '<img src="file://'.$full.'" alt="" class="tax-invoice-letterhead" '
            .'style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1;">';
    }
}
