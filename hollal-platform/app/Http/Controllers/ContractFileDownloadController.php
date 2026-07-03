<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractFileDownloadController extends Controller
{
    public function __invoke(Contract $contract): StreamedResponse
    {
        $this->authorize('downloadFile', $contract);

        if (! $contract->contract_file || ! Storage::disk('local')->exists($contract->contract_file)) {
            abort(404);
        }

        return Storage::disk('local')->download($contract->contract_file);
    }
}
