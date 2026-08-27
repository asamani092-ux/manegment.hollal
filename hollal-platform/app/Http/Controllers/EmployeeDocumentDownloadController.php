<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\EmployeeDocument;
use App\Support\DownloadHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Request $request, EmployeeDocument $employeeDocument): StreamedResponse
    {
        abort_unless(
            auth()->user()?->can('hr.employees.view')
            || (int) auth()->id() === (int) $employeeDocument->user_id,
            403
        );

        if (! $employeeDocument->file_path || ! Storage::disk('local')->exists($employeeDocument->file_path)) {
            abort(404);
        }

        $this->auditFileDownload('employee_document', $employeeDocument);

        $ext = pathinfo($employeeDocument->file_path, PATHINFO_EXTENSION) ?: 'bin';
        $safeType = preg_replace('/\s+/', '-', $employeeDocument->type) ?: 'document';
        $filename = $safeType.'-'.($employeeDocument->document_number ?: $employeeDocument->id).'.'.$ext;
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return Storage::disk('local')->download($employeeDocument->file_path, $filename, [
            'Content-Disposition' => DownloadHeaders::contentDisposition($filename, $disposition),
        ], $disposition);
    }
}
