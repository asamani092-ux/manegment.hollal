<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\CustodySettlementItem;
use App\Models\ExpenseRequest;
use App\Models\PayrollRunItem;
use App\Models\Revenue;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only download / preview for aggregated financial document rows.
 */
class FinancialDocumentDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Request $request, string $type, int $id): StreamedResponse
    {
        abort_unless(auth()->user()->can('finance.revenues.view'), 403);

        $target = match ($type) {
            'expense_invoice' => ExpenseRequest::query()->findOrFail($id),
            'revenue_document' => Revenue::query()->findOrFail($id),
            'custody_invoice' => CustodySettlementItem::query()->findOrFail($id),
            'payroll_proof' => PayrollRunItem::query()->findOrFail($id),
            default => abort(404),
        };

        $path = match ($type) {
            'expense_invoice' => $target->official_document_path,
            'revenue_document' => $target->external_document_path,
            'custody_invoice' => $target->invoice_file,
            'payroll_proof' => $target->proof_file,
            default => null,
        };

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $this->auditFileDownload('financial_document_'.$type, $target);

        $filename = basename($path);
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return Storage::disk('local')->download($path, $filename, [
            'Content-Disposition' => DownloadHeaders::contentDisposition($filename, $disposition),
        ]);
    }
}
