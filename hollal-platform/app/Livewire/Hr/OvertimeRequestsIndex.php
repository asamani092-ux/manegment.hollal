<?php

namespace App\Livewire\Hr;

use App\Models\OvertimeRequest;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OvertimeRequestsIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.view'), 403);
    }

    public function render(): View
    {
        return view('livewire.hr.overtime-requests-index', [
            'rows' => OvertimeRequest::query()->latest('id')->limit(50)->get(),
        ])->layout('layouts.app', ['title' => 'طلبات العمل الإضافي']);
    }
}
