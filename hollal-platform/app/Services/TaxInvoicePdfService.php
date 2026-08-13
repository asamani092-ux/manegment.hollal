<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\TaxInvoice;
use App\Support\PdfArabic;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * 04-B7 — Arabic RTL invoice PDF, including the TLV QR payload.
 */
class TaxInvoicePdfService
{
    public function render(TaxInvoice $invoice): string
    {
        $invoice->loadMissing('items');
        $company = CompanyProfile::current();
        $seller = app(TaxInvoiceService::class)->sellerFromCompanyProfile();

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

        $html = PdfArabic::header('فاتورة ضريبية — '.$invoice->number, includeCr: true)
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
            .' — النوع: '.e($invoice->invoice_type).'</p>'
            .'<table><thead><tr>'
            .'<th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th><th>الضريبة</th><th>الإجمالي</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p>المجموع قبل الضريبة: '.number_format((float) $invoice->subtotal, 2).'</p>'
            .'<p>إجمالي الضريبة: '.number_format((float) $invoice->vat_total, 2).'</p>'
            .'<p><strong>الإجمالي شامل الضريبة: '.number_format((float) $invoice->total, 2)
            .' '.e($invoice->currency).'</strong></p>'
            .'<p><strong>رمز QR (ZATCA Phase A — TLV base64):</strong></p>'
            .'<p style="font-size:9px; word-break: break-all; font-family: monospace;">'.e($qr).'</p>'
            .'</div>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        foreach (PdfArabic::pdfOptions() as $key => $value) {
            $pdf->setOption($key, $value);
        }

        return $pdf->output();
    }
}
