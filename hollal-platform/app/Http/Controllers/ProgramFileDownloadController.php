<?php

namespace App\Http\Controllers;

use App\Models\ProgramFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 06A-B1 — program files live on the private disk and are only reachable
 * through this permission-checked route.
 */
class ProgramFileDownloadController extends Controller
{
    public function __invoke(Request $request, ProgramFile $programFile): StreamedResponse
    {
        abort_unless($request->user()?->can('projects.programs.view'), 403);
        abort_unless(Storage::disk('local')->exists($programFile->path), 404);

        return response()->streamDownload(
            fn () => print (Storage::disk('local')->get($programFile->path)),
            basename($programFile->path),
        );
    }
}
