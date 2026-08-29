<?php

namespace App\Http\Controllers;

use App\Services\SignaturePortalService;
use App\Support\DownloadHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download the document PDF for a signature portal token.
 * Time: O(pdf) | Space: O(pdf)
 */
class SignaturePortalPdfController extends Controller
{
    public function __invoke(string $token, SignaturePortalService $portal): Response
    {
        $request = $portal->resolve($token);
        abort_if($request === null, 404);

        $provider = $portal->providerFor($request);
        $bytes = $provider->pdfBytes($request);
        abort_if($bytes === null || $bytes === '', 404);

        return response(
            $bytes,
            200,
            DownloadHeaders::pdf('sign-'.$request->id.'.pdf', 'inline')
        );
    }
}
