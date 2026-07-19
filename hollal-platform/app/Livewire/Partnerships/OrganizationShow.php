<?php

namespace App\Livewire\Partnerships;

use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 05-B1 — the organization page: data, journeys, projects, cumulative impact
 * record, documents and the communication timeline.
 */
class OrganizationShow extends Component
{
    use AuthorizesRequests;

    public Organization $organization;

    public function mount(Organization $organization): void
    {
        $this->authorize('partnerships.organizations.view');
        $this->organization = $organization;
    }

    public function render(): View
    {
        return view('livewire.partnerships.organization-show', [
            'organization' => $this->organization->load(['contacts', 'partnerships']),
            'projects' => $this->organization->projects(),
            'impact' => $this->organization->cumulativeImpact(),
            'timeline' => $this->organization->timeline(),
        ])->layout('layouts.app', ['title' => $this->organization->name]);
    }
}
