<?php

namespace App\Services;

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
 * 05-B4 — contracts. The signed copy is uploaded (download → sign → upload),
 * stored on the private disk and fingerprinted with a SHA-256 hash. «تعاقد»
 * needs that confirmed signed copy plus, when required, a confirmed first
 * payment — and only then does the partnership move to stage 6.
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
     * Fallback signing path (the approved one): the partner downloads, signs,
     * stamps and uploads. The stored hash fingerprints the uploaded copy.
     */
    public function uploadSignedCopy(
        PartnershipContract $contract,
        UploadedFile $file,
        string $signatureName,
        ?string $device = null,
    ): PartnershipContract {
        $path = $file->store('contracts/'.$contract->id.'/signed', 'local');
        $hash = hash('sha256', (string) Storage::disk('local')->get($path));

        $contract->forceFill([
            'signed_pdf_path' => $path,
            'signed_pdf_hash' => $hash,
            'signature_name' => $signatureName,
            'signature_device' => $device,
            'signed_at' => now(),
            'status' => PartnershipContract::STATUS_SIGNED,
        ])->save();

        return $contract;
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
