<?php

namespace App\Services\Signing;

use App\Contracts\SignableDocumentProvider;
use App\Models\MeetingGuest;
use App\Models\SignatureRequest;
use App\Services\MeetingMinutesPdfService;
use Illuminate\Support\Facades\Storage;

/**
 * Signable provider for external meeting guests.
 * Time: O(1) apply | Space: O(image)
 */
class MeetingGuestSignableProvider implements SignableDocumentProvider
{
    public function __construct(
        private readonly MeetingMinutesPdfService $minutesPdf,
    ) {}

    public function documentType(): string
    {
        return SignatureRequest::TYPE_MEETING_GUEST;
    }

    public function title(SignatureRequest $request): string
    {
        $guest = $this->guest($request);

        return 'توقيع محضر — '.($guest?->meeting?->title ?? 'اجتماع');
    }

    public function summaryLines(SignatureRequest $request): array
    {
        $guest = $this->guest($request);
        if ($guest === null) {
            return ['المستند غير متاح'];
        }

        return array_values(array_filter([
            'الضيف: '.$guest->name,
            'البريد: '.$guest->email,
            $guest->meeting ? 'الاجتماع: '.$guest->meeting->title : null,
            $guest->meeting?->scheduled_at
                ? 'الموعد: '.hollal_dt($guest->meeting->scheduled_at)
                : null,
        ]));
    }

    public function pdfBytes(SignatureRequest $request): ?string
    {
        $guest = $this->guest($request);
        if ($guest?->meeting === null) {
            return null;
        }

        return $this->minutesPdf->output($guest->meeting);
    }

    public function applySignature(
        SignatureRequest $request,
        string $padDataUri,
        string $signerName,
        string $signerPosition,
    ): void {
        $guest = $this->guest($request);
        if ($guest === null) {
            throw new \InvalidArgumentException('الضيف غير موجود');
        }

        $png = $this->decodePng($padDataUri);
        $path = 'signatures/guests/'.$guest->id.'-'.now()->format('YmdHis').'.png';
        Storage::disk('local')->put($path, $png);

        $guest->forceFill([
            'signature_image_path' => $path,
            'confirmed_at' => $guest->confirmed_at ?? now(),
            'viewed_at' => $guest->viewed_at ?? now(),
        ])->save();
    }

    private function guest(SignatureRequest $request): ?MeetingGuest
    {
        return MeetingGuest::query()
            ->with(['meeting:id,title,scheduled_at'])
            ->find($request->document_id);
    }

    private function decodePng(string $padDataUri): string
    {
        $trimmed = trim($padDataUri);
        if (! str_starts_with($trimmed, 'data:image/png;base64,')) {
            throw new \InvalidArgumentException('صيغة التوقيع غير مدعومة');
        }
        $raw = base64_decode(substr($trimmed, strlen('data:image/png;base64,')), true);
        if ($raw === false || $raw === '') {
            throw new \InvalidArgumentException('تعذّر قراءة صورة التوقيع');
        }

        return $raw;
    }
}
