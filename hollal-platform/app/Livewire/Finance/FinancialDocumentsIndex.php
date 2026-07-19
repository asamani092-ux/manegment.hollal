<?php

namespace App\Livewire\Finance;

use App\Models\Project;
use App\Services\FinancialDocumentsService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 04-B4 — read-only financial documents index. Aggregates attachments from every
 * finance module; there is no upload here.
 */
class FinancialDocumentsIndex extends Component
{
    use AuthorizesRequests;

    public string $typeFilter = '';

    public string $monthFilter = '';

    public ?int $projectFilter = null;

    public function mount(): void
    {
        $this->authorize('finance.revenues.view');
    }

    public function render(): View
    {
        $documents = app(FinancialDocumentsService::class)->all([
            'type' => $this->typeFilter,
            'month' => $this->monthFilter,
            'project_id' => $this->projectFilter,
        ]);

        return view('livewire.finance.financial-documents-index', [
            'documents' => $documents,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'المستندات المالية']);
    }
}
