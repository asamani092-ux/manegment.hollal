<?php

namespace App\Livewire\Settings;

use App\Models\ApprovalRule;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ApprovalChainsIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.approval-chains.manage'), 403);
    }

    public function render(): View
    {
        return view('livewire.settings.approval-chains-index', [
            'rules' => ApprovalRule::query()->orderBy('transaction_type')->orderBy('min_amount')->get(),
        ])->layout('layouts.app', ['title' => 'سلاسل الطلبات']);
    }
}
