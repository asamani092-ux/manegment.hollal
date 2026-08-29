<?php

namespace App\Livewire\Signing;

use App\Models\SignatureRequest;
use App\Services\SignaturePortalService;
use Livewire\Component;

/**
 * Public /sign/{token} portal — pad + summary + PDF download.
 * Full UAT deferred; foundation only.
 * Time: O(1) page | Space: O(1)
 */
class SignaturePortal extends Component
{
    public string $token = '';

    public string $signerName = '';

    public string $signerPosition = '';

    public string $signaturePadData = '';

    public bool $done = false;

    public ?string $errorMessage = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $request = app(SignaturePortalService::class)->resolve($token);
        if ($request === null) {
            $this->errorMessage = 'الرابط غير صالح';

            return;
        }
        if ($request->status === SignatureRequest::STATUS_SIGNED) {
            $this->done = true;
            $this->signerName = (string) $request->signer_name;

            return;
        }
        if (! $request->isUsable()) {
            $this->errorMessage = 'انتهت صلاحية رابط التوقيع أو أُلغي';
        }
    }

    public function submitSignature(SignaturePortalService $portal): void
    {
        $this->validate([
            'signerName' => ['required', 'string', 'max:120'],
            'signerPosition' => ['nullable', 'string', 'max:120'],
            'signaturePadData' => ['required', 'string', 'min:40'],
        ], [], [
            'signerName' => 'اسم الموقّع',
            'signerPosition' => 'الصفة',
            'signaturePadData' => 'التوقيع',
        ]);

        $request = $portal->resolve($this->token);
        if ($request === null || ! $request->isUsable()) {
            $this->errorMessage = 'طلب التوقيع غير صالح';

            return;
        }

        try {
            $portal->applyPadSignature(
                $request,
                $this->signaturePadData,
                $this->signerName,
                $this->signerPosition,
            );
            $this->done = true;
            $this->errorMessage = null;
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل التوقيع');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'تعذّر حفظ التوقيع';
        }
    }

    public function render(SignaturePortalService $portal)
    {
        $request = $portal->resolve($this->token);
        $provider = null;
        $title = 'بوابة التوقيع';
        $summary = [];
        $canDownload = false;

        if ($request !== null) {
            try {
                $provider = $portal->providerFor($request);
                $title = $provider->title($request);
                $summary = $provider->summaryLines($request);
                $canDownload = $provider->pdfBytes($request) !== null;
            } catch (\Throwable) {
                $summary = [];
            }
        }

        return view('livewire.signing.signature-portal', [
            'request' => $request,
            'title' => $title,
            'summary' => $summary,
            'canDownload' => $canDownload,
        ])->layout('layouts.guest', ['title' => $title]);
    }
}
