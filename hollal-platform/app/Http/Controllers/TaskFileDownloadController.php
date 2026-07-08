<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure download for task files stored on the local disk.
 */
class TaskFileDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Request $request, Task $task, string $type): StreamedResponse
    {
        $this->authorize('downloadFile', [$task, $type]);

        $path = match ($type) {
            'attachment' => $task->attachment_path,
            'submitted' => $task->submitted_file,
            default => null,
        };

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $this->auditFileDownload($type, $task);

        return Storage::disk('local')->download($path);
    }
}
