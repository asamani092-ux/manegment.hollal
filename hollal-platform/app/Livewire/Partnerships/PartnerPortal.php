<?php

namespace App\Livewire\Partnerships;

use App\Models\ContractPaymentSchedule;
use App\Models\PartnerLink;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\Program;
use App\Models\Quote;
use App\Services\PartnerPortalService;
use App\Services\PartnershipContractService;
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

    /** @var list<int|string> */
    public array $selectedProgramIds = [];

    /** @var array<int|string, string> */
    public array $programQuantities = [];

    /** @var array<int|string, string> */
    public array $programServices = [];

    public string $diagnosisAudience = '';

    public string $diagnosisCount = '';

    public string $diagnosisEnvironment = '';

    public string $quoteNotes = '';

    public string $paymentAmount = '';

    public $paymentProof;

    public $signedContract;

    public string $signatureName = '';

    public string $signaturePosition = '';

    /** data:image/png;base64,... من لوحة التوقيع */
    public string $signaturePadData = '';

    public function mount(string $token): void
    {
        $link = app(PartnerPortalService::class)->resolve($token);

        abort_if($link === null, 404);

        $this->link = $link;
        $this->initializeCatalogSelection();
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
        $quote = $this->saveProgramSelection($quoteId);

        app(QuoteService::class)->accept($quote);
        $this->log('portal.quote_accepted', ['quote_id' => $quote->id]);

        $this->dispatch('ds-toast', message: 'تم قبول العرض');
    }

    /**
     * Rebuild the quote from the allowed catalog. Drafts are updated in place;
     * any already-issued quote becomes a new version before acceptance.
     */
    public function saveProgramSelection(int $quoteId): Quote
    {
        $quote = $this->scopedQuote($quoteId);
        $allowedPrograms = $this->allowedPrograms();
        $allowedIds = $allowedPrograms->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedIds = collect($this->selectedProgramIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->validate([
            'selectedProgramIds' => 'required|array|min:1',
            'programQuantities' => 'array',
            'programServices' => 'array',
        ], [], ['selectedProgramIds' => 'البرامج المختارة']);

        if (array_diff($selectedIds, $allowedIds) !== []) {
            throw new \RuntimeException('لا يمكن اختيار برنامج خارج كتالوج الشراكة');
        }

        $items = [];
        foreach ($selectedIds as $programId) {
            $program = $allowedPrograms->firstWhere('id', $programId);
            $service = (string) ($this->programServices[$programId] ?? $program?->prices->first()?->service_type);
            $price = $program?->prices->firstWhere('service_type', $service);

            if (! $price) {
                throw new \RuntimeException('لا يوجد سعر نشط للخدمة المختارة');
            }

            $quantity = (float) ($this->programQuantities[$programId] ?? 1);
            if ($quantity < 0.01) {
                throw new \RuntimeException('يجب أن تكون الكمية أكبر من صفر');
            }

            $items[] = [
                'program_id' => $programId,
                'service_type' => $price->service_type,
                'quantity' => $quantity,
                'unit_price' => (float) $price->unit_price,
            ];
        }

        $service = app(QuoteService::class);
        $updated = $quote->status === Quote::STATUS_DRAFT
            ? $service->updateDraft($quote, $items)
            : $service->revise($quote, $items);

        $this->log('portal.programs_selected', [
            'quote_id' => $updated->id,
            'program_ids' => $selectedIds,
            'quantities' => $this->programQuantities,
        ]);

        return $updated;
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

    /**
     * Spec-05 §4–5 — الجهة تنزّل العقد ثم ترفع النسخة الموقعة عبر الرابط.
     * Time: O(1) | Space: O(1)
     */
    public function uploadSignedContract(int $contractId): void
    {
        $this->validate([
            'signedContract' => 'required|file|max:10240|mimes:pdf',
            'signatureName' => 'required|string|max:255',
            'signaturePosition' => 'nullable|string|max:255',
        ]);

        $contract = $this->scopedContract($contractId);

        app(PartnershipContractService::class)->uploadSignedCopy(
            $contract,
            $this->signedContract,
            $this->signatureName,
            request()->userAgent(),
            $this->signaturePosition !== '' ? $this->signaturePosition : null,
        );

        $this->signedContract = null;
        $this->signatureName = '';
        $this->signaturePosition = '';
        $this->log('portal.contract_uploaded', ['contract_id' => $contract->id]);

        $this->dispatch('ds-toast', message: 'تم رفع النسخة الموقعة — بانتظار تأكيد مدير الشراكات');
    }

    /**
     * Amendments Q2 — توقيع إلكتروني داخل الرابط (لوحة + اسم + صفة).
     * Time: O(PDF) | Space: O(PDF)
     */
    public function signElectronically(int $contractId): void
    {
        $this->validate([
            'signaturePadData' => 'required|string|min:32',
            'signatureName' => 'required|string|max:255',
            'signaturePosition' => 'required|string|max:255',
        ]);

        $contract = $this->scopedContract($contractId);

        app(PartnershipContractService::class)->signElectronically(
            $contract,
            $this->signaturePadData,
            $this->signatureName,
            $this->signaturePosition,
            request()->userAgent(),
        );

        $this->signaturePadData = '';
        $this->signatureName = '';
        $this->signaturePosition = '';
        $this->log('portal.contract_signed_electronically', ['contract_id' => $contract->id]);

        $this->dispatch('ds-toast', message: 'تم التوقيع الإلكتروني — بانتظار تأكيد مدير الشراكات');
    }

    public function render(): View
    {
        $partnership = $this->link->partnership()->with([
            'organization', 'quotes.items', 'partnershipContracts.schedule',
        ])->firstOrFail();

        return view('livewire.partnerships.partner-portal', [
            'partnership' => $partnership,
            'programs' => $this->allowedPrograms(),
            'quotes' => $partnership->quotes->whereIn('status', [
                Quote::STATUS_DRAFT,
                Quote::STATUS_SENT, Quote::STATUS_WITH_NOTES, Quote::STATUS_ACCEPTED,
            ]),
        ])->layout('layouts.guest', ['title' => 'بوابة الجهة']);
    }

    /** @param array<string, mixed> $metadata */
    private function log(string $action, array $metadata = []): void
    {
        app(PartnerPortalService::class)->log($this->link, $action, $metadata, request()->ip());
    }

    private function initializeCatalogSelection(): void
    {
        $quote = $this->link->partnership()
            ->with('quotes.items')
            ->firstOrFail()
            ->quotes
            ->first();

        foreach ($quote?->items ?? [] as $item) {
            if ($item->program_id === null) {
                continue;
            }

            $this->selectedProgramIds[] = $item->program_id;
            $this->programQuantities[$item->program_id] = (string) $item->quantity;
            $this->programServices[$item->program_id] = $item->service_type;
        }

        if ($this->selectedProgramIds === []) {
            $first = $this->allowedPrograms()->first();
            if ($first) {
                $this->selectedProgramIds = [$first->id];
                $this->programQuantities[$first->id] = '1';
                $this->programServices[$first->id] = (string) $first->prices->first()?->service_type;
            }
        }
    }

    private function allowedPrograms()
    {
        return $this->link->partnership()
            ->firstOrFail()
            ->allowedPrograms()
            ->where('programs.stage', Program::STAGE_ACTIVE)
            ->with(['prices' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
            ->get(['programs.id', 'programs.name', 'programs.description', 'programs.target_audience', 'programs.sessions_count', 'programs.hours_count']);
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

    private function scopedContract(int $contractId): PartnershipContract
    {
        return PartnershipContract::query()
            ->where('partnership_id', $this->link->partnership_id)
            ->findOrFail($contractId);
    }
}
