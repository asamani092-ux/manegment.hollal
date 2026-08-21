<?php

namespace App\Livewire\Finance;

use App\Models\ChartOfAccount;
use App\Services\AccountingReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FIN-ACC-3/4 — ledger, trial balance, income statement, balance sheet, cash flow.
 */
class AccountingReportsIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'trial';

    public string $from = '';

    public string $to = '';

    public ?int $accountId = null;

    public function mount(): void
    {
        $this->authorize('finance.accounting.manage');
        $this->from = now()->startOfYear()->toDateString();
        $this->to = now()->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function downloadTrialPdf(): StreamedResponse
    {
        $this->authorize('finance.accounting.manage');
        $pdf = app(AccountingReportService::class)->trialBalancePdf($this->from ?: null, $this->to ?: null);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'trial-balance.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function render(): View
    {
        $service = app(AccountingReportService::class);
        $data = match ($this->tab) {
            'ledger' => [
                'ledger' => $this->accountId
                    ? $service->generalLedger($this->accountId, $this->from ?: null, $this->to ?: null)
                    : collect(),
            ],
            'income' => ['income' => $service->incomeStatement($this->from ?: null, $this->to ?: null)],
            'balance' => ['balance' => $service->balanceSheet($this->to ?: null)],
            'cash' => ['cash' => $service->cashFlow($this->from ?: null, $this->to ?: null)],
            default => ['trial' => $service->trialBalance($this->from ?: null, $this->to ?: null)],
        };

        return view('livewire.finance.accounting-reports-index', $data + [
            'accounts' => ChartOfAccount::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar']),
        ])->layout('layouts.app', ['title' => 'الدفاتر والقوائم المالية']);
    }
}
