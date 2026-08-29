<?php

namespace App\Services\Signing;

use App\Contracts\SignableDocumentProvider;
use App\Models\PartnershipContract;
use App\Models\SignatureRequest;
use App\Services\PartnershipContractService;

/**
 * Thin adapter: portal /sign applies e-sign via PartnershipContractService.
 * Does not replace the existing /portal/{token} partner journey.
 * Time: O(pdf) | Space: O(pdf)
 */
class PartnershipContractSignableProvider implements SignableDocumentProvider
{
    public function __construct(
        private readonly PartnershipContractService $contracts,
    ) {}

    public function documentType(): string
    {
        return SignatureRequest::TYPE_PARTNERSHIP_CONTRACT;
    }

    public function title(SignatureRequest $request): string
    {
        $contract = $this->contract($request);

        return 'توقيع عقد شراكة رقم '.($contract?->id ?? '—');
    }

    public function summaryLines(SignatureRequest $request): array
    {
        $contract = $this->contract($request);
        if ($contract === null) {
            return ['العقد غير متاح'];
        }

        return array_values(array_filter([
            'الحالة: '.$contract->status,
            $contract->signed_at ? 'موقّع مسبقاً: '.hollal_dt($contract->signed_at) : 'بانتظار التوقيع',
        ]));
    }

    public function pdfBytes(SignatureRequest $request): ?string
    {
        $contract = $this->contract($request);
        if ($contract === null) {
            return null;
        }

        return $this->contracts->renderPdf($contract);
    }

    public function applySignature(
        SignatureRequest $request,
        string $padDataUri,
        string $signerName,
        string $signerPosition,
    ): void {
        $contract = $this->contract($request);
        if ($contract === null) {
            throw new \InvalidArgumentException('العقد غير موجود');
        }

        $this->contracts->signElectronically(
            $contract,
            $padDataUri,
            $signerName,
            $signerPosition,
            request()->userAgent(),
        );
    }

    private function contract(SignatureRequest $request): ?PartnershipContract
    {
        return PartnershipContract::query()->find($request->document_id);
    }
}
