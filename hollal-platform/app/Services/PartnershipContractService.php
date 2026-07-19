<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\Quote;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 05-B4 + amendments Q2 — contracts: e-sign inside the partner link
 * (canvas → SVG/PNG page in PDF) alongside manual upload. SHA-256 fingerprint.
 */
class PartnershipContractService
{
    /**
     * Build a contract from an accepted quote, with its payment schedule.
     *
     * @param  list<array{label?: string, amount: float|int, due_on: string}>  $schedule
     */
    public function createFromQuote(
        Quote $quote,
        array $schedule,
        bool $requiresFirstPayment = true,
        ?string $startsOn = null,
        ?string $endsOn = null,
    ): PartnershipContract {
        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            throw new \RuntimeException('لا يُنشأ العقد إلا من عرض مقبول');
        }

        return DB::transaction(function () use ($quote, $schedule, $requiresFirstPayment, $startsOn, $endsOn) {
            $contract = PartnershipContract::create([
                'partnership_id' => $quote->partnership_id,
                'quote_id' => $quote->id,
                'status' => PartnershipContract::STATUS_AWAITING_SIGNATURE,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'total_value' => $quote->total,
                'requires_first_payment' => $requiresFirstPayment,
            ]);

            foreach (array_values($schedule) as $index => $row) {
                $contract->schedule()->create([
                    'sequence' => $index + 1,
                    'label' => $row['label'] ?? ('دفعة '.($index + 1)),
                    'amount' => (float) $row['amount'],
                    'due_on' => $row['due_on'],
                ]);
            }

            $path = 'contracts/'.$contract->id.'/unsigned.pdf';
            Storage::disk('local')->put($path, $this->renderPdf($contract));
            $contract->forceFill(['unsigned_pdf_path' => $path])->save();

            return $contract->fresh(['schedule']);
        });
    }

    /**
     * Manual upload path: partner downloads, signs, stamps and uploads.
     */
    public function uploadSignedCopy(
        PartnershipContract $contract,
        UploadedFile $file,
        string $signatureName,
        ?string $device = null,
        ?string $signaturePosition = null,
    ): PartnershipContract {
        $path = $file->store('contracts/'.$contract->id.'/signed', 'local');
        $hash = hash('sha256', (string) Storage::disk('local')->get($path));

        $contract->forceFill([
            'signed_pdf_path' => $path,
            'signed_pdf_hash' => $hash,
            'signature_name' => $signatureName,
            'signature_position' => $signaturePosition,
            'signature_method' => PartnershipContract::METHOD_MANUAL_UPLOAD,
            'signature_device' => $device,
            'signed_at' => now(),
            'status' => PartnershipContract::STATUS_SIGNED,
        ])->save();

        $this->auditSignature($contract, PartnershipContract::METHOD_MANUAL_UPLOAD);

        return $contract;
    }

    /**
     * In-link e-signature: SVG (or PNG data-URL) → last PDF page + SHA-256.
     * Time: O(size of PDF) | Space: O(size of PDF)
     */
    public function signElectronically(
        PartnershipContract $contract,
        string $signatureSvg,
        string $signatureName,
        string $signaturePosition,
        ?string $device = null,
    ): PartnershipContract {
        if (trim($signatureSvg) === '') {
            throw new \InvalidArgumentException('التوقيع فارغ');
        }

        $normalizedSvg = $this->normalizeSignatureSvg($signatureSvg);
        $imagePath = 'contracts/'.$contract->id.'/signature.svg';
        Storage::disk('local')->put($imagePath, $normalizedSvg);

        $pngDataUri = null;
        if (preg_match('/xlink:href="(data:image\/png;base64,[^"]+)"/', $normalizedSvg, $m) === 1) {
            $pngDataUri = $m[1];
        } elseif (str_starts_with(trim($signatureSvg), 'data:image/png;base64,')) {
            $pngDataUri = trim($signatureSvg);
        }

        $pdfBinary = $this->renderCombinedSignedPdf(
            $contract,
            extension_loaded('gd') ? $pngDataUri : null,
            $signatureName,
            $signaturePosition,
            $device,
        );

        $pdfPath = 'contracts/'.$contract->id.'/signed/e-sign-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($pdfPath, $pdfBinary);
        $hash = hash('sha256', $pdfBinary);

        $contract->forceFill([
            'signed_pdf_path' => $pdfPath,
            'signed_pdf_hash' => $hash,
            'signature_image_path' => $imagePath,
            'signature_name' => $signatureName,
            'signature_position' => $signaturePosition,
            'signature_method' => PartnershipContract::METHOD_IN_LINK,
            'signature_device' => $device,
            'signed_at' => now(),
            'status' => PartnershipContract::STATUS_SIGNED,
        ])->save();

        $this->auditSignature($contract, PartnershipContract::METHOD_IN_LINK);

        return $contract;
    }

    private function auditSignature(PartnershipContract $contract, string $method): void
    {
        AuditLog::create([
            'actor_id' => null,
            'action' => 'partnership_contract.signed',
            'target_type' => PartnershipContract::class,
            'target_id' => $contract->id,
            'ip_address' => request()->ip(),
            'metadata' => [
                'method' => $method,
                'partnership_id' => $contract->partnership_id,
                'signature_name' => $contract->signature_name,
                'hash' => $contract->signed_pdf_hash,
            ],
            'created_at' => now(),
        ]);
    }

    private function normalizeSignatureSvg(string $input): string
    {
        $trimmed = trim($input);

        if (str_starts_with($trimmed, 'data:image/png;base64,')) {
            $png = base64_decode(substr($trimmed, strlen('data:image/png;base64,')), true) ?: '';
            $b64 = base64_encode($png);

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="400" height="160">'
                .'<image width="400" height="160" xlink:href="data:image/png;base64,'.$b64.'"/>'
                .'</svg>';
        }

        if (str_contains($trimmed, '<svg')) {
            return $trimmed;
        }

        throw new \InvalidArgumentException('صيغة التوقيع غير صالحة');
    }

    private function renderCombinedSignedPdf(
        PartnershipContract $contract,
        ?string $pngDataUri,
        string $signatureName,
        string $signaturePosition,
        ?string $device,
    ): string {
        $contract->loadMissing(['schedule', 'quote.items', 'partnership.organization']);
        $company = CompanyProfile::current();

        $scheduleRows = '';
        foreach ($contract->schedule as $row) {
            $scheduleRows .= '<tr><td>'.e((string) $row->label).'</td><td>'
                .number_format((float) $row->amount, 2).'</td><td>'
                .e($row->due_on->format('Y-m-d')).'</td></tr>';
        }

        $html = '<div dir="rtl" style="font-family: dejavu sans;">'
            .'<h2>عقد شراكة رقم '.(int) $contract->id.'</h2>'
            .'<p>'.e($company->name).' — الرقم الضريبي: '.e((string) $company->tax_number).'</p>'
            .'<p>الجهة: '.e($contract->partnership->organization?->name ?? $contract->partnership->entity_name ?? '—').'</p>'
            .'<p>القيمة الإجمالية: '.number_format((float) $contract->total_value, 2).'</p>'
            .'<h3>جدول الدفعات</h3>'
            .'<table border="1" cellspacing="0" cellpadding="4" width="100%">'
            .'<thead><tr><th>الدفعة</th><th>المبلغ</th><th>تاريخ الاستحقاق</th></tr></thead>'
            .'<tbody>'.$scheduleRows.'</tbody></table>'
            .'<p>التزامات حلل: '.e((string) $contract->hollal_commitments).'</p>'
            .'<p>التزامات الجهة: '.e((string) $contract->partner_commitments).'</p>'
            .'<div style="page-break-before: always;">'
            .'<h2>صفحة التوقيع الإلكتروني</h2>'
            .'<p>اسم الموقّع: '.e($signatureName).'</p>'
            .'<p>الصفة: '.e($signaturePosition).'</p>'
            .'<p>الوقت: '.e(now()->timezone('Asia/Riyadh')->format('Y-m-d H:i:s')).'</p>'
            .'<p>الجهاز: '.e((string) $device).'</p>'
            .($pngDataUri !== null
                ? '<p>التوقيع:</p><img src="'.$pngDataUri.'" width="320" height="120" alt="توقيع"/>'
                : '<p>التوقيع محفوظ إلكترونيًا (ملف SVG على القرص الخاص).</p>')
            .'</div></div>';

        return Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'dejavu sans')->output();
    }

    /**
     * Confirm the signed copy → «تعاقد». Blocked while the signed copy is
     * missing, or while a required first payment is not yet confirmed.
     */
    public function confirm(PartnershipContract $contract, User $confirmer): PartnershipContract
    {
        if (! $contract->hasSignedCopy()) {
            throw new \RuntimeException('لا تعاقد دون نسخة موقعة مرفوعة');
        }

        if ($contract->requires_first_payment && ! $this->firstPaymentConfirmed($contract)) {
            throw new \RuntimeException('لا تعاقد قبل تأكيد المالية للدفعة الأولى');
        }

        return DB::transaction(function () use ($contract, $confirmer) {
            $contract->forceFill([
                'status' => PartnershipContract::STATUS_CONFIRMED,
                'confirmed_by' => $confirmer->id,
                'confirmed_at' => now(),
            ])->save();

            app(PartnershipPipelineService::class)->moveTo(
                $contract->partnership,
                Partnership::STAGE_CONTRACTED,
                $confirmer,
                'اكتمال شروط التعاقد',
            );

            return $contract;
        });
    }

    public function firstPaymentConfirmed(PartnershipContract $contract): bool
    {
        $first = $contract->schedule()->orderBy('sequence')->first();

        if (! $first) {
            return false;
        }

        return PartnershipPayment::query()
            ->where('contract_payment_schedule_id', $first->id)
            ->where('status', PartnershipPayment::STATUS_CONFIRMED)
            ->exists();
    }

    public function renderPdf(PartnershipContract $contract): string
    {
        $contract->loadMissing(['schedule', 'quote.items', 'partnership.organization']);
        $company = CompanyProfile::current();

        $scheduleRows = '';
        foreach ($contract->schedule as $row) {
            $scheduleRows .= '<tr><td>'.e((string) $row->label).'</td><td>'
                .number_format((float) $row->amount, 2).'</td><td>'
                .e($row->due_on->format('Y-m-d')).'</td></tr>';
        }

        $html = '<div dir="rtl" style="font-family: dejavu sans;">'
            .'<h2>عقد شراكة رقم '.(int) $contract->id.'</h2>'
            .'<p>'.e($company->name).' — الرقم الضريبي: '.e((string) $company->tax_number).'</p>'
            .'<p>الجهة: '.e($contract->partnership->organization?->name ?? $contract->partnership->entity_name ?? '—').'</p>'
            .'<p>القيمة الإجمالية: '.number_format((float) $contract->total_value, 2).'</p>'
            .'<h3>جدول الدفعات</h3>'
            .'<table border="1" cellspacing="0" cellpadding="4" width="100%">'
            .'<thead><tr><th>الدفعة</th><th>المبلغ</th><th>تاريخ الاستحقاق</th></tr></thead>'
            .'<tbody>'.$scheduleRows.'</tbody></table>'
            .'<p>التزامات حلل: '.e((string) $contract->hollal_commitments).'</p>'
            .'<p>التزامات الجهة: '.e((string) $contract->partner_commitments).'</p>'
            .'</div>';

        return Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'dejavu sans')->output();
    }
}
