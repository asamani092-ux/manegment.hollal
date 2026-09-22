<?php

namespace App\Livewire\Finance;

use App\Support\CoaCodes;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\FiscalYearClose;
use App\Services\AccountingCloseService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** FIN-ACC-5 UI — cost centers sync, bank reconcile + statement import, opening, year close. */
class AccountingCloseIndex extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $from = '';

    public string $to = '';

    public ?int $bankAccountId = null;

    public string $statementBalance = '';

    public string $openingAmount = '';

    public string $closeYear = '';

    public ?TemporaryUploadedFile $statementFile = null;

    public ?int $viewingReconciliationId = null;

    public function mount(): void
    {
        $this->authorize('finance.accounting.manage');
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
        $this->closeYear = (string) now()->year;
        $this->bankAccountId = ChartOfAccount::where('code', CoaCodes::BANK_RAJHI)->value('id');
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

    public function importStatement(): void
    {
        $this->validate([
            'bankAccountId' => 'required|exists:chart_of_accounts,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'statementFile' => 'required|file|max:10240|mimes:csv,txt,xlsx,xls',
            'statementBalance' => 'nullable|numeric',
        ]);

        if (! $this->statementFile instanceof TemporaryUploadedFile) {
            $this->addError('statementFile', 'انتظر اكتمال رفع الملف');

            return;
        }

        $path = $this->statementFile->store('bank-statements', 'local');
        $full = storage_path('app/'.$path);

        // Livewire قد يخزّن تحت private/
        if (! is_file($full)) {
            $full = storage_path('app/private/'.$path);
        }

        try {
            $result = app(AccountingCloseService::class)->importBankStatement(
                (int) $this->bankAccountId,
                $this->from,
                $this->to,
                $full,
                auth()->user(),
                $this->statementBalance !== '' ? (float) $this->statementBalance : null,
            );
            $this->viewingReconciliationId = $result['reconciliation']->id;
            $this->statementFile = null;
            $this->dispatch(
                'toast',
                type: 'success',
                message: "استيراد {$result['imported']} سطر — مطابقة {$result['matched']}"
            );
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
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
        $statementLines = collect();
        if ($this->viewingReconciliationId) {
            $statementLines = BankStatementLine::query()
                ->where('bank_reconciliation_id', $this->viewingReconciliationId)
                ->orderBy('transaction_date')
                ->limit(50)
                ->get();
        }

        return view('livewire.finance.accounting-close-index', [
            'centers' => app(AccountingCloseService::class)->costCenterReport($this->from, $this->to),
            'reconciliations' => BankReconciliation::query()->latest('id')->limit(10)->get(),
            'closes' => FiscalYearClose::query()->orderByDesc('year')->get(),
            'bankAccounts' => ChartOfAccount::query()->whereIn('code', CoaCodes::cashAndBanks())->get(),
            'statementLines' => $statementLines,
        ])->layout('layouts.app', ['title' => 'مراكز التكلفة والإقفال']);
    }
}
