<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\TaxInvoice;
use App\Models\TaxInvoiceNote;
use App\Services\TaxInvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 04-B7 — tax invoices index: issue an invoice from manual line items, issue
 * credit/debit notes, download the PDF. Totals are never entered by hand.
 */
class TaxInvoicesIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public bool $showIssueModal = false;

    public bool $showNoteModal = false;

    public string $buyerName = '';

    public ?string $buyerVatNumber = null;

    /** @var list<array{description: string, quantity: string, unit_price: string}> */
    public array $lines = [];

    public ?int $noteInvoiceId = null;

    public string $noteType = TaxInvoiceNote::TYPE_CREDIT;

    public string $noteAmount = '';

    public string $noteReason = '';

    public function mount(): void
    {
        $this->authorize('finance.tax_invoices.view');
        $this->resetLines();
    }

    public function openIssueModal(): void
    {
        $this->authorize('finance.tax_invoices.issue');
        $this->buyerName = '';
        $this->buyerVatNumber = null;
        $this->resetLines();
        $this->showIssueModal = true;
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1', 'unit_price' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->resetLines();
        }
    }

    public function issue(): void
    {
        $this->authorize('finance.tax_invoices.issue');

        $this->validate([
            'buyerName' => 'required|string|max:255',
            'buyerVatNumber' => 'nullable|string|max:50',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ], [], [
            'buyerName' => 'اسم المشتري',
            'lines.*.description' => 'وصف البند',
            'lines.*.quantity' => 'الكمية',
            'lines.*.unit_price' => 'سعر الوحدة',
        ]);

        app(TaxInvoiceService::class)->issue(
            items: array_map(fn (array $line) => [
                'description' => $line['description'],
                'quantity' => (float) $line['quantity'],
                'unit_price' => (float) $line['unit_price'],
            ], $this->lines),
            buyer: ['name' => $this->buyerName, 'vat_number' => $this->buyerVatNumber],
            issuer: auth()->user(),
        );

        $this->showIssueModal = false;
        $this->dispatch('ds-toast', message: 'تم إصدار الفاتورة الضريبية');
    }

    public function openNoteModal(int $invoiceId): void
    {
        $this->authorize('finance.tax_invoices.issue');
        $this->noteInvoiceId = $invoiceId;
        $this->noteType = TaxInvoiceNote::TYPE_CREDIT;
        $this->noteAmount = '';
        $this->noteReason = '';
        $this->showNoteModal = true;
    }

    public function issueNote(): void
    {
        $this->authorize('finance.tax_invoices.issue');

        $this->validate([
            'noteInvoiceId' => 'required|exists:tax_invoices,id',
            'noteType' => 'required|in:'.TaxInvoiceNote::TYPE_CREDIT.','.TaxInvoiceNote::TYPE_DEBIT,
            'noteAmount' => 'required|numeric|min:0.01',
            'noteReason' => 'required|string|max:255',
        ], [], [
            'noteAmount' => 'القيمة',
            'noteReason' => 'السبب',
        ]);

        app(TaxInvoiceService::class)->issueNote(
            invoice: TaxInvoice::findOrFail($this->noteInvoiceId),
            noteType: $this->noteType,
            amount: (float) $this->noteAmount,
            reason: $this->noteReason,
            issuer: auth()->user(),
        );

        $this->showNoteModal = false;
        $this->dispatch('ds-toast', message: 'تم إصدار الإشعار');
    }

    public function render(): View
    {
        return view('livewire.finance.tax-invoices-index', [
            'invoices' => TaxInvoice::query()
                ->withCount('notes')
                ->orderByDesc('sequence')
                ->paginate(15),
            'mode' => app(TaxInvoiceService::class)->mode(),
        ])->layout('layouts.app', ['title' => 'الفواتير الضريبية']);
    }

    private function resetLines(): void
    {
        $this->lines = [['description' => '', 'quantity' => '1', 'unit_price' => '']];
    }
}
