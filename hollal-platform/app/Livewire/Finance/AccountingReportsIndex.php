<?php

namespace App\Livewire\Finance;

use App\Models\ChartOfAccount;
use App\Services\AccountingReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FIN-ACC-3/4 — ledger, trial balance, nonprofit statements, cash flow.
 */
class AccountingReportsIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'trial';

    public string $from = '';

    public string $to = '';

    public string $fromYear = '';

    public string $fromMonth = '';

    public string $fromDay = '';

    public string $toYear = '';

    public string $toMonth = '';

    public string $toDay = '';

    public ?int $accountId = null;

    public function mount(): void
    {
        $this->authorize('finance.accounting.manage');
        $this->from = now()->startOfYear()->toDateString();
        $this->to = now()->toDateString();
        $this->splitDates();
    }

    public function updatedFromYear(): void
    {
        $this->composeDate('from');
    }

    public function updatedFromMonth(): void
    {
        $this->composeDate('from');
    }

    public function updatedFromDay(): void
    {
        $this->composeDate('from');
    }

    public function updatedToYear(): void
    {
        $this->composeDate('to');
    }

    public function updatedToMonth(): void
    {
        $this->composeDate('to');
    }

    public function updatedToDay(): void
    {
        $this->composeDate('to');
    }

    private function splitDates(): void
    {
        [$this->fromYear, $this->fromMonth, $this->fromDay] = array_pad(explode('-', $this->from), 3, '');
        [$this->toYear, $this->toMonth, $this->toDay] = array_pad(explode('-', $this->to), 3, '');
    }

    private function composeDate(string $which): void
    {
        $year = $which === 'from' ? $this->fromYear : $this->toYear;
        $month = $which === 'from' ? $this->fromMonth : $this->toMonth;
        $day = $which === 'from' ? $this->fromDay : $this->toDay;
        if ($year === '' || $month === '' || $day === '') {
            return;
        }
        $value = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        if ($which === 'from') {
            $this->from = $value;
        } else {
            $this->to = $value;
        }
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

    public function downloadCashFlowPdf(): StreamedResponse
    {
        $this->authorize('finance.accounting.manage');
        $pdf = app(AccountingReportService::class)->cashFlowPdf($this->from ?: null, $this->to ?: null);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'cash-flow.pdf', ['Content-Type' => 'application/pdf']);
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
