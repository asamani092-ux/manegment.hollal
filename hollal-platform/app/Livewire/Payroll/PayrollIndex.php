<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Former monthly payroll register — merged into profile salary + payroll-runs.
 * Time: O(1) | Space: O(1)
 */
class PayrollIndex extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', Payroll::class);
        $this->redirect(route('payroll-runs.index'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.payroll.payroll-index')
            ->layout('layouts.app', ['title' => 'الرواتب']);
    }
}
