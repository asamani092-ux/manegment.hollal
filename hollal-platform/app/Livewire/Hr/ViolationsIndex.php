<?php

namespace App\Livewire\Hr;

use App\Models\ReferenceItem;
use App\Models\User;
use App\Models\Violation;
use App\Services\ReferenceListService;
use App\Services\ViolationService;
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Violations register. Window query is O(n) rows | Space O(n).
 */
class ViolationsIndex extends Component
{
    public string $tab = 'suggested';

    public ?int $manualEmployeeId = null;

    public ?int $manualItemId = null;

    public string $manualFacts = '';

    public string $manualOccurred = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.violations.view') || auth()->user()->can('hr.violations.manage'), 403);
    }

    public function exclude(int $id, string $reason): void
    {
        abort_unless(auth()->user()->can('hr.violations.manage'), 403);
        if (trim($reason) === '') {
            $this->addError('reason', 'سبب الاستبعاد مطلوب');

            return;
        }
        $violation = Violation::query()->findOrFail($id);
        app(ViolationService::class)->exclude($violation, $reason, auth()->user());
    }

    public function confirmOne(int $id): void
    {
        abort_unless(auth()->user()->can('hr.violations.manage'), 403);
        app(ViolationService::class)->confirm(Violation::query()->findOrFail($id), auth()->user());
    }

    public function confirmAllSuggested(): void
    {
        abort_unless(auth()->user()->can('hr.violations.manage'), 403);
        Violation::query()->where('status', 'suggested')->orderBy('id')->each(function (Violation $violation): void {
            app(ViolationService::class)->confirm($violation, auth()->user());
        });
    }

    public function decide(int $id, string $action, string $reason): void
    {
        abort_unless(auth()->user()->can('hr.violations.decide'), 403);
        app(ViolationService::class)->decide(Violation::query()->findOrFail($id), $action, auth()->user(), $reason);
    }

    public function recordManualFromList(): void
    {
        abort_unless(auth()->user()->can('hr.violations.manage'), 403);
        $employee = User::query()->findOrFail($this->manualEmployeeId);
        $item = ReferenceItem::query()->findOrFail($this->manualItemId);
        app(ViolationService::class)->recordManual(
            $employee,
            $item,
            $this->manualFacts,
            Carbon::parse($this->manualOccurred !== '' ? $this->manualOccurred : now()->toDateString()),
            auth()->user(),
        );
        $this->manualFacts = '';
        $this->tab = 'statement';
    }

    public function render(): View
    {
        $windowFrom = now()->subDays((int) Setting::get('hr.violations.recurrence_window_days', 180))->toDateString();
        $query = Violation::query()->latest('id')->limit(50);
        if ($this->tab === 'window') {
            $query->whereIn('status', ['applied', 'reduced'])->whereDate('occurred_on', '>=', $windowFrom);
        } elseif ($this->tab !== 'all') {
            $status = match ($this->tab) {
                'statement' => 'awaiting_statement',
                'decision' => 'pending_decision',
                default => 'suggested',
            };
            $query->where('status', $status);
        }

        $items = \App\Models\ReferenceList::query()->where('key', 'violations')->exists()
            ? app(ReferenceListService::class)->activeItems('violations')
            : collect();

        return view('livewire.hr.violations-index', [
            'rows' => $query->get(),
            'canManage' => auth()->user()->can('hr.violations.manage'),
            'canDecide' => auth()->user()->can('hr.violations.decide'),
            'violationItems' => $items,
            'employees' => User::query()->orderBy('name')->limit(200)->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'المخالفات']);
    }
}
