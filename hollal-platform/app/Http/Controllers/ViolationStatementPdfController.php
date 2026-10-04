<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Services\ViolationService;
use App\Support\PdfArabic;
use Symfony\Component\HttpFoundation\Response;

class ViolationStatementPdfController extends Controller
{
    public function __invoke(Violation $violation, ViolationService $service): Response
    {
        abort_unless(auth()->user()->can('hr.violations.view') || auth()->user()->can('hr.violations.manage'), 403);
        $html = $service->statementHtml($violation);
        try {
            return response(PdfArabic::outputFromHtml($html), 200, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
    }
}
