<?php

namespace App\Livewire\Partnerships;

use App\Models\ContractPaymentSchedule;
use App\Models\PartnerLink;
use App\Models\PartnershipPayment;
use App\Models\Program;
use App\Models\Quote;
use App\Services\PartnerPortalService;
use App\Services\PartnershipPaymentService;
use App\Services\QuoteService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 05-B5 — the partner portal, reached only through the unique link.
 *
 * Isolation rule: every read and every write is scoped to `$link->partnership`.
 * The token is the only identity; no id ever arrives from the request. Each
 * action is written to the portal activity log.
 */
class PartnerPortal extends Component
{
    use WithFileUploads;

    public PartnerLink $link;

    public string $interestedPrograms = '';

    public string $diagnosisAudience = '';

    public string $diagnosisCount = '';

    public string $diagnosisEnvironment = '';

    public string $quoteNotes = '';

    public string $paymentAmount = '';

    public $paymentProof;

    public $signedContract;

    public function mount(string $token): void
    {
        $link = app(PartnerPortalService::class)->resolve($token);

        abort_if($link === null, 404);

        $this->link = $link;
        $this->log('portal.opened');
    }

    public function submitInterest(): void
    {
        $this->validate(['interestedPrograms' => 'required|string|max:1000']);

        $this->log('portal.programs_selected', ['programs' => $this->interestedPrograms]);
        $this->dispatch('ds-toast', message: 'تم تسجيل اهتمامكم');
    }

    public function submitDiagnosis(): void
    {
        $this->validate([
            'diagnosisAudience' => 'required|string|max:255',
            'diagnosisCount' => 'required|integer|min:1',
            'diagnosisEnvironment' => 'nullable|string|max:1000',
        ]);

        $this->log('portal.diagnosis_submitted', [
            'audience' => $this->diagnosisAudience,
            'count' => (int) $this->diagnosisCount,
            'environment' => $this->diagnosisEnvironment,
        ]);

        $this->dispatch('ds-toast', message: 'تم استلام استبانة التشخيص');
    }

    public function acceptQuote(int $quoteId): void
    {
        $quote = $this->scopedQuote($quoteId);

        app(QuoteService::class)->accept($quote);
        $this->log('portal.quote_accepted', ['quote_id' => $quote->id]);

        $this->dispatch('ds-toast', message: 'تم قبول العرض');
    }

    public function noteQuote(int $quoteId): void
    {
        $this->validate(['quoteNotes' => 'required|string|max:2000']);

        $quote = $this->scopedQuote($quoteId);
        app(QuoteService::class)->addNotes($quote, $this->quoteNotes);
        $this->log('portal.quote_noted', ['quote_id' => $quote->id]);

        $this->dispatch('ds-toast', message: 'تم إرسال ملاحظاتكم');
    }

    public function recordPayment(int $scheduleId): void
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentProof' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $scheduleItem = $this->scopedScheduleItem($scheduleId);
        $proofPath = $this->paymentProof?->store('partnership-payments/'.$this->link->partnership_id, 'local');

        $payment = app(PartnershipPaymentService::class)->record(
            $scheduleItem,
            (float) $this->paymentAmount,
            null,
            $proofPath,
            PartnershipPayment::VIA_PORTAL,
        );

        $this->paymentAmount = '';
        $this->paymentProof = null;
        $this->log('portal.payment_recorded', ['payment_id' => $payment->id]);

        $this->dispatch('ds-toast', message: 'سُجلت الدفعة بانتظار تأكيد المالية');
    }

    public function render(): View
    {
        $partnership = $this->link->partnership()->with([
            'organization', 'quotes.items', 'partnershipContracts.schedule',
        ])->firstOrFail();

        return view('livewire.partnerships.partner-portal', [
            'partnership' => $partnership,
            'programs' => Program::where('stage', Program::STAGE_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'target_audience', 'sessions_count', 'hours_count']),
            'quotes' => $partnership->quotes->whereIn('status', [
                Quote::STATUS_SENT, Quote::STATUS_WITH_NOTES, Quote::STATUS_ACCEPTED,
            ]),
        ])->layout('layouts.guest', ['title' => 'بوابة الجهة']);
    }

    /** @param array<string, mixed> $metadata */
    private function log(string $action, array $metadata = []): void
    {
        app(PartnerPortalService::class)->log($this->link, $action, $metadata, request()->ip());
    }

    private function scopedQuote(int $quoteId): Quote
    {
        return Quote::where('partnership_id', $this->link->partnership_id)->findOrFail($quoteId);
    }

    private function scopedScheduleItem(int $scheduleId): ContractPaymentSchedule
    {
        return ContractPaymentSchedule::query()
            ->whereHas('contract', fn ($q) => $q->where('partnership_id', $this->link->partnership_id))
            ->findOrFail($scheduleId);
    }
}
