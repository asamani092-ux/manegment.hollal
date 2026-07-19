<?php

namespace App\Http\Controllers;

use App\Models\PartnershipContract;
use App\Services\PartnerPortalService;
use App\Services\PartnershipContractService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 05-B5 — contract download through the partner link. The token scopes the
 * lookup, so one organization's link can never reach another's contract.
 */
class PartnerPortalContractPdfController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        int $contract,
        PartnerPortalService $portal,
        PartnershipContractService $contracts,
    ): Response {
        $link = $portal->resolve($token);
        abort_if($link === null, 404);

        $model = PartnershipContract::query()
            ->where('partnership_id', $link->partnership_id)
            ->findOrFail($contract);

        $portal->log($link, 'portal.contract_downloaded', ['contract_id' => $model->id], $request->ip());

        return response($contracts->renderPdf($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="contract-'.$model->id.'.pdf"',
        ]);
    }
}
