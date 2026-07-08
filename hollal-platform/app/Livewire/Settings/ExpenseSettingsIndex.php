<?php

namespace App\Livewire\Settings;

use App\Models\ExpenseSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ExpenseSettingsIndex extends Component
{
    use AuthorizesRequests;

    public string $chain_mode = 'full';

    public bool $skip_missing_department_manager = true;

    public function mount(): void
    {
        $this->authorize('settings.manage');

        $settings = ExpenseSetting::current();
        $this->chain_mode = $settings->chain_mode;
        $this->skip_missing_department_manager = $settings->skip_missing_department_manager;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'chain_mode' => 'required|in:full,short',
            'skip_missing_department_manager' => 'boolean',
        ]);

        ExpenseSetting::current()->update([
            'chain_mode' => $this->chain_mode,
            'skip_missing_department_manager' => $this->skip_missing_department_manager,
        ]);

        $this->dispatch('toast', type: 'success', message: 'تم حفظ إعدادات سلسلة الاعتماد');
    }

    public function render(): View
    {
        return view('livewire.settings.expense-settings-index')
            ->layout('layouts.app', ['title' => 'إعدادات المصروفات']);
    }
}
