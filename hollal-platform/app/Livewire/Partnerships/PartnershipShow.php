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
use App\Services\DiagnosisQuestionService;
use App\Services\PartnerPortalService;
use App\Services\PartnershipContractService;
use App\Services\PartnershipPaymentService;
use App\Services\ProjectGenerationRequestService;
use App\Services\QuoteService;
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Partnership workspace from diagnosis: link, quotes, contract, payments, generate.
 * Commercial close stays under عرض السعر; execution starts on generate.
 */
class PartnershipShow extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Partnership $partnership;

    public string $returnTo = 'pipeline';

    public int $workspaceStep = 1;

    /** @var array<string, mixed> */
    protected $queryString = [
        'workspaceStep' => ['except' => 1],
    ];

    // — diagnosis (workspace step 1)
    public string $diagnosisAudience = '';

    public string $diagnosisCount = '';

    public string $diagnosisEnvironment = '';

    /** @var array<int|string, string> */
    public array $diagnosisAnswers = [];

    /** @var list<int> */
    public array $quoteProgramIds = [];

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

    public string $internalApprovalNotes = '';

    // — payments (05-B6)
    public ?int $payingScheduleId = null;

    public string $paymentAmount = '';

    // — generation (05-B7)
    public bool $showGenerateModal = false;

    public ?int $generateProgramId = null;

    public ?string $generateLaunchDate = null;

    public ?int $generateManagerId = null;

    /** @var array{programs?: bool, diagnosis?: bool, quotes?: bool, payments?: bool, contract?: bool} */
    public array $portalFeatures = [
        'programs' => true,
        'diagnosis' => true,
        'quotes' => false,
        'payments' => false,
        'contract' => false,
    ];

    public int $linkExpiryDays = 7;

    public function mount(Partnership $partnership): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('partnerships.pipeline.view')
                || $user->can('partnerships.contracts.confirm')
                || $user->can('projects.update')
            ),
            403,
        );
        $this->partnership = $partnership;
        $this->returnTo = $partnership->organization_id ? 'organization' : 'pipeline';
        if (! request()->filled('workspaceStep')) {
            $this->workspaceStep = $this->defaultWorkspaceStep($partnership);
        }
        $this->resetQuoteLines();
        $this->scheduleRows = [['label' => 'الدفعة الأولى', 'amount' => '', 'due_on' => now()->toDateString()]];
        $this->portalFeatures = $partnership->portalFeatureFlags();
        $this->linkExpiryDays = (int) Setting::get('links.default_expiry_days', 7);
    }

    /** Workspace opens only from diagnosis onward. Time: O(1) | Space: O(1) */
    public function workspaceAvailable(): bool
    {
        $stage = (int) $this->partnership->stage;

        return ($stage >= Partnership::STAGE_DIAGNOSIS && $stage <= Partnership::STAGE_EXECUTION)
            || $stage === Partnership::STAGE_CONTRACTED;
    }

    /** Move journey into diagnosis and open workspace. Time: O(1) | Space: O(1) */
    public function startDiagnosisWorkspace(): void
    {
        $this->authorize('partnerships.pipeline.manage');

        app(\App\Services\PartnershipPipelineService::class)->advanceIfBefore(
            $this->partnership,
            Partnership::STAGE_DIAGNOSIS,
            auth()->user(),
            'بدء مرحلة التشخيص وفتح مساحة العمل',
        );
        $this->partnership->refresh();
        $this->syncWorkspace(1);
        $this->dispatch('ds-toast', message: 'فُتحت مساحة العمل عند التشخيص');
    }

    public function openWorkspace(int $step): void
    {
        if (! $this->workspaceAvailable()) {
            return;
        }
        $this->syncWorkspace($step);
    }

    // ---------------------------------------------------------------- quotes

    public function openQuoteModal(?int $reviseId = null): void
    {
        $this->authorize('partnerships.quotes.create');
        if (! $this->workspaceAvailable()) {
            return;
        }
        $this->syncWorkspace(2);
        $this->revisingQuoteId = $reviseId;
        $this->resetQuoteLines();
        $this->quoteDiscount = '0';
        $this->showQuoteModal = true;
    }

    /** Prefill quote lines from diagnosis × priced services. Time: O(p·s + q) | Space: O(p·s) */
    public function openQuoteFromDiagnosis(): void
    {
        $this->authorize('partnerships.quotes.create');
        if (! $this->workspaceAvailable()) {
            return;
        }
        $this->syncWorkspace(2);

        $programIds = array_values(array_filter(array_map('intval', $this->quoteProgramIds)));
        if ($programIds === []) {
            $programIds = $this->partnership->allowedPrograms()->pluck('programs.id')->map(fn ($id) => (int) $id)->all();
        }

        $items = app(QuoteService::class)->suggestItemsFromDiagnosis($this->partnership, $programIds);
        if ($items === []) {
            $this->addError('quote', 'اختر برنامجًا مسعّرًا أو أكمل التشخيص أولًا');
            $this->dispatch('ds-toast', message: 'اختر برنامجًا مسعّرًا أو أكمل التشخيص أولًا');

            return;
        }

        $this->revisingQuoteId = null;
        $this->quoteDiscount = '0';
        $this->quoteLines = array_map(fn (array $item) => [
            'program_id' => (string) $item['program_id'],
            'service_type' => $item['service_type'],
            'quantity' => (string) $item['quantity'],
            'unit_price' => (string) $item['unit_price'],
        ], $items);
        $this->showQuoteModal = true;
    }

    /** Manager submits diagnosis in workspace. Time: O(q) | Space: O(q) */
    public function submitWorkspaceDiagnosis(): void
    {
        $this->authorize('partnerships.pipeline.manage');
        $this->syncWorkspace(1);

        $questions = app(DiagnosisQuestionService::class)->activeQuestions();
        $answers = [];

        if ($questions->isEmpty()) {
            $this->validate([
                'diagnosisAudience' => 'required|string|max:255',
                'diagnosisCount' => 'required|numeric|min:1',
            ], [], ['diagnosisAudience' => 'الفئة', 'diagnosisCount' => 'الأعداد']);
        } else {
            foreach ($questions as $question) {
                $value = match ($question->key) {
                    'audience' => $this->diagnosisAudience,
                    'count' => $this->diagnosisCount,
                    'environment' => $this->diagnosisEnvironment,
                    default => (string) ($this->diagnosisAnswers[$question->id] ?? ''),
                };
                if ($question->required && trim($value) === '') {
                    $this->addError('diagnosisAudience', $question->label.' مطلوب');

                    return;
                }
                if (trim($value) !== '') {
                    $answers[$question->id] = $value;
                }
            }
            app(DiagnosisQuestionService::class)->recordAnswers($this->partnership, $answers);
        }

        if ($this->quoteProgramIds !== []) {
            $this->partnership->allowedPrograms()->syncWithoutDetaching(
                array_values(array_filter(array_map('intval', $this->quoteProgramIds)))
            );
        }

        app(\App\Services\PartnershipPipelineService::class)->advanceIfBefore(
            $this->partnership,
            Partnership::STAGE_DIAGNOSIS,
            auth()->user(),
            'تسجيل التشخيص من مساحة العمل',
        );
        app(\App\Services\PartnershipPipelineService::class)->advanceIfBefore(
            $this->partnership->fresh(),
            Partnership::STAGE_QUOTE,
            auth()->user(),
            'اكتمال التشخيص — الانتقال لعرض السعر',
        );
        $this->partnership->refresh();
        $this->syncWorkspace(2);
        $this->dispatch('ds-toast', message: 'تم حفظ التشخيص والانتقال لعرض السعر');
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
            $service->create($this->partnership, $items, (float) $this->quoteDiscount, auth()->user(), false);
        }

        $this->showQuoteModal = false;
        $this->dispatch('ds-toast', message: 'تم حفظ العرض بنسخة جديدة');
    }

    public function approveQuote(int $quoteId): void
    {
        $this->authorize('partnerships.quotes.approve');

        try {
            $quote = app(QuoteService::class)->approve($this->quote($quoteId), auth()->user());
        } catch (\RuntimeException $exception) {
            $this->addError('quote', $exception->getMessage());
            $this->dispatch('ds-toast', message: $exception->getMessage());

            return;
        }

        $this->partnership->refresh();
        $this->portalFeatures = $this->partnership->portalFeatureFlags();
        $final = $quote->status === Quote::STATUS_APPROVED;
        $this->dispatch('ds-toast', message: $final
            ? 'تم الاعتماد النهائي — العرض ظاهر على رابط الجهة'
            : 'تم الاعتماد الداخلي — بانتظار الاعتماد النهائي');
    }

    public function sendQuote(int $quoteId): void
    {
        $this->authorize('partnerships.quotes.approve');
        unset($quoteId);
        $this->addError('quote', 'إصدار الرابط هو الإرسال للجهة');
        $this->dispatch('ds-toast', message: 'إصدار الرابط هو الإرسال للجهة');
    }

    public function returnQuote(int $quoteId): void
    {
        $this->authorize('partnerships.quotes.finalize');
        $this->validate([
            'internalApprovalNotes' => 'required|string|max:2000',
        ], [], ['internalApprovalNotes' => 'ملاحظات الإرجاع']);

        app(QuoteService::class)->returnToDraft(
            $this->quote($quoteId),
            auth()->user(),
            $this->internalApprovalNotes,
        );
        $this->internalApprovalNotes = '';
        $this->partnership->refresh();
        $this->dispatch('ds-toast', message: 'أُعيد العرض مسودة مع الملاحظات');
    }

    // ------------------------------------------------------------- contracts

    public function openContractModal(int $quoteId): void
    {
        $this->authorize('partnerships.contracts.create');
        $this->syncWorkspace(3);
        $this->contractQuoteId = $quoteId;
        $quote = $this->quote($quoteId);
        $this->scheduleRows = [[
            'label' => 'الدفعة الأولى',
            'amount' => (string) $quote->total,
            'due_on' => now()->addDays(7)->toDateString(),
        ]];
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
        $this->authorizeInternalApproval();

        try {
            app(PartnershipContractService::class)->confirm($this->contract($contractId), auth()->user());
            $this->partnership->refresh();
            $this->dispatch('ds-toast', message: 'تم التعاقد');
        } catch (\RuntimeException $e) {
            $this->addError('contract', $e->getMessage());
        }
    }

    public function returnContract(int $contractId): void
    {
        $this->authorizeInternalApproval();
        $this->validate([
            'internalApprovalNotes' => 'required|string|max:2000',
        ], [], ['internalApprovalNotes' => 'ملاحظات الإرجاع']);

        app(PartnershipContractService::class)->returnForRevision(
            $this->contract($contractId),
            auth()->user(),
            $this->internalApprovalNotes,
        );
        $this->internalApprovalNotes = '';
        $this->partnership->refresh();
        $this->dispatch('ds-toast', message: 'أُعيد العقد للجهة مع الملاحظات');
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
        if (! $this->workspaceAvailable()) {
            return;
        }
        $this->syncWorkspace(1);
        $this->validate([
            'linkExpiryDays' => 'required|integer|min:1|max:365',
        ], [], ['linkExpiryDays' => 'مدة الرابط']);

        if ($this->partnership->portal_features === null || $this->partnership->portal_features === []) {
            $this->partnership->forceFill(['portal_features' => Partnership::defaultPortalFeatures()])->save();
            $this->portalFeatures = $this->partnership->portalFeatureFlags();
        }

        $link = app(PartnerPortalService::class)->issue(
            $this->partnership,
            auth()->user(),
            $this->linkExpiryDays,
        );

        app(\App\Services\PartnershipPipelineService::class)->advanceIfBefore(
            $this->partnership,
            Partnership::STAGE_DIAGNOSIS,
            auth()->user(),
            'إصدار رابط الجهة لمرحلة التشخيص',
        );
        $this->partnership->refresh();

        $this->dispatch('ds-toast', message: 'تم إصدار رابط الجهة: '.app(PartnerPortalService::class)->portalUrl($link->token));
    }

    public function sendLinkEmail(int $linkId): void
    {
        $this->authorize('partnerships.links.manage');
        $link = $this->partnership->links()->findOrFail($linkId);
        $url = app(PartnerPortalService::class)->emailLink($link);

        $this->dispatch('ds-toast', message: 'تم تسجيل إرسال الرابط بالبريد: '.$url);
    }

    public function revokeLink(int $linkId): void
    {
        $this->authorize('partnerships.links.manage');
        app(PartnerPortalService::class)->revoke($this->partnership->links()->findOrFail($linkId));

        $this->dispatch('ds-toast', message: 'تم إبطال الرابط');
    }

    public function savePortalFeatures(): void
    {
        $this->authorize('partnerships.links.manage');
        $this->partnership->forceFill(['portal_features' => $this->portalFeatures])->save();
        $this->dispatch('ds-toast', message: 'تم حفظ إعدادات بوابة الشريك');
    }

    /** Time: O(1) | Space: O(1) */
    public function deleteLink(int $linkId): void
    {
        $this->authorize('partnerships.links.manage');
        $link = $this->partnership->links()->findOrFail($linkId);

        if ($link->isUsable()) {
            $this->addError('links', 'لا يُحذف الرابط إلا بعد إبطاله أو انتهاء مدته.');
            $this->dispatch('ds-toast', message: 'لا يُحذف الرابط إلا بعد إبطاله أو انتهاء مدته.');

            return;
        }

        $link->delete();
        $this->dispatch('ds-toast', message: 'تم حذف الرابط');
    }

    // ------------------------------------------------------------ generation

    public function openGenerateModal(): void
    {
        $this->authorize('partnerships.generate');
        $this->syncWorkspace(5);
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
                'payments', 'links', 'generationRequests.program', 'allowedPrograms:id,name',
            ]),
            'programs' => Program::orderBy('name')->get(['id', 'name']),
            'managers' => User::orderBy('name')->get(['id', 'name']),
            'services' => ProgramPrice::SERVICES,
            'quoteStatuses' => [
                Quote::STATUS_DRAFT, Quote::STATUS_PENDING_FINAL, Quote::STATUS_APPROVED,
                Quote::STATUS_SENT, Quote::STATUS_WITH_NOTES, Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED,
            ],
            'diagnosisSnapshot' => app(DiagnosisQuestionService::class)->latestLabeledAnswers($this->partnership),
            'diagnosisQuestions' => app(DiagnosisQuestionService::class)->activeQuestions(),
            'linkDefaultDays' => (int) Setting::get('links.default_expiry_days', 7),
            'workspaceAvailable' => $this->workspaceAvailable(),
        ])->layout('layouts.app', ['title' => 'ملف الشراكة']);
    }

    /** Time: O(1) | Space: O(1) */
    private function syncWorkspace(int $step): void
    {
        if ($step < 1 || $step > 5) {
            return;
        }

        $this->workspaceStep = $step;
        $this->dispatch('open-workspace', step: $step);
    }

    /**
     * 1 diagnosis/link → 2 quotes → 3 contract → 4 payments → 5 generate.
     * Time: O(1) | Space: O(1)
     */
    private function defaultWorkspaceStep(Partnership $partnership): int
    {
        $partnership->loadMissing(['quotes:id,partnership_id', 'partnershipContracts:id,partnership_id', 'links:id,partnership_id']);
        $stage = (int) $partnership->stage;
        $hasDiagnosis = $partnership->diagnosisAnswers()->exists();

        return match (true) {
            $stage < Partnership::STAGE_DIAGNOSIS && ! $hasDiagnosis => 1,
            ! $hasDiagnosis && $partnership->quotes->isEmpty() => 1,
            $partnership->quotes->isEmpty() => 2,
            $partnership->partnershipContracts->isEmpty() => 3,
            $stage < Partnership::STAGE_EXECUTION => 4,
            default => 5,
        };
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

    private function authorizeInternalApproval(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('partnerships.contracts.confirm')
                || $user->can('projects.update')
                || $user->hasAnyRole(['Super Admin', 'Executive Manager'])
            ),
            403,
        );
    }
}
