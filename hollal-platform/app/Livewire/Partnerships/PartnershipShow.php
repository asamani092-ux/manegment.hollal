<?php

namespace App\Livewire\Partnerships;

use App\Models\ContractPaymentSchedule;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\Quote;
use App\Models\User;
use App\Services\PartnerPortalService;
use App\Services\PartnershipContractService;
use App\Services\PartnershipPaymentService;
use App\Services\ProjectGenerationRequestService;
use App\Services\QuoteService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 05-B3..05-B7 — the partnership workspace: quotes, contract + signed copy,
 * payments, the partner link, and the «توليد مشروع» handoff.
 */
class PartnershipShow extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Partnership $partnership;

    // — quote builder (05-B3)
    public bool $showQuoteModal = false;

    public ?int $revisingQuoteId = null;

    public string $quoteDiscount = '0';

    /** @var list<array{program_id: ?string, service_type: string, quantity: string, unit_price: string}> */
    public array $quoteLines = [];

    // — contract (05-B4)
    public bool $showContractModal = false;

    public ?int $contractQuoteId = null;

    public bool $requiresFirstPayment = true;

    /** @var list<array{label: string, amount: string, due_on: string}> */
    public array $scheduleRows = [];

    public ?int $signingContractId = null;

    public ?string $signatureName = null;

    public $signedCopy;

    // — payments (05-B6)
    public ?int $payingScheduleId = null;

    public string $paymentAmount = '';

    // — generation (05-B7)
    public bool $showGenerateModal = false;

    public ?int $generateProgramId = null;

    public ?string $generateLaunchDate = null;

    public ?int $generateManagerId = null;

    public function mount(Partnership $partnership): void
    {
        $this->authorize('partnerships.pipeline.view');
        $this->partnership = $partnership;
        $this->resetQuoteLines();
        $this->scheduleRows = [['label' => 'الدفعة الأولى', 'amount' => '', 'due_on' => now()->toDateString()]];
    }

    // ---------------------------------------------------------------- quotes

    public function openQuoteModal(?int $reviseId = null): void
    {
        $this->authorize('partnerships.quotes.create');
        $this->revisingQuoteId = $reviseId;
        $this->resetQuoteLines();
        $this->quoteDiscount = '0';
        $this->showQuoteModal = true;
    }

    public function addQuoteLine(): void
    {
        $this->quoteLines[] = ['program_id' => null, 'service_type' => ProgramPrice::SERVICE_TRAINING, 'quantity' => '1', 'unit_price' => ''];
    }

    public function removeQuoteLine(int $index): void
    {
        unset($this->quoteLines[$index]);
        $this->quoteLines = array_values($this->quoteLines);

        if ($this->quoteLines === []) {
            $this->resetQuoteLines();
        }
    }

    public function saveQuote(): void
    {
        $this->authorize('partnerships.quotes.create');

        $this->validate([
            'quoteDiscount' => 'required|numeric|min:0',
            'quoteLines' => 'required|array|min:1',
            'quoteLines.*.service_type' => 'required|in:'.implode(',', ProgramPrice::SERVICES),
            'quoteLines.*.quantity' => 'required|numeric|min:0.01',
            'quoteLines.*.unit_price' => 'nullable|numeric|min:0',
        ], [], ['quoteLines' => 'بنود العرض']);

        $items = array_map(fn (array $line) => array_filter([
            'program_id' => $line['program_id'] ? (int) $line['program_id'] : null,
            'service_type' => $line['service_type'],
            'quantity' => (float) $line['quantity'],
            'unit_price' => $line['unit_price'] === '' ? null : (float) $line['unit_price'],
        ], fn ($value) => $value !== null), $this->quoteLines);

        $service = app(QuoteService::class);

        if ($this->revisingQuoteId) {
            $service->revise(Quote::findOrFail($this->revisingQuoteId), $items, (float) $this->quoteDiscount, auth()->user());
        } else {
            $service->create($this->partnership, $items, (float) $this->quoteDiscount, auth()->user());
        }

        $this->showQuoteModal = false;
        $this->dispatch('ds-toast', message: 'تم حفظ العرض بنسخة جديدة');
    }

    public function approveQuote(int $quoteId): void
    {
        $this->authorize('partnerships.quotes.approve');
        app(QuoteService::class)->approve($this->quote($quoteId), auth()->user());

        $this->dispatch('ds-toast', message: 'تم اعتماد العرض داخليًا');
    }

    public function sendQuote(int $quoteId): void
    {
        $this->authorize('partnerships.quotes.approve');
        app(QuoteService::class)->send($this->quote($quoteId));

        $this->dispatch('ds-toast', message: 'أُرسل العرض للجهة');
    }

    // ------------------------------------------------------------- contracts

    public function openContractModal(int $quoteId): void
    {
        $this->authorize('partnerships.contracts.create');
        $this->contractQuoteId = $quoteId;
        $this->showContractModal = true;
    }

    public function addScheduleRow(): void
    {
        $this->scheduleRows[] = ['label' => 'دفعة '.(count($this->scheduleRows) + 1), 'amount' => '', 'due_on' => now()->toDateString()];
    }

    public function saveContract(): void
    {
        $this->authorize('partnerships.contracts.create');

        $this->validate([
            'contractQuoteId' => 'required|exists:quotes,id',
            'scheduleRows' => 'required|array|min:1',
            'scheduleRows.*.amount' => 'required|numeric|min:0.01',
            'scheduleRows.*.due_on' => 'required|date',
        ], [], ['scheduleRows' => 'جدول الدفعات']);

        app(PartnershipContractService::class)->createFromQuote(
            $this->quote($this->contractQuoteId),
            $this->scheduleRows,
            $this->requiresFirstPayment,
        );

        $this->showContractModal = false;
        $this->dispatch('ds-toast', message: 'تم إنشاء العقد وجدول الدفعات');
    }

    public function uploadSignedCopy(int $contractId): void
    {
        $this->authorize('partnerships.contracts.manage');

        $this->validate([
            'signatureName' => 'required|string|max:255',
            'signedCopy' => 'required|file|max:20480|mimes:pdf',
        ], [], ['signatureName' => 'اسم الموقّع', 'signedCopy' => 'النسخة الموقعة']);

        app(PartnershipContractService::class)->uploadSignedCopy(
            $this->contract($contractId),
            $this->signedCopy,
            $this->signatureName,
            request()->userAgent(),
        );

        $this->signedCopy = null;
        $this->signatureName = null;
        $this->dispatch('ds-toast', message: 'تم رفع النسخة الموقعة');
    }

    public function confirmContract(int $contractId): void
    {
        $this->authorize('partnerships.contracts.confirm');

        try {
            app(PartnershipContractService::class)->confirm($this->contract($contractId), auth()->user());
            $this->partnership->refresh();
            $this->dispatch('ds-toast', message: 'تم التعاقد');
        } catch (\RuntimeException $e) {
            $this->addError('contract', $e->getMessage());
        }
    }

    // -------------------------------------------------------------- payments

    public function recordPayment(int $scheduleId): void
    {
        $this->authorize('partnerships.payments.record');

        $this->validate(['paymentAmount' => 'required|numeric|min:0.01'], [], ['paymentAmount' => 'المبلغ']);

        app(PartnershipPaymentService::class)->record(
            ContractPaymentSchedule::findOrFail($scheduleId),
            (float) $this->paymentAmount,
        );

        $this->paymentAmount = '';
        $this->dispatch('ds-toast', message: 'سُجلت الدفعة بانتظار تأكيد المالية');
    }

    public function confirmPayment(int $paymentId): void
    {
        $this->authorize('partnerships.payments.confirm');

        app(PartnershipPaymentService::class)->confirm(
            PartnershipPayment::where('partnership_id', $this->partnership->id)->findOrFail($paymentId),
            auth()->user(),
        );

        $this->dispatch('ds-toast', message: 'تم تأكيد الدفعة وإنشاء الإيراد');
    }

    /** «إصدار فاتورة» — 05-B6 hook into 04-B7. */
    public function issueInvoice(int $paymentId): void
    {
        $this->authorize('finance.tax_invoices.issue');

        app(PartnershipPaymentService::class)->issueTaxInvoice(
            PartnershipPayment::where('partnership_id', $this->partnership->id)->findOrFail($paymentId),
            auth()->user(),
        );

        $this->dispatch('ds-toast', message: 'تم إصدار الفاتورة الضريبية');
    }

    // ------------------------------------------------------------ partner link

    public function issueLink(): void
    {
        $this->authorize('partnerships.links.manage');
        app(PartnerPortalService::class)->issue($this->partnership, auth()->user());

        $this->dispatch('ds-toast', message: 'تم إصدار رابط الجهة');
    }

    public function revokeLink(int $linkId): void
    {
        $this->authorize('partnerships.links.manage');
        app(PartnerPortalService::class)->revoke($this->partnership->links()->findOrFail($linkId));

        $this->dispatch('ds-toast', message: 'تم إبطال الرابط');
    }

    // ------------------------------------------------------------ generation

    public function openGenerateModal(): void
    {
        $this->authorize('partnerships.generate');
        $this->generateLaunchDate = now()->addWeek()->toDateString();
        $this->showGenerateModal = true;
    }

    public function generateProject(): void
    {
        $this->authorize('partnerships.generate');

        $this->validate([
            'generateProgramId' => 'required|exists:programs,id',
            'generateLaunchDate' => 'required|date',
            'generateManagerId' => 'nullable|exists:users,id',
        ], [], ['generateProgramId' => 'البرنامج', 'generateLaunchDate' => 'تاريخ الانطلاق']);

        try {
            app(ProjectGenerationRequestService::class)->create(
                partnership: $this->partnership,
                program: Program::findOrFail($this->generateProgramId),
                launchDate: $this->generateLaunchDate,
                projectManager: $this->generateManagerId ? User::find($this->generateManagerId) : null,
                requestedBy: auth()->user(),
            );

            $this->showGenerateModal = false;
            $this->dispatch('ds-toast', message: 'تم تسجيل طلب التوليد');
        } catch (\RuntimeException $e) {
            $this->addError('generateProgramId', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.partnerships.partnership-show', [
            'partnership' => $this->partnership->load([
                'organization', 'quotes.items', 'partnershipContracts.schedule',
                'payments', 'links', 'generationRequests.program',
            ]),
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'managers' => User::orderBy('name')->get(['id', 'name']),
            'services' => ProgramPrice::SERVICES,
            'quoteStatuses' => [
                Quote::STATUS_DRAFT, Quote::STATUS_APPROVED, Quote::STATUS_SENT,
                Quote::STATUS_WITH_NOTES, Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED,
            ],
        ])->layout('layouts.app', ['title' => 'ملف الشراكة']);
    }

    private function quote(int $id): Quote
    {
        return Quote::where('partnership_id', $this->partnership->id)->findOrFail($id);
    }

    private function contract(int $id): PartnershipContract
    {
        return PartnershipContract::where('partnership_id', $this->partnership->id)->findOrFail($id);
    }

    private function resetQuoteLines(): void
    {
        $this->quoteLines = [[
            'program_id' => null,
            'service_type' => ProgramPrice::SERVICE_TRAINING,
            'quantity' => '1',
            'unit_price' => '',
        ]];
    }
}
