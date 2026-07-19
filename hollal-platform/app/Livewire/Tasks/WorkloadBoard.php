<?php

namespace App\Livewire\Tasks;

use App\Services\WorkloadService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 02-B3 — workload board: per-team-member open/overdue/due-this-week counts and
 * last-30-day rating distribution, with an overload badge.
 */
class WorkloadBoard extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('esnad.tasks.team.view');
    }

    public function render(): View
    {
        $service = app(WorkloadService::class);

        return view('livewire.tasks.workload-board', [
            'rows' => $service->board(auth()->user()),
            'threshold' => $service->threshold(),
        ])->layout('layouts.app', ['title' => 'عبء عمل الفريق']);
    }
}
