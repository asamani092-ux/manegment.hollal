<?php

namespace App\Livewire\Finance;

use App\Models\Custody;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\CustodyService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * 04-B3 UI — custody requests and multi-invoice settlement.
 * Time: O(n) list | Space: O(page size + invoices).
 */
class CustodiesIndex extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;
    use WithPagination;

    public bool $showRequestModal = false;

    public string $amount = '';

    public string $purpose = '';

    public ?int $employee_id = null;

    public string $rejectReason = '';

    public ?int $rejectingId = null;

    public ?int $disbursingId = null;

    public ?TemporaryUploadedFile $disbursementProof = null;

    public string $statusFilter = '';

    public string $search = '';

    public ?int $open = null;

    public ?int $settlingId = null;

    /** @var list<array{vendor_name: string, description: string, amount: string, vat_rate: string, invoice_number: string, invoice_date: string, category_id: string, invoice_file: mixed}> */
    public array $settleInvoices = [];

    public ?TemporaryUploadedFile $settleInvoiceFile = null;

    public int $settleFileRow = 0;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
        'open' => ['except' => null],
    ];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('finance.custodies.view')
            || auth()->user()->can('finance.custodies.approve')
            || auth()->user()->can('finance.custodies.disburse'),
            403
        );
    }

    public function openRequestModal(): void
    {
        $this->reset(['amount', 'purpose', 'employee_id']);
        $this->employee_id = auth()->id();
        $this->showRequestModal = true;
    }

    public function submitRequest(): void
    {
        $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'required|string|min:3',
            'employee_id' => 'required|exists:users,id',
        ]);

        if ($this->employee_id !== auth()->id()) {
            abort_unless(auth()->user()->can('finance.custodies.approve'), 403);
        }

        app(CustodyService::class)->request(
            User::findOrFail($this->employee_id),
            (float) $this->amount,
            $this->purpose,
            null,
            null,
            null,
            auth()->user()
        );

        $this->showRequestModal = false;
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل طلب العهدة');
    }

    public function approveCustody(int $id): void
    {
        abort_unless(auth()->user()->can('finance.custodies.approve'), 403);
        app(CustodyService::class)->approve(Custody::findOrFail($id), auth()->user());
        $this->dispatch('toast', type: 'success', message: 'تم اعتماد العهدة');
    }

    public function openReject(int $id): void
    {
        abort_unless(auth()->user()->can('finance.custodies.approve'), 403);
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function rejectCustody(): void
    {
        abort_unless(auth()->user()->can('finance.custodies.approve'), 403);
        $this->validate([
            'rejectingId' => 'required|exists:custodies,id',
            'rejectReason' => 'required|string|min:3|max:500',
        ]);

        try {
            app(CustodyService::class)->reject(Custody::findOrFail($this->rejectingId), auth()->user(), $this->rejectReason);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->rejectingId = null;
        $this->rejectReason = '';
        $this->dispatch('toast', type: 'success', message: 'رُفض طلب العهدة');
    }

    public function openDisburse(int $id): void
    {
        abort_unless(auth()->user()->can('finance.custodies.disburse'), 403);
        $this->disbursingId = $id;
        $this->disbursementProof = null;
    }

    public function disburseCustody(): void
    {
        abort_unless(auth()->user()->can('finance.custodies.disburse'), 403);
        $this->validate([
            'disbursingId' => 'required|exists:custodies,id',
            'disbursementProof' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ], [
            'disbursementProof.required' => 'إثبات الصرف إلزامي — انتظر اكتمال رفع الملف (الشاهد) قبل التأكيد',
        ]);

        if (! $this->disbursementProof instanceof TemporaryUploadedFile) {
            $this->addError('disbursementProof', 'انتظر اكتمال رفع إثبات الصرف ثم أكّد');

            return;
        }

        $path = $this->disbursementProof->store('custodies/disbursements', 'local');

        try {
            app(CustodyService::class)->disburse(Custody::findOrFail($this->disbursingId), $path);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->disbursingId = null;
        $this->disbursementProof = null;
        $this->dispatch('toast', type: 'success', message: 'تم صرف العهدة');
    }

    public function openSettle(int $id): void
    {
        abort_unless(
            auth()->user()->can('finance.custodies.view')
            || auth()->user()->can('finance.custodies.disburse')
            || auth()->user()->can('finance.custodies.approve'),
            403
        );

        $custody = Custody::findOrFail($id);
        if (! in_array($custody->status, [Custody::STATUS_DISBURSED, Custody::STATUS_SETTLING], true)) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكن التسوية إلا بعد الصرف');

            return;
        }

        $this->settlingId = $id;
        $this->settleInvoices = [
            $this->emptyInvoiceRow(),
        ];
        $this->settleInvoiceFile = null;
        $this->settleFileRow = 0;
    }

    public function addSettleInvoiceRow(): void
    {
        $this->settleInvoices[] = $this->emptyInvoiceRow();
    }

    public function removeSettleInvoiceRow(int $index): void
    {
        unset($this->settleInvoices[$index]);
        $this->settleInvoices = array_values($this->settleInvoices);
        if ($this->settleInvoices === []) {
            $this->settleInvoices = [$this->emptyInvoiceRow()];
        }
    }

    public function updatedSettleInvoiceFile(): void
    {
        if (! $this->settleInvoiceFile instanceof TemporaryUploadedFile) {
            return;
        }

        $idx = $this->settleFileRow;
        if (! isset($this->settleInvoices[$idx])) {
            return;
        }

        $path = $this->settleInvoiceFile->store('custodies/invoices', 'local');
        $this->settleInvoices[$idx]['invoice_file'] = $path;
        $this->settleInvoiceFile = null;
    }

    public function attachFileToRow(int $index): void
    {
        $this->settleFileRow = $index;
    }

    public function submitSettlement(): void
    {
        abort_unless(
            auth()->user()->can('finance.custodies.view')
            || auth()->user()->can('finance.custodies.disburse'),
            403
        );

        $this->validate([
            'settlingId' => 'required|exists:custodies,id',
            'settleInvoices' => 'required|array|min:1',
            'settleInvoices.*.amount' => 'required|numeric|min:0.01',
            'settleInvoices.*.vat_rate' => 'nullable|numeric|min:0|max:1',
            'settleInvoices.*.vendor_name' => 'nullable|string|max:255',
            'settleInvoices.*.description' => 'nullable|string|max:500',
            'settleInvoices.*.invoice_number' => 'nullable|string|max:100',
            'settleInvoices.*.invoice_date' => 'nullable|date',
            'settleInvoices.*.category_id' => 'nullable|exists:expense_categories,id',
        ]);

        $custody = Custody::findOrFail($this->settlingId);
        $service = app(CustodyService::class);

        try {
            foreach ($this->settleInvoices as $row) {
                $service->addSettlementItem($custody->fresh(), [
                    'description' => (string) ($row['description'] ?: ($row['vendor_name'] ?? 'فاتورة')),
                    'amount' => (float) $row['amount'],
                    'vat_rate' => (float) ($row['vat_rate'] !== '' && $row['vat_rate'] !== null ? $row['vat_rate'] : 0.15),
                    'category_id' => ($row['category_id'] ?? '') !== '' ? (int) $row['category_id'] : null,
                    'invoice_number' => $row['invoice_number'] ?: null,
                    'invoice_date' => $row['invoice_date'] ?: null,
                    'invoice_file' => $row['invoice_file'] ?: null,
                    'vendor_name' => $row['vendor_name'] ?: null,
                ]);
            }
            $service->close($custody->fresh());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->settlingId = null;
        $this->settleInvoices = [];
        $this->dispatch('toast', type: 'success', message: 'تمت تسوية العهدة');
    }

    public function settleSummary(): array
    {
        $custodyAmount = 0.0;
        if ($this->settlingId) {
            $c = Custody::find($this->settlingId);
            $custodyAmount = (float) ($c?->disbursed_amount ?? $c?->amount ?? 0);
        }

        $net = 0.0;
        $vat = 0.0;
        $total = 0.0;
        foreach ($this->settleInvoices as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $rate = (float) (($row['vat_rate'] ?? '') !== '' ? $row['vat_rate'] : 0.15);
            $vatAmt = round($amount * $rate, 2);
            $net += $amount;
            $vat += $vatAmt;
            $total += round($amount + $vatAmt, 2);
        }

        $diff = round($total - $custodyAmount, 2);

        return [
            'custody_amount' => round($custodyAmount, 2),
            'net' => round($net, 2),
            'vat' => round($vat, 2),
            'total' => round($total, 2),
            'diff' => $diff,
            'claim' => max(0, $diff),
            'return' => max(0, -$diff),
        ];
    }

    /** @return array{vendor_name: string, description: string, amount: string, vat_rate: string, invoice_number: string, invoice_date: string, category_id: string, invoice_file: ?string} */
    private function emptyInvoiceRow(): array
    {
        return [
            'vendor_name' => '',
            'description' => '',
            'amount' => '',
            'vat_rate' => '0.15',
            'invoice_number' => '',
            'invoice_date' => '',
            'category_id' => '',
            'invoice_file' => null,
        ];
    }

    public function render(): View
    {
        $query = Custody::query()
            ->select(['id', 'employee_id', 'amount', 'disbursed_amount', 'purpose', 'status', 'due_date', 'rejection_reason', 'created_at'])
            ->with('employee:id,name')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->open, fn ($q) => $q->where('id', $this->open))
            ->when($this->search && ! $this->open, fn ($q) => $q->whereHas(
                'employee',
                fn ($e) => $e->where('name', 'like', '%'.$this->search.'%')
            ))
            ->latest();

        if (! auth()->user()->can('finance.custodies.view') && auth()->user()->can('finance.custodies.approve')) {
            $query->whereIn('status', [Custody::STATUS_REQUESTED, Custody::STATUS_APPROVED]);
        } elseif (! auth()->user()->can('finance.custodies.view')) {
            $query->where('employee_id', auth()->id());
        }

        $settlingCustody = $this->settlingId
            ? Custody::with('employee:id,name')->find($this->settlingId)
            : null;

        return view('livewire.finance.custodies-index', [
            'custodies' => $query->paginate(10),
            'employees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
            'statusOptions' => [
                Custody::STATUS_REQUESTED,
                Custody::STATUS_APPROVED,
                Custody::STATUS_DISBURSED,
                Custody::STATUS_SETTLING,
                Custody::STATUS_CLOSED,
                Custody::STATUS_REJECTED,
            ],
            'canApprove' => auth()->user()->can('finance.custodies.approve'),
            'canDisburse' => auth()->user()->can('finance.custodies.disburse'),
            'settlingCustody' => $settlingCustody,
            'settleSummary' => $this->settlingId ? $this->settleSummary() : null,
        ])->layout('layouts.app', ['title' => 'العهد']);
    }
}
