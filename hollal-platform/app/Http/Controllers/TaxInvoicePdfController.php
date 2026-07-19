<?php

namespace App\Http\Controllers;

use App\Models\TaxInvoice;
use App\Services\TaxInvoicePdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxInvoicePdfController extends Controller
{
    public function __invoke(Request $request, TaxInvoice $taxInvoice, TaxInvoicePdfService $pdf): Response
    {
        abort_unless($request->user()?->can('finance.tax_invoices.view'), 403);

        return response($pdf->render($taxInvoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$taxInvoice->number.'.pdf"',
        ]);
    }
}
