<?php

namespace App\Livewire\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * FIN-ACC-2 — journal list + balanced manual entry.
 * Time: O(n) | Space: O(page)
 */
class JournalEntriesIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showModal = false;

    public string $description = '';

    public string $entry_date = '';

    /** @var list<array{account_id: string, debit: string, credit: string, description: string}> */
    public array $lines = [];

    public function mount(): void
    {
        $this->authorize('finance.accounting.manage');
        $this->entry_date = now()->toDateString();
        $this->resetLines();
    }

    public function openCreate(): void
    {
        $this->description = '';
        $this->entry_date = now()->toDateString();
        $this->resetLines();
        $this->showModal = true;
    }

    public function addLine(): void
    {
        $this->lines[] = ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->resetLines();
        }
    }

    public function save(): void
    {
        $this->authorize('finance.accounting.manage');

        $this->validate([
            'description' => 'required|string|max:500',
            'entry_date' => 'required|date',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ], [], [
            'description' => 'الوصف',
            'entry_date' => 'التاريخ',
            'lines' => 'البنود',
            'lines.*.account_id' => 'الحساب',
        ]);

        $payload = collect($this->lines)->map(fn (array $line) => [
            'account_id' => (int) $line['account_id'],
            'debit' => (float) ($line['debit'] ?: 0),
            'credit' => (float) ($line['credit'] ?: 0),
            'description' => $line['description'] !== '' ? $line['description'] : null,
        ])->all();

        try {
            app(JournalService::class)->postManual(
                $this->description,
                $this->entry_date,
                $payload,
                auth()->user(),
            );
            $this->showModal = false;
            $this->dispatch('toast', type: 'success', message: 'تم ترحيل القيد');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addError('lines', $e->getMessage());
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.finance.journal-entries-index', [
            'entries' => JournalEntry::query()->with(['lines.account', 'creator'])->latest('id')->paginate(20),
            'accounts' => ChartOfAccount::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar']),
        ])->layout('layouts.app', ['title' => 'القيود اليومية']);
    }

    private function resetLines(): void
    {
        $this->lines = [
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''],
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''],
        ];
    }
}
