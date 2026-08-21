<?php

namespace App\Livewire\Finance;

use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\FiscalYearClose;
use App\Services\AccountingCloseService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/** FIN-ACC-5 UI — cost centers sync, bank reconcile, opening, year close. */
class AccountingCloseIndex extends Component
{
    use AuthorizesRequests;

    public string $from = '';

    public string $to = '';

    public ?int $bankAccountId = null;

    public string $statementBalance = '';

    public string $openingAmount = '';

    public string $closeYear = '';

    public function mount(): void
    {
        $this->authorize('finance.accounting.manage');
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
        $this->closeYear = (string) now()->year;
        $this->bankAccountId = ChartOfAccount::where('code', '1200')->value('id');
    }

    public function syncCenters(): void
    {
        $n = app(AccountingCloseService::class)->syncCostCentersFromStructure();
        $this->dispatch('toast', type: 'success', message: "تمت مزامنة {$n} مركز تكلفة");
    }

    public function reconcile(): void
    {
        $this->validate([
            'bankAccountId' => 'required|exists:chart_of_accounts,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'statementBalance' => 'required|numeric',
        ]);

        $row = app(AccountingCloseService::class)->reconcileBank(
            (int) $this->bankAccountId,
            $this->from,
            $this->to,
            (float) $this->statementBalance,
            auth()->user(),
        );
        $this->dispatch('toast', type: 'success', message: 'فروقات التسوية: '.number_format((float) $row->difference, 2));
    }

    public function postOpening(): void
    {
        $this->validate(['openingAmount' => 'required|numeric|min:0.01']);
        app(AccountingCloseService::class)->postOpeningBalance((float) $this->openingAmount, auth()->user());
        $this->dispatch('toast', type: 'success', message: 'تم قيد الرصيد الافتتاحي');
    }

    public function closeFiscalYearAction(): void
    {
        $this->validate(['closeYear' => 'required|integer|min:2000|max:2100']);
        try {
            app(AccountingCloseService::class)->closeFiscalYear((int) $this->closeYear, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم إقفال السنة');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.finance.accounting-close-index', [
            'centers' => app(AccountingCloseService::class)->costCenterReport($this->from, $this->to),
            'reconciliations' => BankReconciliation::query()->latest('id')->limit(10)->get(),
            'closes' => FiscalYearClose::query()->orderByDesc('year')->get(),
            'bankAccounts' => ChartOfAccount::query()->whereIn('code', ['1100', '1200'])->get(),
        ])->layout('layouts.app', ['title' => 'مراكز التكلفة والإقفال']);
    }
}
