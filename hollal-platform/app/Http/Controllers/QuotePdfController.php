<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 05-B3 — quote PDF, rendered live with the company profile (tax number).
 */
class QuotePdfController extends Controller
{
    public function __invoke(Request $request, Quote $quote, QuoteService $quotes): Response
    {
        abort_unless($request->user()?->can('partnerships.quotes.view'), 403);

        $disposition = $request->boolean('print') ? 'inline' : 'attachment';

        return response($quotes->renderPdf($quote), 200, \App\Support\DownloadHeaders::pdf(
            'quote-'.$quote->id.'-v'.$quote->version.'.pdf',
            $disposition,
        ));
    }
}
