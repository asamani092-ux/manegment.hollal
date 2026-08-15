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

    public string $invoiceType = TaxInvoice::TYPE_STANDARD;

    public ?int $editInvoiceId = null;

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
        $this->invoiceType = TaxInvoice::TYPE_STANDARD;
        $this->editInvoiceId = null;
        $this->resetLines();
        $this->showIssueModal = true;
    }

    public function openEditModal(int $invoiceId): void
    {
        $this->authorize('finance.tax_invoices.issue');
        $invoice = TaxInvoice::with('items')->findOrFail($invoiceId);
        $this->editInvoiceId = $invoice->id;
        $this->buyerName = $invoice->buyer_name;
        $this->buyerVatNumber = $invoice->buyer_vat_number;
        $this->invoiceType = $invoice->invoice_type ?: TaxInvoice::TYPE_STANDARD;
        $this->lines = $invoice->items->map(fn ($item) => [
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
        ])->values()->all();
        if ($this->lines === []) {
            $this->resetLines();
        }
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
            'buyerVatNumber' => $this->invoiceType === TaxInvoice::TYPE_STANDARD ? 'required|string|max:50' : 'nullable|string|max:50',
            'invoiceType' => 'required|in:'.TaxInvoice::TYPE_STANDARD.','.TaxInvoice::TYPE_SIMPLIFIED,
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ], [], [
            'buyerName' => 'اسم المشتري',
            'buyerVatNumber' => 'الرقم الضريبي للمشتري',
            'lines.*.description' => 'وصف البند',
            'lines.*.quantity' => 'الكمية',
            'lines.*.unit_price' => 'سعر الوحدة',
        ]);

        $payload = array_map(fn (array $line) => [
            'description' => $line['description'],
            'quantity' => (float) $line['quantity'],
            'unit_price' => (float) $line['unit_price'],
        ], $this->lines);

        if ($this->editInvoiceId) {
            $invoice = TaxInvoice::findOrFail($this->editInvoiceId);
            $invoice->update([
                'buyer_name' => $this->buyerName,
                'buyer_vat_number' => $this->buyerVatNumber,
                'invoice_type' => $this->invoiceType,
            ]);
            $this->dispatch('ds-toast', message: 'تم تحديث بيانات الفاتورة (البنود تُعدَّل عبر إشعار دائن/مدين)');
        } else {
            app(TaxInvoiceService::class)->issue(
                items: $payload,
                buyer: ['name' => $this->buyerName, 'vat_number' => $this->buyerVatNumber],
                issuer: auth()->user(),
                invoiceType: $this->invoiceType,
            );
            $this->dispatch('ds-toast', message: 'تم إصدار الفاتورة الضريبية');
        }

        $this->showIssueModal = false;
        $this->editInvoiceId = null;
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
