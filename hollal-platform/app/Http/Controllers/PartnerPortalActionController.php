<?php

namespace App\Http\Controllers;

use App\Services\PartnerPortalSelfServeService;
use App\Services\PartnerPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Token-scoped portal writes that work without Livewire JavaScript.
 * Time: O(p + q) | Space: O(p + q)
 */
class PartnerPortalActionController extends Controller
{
    public function confirmPrograms(Request $request, string $token, PartnerPortalService $portal, PartnerPortalSelfServeService $selfServe): RedirectResponse
    {
        $link = $portal->resolve($token);
        abort_if($link === null, 404);

        try {
            $selfServe->confirmPrograms(
                $link,
                $request->input('selectedProgramIds', []),
                $request->input('programQuantities', []),
                $request->input('programServices', []),
            );
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('partner.portal.page', ['token' => $token, 'page' => 'diagnosis'])
            ->with('success', 'حُفظ الاختيار وبُني العرض من أسعار البرامج');
    }

    public function submitDiagnosis(Request $request, string $token, PartnerPortalService $portal, PartnerPortalSelfServeService $selfServe): RedirectResponse
    {
        $link = $portal->resolve($token);
        abort_if($link === null, 404);

        try {
            $selfServe->submitDiagnosis(
                $link,
                (string) $request->input('diagnosisAudience', ''),
                (string) $request->input('diagnosisCount', ''),
                (string) $request->input('diagnosisEnvironment', ''),
                $request->input('diagnosisAnswers', []),
            );
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('partner.portal.page', ['token' => $token, 'page' => 'quotes'])
            ->with('success', 'تم استلام استبانة التشخيص');
    }

    public function acceptQuote(Request $request, string $token, int $quote, PartnerPortalService $portal, PartnerPortalSelfServeService $selfServe): RedirectResponse
    {
        $link = $portal->resolve($token);
        abort_if($link === null, 404);

        try {
            $selfServe->acceptQuote($link, $quote, $request->input('quoteNotes'));
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('partner.portal.page', ['token' => $token, 'page' => 'contract'])
            ->with('success', 'تم قبول العرض — العقد جاهز للتوقيع');
    }
}
