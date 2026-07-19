<?php

namespace App\Services;

use App\Models\ContractPaymentSchedule;
use App\Models\PartnershipPayment;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Notifications\PartnershipPaymentLate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 05-B6 — recorded payments vs the contract schedule.
 *
 * Finance confirmation creates exactly one revenue (idempotent, double
 * reference) and unlocks the «إصدار فاتورة» hook into 04-B7.
 */
class PartnershipPaymentService
{
    public function record(
        ContractPaymentSchedule $scheduleItem,
        float $amount,
        ?string $paidOn = null,
        ?string $proofPath = null,
        string $via = PartnershipPayment::VIA_INTERNAL,
    ): PartnershipPayment {
        return PartnershipPayment::create([
            'partnership_id' => $scheduleItem->contract->partnership_id,
            'contract_payment_schedule_id' => $scheduleItem->id,
            'amount' => $amount,
            'paid_on' => $paidOn ?? now()->toDateString(),
            'proof_path' => $proofPath,
            'status' => PartnershipPayment::STATUS_PENDING,
            'recorded_via' => $via,
        ]);
    }

    /**
     * Finance confirmation → one confirmed revenue, ever. Re-confirming an
     * already confirmed payment is a no-op.
     */
    public function confirm(PartnershipPayment $payment, User $financeUser): PartnershipPayment
    {
        if ($payment->isConfirmed()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $financeUser) {
            $revenue = app(RevenueService::class)->recordFromPartnershipPayment(
                paymentId: $payment->id,
                amount: (float) $payment->amount,
                categoryId: null,
                confirmedBy: $financeUser->id,
            );

            $payment->forceFill([
                'status' => PartnershipPayment::STATUS_CONFIRMED,
                'confirmed_by' => $financeUser->id,
                'confirmed_at' => now(),
                'revenue_id' => $revenue->id,
            ])->save();

            return $payment;
        });
    }

    /**
     * «إصدار فاتورة» — hook into 04-B7. Idempotent: one invoice per payment.
     */
    public function issueTaxInvoice(PartnershipPayment $payment, ?User $issuer = null): TaxInvoice
    {
        if (! $payment->isConfirmed()) {
            throw new \RuntimeException('لا تُصدر فاتورة لدفعة غير مؤكدة');
        }

        $partnership = $payment->partnership;

        $invoice = app(TaxInvoiceService::class)->issueFromPayment(
            paymentId: $payment->id,
            amount: (float) $payment->amount,
            buyerName: $partnership->organization?->name ?? $partnership->entity_name ?? 'جهة شريكة',
            buyerVatNumber: null,
            organizationId: $partnership->organization_id,
            issuer: $issuer,
        );

        $payment->forceFill(['tax_invoice_id' => $invoice->id])->save();

        return $invoice;
    }

    /**
     * Schedule rows past due with less than their amount confirmed.
     *
     * @return Collection<int, ContractPaymentSchedule>
     */
    public function late(): Collection
    {
        return ContractPaymentSchedule::query()
            ->whereDate('due_on', '<', now()->toDateString())
            ->with('contract.partnership')
            ->get()
            ->filter(fn (ContractPaymentSchedule $row) => $row->isLate())
            ->values();
    }

    /**
     * Alert the partnership owner about late payments.
     *
     * @return list<int> alerted schedule ids
     */
    public function fireLateAlerts(): array
    {
        $alerted = [];

        foreach ($this->late() as $row) {
            $partnership = $row->contract->partnership;

            if ($partnership?->owner_id && $owner = User::find($partnership->owner_id)) {
                $owner->notify(new PartnershipPaymentLate($row));
            }

            $alerted[] = $row->id;
        }

        return $alerted;
    }
}
