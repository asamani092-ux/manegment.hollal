<?php

namespace App\Livewire\Settings;

use App\Models\ExpenseSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ExpenseSettingsIndex extends Component
{
    use AuthorizesRequests;

    /** @deprecated مسار الاعتماد من ApprovalRule — الحقل معروض للقراءة فقط إن وُجد في الواجهة */
    public string $chain_mode = 'full';

    public bool $skip_missing_department_manager = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.manage') || auth()->user()->can('settings.finance.manage'), 403);

        $settings = ExpenseSetting::current();
        $this->chain_mode = (string) ($settings->chain_mode ?? 'full');
        $this->skip_missing_department_manager = $settings->skip_missing_department_manager;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.manage') || auth()->user()->can('settings.finance.manage'), 403);

        $this->validate([
            'skip_missing_department_manager' => 'boolean',
        ]);

        ExpenseSetting::current()->update([
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
