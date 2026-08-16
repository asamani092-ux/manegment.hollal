<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\TaxInvoice;
use App\Models\TaxInvoiceTemplate;
use App\Support\PdfArabic;
use Illuminate\Support\Facades\Storage;

/**
 * 04-B7 — Arabic RTL invoice PDF, including the TLV QR payload.
 * Wave D-deep: the per-type template only supplies the letterhead
 * background — company data always comes from CompanyProfile, and the
 * client/line items/tax/totals are always filled from the issued invoice.
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
            $rows .= '<tr>'
                .'<td>'.e($item->description).'</td>'
                .'<td>'.number_format((float) $item->quantity, 2).'</td>'
                .'<td>'.number_format((float) $item->unit_price, 2).'</td>'
                .'<td>'.number_format((float) $item->vat_rate * 100, 2).'%</td>'
                .'<td>'.number_format((float) $item->line_total, 2).'</td>'
                .'</tr>';
        }

        $cr = $seller['commercial_register'] ?: $company->commercial_register;
        $address = $seller['address'] ?: $company->address;
        $qr = (string) $invoice->qr_payload;
        $typeLabel = $invoice->invoice_type === TaxInvoice::TYPE_SIMPLIFIED
            ? 'فاتورة ضريبية مبسطة'
            : 'فاتورة ضريبية';

        $letterhead = $this->letterheadBackground($template);

        $html = $letterhead
            .'<div class="tax-invoice-content">'
            .PdfArabic::header($typeLabel.' — '.$invoice->number, includeCr: true)
            .'<div dir="rtl" style="unicode-bidi: embed;">'
            .'<p><strong>البائع:</strong> '.e($invoice->seller_name)
            .' — الرقم الضريبي: '.e((string) $invoice->seller_vat_number)
            .($cr ? ' — السجل التجاري: '.e((string) $cr) : '')
            .'</p>'
            .($address ? '<p><strong>عنوان البائع:</strong> '.e((string) $address).'</p>' : '')
            .'<p><strong>المشتري:</strong> '.e($invoice->buyer_name)
            .' — الرقم الضريبي: '.e((string) $invoice->buyer_vat_number).'</p>'
            .'<p>تاريخ الإصدار: '.e($invoice->issued_at?->format('Y-m-d H:i') ?? '—')
            .' — الوضع: '.e($invoice->mode)
            .' — النوع: '.e($typeLabel).'</p>'
            .'<table><thead><tr>'
            .'<th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th><th>الضريبة</th><th>الإجمالي</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p>المجموع قبل الضريبة: '.number_format((float) $invoice->subtotal, 2).'</p>'
            .'<p>إجمالي الضريبة: '.number_format((float) $invoice->vat_total, 2).'</p>'
            .'<p><strong>الإجمالي شامل الضريبة: '.number_format((float) $invoice->total, 2)
            .' '.e($invoice->currency).'</strong></p>'
            .'<p><strong>رمز QR (ZATCA Phase A — TLV base64):</strong></p>'
            .'<p style="font-size:9px; word-break: break-all; font-family: monospace;">'.e($qr).'</p>'
            .'</div>'
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
