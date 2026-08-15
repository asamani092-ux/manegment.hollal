<?php

namespace App\Http\Controllers;

use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueFileDownloadController extends Controller
{
    public function __invoke(Request $request, Revenue $revenue): StreamedResponse
    {
        abort_unless(
            $request->user()?->can('finance.revenues.view')
            || $request->user()?->can('finance.revenues.manage'),
            403
        );

        abort_unless(filled($revenue->external_document_path), 404);
        abort_unless(Storage::disk('local')->exists($revenue->external_document_path), 404);

        $inline = $request->boolean('inline');
        $name = basename($revenue->external_document_path);

        return Storage::disk('local')->download(
            $revenue->external_document_path,
            $name,
            $inline ? ['Content-Disposition' => 'inline; filename="'.$name.'"'] : []
        );
    }
}
