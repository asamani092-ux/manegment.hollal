<?php

namespace App\Livewire\Hr;

use App\Models\Violation;
use App\Services\ViolationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ViolationsIndex extends Component
{
    public string $tab = 'suggested';

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

    public function render(): View
    {
        $status = match ($this->tab) {
            'statement' => 'awaiting_statement',
            'decision' => 'pending_decision',
            'all' => null,
            default => 'suggested',
        };

        return view('livewire.hr.violations-index', [
            'rows' => Violation::query()
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('id')
                ->limit(50)
                ->get(),
            'canManage' => auth()->user()->can('hr.violations.manage'),
            'canDecide' => auth()->user()->can('hr.violations.decide'),
        ])->layout('layouts.app', ['title' => 'المخالفات']);
    }
}
