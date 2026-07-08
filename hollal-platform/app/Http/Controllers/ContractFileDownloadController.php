<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsFileDownloads;
use App\Models\Contract;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractFileDownloadController extends Controller
{
    use LogsFileDownloads;

    public function __invoke(Contract $contract): StreamedResponse
    {
        $this->authorize('downloadFile', $contract);

        if (! $contract->contract_file || ! Storage::disk('local')->exists($contract->contract_file)) {
            abort(404);
        }

        $this->auditFileDownload('contract', $contract);

        return Storage::disk('local')->download($contract->contract_file);
    }
}
