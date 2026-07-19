<?php

namespace App\Services;

use App\Models\TaxInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * 04-B7 — Arabic RTL invoice PDF, including the TLV QR payload.
 */
class TaxInvoicePdfService
{
    public function render(TaxInvoice $invoice): string
    {
        $invoice->loadMissing('items');

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

        $html = '<div dir="rtl" style="font-family: dejavu sans;">'
            .'<h2>فاتورة ضريبية — '.e($invoice->number).'</h2>'
            .'<p>البائع: '.e($invoice->seller_name).' — الرقم الضريبي: '.e((string) $invoice->seller_vat_number).'</p>'
            .'<p>المشتري: '.e($invoice->buyer_name).' — الرقم الضريبي: '.e((string) $invoice->buyer_vat_number).'</p>'
            .'<p>تاريخ الإصدار: '.e($invoice->issued_at?->format('Y-m-d H:i')).' — الوضع: '.e($invoice->mode).'</p>'
            .'<table border="1" cellspacing="0" cellpadding="4" width="100%">'
            .'<thead><tr><th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th><th>الضريبة</th><th>الإجمالي</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>'
            .'<p>المجموع قبل الضريبة: '.number_format((float) $invoice->subtotal, 2).'</p>'
            .'<p>إجمالي الضريبة: '.number_format((float) $invoice->vat_total, 2).'</p>'
            .'<p><strong>الإجمالي شامل الضريبة: '.number_format((float) $invoice->total, 2).' '.e($invoice->currency).'</strong></p>'
            .'<p style="font-size:9px;">QR (TLV base64): '.e((string) $invoice->qr_payload).'</p>'
            .'</div>';

        return Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'dejavu sans')->output();
    }
}
