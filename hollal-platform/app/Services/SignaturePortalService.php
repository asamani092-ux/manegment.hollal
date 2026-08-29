<?php

namespace App\Services;

use App\Contracts\SignableDocumentProvider;
use App\Models\SignatureRequest;
use App\Services\Signing\MeetingGuestSignableProvider;
use App\Services\Signing\PartnershipContractSignableProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Platform-wide signature portal: issue tokens, resolve, apply pad signatures.
 * Time: O(1) issue/resolve | Space: O(image) on sign
 */
class SignaturePortalService
{
    /** @var array<string, class-string<SignableDocumentProvider>> */
    private array $providers = [
        SignatureRequest::TYPE_MEETING_GUEST => MeetingGuestSignableProvider::class,
        SignatureRequest::TYPE_PARTNERSHIP_CONTRACT => PartnershipContractSignableProvider::class,
    ];

    public function issue(
        string $documentType,
        int $documentId,
        ?\DateTimeInterface $expiresAt = null,
        array $meta = [],
    ): SignatureRequest {
        if (! isset($this->providers[$documentType])) {
            throw new \InvalidArgumentException('نوع مستند غير مدعوم للتوقيع');
        }

        return SignatureRequest::query()->create([
            'token' => Str::random(64),
            'document_type' => $documentType,
            'document_id' => $documentId,
            'status' => SignatureRequest::STATUS_PENDING,
            'expires_at' => $expiresAt,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    public function resolve(string $token): ?SignatureRequest
    {
        $request = SignatureRequest::query()->where('token', $token)->first();
        if ($request === null) {
            return null;
        }

        if ($request->status === SignatureRequest::STATUS_PENDING
            && $request->expires_at !== null
            && $request->expires_at->isPast()) {
            $request->forceFill(['status' => SignatureRequest::STATUS_EXPIRED])->save();
        }

        return $request->fresh();
    }

    public function providerFor(SignatureRequest $request): SignableDocumentProvider
    {
        $class = $this->providers[$request->document_type] ?? null;
        if ($class === null) {
            throw new \InvalidArgumentException('لا يوجد موفّر لهذا النوع');
        }

        return app($class);
    }

    public function signUrl(SignatureRequest $request): string
    {
        return route('sign.portal', ['token' => $request->token]);
    }

    /**
     * Persist pad image, apply via provider, mark request signed.
     */
    public function applyPadSignature(
        SignatureRequest $request,
        string $padDataUri,
        string $signerName,
        string $signerPosition = '',
    ): SignatureRequest {
        if (! $request->isUsable()) {
            throw new \InvalidArgumentException('طلب التوقيع غير صالح أو منتهٍ');
        }

        $provider = $this->providerFor($request);
        $provider->applySignature($request, $padDataUri, $signerName, $signerPosition);

        $hash = hash('sha256', $padDataUri);
        $path = 'signatures/portal/'.$request->id.'-'.now()->format('YmdHis').'.png';
        $raw = base64_decode(substr(trim($padDataUri), strlen('data:image/png;base64,')), true);
        if (is_string($raw) && $raw !== '') {
            Storage::disk('local')->put($path, $raw);
        }

        $request->forceFill([
            'status' => SignatureRequest::STATUS_SIGNED,
            'signer_name' => $signerName,
            'signer_position' => $signerPosition !== '' ? $signerPosition : null,
            'signature_image_path' => $path,
            'signature_hash' => $hash,
            'signed_at' => now(),
        ])->save();

        return $request->fresh();
    }
}
