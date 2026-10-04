<?php

namespace App\Livewire\Hr;

use App\Models\Violation;
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
        Violation::query()->whereKey($id)->update([
            'status' => 'excluded',
            'decision_reason' => $reason,
        ]);
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
        ])->layout('layouts.app', ['title' => 'المخالفات']);
    }
}
