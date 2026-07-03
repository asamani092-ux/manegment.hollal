<?php

namespace App\Http\Controllers;

use App\Models\ExpenseRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure download for expense attachments stored on the local disk.
 */
class ExpenseFileDownloadController extends Controller
{
    public function __invoke(ExpenseRequest $expenseRequest): StreamedResponse
    {
        $this->authorize('downloadAttachment', $expenseRequest);

        if (! $expenseRequest->attachment || ! Storage::disk('local')->exists($expenseRequest->attachment)) {
            abort(404);
        }

        return Storage::disk('local')->download($expenseRequest->attachment);
    }
}
